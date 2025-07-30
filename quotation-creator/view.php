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
$html = $data['html'];
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
html,body{transition:font-size .2s;}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
</head>
<body>
<div id="quote" class="container mt-4">
  <button id="downloadBtn" class="btn btn-primary mb-3">Download PDF</button>
  <?= $html ?>
</div>
<script>
const clientName = <?= json_encode($data['name']) ?>;
document.getElementById('downloadBtn').addEventListener('click', () => {
  const element = document.getElementById('quote');
  const btn = document.getElementById('downloadBtn');
  btn.style.display = 'none';
  const root = document.documentElement;
  const prevRoot = root.style.fontSize;
  const body = document.body;
  const prevBody = body.style.fontSize;
  root.style.fontSize = '60%';
  body.style.fontSize = '130%';
  const opt = {
    margin: 10,
    filename: `Table of Prices - ${clientName} - ${new Date().toISOString().slice(0,10)}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 1 },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  };
  setTimeout(() => {
    html2pdf().set(opt).from(element).save().then(() => {
      btn.style.display = '';
      root.style.fontSize = prevRoot;
      body.style.fontSize = prevBody;
    });
  }, 100);
});
</script>
</body>
</html>
