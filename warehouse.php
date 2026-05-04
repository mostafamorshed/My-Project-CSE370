<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$db = getDB();
if (!$db) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $result = $db->query("SELECT * FROM warehouse ORDER BY location ASC");
        if (!$result) {
            http_response_code(500);
            echo json_encode(["error" => $db->error]);
            break;
        }
        $warehouses = [];
        while ($row = $result->fetch_assoc()) $warehouses[] = $row;
        echo json_encode($warehouses);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['location']) || trim($data['location']) === '') {
            http_response_code(400);
            echo json_encode(["error" => "location is required"]);
            break;
        }
        $stmt = $db->prepare("INSERT INTO warehouse (location) VALUES (?)");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["error" => $db->error]);
            break;
        }
        $stmt->bind_param("s", $data['location']);
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(["error" => $stmt->error]);
            break;
        }
        echo json_encode(["success" => true, "id" => $db->insert_id]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
$db->close();