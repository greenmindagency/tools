<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = (int)($input['client_id'] ?? 0);
$date = $input['date'] ?? '';
if(!$client_id || !$date){
    http_response_code(400);
    echo json_encode(['error'=>'invalid']);
    exit;
}
$stmt = $pdo->prepare('SELECT short_url FROM content_links WHERE client_id=? AND post_date=?');
$stmt->execute([$client_id,$date]);
$url = $stmt->fetchColumn();
if(!$url){
    $dt = date_create($date);
    if($dt){
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme.'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']);
        $full = $baseUrl.'/content.php?client_id='.$client_id.'&date='.$dt->format('Y-m-d');
        $short = @file_get_contents('https://tinyurl.com/api-create.php?url='.urlencode($full));
        if($short){
            $ins = $pdo->prepare('INSERT INTO content_links (client_id, post_date, short_url) VALUES (?,?,?) ON DUPLICATE KEY UPDATE short_url=VALUES(short_url)');
            $ins->execute([$client_id,$date,$short]);
            $url = $short;
        }
    }
}
echo json_encode(['short_url'=>$url]);
