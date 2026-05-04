<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$db = getDB();
try {
  $method = $_SERVER['REQUEST_METHOD'];

  if ($method === 'GET') {
    // optional product_id or search
    $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;

    // We will select reviewer name and product name for convenience
    if ($product_id) {
      $stmt = $db->prepare('SELECT r.*, p.product_name, u.name as user_name FROM reviews r LEFT JOIN products p ON r.product_id = p.product_id LEFT JOIN users u ON r.user_id = u.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC');
      $stmt->bind_param('i', $product_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $out = [];
      while ($r = $res->fetch_assoc()) $out[] = $r;
      echo json_encode($out);
      $stmt->close(); $db->close(); exit;
    }

    if ($search) {
      // server-side search across product_name, comment, user name, and rating
      $like = '%' . $db->real_escape_string($search) . '%';
      $sql = "SELECT r.*, p.product_name, u.name as user_name FROM reviews r LEFT JOIN products p ON r.product_id = p.product_id LEFT JOIN users u ON r.user_id = u.user_id WHERE p.product_name LIKE ? OR r.comment LIKE ? OR u.name LIKE ? OR r.rating = ? ORDER BY r.created_at DESC";
      $stmt = $db->prepare($sql);
      // rating exact match if numeric, else pass a sentinel that won't match
      $ratingMatch = is_numeric($search) ? intval($search) : -1;
      $stmt->bind_param('sssi', $like, $like, $like, $ratingMatch);
      $stmt->execute();
      $res = $stmt->get_result();
      $out = [];
      while ($r = $res->fetch_assoc()) $out[] = $r;
      echo json_encode($out);
      $stmt->close(); $db->close(); exit;
    }

    // default: all reviews with product and user names
    $res = $db->query('SELECT r.*, p.product_name, u.name as user_name FROM reviews r LEFT JOIN products p ON r.product_id = p.product_id LEFT JOIN users u ON r.user_id = u.user_id ORDER BY r.created_at DESC');
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    echo json_encode($out);
    $db->close(); exit;
  }

  if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['product_id']) || !isset($data['rating'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Missing required fields: product_id and rating']);
      $db->close(); exit;
    }
    $product_id = intval($data['product_id']);
    // default to a valid system/user id if not provided or zero to avoid FK failures
    $user_id = isset($data['user_id']) && intval($data['user_id']) > 0 ? intval($data['user_id']) : 1;
    $rating = intval($data['rating']);
    $comment = isset($data['comment']) ? $data['comment'] : null;

    $stmt = $db->prepare('INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('iiis', $user_id, $product_id, $rating, $comment);
    if ($stmt->execute()) {
      echo json_encode(['success' => true, 'review_id' => $stmt->insert_id]);
    } else {
      http_response_code(500);
      echo json_encode(['error' => 'Insert failed', 'db_error' => $stmt->error]);
    }
    $stmt->close(); $db->close(); exit;
  }

  http_response_code(405); echo json_encode(['error' => 'Method not allowed']); $db->close(); exit;
} catch (Exception $e) {
  http_response_code(500); echo json_encode(['error' => $e->getMessage()]); $db->close(); exit;
}

?>