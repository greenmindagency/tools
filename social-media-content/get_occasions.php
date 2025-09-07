<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$month = (int)($input['month'] ?? date('n'));
$year  = (int)($input['year'] ?? date('Y'));
$countries = $input['countries'] ?? [];
if (!$countries) { echo json_encode([]); exit; }
$place = implode(',', array_fill(0, count($countries), '?'));
$params = array_merge($countries, [$year, $month]);
$sql = "SELECT country, DATE_FORMAT(occasion_date,'%Y-%m-%d') as date, name FROM occasions WHERE country IN ($place) AND YEAR(occasion_date)=? AND MONTH(occasion_date)=? ORDER BY occasion_date";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
?>
