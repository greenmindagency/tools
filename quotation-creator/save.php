<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);
$name = trim($data['name'] ?? '');
$html = $data['html'] ?? '';
$publish = !empty($data['publish']);
$slug = $data['slug'] ?? '';
if (!$slug) $slug = bin2hex(random_bytes(5));
$pdo->exec("CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    html MEDIUMTEXT,
    slug VARCHAR(255) UNIQUE,
    published TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
if ($id) {
    $stmt = $pdo->prepare('UPDATE clients SET name=?, html=?, published=?, slug=? WHERE id=?');
    $stmt->execute([$name, $html, $publish?1:0, $slug, $id]);
} else {
    $stmt = $pdo->prepare('INSERT INTO clients (name, html, published, slug) VALUES (?,?,?,?)');
    $stmt->execute([$name, $html, $publish?1:0, $slug]);
    $id = $pdo->lastInsertId();
}
$link = null;
if ($publish) {
    $link = sprintf('view.php?slug=%s', urlencode($slug));
}
echo json_encode(['success'=>true,'id'=>$id,'link'=>$link]);
?>
