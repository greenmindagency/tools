<?php
$host = "localhost";
$dbname = "greenm38_smc_platform";
$username = "greenm38_smc_platform"; // replace with your actual username
$password = "ph&zpit.MNeQ"; // replace with your secure password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $tables = ['clients','keywords','keyword_positions','sc_domains','keyword_stats'];
    foreach ($tables as $tbl) {
        try {
            $pdo->exec("ALTER TABLE `".$tbl."` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // ignore missing tables
        }
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
