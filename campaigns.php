<?php
require_once '../config/db.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

switch ($method) {
    case 'GET':
        $sql = "
            SELECT g.*, p.product_name, p.price, p.brand_name,
                   ROUND((g.curr_participants / g.min_participants) * 100, 1) AS progress_pct,
                   (g.min_participants - g.curr_participants) AS spots_left,
                   TIMESTAMPDIFF(HOUR, NOW(), g.end_date) AS hours_left
            FROM group_buy_campaigns g
            LEFT JOIN products p ON g.product_id = p.product_id
            ORDER BY g.status ASC, g.end_date ASC
        ";
        $result = $db->query($sql);
        $campaigns = [];
        while ($row = $result->fetch_assoc()) $campaigns[] = $row;
        echo json_encode($campaigns);
        break;

    case 'POST':
        // Join a campaign
        $data = json_decode(file_get_contents("php://input"), true);
        $db->begin_transaction();
        try {
            // Check campaign is active
            $check = $db->prepare("SELECT * FROM group_buy_campaigns WHERE campaign_id=? AND status='active' FOR UPDATE");
            $check->bind_param("i", $data['campaign_id']);
            $check->execute();
            $camp = $check->get_result()->fetch_assoc();
            if (!$camp) throw new Exception("Campaign not active or not found");

            // Check not already joined
            $dup = $db->prepare("SELECT 1 FROM campaign_participants WHERE user_id=? AND campaign_id=?");
            $dup->bind_param("ii", $data['user_id'], $data['campaign_id']);
            $dup->execute();
            if ($dup->get_result()->num_rows > 0) throw new Exception("Already joined this campaign");

            // Join
            $join = $db->prepare("INSERT INTO campaign_participants (user_id, campaign_id) VALUES (?, ?)");
            $join->bind_param("ii", $data['user_id'], $data['campaign_id']);
            $join->execute();

            // Increment count
            $upd = $db->prepare("UPDATE group_buy_campaigns SET curr_participants = curr_participants + 1 WHERE campaign_id=?");
            $upd->bind_param("i", $data['campaign_id']);
            $upd->execute();

            // Check if target met -> complete
            $newCount = $camp['curr_participants'] + 1;
            if ($newCount >= $camp['min_participants']) {
                $complete = $db->prepare("UPDATE group_buy_campaigns SET status='completed' WHERE campaign_id=?");
                $complete->bind_param("i", $data['campaign_id']);
                $complete->execute();
            }

            $db->commit();
            echo json_encode(["success" => true, "message" => "Joined campaign!"]);
        } catch (Exception $e) {
            $db->rollback();
            http_response_code(400);
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;
}

$db->close();
