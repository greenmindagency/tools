<?php
$title = 'Quotation Creator';
include 'header.php';
require_once __DIR__ . '/lib.php';

function fetch_packages() {
    $cache = __DIR__ . '/pricing-cache.json';

    // Always try to get the latest prices from the live page first
    $url = 'https://greenmindagency.com/price-list/';
    $html = fetch_remote_html($url);

    if (!$html) {
        // Fallback to local static file
        $localPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/').'/price-list/index.html';
        if (is_readable($localPath)) {
            $html = file_get_contents($localPath);
        } elseif (is_readable($cache)) {
            $data = json_decode(file_get_contents($cache), true);
            if ($data) {
                return $data;
            }
        }
    }

    if (!$html) {
        return [];
    }

    $data = parse_html($html);
    file_put_contents($cache, json_encode($data));
    return $data;
}
$packages = fetch_packages();
?>
<style>
#quoteTable th, #quoteTable td { vertical-align: top; }
.add-btn { cursor: pointer; }
</style>
<div class="row">
<div class="col-md-4">
<?php if(!$packages): ?>
<div class="alert alert-warning">Unable to retrieve pricing packages.</div>
<?php else: ?>
<?php foreach($packages as $svc): ?>
<h5><strong><?= htmlspecialchars($svc['name']) ?></strong></h5>
<?php foreach($svc['packages'] as $i => $p): ?>
<div class="card mb-2">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div>
      <strong>Pack <?= $i + 1 ?></strong> - $<?= number_format($p['usd_val'],0) ?> <span class="text-muted">(EGP <?= number_format($p['egp_val'],0) ?>)</span>
    </div>
    <div class="add-btn text-primary" data-service="<?= htmlspecialchars($svc['name']) ?>" data-usd="<?= $p['usd_val'] ?>" data-egp="<?= $p['egp_val'] ?>" data-desc="<?= htmlspecialchars(implode("\n", $p['details'])) ?>">&#43;</div>
  </div>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="col-md-8">
<div id="quote-area">
<div class="d-flex justify-content-between align-items-center mb-3">
    <img src="https://i.ibb.co/d0T2Pzb3/Green-Mind-Agency-Logo.png" alt="Logo" style="height:40px;">
    <div class="ms-auto">
        <div class="input-group input-group-sm">
            <input type="text" id="clientName" class="form-control" placeholder="Client Name">
            <button id="updateBtn" class="btn btn-outline-secondary">Update</button>
        </div>
    </div>
</div>
<h4 id="quoteTitle">Table of Prices - <?= date('Y-m-d') ?></h4>
<table class="table" id="quoteTable">
<thead><tr><th>Service</th><th>Service Details</th><th>Total Cost USD</th><th>Cost EGP</th><th></th></tr></thead>
<tbody></tbody>
<tfoot>
<tr><th colspan="2" class="text-end">Total</th><th id="tUsd">$0</th><th id="tEgp">EGP 0</th><th></th></tr>
<tr><th colspan="3" class="text-end">VAT 14%</th><th id="tVat">EGP 0</th><th></th></tr>
<tr><th colspan="3" class="text-end">Total + VAT</th><th id="tEgpVat">EGP 0</th><th></th></tr>
</tfoot>
</table>
</div>
</div>
</div>
<button class="btn btn-success mt-3" onclick="downloadPDF()">Download PDF</button>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
const today = document.getElementById('quoteTitle').textContent.split(' - ')[1];
function updateHeader(){
  const name=document.getElementById('clientName').value.trim();
  let title='Table of Prices - '+today;
  if(name){
    title+=' - '+name;
  }
  document.getElementById('quoteTitle').textContent=title;
}

function addRow(service, desc, usd, egp){
  const tbody=document.querySelector('#quoteTable tbody');
  const tr=document.createElement('tr');
  tr.innerHTML='<td><strong>'+service+'</strong></td><td>'+desc.replace(/\n/g,'<br>')+'</td><td class="usd">$'+usd.toFixed(0)+'</td><td class="egp">EGP '+egp.toFixed(0)+'</td><td><button class="btn btn-sm btn-danger">&times;</button></td>';
  tr.querySelector('button').addEventListener('click', ()=>{tr.remove();updateTotals();});
  tbody.appendChild(tr);updateTotals();
}
function updateTotals(){
  let usd=0, egp=0;
  document.querySelectorAll('#quoteTable tbody tr').forEach(tr=>{
    usd+=parseFloat(tr.querySelector('.usd').textContent.replace(/[^0-9.]/g,''));
    egp+=parseFloat(tr.querySelector('.egp').textContent.replace(/[^0-9.]/g,''));
  });
  document.getElementById('tUsd').textContent='$'+usd.toFixed(0);
  document.getElementById('tEgp').textContent='EGP '+egp.toFixed(0);
  const vat = egp*0.14;
  document.getElementById('tVat').textContent='EGP '+vat.toFixed(0);
  document.getElementById('tEgpVat').textContent='EGP '+(egp+vat).toFixed(0);
}
document.querySelectorAll('.add-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    addRow(btn.dataset.service, btn.dataset.desc, parseFloat(btn.dataset.usd), parseFloat(btn.dataset.egp));
  });
});
document.getElementById('updateBtn').addEventListener('click', updateHeader);
updateHeader();
function downloadPDF(){
  const { jsPDF } = window.jspdf;
  html2canvas(document.getElementById('quote-area')).then(canvas=>{
    const img=canvas.toDataURL('image/png');
    const pdf=new jsPDF();
    const imgProps=pdf.getImageProperties(img);
    const pdfWidth=pdf.internal.pageSize.getWidth();
    const pdfHeight=(imgProps.height * pdfWidth) / imgProps.width;
    pdf.addImage(img,'PNG',0,0,pdfWidth,pdfHeight);
    pdf.save('quote.pdf');
  });
}
</script>
<?php include 'footer.php'; ?>
