<?php

function rotate_position_months(PDO $pdo, int $clientId, string $country): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS keyword_months (
        client_id INT NOT NULL,
        country VARCHAR(3) NOT NULL DEFAULT '',
        last_month CHAR(7) NOT NULL,
        PRIMARY KEY (client_id, country),
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");

    $current = date('Y-m');
    $stmt = $pdo->prepare('SELECT last_month FROM keyword_months WHERE client_id = ? AND country = ?');
    $stmt->execute([$clientId, $country]);
    $last = $stmt->fetchColumn();

    if (!$last) {
        $ins = $pdo->prepare('REPLACE INTO keyword_months (client_id, country, last_month) VALUES (?, ?, ?)');
        $ins->execute([$clientId, $country, $current]);
        return;
    }

    $lastTime = strtotime($last . '-01');
    $currTime = strtotime($current . '-01');
    $diff = ((int)date('Y', $currTime) - (int)date('Y', $lastTime)) * 12 + ((int)date('n', $currTime) - (int)date('n', $lastTime));
    if ($diff <= 0) return;
    $diff = min(12, $diff);

    for ($d = 0; $d < $diff; $d++) {
        for ($i = 13; $i >= 2; $i--) {
            $from = 'm' . ($i - 1);
            $to   = 'm' . $i;
            $pdo->prepare("UPDATE keyword_positions SET `$to` = `$from` WHERE client_id = ? AND country = ?")
                ->execute([$clientId, $country]);
        }
        $pdo->prepare("UPDATE keyword_positions SET m1 = NULL, sort_order = NULL WHERE client_id = ? AND country = ?")
            ->execute([$clientId, $country]);
    }

    $up = $pdo->prepare('REPLACE INTO keyword_months (client_id, country, last_month) VALUES (?, ?, ?)');
    $up->execute([$clientId, $country, $current]);
}

