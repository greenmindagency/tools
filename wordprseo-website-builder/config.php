<?php
$host = "localhost";
$dbname = "greenm38_wordprseo_builder";
$username = "greenm38_wordprseo_builder"; // replace with your actual username
$password = "ph&zpit.MNeQ"; // replace with your secure password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
