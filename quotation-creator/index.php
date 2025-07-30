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
.quote-table table th, .quote-table table td { vertical-align: top; }
.add-btn { cursor: pointer; }
.package-selector .dropdown-menu{min-width:260px;}
.table thead th{background:#000;color:#fff;font-weight:bold;}
.term-select{min-width:110px;}
</style>

<div class="package-selector mb-3 d-flex flex-wrap gap-2">
<?php if(!$packages): ?>
  <div class="alert alert-warning">Unable to retrieve pricing packages.</div>
<?php else: ?>
  <?php foreach($packages as $sIndex => $svc): ?>
    <div class="dropdown">
      <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <?= htmlspecialchars($svc['name']) ?>
      </button>
      <div class="dropdown-menu p-2 shadow">
        <?php foreach($svc['packages'] as $i => $p): ?>
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span><strong>Pack <?= $i + 1 ?></strong> - $<?= number_format($p['usd_val'],0) ?> <span class="text-muted">(EGP <?= number_format($p['egp_val'],0) ?>)</span></span>
          <span class="add-btn text-primary ms-2" data-service="<?= htmlspecialchars($svc['name']) ?>" data-usd="<?= $p['usd_val'] ?>" data-egp="<?= $p['egp_val'] ?>" data-desc="<?= htmlspecialchars(implode("\n", $p['details'])) ?>">&#43;</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<div id="quote-area">
<div class="row align-items-center mb-3 text-center text-md-start">
  <div class="col-md-4 mb-3 mb-md-0">
    <img src="https://i.ibb.co/d0T2Pzb3/Green-Mind-Agency-Logo.png" alt="Logo" style="height:50px;">
    <h1 class="h4 mt-2">Green Mind Agency</h1>
  </div>
  <div class="col-md-4">
    <h2 id="clientDisplay" class="h5"></h2>
    <p class="mb-1"><small>These prices are valid for 1 month until <span id="validUntil"></span></small></p>
    <div class="input-group input-group-sm mx-auto" style="max-width:300px;">
      <input type="text" id="clientName" class="form-control" placeholder="Client Name">
      <button id="updateBtn" class="btn btn-outline-secondary">Update</button>
    </div>
  </div></div>

<div class="d-flex justify-content-end mb-2">
  <button id="addTableBtn" class="btn btn-primary btn-sm">+</button>
</div>
<div id="tables">
  <div class="quote-table mb-4">
    <table class="table table-bordered">
      <thead><tr><th>Service</th><th>Service Details</th><th>Payment Term</th><th>Total Cost USD</th><th>Cost EGP</th><th></th></tr></thead>
      <tbody></tbody>
      <tfoot></tfoot>
    </table>
  </div>
</div>
</div>
</div>
</div>
<button class="btn btn-success mt-3" onclick="downloadPDF()">Download PDF</button>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
const validUntilEl=document.getElementById('validUntil');
const today=new Date();
validUntilEl.textContent=new Date(today.getFullYear(),today.getMonth()+1,today.getDate()).toISOString().split('T')[0];
function updateHeader(){
  const name=document.getElementById('clientName').value.trim();
  document.getElementById('clientDisplay').textContent=name;
}

function addRow(service, desc, usd, egp){
  const tbody=getCurrentTbody();
  const tr=document.createElement('tr');
  const term="one-time";
  tr.innerHTML='<td><strong>'+service+'</strong></td>'+
    '<td contenteditable="true">'+desc.replace(/\n/g,'<br>')+'</td>'+
    '<td><select class="form-select form-select-sm term-select"><option value="one-time">One-time</option><option value="monthly">Monthly</option></select></td>'+
    '<td class="usd">$'+usd.toLocaleString("en-US")+'</td><td class="egp">EGP '+egp.toLocaleString("en-US")+'</td>'+
    '<td><button class="btn btn-sm btn-danger">&times;</button></td>';
  tr.querySelector('.term-select').value=term;
  tr.querySelector('button').addEventListener('click', ()=>{tr.remove();updateTotals();});
  tbody.appendChild(tr);
  updateTotals();
}
function getCurrentTbody(){
  const tbs=document.querySelectorAll('#tables .quote-table tbody');
  return tbs[tbs.length-1];
}

function updateTotals(){
  document.querySelectorAll('#tables .quote-table').forEach(tbl=>{
    const totals={"one-time":{usd:0,egp:0},"monthly":{usd:0,egp:0}};
    tbl.querySelectorAll('tbody tr').forEach(tr=>{
      const term=tr.querySelector('.term-select').value;
      totals[term].usd+=parseFloat(tr.querySelector('.usd').textContent.replace(/[^0-9.]/g,''));
      totals[term].egp+=parseFloat(tr.querySelector('.egp').textContent.replace(/[^0-9.]/g,''));
    });
    const tfoot=tbl.querySelector('tfoot');
    tfoot.innerHTML='';
    for(const key of Object.keys(totals)){
      const t=totals[key];
      if(t.usd===0 && t.egp===0) continue;
      const vat=t.egp*0.14;
      tfoot.innerHTML+=`<tr class="table-secondary fw-bold"><th colspan="3" class="text-end">Total</th><th class="bg-warning text-black">$${t.usd.toLocaleString('en-US')}</th><th class="bg-warning text-black">EGP ${t.egp.toLocaleString('en-US')}</th><th></th></tr>`+
        `<tr class="bg-danger text-white"><th colspan="4" class="text-end">VAT 14%</th><th>EGP ${vat.toLocaleString('en-US')}</th><th></th></tr>`+
        `<tr class="text-white" style="background:#14633c"><th colspan="4" class="text-end">Total + VAT</th><th>EGP ${(t.egp+vat).toLocaleString('en-US')}</th><th></th></tr>`;
    }
  });
}
function createTable(){
  const container=document.createElement("div");
  container.className="quote-table mb-4";
  container.innerHTML="<table class="table table-bordered"><thead><tr><th>Service</th><th>Service Details</th><th>Payment Term</th><th>Total Cost USD</th><th>Cost EGP</th><th></th></tr></thead><tbody></tbody><tfoot></tfoot></table>";
  document.getElementById("tables").appendChild(container);
  new Sortable(container.querySelector("tbody"),{animation:150});
  updateTotals();
}
document.getElementById("addTableBtn").addEventListener("click",createTable);
document.querySelectorAll('.add-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    addRow(btn.dataset.service, btn.dataset.desc, parseFloat(btn.dataset.usd), parseFloat(btn.dataset.egp));
  });
});
document.getElementById('updateBtn').addEventListener('click', updateHeader);
updateHeader();
document.querySelectorAll("#tables .quote-table tbody").forEach(tb=>{new Sortable(tb,{animation:150});});
function downloadPDF(){
  const { jsPDF } = window.jspdf;
  html2canvas(document.getElementById('quote-area')).then(canvas=>{
    const img=canvas.toDataURL('image/png');
    const pdf=new jsPDF({orientation:'portrait', unit:'mm', format:'a4'});
    const imgProps=pdf.getImageProperties(img);
    const pdfWidth=pdf.internal.pageSize.getWidth();
    const pdfHeight=(imgProps.height * pdfWidth) / imgProps.width;
    pdf.addImage(img,'PNG',0,0,pdfWidth,pdfHeight);
    pdf.save('quote.pdf');
  });
}
</script>
<?php include 'footer.php'; ?>
