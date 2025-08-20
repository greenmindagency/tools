<?php
// Database configuration
const DB_DSN = 'mysql:host=localhost;dbname=greenm38_todotasks;charset=utf8mb4';
const DB_USER = 'greenm38_todotasks';
const DB_PASS = '8QPNw)_?Jc-S';

// Admin credentials
const ADMIN_USER = 'peter';
const ADMIN_PASS_HASH = '$2y$12$peUkaingLFZ/4ykdcRe1q.VM7bwXv8nUirMXZzV0se.tTl5FLbHxm';

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
        $stmt = $pdo->prepare('INSERT IGNORE INTO users (username) VALUES (?)');
        $stmt->execute([ADMIN_USER]);
    }
}
?>
