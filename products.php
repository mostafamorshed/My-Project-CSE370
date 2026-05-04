<?php
require_once __DIR__ . '/../config/db.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($method) {
    case 'GET':
        if ($id) {
            // Single product with avg rating; we'll attach inventory rows separately
            $stmt = $db->prepare("
                SELECT p.*, c.category_name,
                       ROUND(AVG(r.rating), 1) AS avg_rating,
                       COUNT(r.review_id) AS review_count
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN reviews r ON p.product_id = r.product_id
                WHERE p.product_id = ?
                GROUP BY p.product_id
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            if (!$product) { echo json_encode(["error" => "Not found"]); break; }

            // fetch inventory rows for this product
            $invStmt = $db->prepare("SELECT i.warehouse_id, i.stock_quantity, w.location FROM inventory i LEFT JOIN warehouse w ON i.warehouse_id = w.warehouse_id WHERE i.product_id = ?");
            $invStmt->bind_param("i", $id);
            $invStmt->execute();
            $invRes = $invStmt->get_result();
            $warehouses = [];
            while ($r = $invRes->fetch_assoc()) $warehouses[] = $r;
            $product['warehouses'] = $warehouses;

            echo json_encode($product);
        } else {
            // All products with filters
            $category = isset($_GET['category']) ? intval($_GET['category']) : null;
            $search = isset($_GET['search']) ? '%' . $db->real_escape_string($_GET['search']) . '%' : null;

            $sql = "
                SELECT p.*, c.category_name,
                       COALESCE(i.stock_quantity, 0) AS stock_quantity,
                       ROUND(AVG(r.rating), 1) AS avg_rating,
                       COUNT(r.review_id) AS review_count
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN inventory i ON p.product_id = i.product_id
                LEFT JOIN reviews r ON p.product_id = r.product_id
                WHERE 1=1
            ";
            $params = [];
            $types = "";

            if ($category) {
                $sql .= " AND p.category_id = ?";
                $params[] = $category;
                $types .= "i";
            }
            if ($search) {
                $sql .= " AND (p.product_name LIKE ? OR p.brand_name LIKE ?)";
                $params[] = $search;
                $params[] = $search;
                $types .= "ss";
            }

            $sql .= " GROUP BY p.product_id ORDER BY p.created_at DESC";

            if (!empty($params)) {
                $stmt = $db->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $db->query($sql);
            }

            $products = [];
            $ids = [];
            while ($row = $result->fetch_assoc()) { $products[] = $row; $ids[] = $row['product_id']; }

            // attach inventory rows for all products fetched
            if (!empty($ids)) {
                $in = implode(',', array_map('intval', $ids));
                $invSql = "SELECT i.product_id, i.warehouse_id, i.stock_quantity, w.location FROM inventory i LEFT JOIN warehouse w ON i.warehouse_id = w.warehouse_id WHERE i.product_id IN ($in)";
                $invRes = $db->query($invSql);
                $map = [];
                while ($r = $invRes->fetch_assoc()) {
                    $pid = $r['product_id'];
                    if (!isset($map[$pid])) $map[$pid] = [];
                    $map[$pid][] = $r;
                }
                foreach ($products as &$p) {
                    $p['warehouses'] = $map[$p['product_id']] ?? [];
                }
                unset($p);
            }

            echo json_encode($products);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $db->prepare("INSERT INTO products (product_name, price, brand_name, category_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sdsi", $data['product_name'], $data['price'], $data['brand_name'], $data['category_id']);
        if ($stmt->execute()) {
            $newId = $db->insert_id;
            // If inventory array supplied, insert per-warehouse inventory rows
            if (!empty($data['inventory']) && is_array($data['inventory'])) {
                $del = $db->prepare("DELETE FROM inventory WHERE product_id = ?");
                $del->bind_param("i", $newId);
                $del->execute();
                $ins = $db->prepare("INSERT INTO inventory (product_id, stock_quantity, warehouse_id) VALUES (?, ?, ?)");
                foreach ($data['inventory'] as $invRow) {
                    $wid = intval($invRow['warehouse_id']);
                    $qty = intval($invRow['stock_quantity']);
                    $ins->bind_param("iii", $newId, $qty, $wid);
                    $ins->execute();
                }
            } else {
                // Backwards compat: single stock_quantity (no warehouse provided)
                $inv = $db->prepare("INSERT INTO inventory (product_id, stock_quantity, warehouse_id) VALUES (?, ?, NULL)");
                $stock = isset($data['stock_quantity']) ? intval($data['stock_quantity']) : 0;
                $inv->bind_param("ii", $newId, $stock);
                $inv->execute();
            }
            echo json_encode(["success" => true, "product_id" => $newId]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => $stmt->error]);
        }
        break;

    case 'PUT':
        if (!$id) { http_response_code(400); echo json_encode(["error" => "ID required"]); break; }
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $db->prepare("UPDATE products SET product_name=?, price=?, brand_name=?, category_id=? WHERE product_id=?");
        $stmt->bind_param("sdsii", $data['product_name'], $data['price'], $data['brand_name'], $data['category_id'], $id);
        if ($stmt->execute()) {
            // If inventory array provided, replace inventory rows for this product
            if (!empty($data['inventory']) && is_array($data['inventory'])) {
                $del = $db->prepare("DELETE FROM inventory WHERE product_id = ?");
                $del->bind_param("i", $id);
                $del->execute();
                $ins = $db->prepare("INSERT INTO inventory (product_id, stock_quantity, warehouse_id) VALUES (?, ?, ?)");
                foreach ($data['inventory'] as $invRow) {
                    $wid = intval($invRow['warehouse_id']);
                    $qty = intval($invRow['stock_quantity']);
                    $ins->bind_param("iii", $id, $qty, $wid);
                    $ins->execute();
                }
            } else if (isset($data['stock_quantity'])) {
                // Backwards compatibility: update any single inventory row
                $inv = $db->prepare("UPDATE inventory SET stock_quantity=? WHERE product_id=?");
                $inv->bind_param("ii", $data['stock_quantity'], $id);
                $inv->execute();
            }
            echo json_encode(["success" => true]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => $stmt->error]);
        }
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(["error" => "ID required"]); break; }
        $stmt = $db->prepare("DELETE FROM products WHERE product_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(["success" => true]);
        break;
}

$db->close();