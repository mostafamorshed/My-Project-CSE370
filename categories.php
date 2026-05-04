<?php
require_once '../config/db.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($method) {
    case 'GET':
        $result = $db->query("
            SELECT c.*, COUNT(p.product_id) AS product_count
            FROM categories c
            LEFT JOIN products p ON c.category_id = p.category_id
            GROUP BY c.category_id
            ORDER BY c.category_name
        ");
        $cats = [];
        while ($row = $result->fetch_assoc()) $cats[] = $row;
        echo json_encode($cats);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $stmt = $db->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $data['category_name']);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "category_id" => $db->insert_id]);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Category already exists or invalid"]);
        }
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(["error" => "ID required"]); break; }
        $stmt = $db->prepare("DELETE FROM categories WHERE category_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(["success" => true]);
        break;
}

$db->close();
