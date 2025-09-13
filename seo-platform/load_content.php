<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');

$client_id = $_GET['client_id'] ?? 0;
$year = (int)($_GET['year'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
if(!$client_id || !$year || !$month){
    echo json_encode([]);
    exit;
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_content (
      id INT AUTO_INCREMENT PRIMARY KEY,
      client_id INT,
      post_date DATE,
      title TEXT,
      cluster VARCHAR(255),
      doc_link TEXT,
      status VARCHAR(50),
      comments TEXT
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach ([
        'cluster VARCHAR(255)',
        'doc_link TEXT',
        'status VARCHAR(50)',
        'comments TEXT'
    ] as $col) {
        try { $pdo->exec("ALTER TABLE client_content ADD COLUMN $col"); } catch (PDOException $e) {}
    }
    $start = sprintf('%04d-%02d-01',$year,$month);
    $end = date('Y-m-t', strtotime($start));
    $stmt = $pdo->prepare('SELECT post_date, title, cluster, doc_link, status, comments FROM client_content WHERE client_id = ? AND post_date BETWEEN ? AND ? ORDER BY post_date');
    $stmt->execute([$client_id,$start,$end]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

