<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
session_start();

$db = getDB();
try {
  $method = $_SERVER['REQUEST_METHOD'];
  if ($method !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); $db->close(); exit; }

  $data = json_decode(file_get_contents('php://input'), true);
  if (!$data || empty($data['id']) || !isset($data['password'])) {
    http_response_code(400); echo json_encode(['error'=>'Missing id or password']); $db->close(); exit;
  }

  $id = trim($data['id']);
  $password = $data['password'];

  // check if user exists
  $stmt = $db->prepare('SELECT * FROM users WHERE name = ? LIMIT 1');
  $stmt->bind_param('s', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();
  if ($row) {
    http_response_code(409); echo json_encode(['error'=>'User already exists']); $db->close(); exit;
  }

  $role = 'customer';
  $email = strtolower(preg_replace('/\s+/', '', $id)) . '@local';
  $stmt2 = $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
  $stmt2->bind_param('ssss', $id, $email, $password, $role);
  if ($stmt2->execute()) {
    $uid = $db->insert_id;
    $_SESSION['user'] = ['user_id'=>$uid,'name'=>$id,'role'=>$role];
    echo json_encode(['success'=>true,'user'=>$_SESSION['user']]); $stmt2->close(); $db->close(); exit;
  } else {
    http_response_code(500); echo json_encode(['error'=>'Could not create user']); $stmt2->close(); $db->close(); exit;
  }

} catch (Exception $e) {
  http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); $db->close(); exit;
}

?>