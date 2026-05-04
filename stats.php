<?php
require_once '../config/db.php';

$db = getDB();

$stats = [];

// Total revenue from confirmed orders
$r = $db->query("SELECT SUM(total_price) as revenue FROM orders WHERE status='confirmed'");
$stats['total_revenue'] = $r->fetch_assoc()['revenue'] ?? 0;

// Order counts by status
$r = $db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$stats['orders'] = [];
while ($row = $r->fetch_assoc()) $stats['orders'][$row['status']] = $row['count'];

// Total products
$r = $db->query("SELECT COUNT(*) as count FROM products");
$stats['total_products'] = $r->fetch_assoc()['count'];

// Low stock (under 30)
$r = $db->query("SELECT COUNT(*) as count FROM inventory WHERE stock_quantity < 30");
$stats['low_stock'] = $r->fetch_assoc()['count'];

// Active campaigns
$r = $db->query("SELECT COUNT(*) as count FROM group_buy_campaigns WHERE status='active'");
$stats['active_campaigns'] = $r->fetch_assoc()['count'];

// Total customers
$r = $db->query("SELECT COUNT(*) as count FROM users WHERE role='customer'");
$stats['total_customers'] = $r->fetch_assoc()['count'];

echo json_encode($stats);
$db->close();
