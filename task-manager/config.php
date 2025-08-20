<?php
// Database configuration
const DB_DSN = 'mysql:host=localhost;dbname=greenm38_todotasks;charset=utf8mb4';
const DB_USER = 'greenm38_todotasks';
const DB_PASS = '8QPNw)_?Jc-S';

// Admin credentials
const ADMIN_USER = 'Peter';
const ADMIN_PASS_HASH = '$2y$10$sSEDcVY0xFcCpgoSoltYvOnWP7J7slbe35fgPwHCb3WM6rGmZXi2K';

function get_pdo(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    return $pdo;
}

function init_db(PDO $pdo): void {
    $sqlFile = __DIR__ . '/setup.sql';
    if (is_readable($sqlFile)) {
        $pdo->exec(file_get_contents($sqlFile));
        $stmt = $pdo->prepare('INSERT IGNORE INTO users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([ADMIN_USER, ADMIN_PASS_HASH]);
    }
}
?>
