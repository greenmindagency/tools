<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = $_SESSION['client_id'] ?? ($input['client_id'] ?? 0);
$from = $input['from'] ?? '';
$to   = $input['to'] ?? '';
if(!$client_id || !$from || !$to){
    echo json_encode(['status'=>'error']);
    exit;
}
$stmt = $pdo->prepare('UPDATE client_calendar SET post_date = CASE WHEN post_date = ? THEN ? WHEN post_date = ? THEN ? END WHERE client_id = ? AND post_date IN (?, ?)');
$stmt->execute([$from, $to, $to, $from, $client_id, $from, $to]);
echo json_encode(['status'=>'ok']);
?>
