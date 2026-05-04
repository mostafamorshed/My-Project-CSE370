<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
session_start();

if (isset($_SESSION['user'])) {
  echo json_encode(['user' => $_SESSION['user']]);
  exit;
}
http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit;

?>
