<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = $_SESSION['client_id'] ?? ($input['client_id'] ?? 0);
$entries = $input['entries'] ?? [];
$pdo->exec("CREATE TABLE IF NOT EXISTS client_content (
  client_id INT,
  post_date DATE,
  title TEXT,
  cluster VARCHAR(255),
  doc_link TEXT,
  PRIMARY KEY (client_id, post_date)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
    $pdo->beginTransaction();
    $check = $pdo->prepare('SELECT 1 FROM client_content WHERE client_id=? AND post_date=?');
    $ins = $pdo->prepare('INSERT INTO client_content (client_id, post_date, title, cluster, doc_link) VALUES (?,?,?,?,?)');
    $upd = $pdo->prepare('UPDATE client_content SET title=?, cluster=?, doc_link=? WHERE client_id=? AND post_date=?');
    foreach($entries as $row){
        $date = $row['post_date'] ?? '';
        $title = $row['title'] ?? '';
        $cluster = $row['cluster'] ?? '';
        $link = $row['doc_link'] ?? '';
        if(!$date) continue;
        $check->execute([$client_id,$date]);
        if($check->fetch()){
            $upd->execute([$title,$cluster,$link,$client_id,$date]);
        }else{
            $ins->execute([$client_id,$date,$title,$cluster,$link]);
        }
    }
    $pdo->commit();
    echo json_encode(['status'=>'ok']);
}catch(Exception $e){
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
