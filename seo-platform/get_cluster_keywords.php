<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');
$client_id = $_GET['client_id'] ?? 0;
$cluster = $_GET['cluster'] ?? '';
if(!$client_id || !$cluster){
    echo json_encode([]);
    exit;
}
$stmt = $pdo->prepare('SELECT keyword FROM keywords WHERE client_id = ? AND cluster_name = ? ORDER BY keyword');
$stmt->execute([$client_id, $cluster]);
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
