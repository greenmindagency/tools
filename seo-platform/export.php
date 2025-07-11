<?php
session_start();
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
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="keywords_'.$client_id.'.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Keyword','Volume','Form','Link','Type','Group','#','Cluster']);

$stmt = $pdo->prepare(
    "SELECT keyword, volume, form, content_link, page_type, group_name, group_count, cluster_name
     FROM keywords WHERE client_id = ? ORDER BY id"
);
$stmt->execute([$client_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $cluster   = trim($row['cluster_name'] ?? '');

    $groupCnt  = $row['group_count'] ?? '';
    fputcsv($out, [
        $row['keyword'],
        $row['volume'],
        $row['form'],
        $row['content_link'],
        $row['page_type'],
        $row['group_name'],
        $groupCnt,
        $cluster
    ]);
}
fclose($out);
exit;
?>
