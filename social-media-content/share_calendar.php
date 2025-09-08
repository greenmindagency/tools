<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = (int)($input['client_id'] ?? 0);
$year = (int)($input['year'] ?? 0);
$month = (int)($input['month'] ?? 0);
if(!$client_id || !$year || !$month){
    http_response_code(400);
    echo json_encode(['error'=>'invalid']);
    exit;
}
$stmt = $pdo->prepare('SELECT short_url FROM calendar_links WHERE client_id=? AND year=? AND month=?');
$stmt->execute([$client_id,$year,$month]);
$url = $stmt->fetchColumn();
if(!$url){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme.'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']);
    $full = $baseUrl.'/calendar.php?client_id='.$client_id.'&year='.$year.'&month='.$month;
    $short = @file_get_contents('https://tinyurl.com/api-create.php?url='.urlencode($full));
    if($short){
        $ins = $pdo->prepare('INSERT INTO calendar_links (client_id,year,month,short_url) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE short_url=VALUES(short_url)');
        $ins->execute([$client_id,$year,$month,$short]);
        $url = $short;
    }
}
echo json_encode(['short_url'=>$url]);
