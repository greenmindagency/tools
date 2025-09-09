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
    $check = $pdo->prepare('SELECT id FROM client_calendar WHERE client_id = ? AND post_date = ?');
    $upd = $pdo->prepare('UPDATE client_calendar SET title = ?, content = ? WHERE client_id = ? AND post_date = ?');
    $ins = $pdo->prepare('INSERT INTO client_calendar (client_id, post_date, title, content) VALUES (?,?,?,?)');
    foreach ($input['entries'] ?? [] as $row) {
        $date = $row['date'] ?? '';
        $title = $row['title'] ?? '';
        $content = $row['content'] ?? '';
        $check->execute([$client_id, $date]);
        if ($check->fetch()) {
            $upd->execute([$title, $content, $client_id, $date]);
        } else {
            $ins->execute([$client_id, $date, $title, $content]);
        }
    }
    $pdo->commit();
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
