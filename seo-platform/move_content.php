<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = $input['client_id'] ?? 0;
$from = $input['from'] ?? '';
$to = $input['to'] ?? '';
if(!$client_id || !$from || !$to){
    echo json_encode(['error'=>'invalid']);
    exit;
}
$pdo->exec("CREATE TABLE IF NOT EXISTS client_content (
  client_id INT,
  post_date DATE,
  title TEXT,
  cluster VARCHAR(255),
  doc_link TEXT,
  PRIMARY KEY (client_id, post_date)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$stmt = $pdo->prepare('UPDATE client_content SET post_date=? WHERE client_id=? AND post_date=?');
$stmt->execute([$to,$client_id,$from]);
echo json_encode(['status'=>'ok']);
