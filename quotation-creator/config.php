<?php
$host = 'localhost';
$dbname = 'greenm38_quotationcreator';
$user = 'greenm38_quotationcreator';
$pass = 'WP.nF{y!!n[i';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}
?>
