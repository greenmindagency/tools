<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');

$client_id = $_GET['client_id'] ?? 0;
$year = (int)($_GET['year'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
if (!$client_id || !$year || !$month) {
    echo json_encode([]);
    exit;
}
$start = sprintf('%04d-%02d-01', $year, $month);
$end = date('Y-m-t', strtotime($start));
$stmt = $pdo->prepare('SELECT post_date, title FROM client_calendar WHERE client_id = ? AND post_date BETWEEN ? AND ? AND TRIM(title) <> "" ORDER BY post_date');
$stmt->execute([$client_id, $start, $end]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
