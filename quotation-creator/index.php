<?php
$title = 'Quotation Creator';
include 'header.php';
require_once __DIR__ . '/lib.php';

function fetch_packages() {
    // Use cached data if available
    $cache = __DIR__ . '/pricing-cache.json';
    if (is_readable($cache)) {
        $data = json_decode(file_get_contents($cache), true);
        if ($data) {
            return $data;
        }
    }

    // Try loading the pricing page directly from the same host
    $localPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/').'/price-list/';
    if (is_readable($localPath)) {
        $html = file_get_contents($localPath);
    } else {
        // Fallback to fetching over HTTP
        $url = 'https://greenmindagency.com/price-list/';
        $html = @file_get_contents($url);
        if (!$html) {
            return [];
        }
    }

    $data = parse_html($html);

    // Save cache for future use
    file_put_contents($cache, json_encode($data));

    return $data;
}
$packages = fetch_packages();
?>
<style>
#quoteTable th, #quoteTable td { vertical-align: top; }
.add-btn { cursor: pointer; }
</style>
<div class="mb-3">
  <button id="refreshBtn" class="btn btn-outline-secondary btn-sm">Refresh live pricing</button>
  <span id="updateStatus" class="ms-2 text-muted"></span>
</div>
<div class="row">
<div class="col-md-6">
<?php if(!$packages): ?>
<div class="alert alert-warning">Unable to retrieve pricing packages.</div>
<?php else: ?>
<?php foreach($packages as $svc): ?>
<h5><?= htmlspecialchars($svc['name']) ?></h5>
<?php foreach($svc['packages'] as $p): ?>
<div class="card mb-2">
  <div class="card-body">
    <div class="d-flex justify-content-between">
      <div>
        <strong><?= htmlspecialchars($p['usd']) ?></strong> <span class="text-muted">(<?= htmlspecialchars($p['egp']) ?>)</span>
      </div>
      <div class="add-btn text-primary" data-service="<?= htmlspecialchars($svc['name']) ?>" data-usd="<?= $p['usd_val'] ?>" data-egp="<?= $p['egp_val'] ?>" data-desc="<?= htmlspecialchars(implode("\n", $p['details'])) ?>">&#43;</div>
    </div>
    <ul class="mb-0">
      <?php foreach($p['details'] as $d): ?><li><?= htmlspecialchars($d) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="col-md-6">
<div id="quote-area">
<div class="d-flex justify-content-between align-items-center mb-3">
    <img src="https://greenmindagency.com/greenmindagency15/wp-content/themes/gmbuilder/Green-Mind-Agency-Logo.jpg" alt="Logo" style="height:40px;">
    <div class="ms-auto text-end">
        <input type="text" id="clientName" class="form-control form-control-sm mb-1" placeholder="Client Name">
        <input type="date" id="quoteDate" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
    </div>
</div>
<h4 id="quoteTitle">Table of Prices</h4>
<table class="table" id="quoteTable">
<thead><tr><th>Service</th><th>Service Details</th><th>Total Cost USD</th><th>Cost EGP</th><th></th></tr></thead>
<tbody></tbody>
<tfoot><tr><th colspan="2" class="text-end">Total</th><th id="tUsd">$0</th><th id="tEgp">EGP 0</th><th></th></tr></tfoot>
</table>
</div>
</div>
</div>
<button class="btn btn-success mt-3" onclick="downloadPDF()">Download PDF</button>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function updateHeader(){
  const name=document.getElementById('clientName').value.trim();
  const date=document.getElementById('quoteDate').value;
  let title='Table of Prices';
  if(name||date){
    title+=' - '+name+(date?' '+date:'');
  }
  document.getElementById('quoteTitle').textContent=title;
}

function addRow(service, desc, usd, egp){
  const tbody=document.querySelector('#quoteTable tbody');
  const tr=document.createElement('tr');
  tr.innerHTML='<td>'+service+'</td><td>'+desc.replace(/\n/g,'<br>')+'</td><td class="usd">$'+usd.toFixed(2)+'</td><td class="egp">EGP '+egp.toFixed(2)+'</td><td><button class="btn btn-sm btn-danger">&times;</button></td>';
  tr.querySelector('button').addEventListener('click', ()=>{tr.remove();updateTotals();});
  tbody.appendChild(tr);updateTotals();
}
function updateTotals(){
  let usd=0, egp=0;
  document.querySelectorAll('#quoteTable tbody tr').forEach(tr=>{
    usd+=parseFloat(tr.querySelector('.usd').textContent.replace(/[^0-9.]/g,''));
    egp+=parseFloat(tr.querySelector('.egp').textContent.replace(/[^0-9.]/g,''));
  });
  document.getElementById('tUsd').textContent='$'+usd.toFixed(2);
  document.getElementById('tEgp').textContent='EGP '+egp.toFixed(2);
}
document.querySelectorAll('.add-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    addRow(btn.dataset.service, btn.dataset.desc, parseFloat(btn.dataset.usd), parseFloat(btn.dataset.egp));
  });
});
document.getElementById('clientName').addEventListener('input', updateHeader);
document.getElementById('quoteDate').addEventListener('input', updateHeader);
updateHeader();
document.getElementById('refreshBtn').addEventListener('click', () => {
  const status = document.getElementById('updateStatus');
  status.textContent = 'Refreshing...';
  fetch('update-cache.php')
    .then(r => { if(!r.ok) throw new Error(); return r.json(); })
    .then(data => {
      if (data.success) {
        status.textContent = 'Pricing updated.';
        location.reload();
      } else {
        throw new Error();
      }
    })
    .catch(() => { status.textContent = 'Failed to update pricing.'; });
});
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
