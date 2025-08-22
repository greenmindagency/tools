<?php
// Database configuration
const DB_DSN = 'mysql:host=localhost;dbname=greenm38_todotasks;charset=utf8mb4';
const DB_USER = 'greenm38_todotasks';
const DB_PASS = '8QPNw)_?Jc-S';

// Admin credentials
const ADMIN_USER = 'peter';
// Uses the same hashed password as other tools so credentials stay consistent
const ADMIN_PASS_HASH = '$2y$10$oLagrVLcLxtWr2K3Ghmq9euso7jSCVDxS4hCyZgVfUpCkAlj9RajW';

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
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)' .
                               ' ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)');
        $stmt->execute([ADMIN_USER, ADMIN_PASS_HASH]);
    }
}

function refresh_client_priorities(PDO $pdo): void {
    $url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQiesZdFZ-jCIcuz5J53buN5ACEepvOiKcDDbn66fQpxEe9dfKBL86FLXIPH1dYUR9N6pFaUcjPw0CX/pub?gid=542324811&single=true&output=csv';
    $csv = @file_get_contents($url);
    if ($csv === false) return;
    $rows = array_map('str_getcsv', explode("\n", trim($csv)));
    $stmt = $pdo->prepare('UPDATE clients SET priority=?, sort_order=? WHERE name=?');
    foreach ($rows as $i => $row) {
        if ($i === 0) continue;
        if (count($row) >= 18) {
            $client = trim($row[16]);
            $prio = trim($row[17]);
            if ($client !== '') {
                $stmt->execute([
                    $prio !== '' ? $prio : null,
                    $i - 1,
                    $client
                ]);
            }
        }
    }
    $pdo->exec('UPDATE tasks t JOIN clients c ON t.client_id=c.id SET t.priority=c.priority');
}

?>
