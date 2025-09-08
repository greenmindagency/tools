<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$month = (int)($input['month'] ?? date('n'));
$year  = (int)($input['year'] ?? date('Y'));
$countries = $input['countries'] ?? [];
$client_id = (int)($input['client_id'] ?? ($_SESSION['client_id'] ?? 0));
if ($countries) {
    $place = implode(',', array_fill(0, count($countries), '?'));
    $params = array_merge([$client_id], $countries, [$year, $month]);
    $sql = "SELECT country, DATE_FORMAT(occasion_date,'%Y-%m-%d') as date, name FROM occasions WHERE client_id = ? AND country IN ($place) AND YEAR(occasion_date)=? AND MONTH(occasion_date)=? ORDER BY occasion_date";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT country, DATE_FORMAT(occasion_date,'%Y-%m-%d') as date, name FROM occasions WHERE client_id = ? AND YEAR(occasion_date)=? AND MONTH(occasion_date)=? ORDER BY occasion_date");
    $stmt->execute([$client_id, $year, $month]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
echo json_encode($rows);
?>
