<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
session_start();
session_unset();
session_destroy();
echo json_encode(['success' => true]);
exit;
?>
