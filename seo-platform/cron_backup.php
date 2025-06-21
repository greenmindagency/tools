<?php
require __DIR__ . '/config.php';

$backupRoot = __DIR__ . '/backups';
if (!is_dir($backupRoot)) {
    mkdir($backupRoot, 0777, true);
}

$date = date('d-m-Y');

$clientStmt = $pdo->query("SELECT id FROM clients");
$clients = $clientStmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($clients as $cid) {
    $dir = $backupRoot . "/client_{$cid}";
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $file = "$dir/$date.csv";
    $out = fopen($file, 'w');
    fputcsv($out, ['Keyword','Volume','Form','Link','Page Type','Group','# in Group','Cluster']);
    $stmt = $pdo->prepare("SELECT keyword, volume, form, content_link, page_type, group_name, group_count, cluster_name FROM keywords WHERE client_id = ? ORDER BY id");
    $stmt->execute([$cid]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['keyword'],
            $row['volume'],
            $row['form'],
            $row['content_link'],
            $row['page_type'],
            $row['group_name'],
            $row['group_count'],
            $row['cluster_name']
        ]);
    }
    fclose($out);

    $files = glob("$dir/*.csv");
    usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
    if (count($files) > 7) {
        foreach (array_slice($files, 0, count($files) - 7) as $f) {
            unlink($f);
        }
    }
}
