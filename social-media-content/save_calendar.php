<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = $_SESSION['client_id'] ?? ($input['client_id'] ?? 0);
$year = (int)($input['year'] ?? 0);
$month = (int)($input['month'] ?? 0);

try {
    $pdo->beginTransaction();
    if ($year && $month) {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $del = $pdo->prepare('DELETE FROM client_calendar WHERE client_id = ? AND post_date BETWEEN ? AND ?');
        $del->execute([$client_id, $start, $end]);
    } else {
        $del = $pdo->prepare('DELETE FROM client_calendar WHERE client_id = ?');
        $del->execute([$client_id]);
    }
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
