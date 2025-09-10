<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = $_SESSION['client_id'] ?? ($input['client_id'] ?? 0);
$date = $input['date'] ?? '';
$approved = $input['approved'] ?? 0;
if (!$client_id || !$date) {
    echo json_encode(['status' => 'error']);
    exit;
}
$stmt = $pdo->prepare('UPDATE client_calendar SET approved = ? WHERE client_id = ? AND post_date = ?');
$stmt->execute([$approved, $client_id, $date]);
echo json_encode(['status' => 'ok']);
?>
