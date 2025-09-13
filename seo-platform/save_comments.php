<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id = $_SESSION['client_id'] ?? ($input['client_id'] ?? 0);
$date = $input['date'] ?? '';
$comments = $input['comments'] ?? [];
if (!$client_id || !$date) {
    echo json_encode(['status'=>'error']);
    exit;
}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_content (
      id INT AUTO_INCREMENT PRIMARY KEY,
      client_id INT,
      post_date DATE,
      title TEXT,
      cluster VARCHAR(255),
      content_type VARCHAR(100),
      doc_link TEXT,
      status VARCHAR(50),
      comments TEXT
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    try { $pdo->exec("ALTER TABLE client_content ADD COLUMN comments TEXT"); } catch (PDOException $e) {}
    $stmt = $pdo->prepare('UPDATE client_content SET comments = ? WHERE client_id = ? AND post_date = ?');
    $stmt->execute([json_encode($comments), $client_id, $date]);
    if ($stmt->rowCount() === 0) {
        $ins = $pdo->prepare('INSERT INTO client_content (client_id, post_date, comments) VALUES (?,?,?)');
        $ins->execute([$client_id, $date, json_encode($comments)]);
    }
    echo json_encode(['status'=>'ok']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error']);
}
?>
