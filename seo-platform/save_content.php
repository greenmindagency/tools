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
try { $pdo->exec("ALTER TABLE keywords ADD COLUMN content_link TEXT"); } catch (PDOException $e) {}
try{
    $pdo->beginTransaction();
    if($year && $month){
        $start = sprintf('%04d-%02d-01',$year,$month);
        $end = date('Y-m-t', strtotime($start));
        $del = $pdo->prepare('DELETE FROM client_content WHERE client_id=? AND post_date BETWEEN ? AND ?');
        $del->execute([$client_id,$start,$end]);
    }
    $ins = $pdo->prepare('INSERT INTO client_content (client_id, post_date, title, cluster, doc_link, status, comments) VALUES (?,?,?,?,?,?,?)');
    foreach($entries as $row){
        $date = $row['post_date'] ?? '';
        if(!$date) continue;
        $title = $row['title'] ?? '';
        $cluster = $row['cluster'] ?? '';
        $link = $row['doc_link'] ?? '';
        $status = $row['status'] ?? '';
        $comments = $row['comments'] ?? '';
        $ins->execute([$client_id,$date,$title,$cluster,$link,$status,$comments]);
    }
    $pdo->commit();

    try {
        $clr = $pdo->prepare('UPDATE keywords SET content_link = "" WHERE client_id = ?');
        $clr->execute([$client_id]);
        $map = $pdo->prepare('SELECT cluster, doc_link FROM client_content WHERE client_id = ? AND cluster <> "" AND doc_link <> "" GROUP BY cluster');
        $map->execute([$client_id]);
        $upd = $pdo->prepare('UPDATE keywords SET content_link = ? WHERE client_id = ? AND cluster_name = ?');
        while ($row = $map->fetch(PDO::FETCH_ASSOC)) {
            $upd->execute([$row['doc_link'], $client_id, $row['cluster']]);
        }
    } catch (Exception $e) {}

    echo json_encode(['status'=>'ok']);
}catch(Exception $e){
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
