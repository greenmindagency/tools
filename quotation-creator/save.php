<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);
$name = trim($data['name'] ?? '');
$html = $data['html'] ?? '';
$publish = !empty($data['publish']);
$slugify = function($text){
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
};
$slugBase = $slugify($name);
$slug = $slugBase ?: bin2hex(random_bytes(5));
$check = $pdo->prepare('SELECT id FROM clients WHERE slug=? AND id<>?');
$counter = 1;
while(true){
    $check->execute([$slug, $id]);
    if(!$check->fetchColumn()) break;
    $slug = $slugBase . '-' . $counter++;
}
$pdo->exec("CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    html MEDIUMTEXT,
    slug VARCHAR(255) UNIQUE,
    short_url VARCHAR(255),
    published TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS short_url VARCHAR(255)");
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
    $link = sprintf('view.php?client=%s', urlencode($slug));
}
echo json_encode(['success'=>true,'id'=>$id,'link'=>$link,'slug'=>$slug]);
?>
