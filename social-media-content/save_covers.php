<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = $_SESSION['client_id'] ?? ($input['client_id'] ?? 0);
$covers = $input['covers'] ?? [];
if (!$client_id) {
    echo json_encode(['status' => 'error']);
    exit;
}
$pdo->prepare('REPLACE INTO client_covers (client_id, covers) VALUES (?,?)')->execute([$client_id, json_encode($covers)]);
echo json_encode(['status' => 'ok']);
?>
