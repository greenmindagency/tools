<?php
require_once __DIR__ . '/config.php';
$slug = $_GET['client'] ?? '';
$pdo->exec("CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    html MEDIUMTEXT,
    slug VARCHAR(255) UNIQUE,
    published TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$stmt = $pdo->prepare('SELECT name, html FROM clients WHERE slug=? AND published=1');
$stmt->execute([$slug]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$data){
    http_response_code(404);
    echo 'Quote not found';
    exit;
}
$html = preg_replace('/\bPrices\b/i','',$data['html']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quotation for <?= htmlspecialchars($data['name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.hide-egp .egp,
.hide-egp .egp-header,
.hide-egp .vat-row,
.hide-egp .total-vat-row{display:none;}
.hide-usd .usd,
.hide-usd .usd-header{display:none;}
.hide-usd .vat-row th:nth-child(2),
.hide-usd .total-vat-row th:nth-child(2){display:none;}
.table thead th{background:#000;color:#fff;font-weight:bold;}
</style>
</head>
<body>
<div class="container mt-4">
<?= $html ?>
</div>
</body>
</html>
