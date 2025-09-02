<?php
require_once __DIR__ . '/session.php';
require 'config.php';
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$isAdmin = $_SESSION['is_admin'] ?? false;
if (!$isAdmin) {
    $allowed = $_SESSION['client_ids'] ?? [];
    if ($allowed) {
        if (!in_array($client_id, $allowed)) {
            header('Location: login.php');
            exit;
        }
        $_SESSION['client_id'] = $client_id;
    } elseif (!isset($_SESSION['client_id']) || $_SESSION['client_id'] != $client_id) {
        header('Location: login.php');
        exit;
    }
}
if (!$client_id) {
    die('Invalid client');
}
require_once __DIR__ . '/lib/SimpleXLSXGen.php';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="positions_'.$client_id.'.xlsx"');
$rows = [];
$header = ['Keyword','Sort'];
for ($i = 1; $i <= 12; $i++) {
    $header[] = 'M'.$i;
}
$rows[] = $header;
$stmt = $pdo->prepare("SELECT keyword, sort_order, m2,m3,m4,m5,m6,m7,m8,m9,m10,m11,m12,m13 FROM keyword_positions WHERE client_id = ? ORDER BY sort_order IS NULL, sort_order, id DESC");
$stmt->execute([$client_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $line = [
        $row['keyword'],
        $row['sort_order']
    ];
    for ($i = 2; $i <= 13; $i++) {
        $line[] = $row['m'.$i];
    }
    $rows[] = $line;
}
\Shuchkin\SimpleXLSXGen::fromArray($rows)->downloadAs('positions_'.$client_id.'.xlsx');
exit;
?>
