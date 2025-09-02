<?php
require_once __DIR__ . '/session.php';
require 'config.php';
$pdo->exec("ALTER TABLE keywords ADD COLUMN IF NOT EXISTS priority VARCHAR(10) DEFAULT ''");
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
$slugify = function(string $name): string {
    $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    $name = preg_replace('/[^a-zA-Z0-9]+/', '-', $name);
    return strtolower(trim($name, '-'));
};

$stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$clientName = $stmt->fetchColumn() ?: 'client';

$kwRows = [];
$kwRows[] = ['Keyword','Volume','Form','Link','Type','Priority','Cluster'];

$stmt = $pdo->prepare(
    "SELECT keyword, volume, form, content_link, page_type, priority, cluster_name"
    . " FROM keywords WHERE client_id = ? ORDER BY id"
);
$stmt->execute([$client_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cluster  = trim($row['cluster_name'] ?? '');
    $kwRows[] = [
        $row['keyword'],
        $row['volume'],
        $row['form'],
        $row['content_link'],
        $row['page_type'],
        $row['priority'],
        $cluster
    ];
}

$posRows = [];
$header = ['Keyword','Sort'];
for ($i = 1; $i <= 12; $i++) {
    $header[] = 'M'.$i;
}
$posRows[] = $header;
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
    $posRows[] = $line;
}

$xlsx = \Shuchkin\SimpleXLSXGen::fromArray($kwRows, 'Keywords');
$xlsx->addSheet($posRows, 'Positions');
$filename = $slugify($clientName) . '-' . date('Y-m-d') . '.xlsx';
$xlsx->downloadAs($filename);
exit;
?>
