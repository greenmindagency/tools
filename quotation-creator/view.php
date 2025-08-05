<?php
require_once __DIR__ . '/config.php';
$slug = $_GET['client'] ?? '';
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
$stmt = $pdo->prepare('SELECT id, name, html, short_url FROM clients WHERE slug=? AND published=1');
$stmt->execute([$slug]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$data){
    http_response_code(404);
    echo 'Quote not found';
    exit;
}
$html = $data['html'];
$shortUrl = $data['short_url'] ?? '';
if(!$shortUrl){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $currentUrl = $scheme.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $shortUrl = file_get_contents('https://tinyurl.com/api-create.php?url='.urlencode($currentUrl));
    $upd = $pdo->prepare('UPDATE clients SET short_url=? WHERE id=?');
    $upd->execute([$shortUrl, $data['id']]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quotation for <?= htmlspecialchars($data['name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--tbl-border:1px;}
.hide-egp .egp,
.hide-egp .egp-header,
.hide-egp .vat-row,
.hide-egp .total-vat-row{display:none;}
.hide-usd .usd,
.hide-usd .usd-header{display:none;}
.hide-usd .vat-row th:nth-child(2),
.hide-usd .total-vat-row th:nth-child(2){display:none;}
.table thead th{background:#000;color:#fff;font-weight:bold;}
.table-bordered{border-color:#000;border-width:var(--tbl-border);}
.table-bordered th,.table-bordered td{border-color:#000;border-width:var(--tbl-border);vertical-align:middle; border:1px}
.quote-table{table-layout:fixed;width:100%;}
.quote-table th:nth-child(1),.quote-table td:nth-child(1){width:25%;}
.quote-table th:nth-child(2),.quote-table td:nth-child(2){width:36%;}
.quote-table th:nth-child(3),.quote-table td:nth-child(3){width:15%;text-align:center;}
.quote-table th:nth-child(4),.quote-table td:nth-child(4){width:12%;text-align:center;}
.quote-table th:nth-child(5),.quote-table td:nth-child(5){width:12%;text-align:center;}
html,body{transition:font-size .2s;}
.content-block{border:1px dashed #ccc;padding:10px;min-height:60px;margin-bottom:1rem;}
.vat-row .egp{background:#eb8a94!important;color:#000!important;}
.total-vat-row .egp{background:#5edf9f!important;color:#000!important;}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
</head>
<body>
<div id="quote" class="container mt-4">
  <div class="d-flex gap-2 mb-3">
    <button id="downloadBtn" class="btn btn-primary">Download PDF</button>
    <button id="copyLinkBtn" class="btn btn-outline-secondary" data-url="<?= htmlspecialchars($shortUrl) ?>" title="Copy short link">&#128279;</button>
  </div>
  <?= $html ?>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="copyToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-body">Link copied to clipboard</div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const clientName = <?= json_encode($data['name']) ?>;
const copyBtn = document.getElementById('copyLinkBtn');
  document.getElementById('downloadBtn').addEventListener('click', () => {
    const element = document.getElementById('quote');
    const btn = document.getElementById('downloadBtn');
    const controls = btn.parentElement;
    controls.remove();
    const root = document.documentElement;
    const prevRoot = root.style.fontSize;
    const prevBorder = root.style.getPropertyValue('--tbl-border');
    const body = document.body;
    const prevBody = body.style.fontSize;
    root.style.fontSize = '50%';
    body.style.fontSize = '100%';
    root.style.setProperty('--tbl-border','0.5px');
    const opt = {
      margin: 10,
      filename: `Table of Prices - ${clientName} - ${new Date().toISOString().slice(0,10)}.pdf`,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2 }, // higher scale for clearer text
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    setTimeout(() => {
      html2pdf().set(opt).from(element).save().then(() => {
        element.insertBefore(controls, element.firstChild);
        root.style.fontSize = prevRoot;
        body.style.fontSize = prevBody;
        root.style.setProperty('--tbl-border', prevBorder || '1px');
      });
    }, 100);
  });
if (copyBtn) {
  copyBtn.addEventListener('click', () => {
    navigator.clipboard.writeText(copyBtn.dataset.url).then(() => {
      const toast = new bootstrap.Toast(document.getElementById('copyToast'));
      toast.show();
    });
  });
}
document.querySelectorAll('th').forEach(th=>{
  if(th.classList.contains('usd-header') || th.textContent.trim()==='Total Cost USD'){
    th.textContent='Cost USD';
    th.classList.add('usd-header');
  }
});
if (copyBtn) {
  copyBtn.addEventListener('click', () => {
    navigator.clipboard.writeText(copyBtn.dataset.url).then(() => {
      const toast = new bootstrap.Toast(document.getElementById('copyToast'));
      toast.show();
    });
  });
}
document.querySelectorAll('th').forEach(th=>{
  if(th.classList.contains('usd-header') || th.textContent.trim()==='Total Cost USD'){
    th.textContent='Cost USD';
    th.classList.add('usd-header');
  }
});
</script>
</body>
</html>