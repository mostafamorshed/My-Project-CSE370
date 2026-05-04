<?php
require_once '../config/db.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($method) {
    case 'GET':
        if ($id) {
            // Single order with items
            $stmt = $db->prepare("
                SELECT o.*, u.name AS customer_name, u.email AS customer_email
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.user_id
                WHERE o.order_id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();

            if ($order) {
                $items_stmt = $db->prepare("
                    SELECT oi.*, p.product_name, p.brand_name
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.product_id
                    WHERE oi.order_id = ?
                ");
                $items_stmt->bind_param("i", $id);
                $items_stmt->execute();
                $items = [];
                $res = $items_stmt->get_result();
                while ($row = $res->fetch_assoc()) $items[] = $row;
                $order['items'] = $items;
                echo json_encode($order);
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Order not found"]);
            }
        } else {
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $sql = "
                SELECT o.*, u.name AS customer_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.user_id
                WHERE 1=1
            ";
            if ($status && in_array($status, ['pending', 'confirmed', 'cancelled'])) {
                $sql .= " AND o.status = '" . $db->real_escape_string($status) . "'";
            }
            $sql .= " ORDER BY o.order_date DESC";
            $result = $db->query($sql);
            $orders = [];
            while ($row = $result->fetch_assoc()) $orders[] = $row;
            echo json_encode($orders);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("INSERT INTO orders (user_id, total_price, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("id", $data['user_id'], $data['total_price']);
            $stmt->execute();
            $order_id = $db->insert_id;

            foreach ($data['items'] as $item) {
                $is = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $is->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                $is->execute();
            }
            $db->commit();
            echo json_encode(["success" => true, "order_id" => $order_id]);
        } catch (Exception $e) {
            $db->rollback();
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Update order status
        if (!$id) { http_response_code(400); echo json_encode(["error" => "ID required"]); break; }
        $data = json_decode(file_get_contents("php://input"), true);
        if (!in_array($data['status'], ['pending', 'confirmed', 'cancelled'])) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid status"]);
            break;
        }
        $stmt = $db->prepare("UPDATE orders SET status=? WHERE order_id=?");
        $stmt->bind_param("si", $data['status'], $id);
        $stmt->execute();
        echo json_encode(["success" => true]);
        break;
}

$db->close();
