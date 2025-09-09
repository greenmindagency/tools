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
$tmpDate = '9999-12-31';
try {
    $pdo->beginTransaction();

    // Check if target date already has a post
    $check = $pdo->prepare('SELECT id FROM client_calendar WHERE client_id = ? AND post_date = ?');
    $check->execute([$client_id, $to]);
    $toId = $check->fetchColumn();

    if ($toId) {
        // Move target date to a temporary placeholder to avoid unique constraint issues
        $upd = $pdo->prepare('UPDATE client_calendar SET post_date = ? WHERE client_id = ? AND post_date = ?');
        $upd->execute([$tmpDate, $client_id, $to]);
        $upd->execute([$to, $client_id, $from]);
        $upd->execute([$from, $client_id, $tmpDate]);
    } else {
        // Simply move the source post to the target date
        $upd = $pdo->prepare('UPDATE client_calendar SET post_date = ? WHERE client_id = ? AND post_date = ?');
        $upd->execute([$to, $client_id, $from]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
?>
