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

  // Special handling: ensure admin account exists with given credentials if id matches
  if ($id === 'Jablu') {
    // check if admin exists
    $stmt = $db->prepare('SELECT * FROM users WHERE name = ? LIMIT 1');
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if (!$row) {
      // create admin
      $stmt2 = $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
      $email = strtolower($id) . '@local';
      $role = 'admin';
      $stmt2->bind_param('ssss', $id, $email, $password, $role);
      $stmt2->execute();
      $stmt2->close();
      $user_id = $db->insert_id;
      $_SESSION['user'] = ['user_id'=>$user_id,'name'=>$id,'role'=>$role];
      echo json_encode(['success'=>true,'user'=>$_SESSION['user']]); $stmt->close(); $db->close(); exit;
    }
    // else fallthrough to normal auth check
    $stmt->close();
  }

  // lookup user by name
  $stmt = $db->prepare('SELECT * FROM users WHERE name = ? LIMIT 1');
  $stmt->bind_param('s', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  if ($user) {
    // compare plain-text password (existing DB stores plain text)
    if ($user['password'] === $password) {
      $_SESSION['user'] = ['user_id'=>$user['user_id'],'name'=>$user['name'],'role'=>$user['role']];
      echo json_encode(['success'=>true,'user'=>$_SESSION['user']]); $stmt->close(); $db->close(); exit;
    } else {
      http_response_code(401); echo json_encode(['error'=>'Invalid credentials']); $stmt->close(); $db->close(); exit;
    }
  }

  // user not found => create a new customer account automatically
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
