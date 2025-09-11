<?php
require_once __DIR__ . '/../config.php';

function cache_init(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS gsc_cache (
        client_id INT NOT NULL,
        cache_key VARCHAR(255) NOT NULL,
        response LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (client_id, cache_key),
        INDEX idx_client (client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function cache_get(PDO $pdo, int $clientId, string $key): ?array {
    cache_init($pdo);
    $stmt = $pdo->prepare('SELECT response FROM gsc_cache WHERE client_id = ? AND cache_key = ?');
    $stmt->execute([$clientId, $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? json_decode($row['response'], true) : null;
}

function cache_set(PDO $pdo, int $clientId, string $key, $data): void {
    cache_init($pdo);
    $stmt = $pdo->prepare('REPLACE INTO gsc_cache (client_id, cache_key, response, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$clientId, $key, json_encode($data)]);
    // keep only the most recent 12 months of cache entries
    $pdo->exec("DELETE FROM gsc_cache WHERE created_at < DATE_SUB(NOW(), INTERVAL 13 MONTH)");
}

function cache_clear_client(PDO $pdo, int $clientId): void {
    cache_init($pdo);
    $stmt = $pdo->prepare('DELETE FROM gsc_cache WHERE client_id = ?');
    $stmt->execute([$clientId]);
}
