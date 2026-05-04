<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$db = getDB();

try {
  $method = $_SERVER['REQUEST_METHOD'];

  if ($method === 'GET') {
    // optional id -> single
    if (isset($_GET['id'])) {
      $id = intval($_GET['id']);
      $stmt = $db->prepare('SELECT * FROM oem_designs WHERE design_id = ?');
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res->fetch_assoc();
      echo json_encode($row ?: new stdClass());
      $stmt->close();
      $db->close();
      exit;
    }

    // list all, newest first
    $res = $db->query('SELECT * FROM oem_designs ORDER BY created_at DESC');
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode($out);
    $db->close();
    exit;
  }

  // POST -> create new design
  if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['title'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Missing required fields: title']);
      $db->close();
      exit;
    }

    $title = $data['title'];
    $description = $data['description'] ?? null;
    $features = isset($data['features']) ? json_encode($data['features']) : null;
    $image = $data['image_path'] ?? null;
    $user = $data['user_name'] ?? null;

    $stmt = $db->prepare('INSERT INTO oem_designs (title, description, features, image_path, user_name) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $title, $description, $features, $image, $user);
    if ($stmt->execute()) {
      echo json_encode(['success' => true, 'design_id' => $stmt->insert_id]);
    } else {
      http_response_code(500);
      echo json_encode(['error' => 'Insert failed']);
    }
    $stmt->close();
    $db->close();
    exit;
  }

  // PUT -> update (use for voting or editing)
  if ($method === 'PUT') {
    parse_str(file_get_contents('php://input'), $put_vars);
    $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($put_vars['id']) ? intval($put_vars['id']) : 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); $db->close(); exit; }

    // voting: pass action=upvote or action=downvote
    if (isset($put_vars['action']) && ($put_vars['action'] === 'upvote' || $put_vars['action'] === 'downvote')) {
      if ($put_vars['action'] === 'upvote') {
        $stmt = $db->prepare('UPDATE oem_designs SET upvotes = upvotes + 1 WHERE design_id = ?');
      } else {
        $stmt = $db->prepare('UPDATE oem_designs SET downvotes = downvotes + 1 WHERE design_id = ?');
      }
      $stmt->bind_param('i', $id);
      if ($stmt->execute()) echo json_encode(['success' => true]); else { http_response_code(500); echo json_encode(['error' => 'Vote failed']); }
      $stmt->close(); $db->close(); exit;
    }

    // editing fields
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data) {
      $title = $data['title'] ?? null;
      $description = $data['description'] ?? null;
      $features = isset($data['features']) ? json_encode($data['features']) : null;
      $stmt = $db->prepare('UPDATE oem_designs SET title = ?, description = ?, features = ? WHERE design_id = ?');
      $stmt->bind_param('sssi', $title, $description, $features, $id);
      if ($stmt->execute()) echo json_encode(['success' => true]); else { http_response_code(500); echo json_encode(['error' => 'Update failed']); }
      $stmt->close(); $db->close(); exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Nothing to update']);
    $db->close();
    exit;
  }

  // DELETE -> remove design
  if ($method === 'DELETE') {
    if (!isset($_GET['id'])) { http_response_code(400); echo json_encode(['error' => 'Missing id']); $db->close(); exit; }
    $id = intval($_GET['id']);
    $stmt = $db->prepare('DELETE FROM oem_designs WHERE design_id = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) echo json_encode(['success' => true]); else { http_response_code(500); echo json_encode(['error' => 'Delete failed']); }
    $stmt->close(); $db->close(); exit;
  }

  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  $db->close();
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
  $db->close();
}

?>