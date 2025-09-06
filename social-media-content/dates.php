<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$out = [];
for ($d = 1; $d <= $days; $d++) {
    $out[] = ['date' => sprintf('%04d-%02d-%02d', $year, $month, $d)];
}
echo json_encode($out);
