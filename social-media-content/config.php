<?php
// Database connection dedicated for the social media content tool
$host = "localhost";
$dbname = "greenm38_smc_platform";
$username = "greenm38_smc_platform"; // replace with your actual username
$password = "ph&zpit.MNeQ"; // replace with your secure password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Ensure required tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(255),
        pass_hash VARCHAR(255)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Store uploaded source material for each client
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_sources (
        client_id INT PRIMARY KEY,
        source LONGTEXT
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Saved calendar entries for each client
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_calendar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        post_date DATE NOT NULL,
        title VARCHAR(255),
        content TEXT,
        UNIQUE KEY uniq_client_date (client_id, post_date)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    if (!$pdo->query("SHOW COLUMNS FROM client_calendar LIKE 'content'")->fetch()) {
        $pdo->exec("ALTER TABLE client_calendar ADD COLUMN content TEXT AFTER title");
    }

    // Short links for shared calendars
    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        year INT NOT NULL,
        month INT NOT NULL,
        short_url VARCHAR(255),
        UNIQUE KEY uniq_client_month (client_id, year, month)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Manual occasions repository, scoped per client
    $pdo->exec("CREATE TABLE IF NOT EXISTS occasions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        country VARCHAR(100) NOT NULL,
        occasion_date DATE NOT NULL,
        name VARCHAR(255) NOT NULL,
        INDEX idx_client (client_id)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Ensure client_id column exists for legacy tables
    if (!$pdo->query("SHOW COLUMNS FROM occasions LIKE 'client_id'")->fetch()) {
        $pdo->exec("ALTER TABLE occasions ADD COLUMN client_id INT NOT NULL AFTER id");
        $pdo->exec("ALTER TABLE occasions ADD INDEX idx_client (client_id)");
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
