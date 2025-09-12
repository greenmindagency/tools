<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = $_SESSION['client_id'] ?? ($input['client_id'] ?? 0);
$year = (int)($input['year'] ?? 0);
$month = (int)($input['month'] ?? 0);
$entries = $input['entries'] ?? [];
$pdo->exec("CREATE TABLE IF NOT EXISTS client_content (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT,
  post_date DATE,
  title TEXT,
  cluster VARCHAR(255),
  doc_link TEXT,
  status VARCHAR(50)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
    $pdo->beginTransaction();
    if($year && $month){
        $start = sprintf('%04d-%02d-01',$year,$month);
        $end = date('Y-m-t', strtotime($start));
        $del = $pdo->prepare('DELETE FROM client_content WHERE client_id=? AND post_date BETWEEN ? AND ?');
        $del->execute([$client_id,$start,$end]);
    }
    $ins = $pdo->prepare('INSERT INTO client_content (client_id, post_date, title, cluster, doc_link, status) VALUES (?,?,?,?,?,?)');
    foreach($entries as $row){
        $date = $row['post_date'] ?? '';
        if(!$date) continue;
        $title = $row['title'] ?? '';
        $cluster = $row['cluster'] ?? '';
        $link = $row['doc_link'] ?? '';
        $status = $row['status'] ?? '';
        $ins->execute([$client_id,$date,$title,$cluster,$link,$status]);
    }
    $pdo->commit();
    echo json_encode(['status'=>'ok']);
}catch(Exception $e){
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
