<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = $_SESSION['client_id'] ?? ($input['client_id'] ?? 0);

try {
    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM client_calendar WHERE client_id = ?');
    $del->execute([$client_id]);
    $ins = $pdo->prepare('INSERT INTO client_calendar (client_id, post_date, title) VALUES (?,?,?)');
    foreach ($input['entries'] ?? [] as $row) {
        $ins->execute([$client_id, $row['date'] ?? '', $row['title'] ?? '']);
    }
    $pdo->commit();
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
