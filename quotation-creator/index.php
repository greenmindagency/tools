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
.quote-table th, .quote-table td { vertical-align: top; }
.add-btn { cursor: pointer; }
.package-selector .dropdown-menu{min-width:260px;}
.table thead th{background:#000;color:#fff;font-weight:bold;}
.term-select{min-width:110px;}
.table-handle{cursor:move;}
/* center the payment term and cost columns */
.quote-table th:nth-child(3),
.quote-table th:nth-child(4),
.quote-table th:nth-child(5),
.quote-table td:nth-child(3),
.quote-table td:nth-child(4),
.quote-table td:nth-child(5){
  text-align:center;
}
/* hide egp column when toggle class present */
.hide-egp .egp,
.hide-egp .egp-header,
.hide-egp .vat-row,
.hide-egp .total-vat-row{
  display:none;
}
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

<div id="client-input" class="mb-3" style="max-width:300px;">
  <div class="input-group input-group-sm">
    <input type="text" id="clientName" class="form-control" placeholder="Client Name">
  </div>
</div>

<div id="quote-area">
<div class="row align-items-center mb-3 text-center text-md-start">
  <div class="col-md-4 mb-3 mb-md-0">
    <img src="https://i.ibb.co/d0T2Pzb3/Green-Mind-Agency-Logo.png" alt="Logo" style="height:50px;" crossorigin="anonymous">
    <h1 class="h4 mt-2">Green Mind Agency</h1>
  </div>
  <div class="col-md-4">
    <h2 id="clientDisplay" class="h5 text-start"></h2>
    <p class="mb-1 text-start"><small>These prices are valid for 1 month until <span id="validUntil"></span></small></p>
  </div>
  <div class="col-md-4">
    <p class="mb-1 text-start">Date:</p>
    <p id="currentDate" class="mb-0 text-start"></p>
  </div>
</div>

<div id="tablesContainer"></div>
<button id="addTableBtn" class="btn btn-primary btn-sm mb-3">&#43; Add Table</button>
</div>
</div>
</div>
<div class="d-flex align-items-center gap-3 mt-3">
  <div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" id="toggleEGP">
    <label class="form-check-label" for="toggleEGP">Hide EGP</label>
  </div>
  <button class="btn btn-success" onclick="downloadPDF()">Download PDF</button>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
const validUntilEl=document.getElementById('validUntil');
const currentDateEl=document.getElementById('currentDate');
const today=new Date();
validUntilEl.textContent=new Date(today.getFullYear(),today.getMonth()+1,today.getDate()).toISOString().split('T')[0];
currentDateEl.textContent=today.toLocaleDateString('en-GB',{timeZone:'Africa/Cairo'});
function updateHeader(){
  const name=document.getElementById('clientName').value.trim();
  document.getElementById('clientDisplay').textContent=name;
}

function formatNum(num){
  return Number(num).toLocaleString('en-US');
}

const tablesContainer=document.getElementById('tablesContainer');
new Sortable(tablesContainer,{animation:150,handle:'.table-handle'});
let currentTable=null;

function createTable(){
  const table=document.createElement('table');
  table.className='table table-bordered quote-table mb-4';
  table.innerHTML=`<thead>
    <tr class="bg-light"><th colspan="6" class="text-end">
      <span class="table-handle me-2" style="cursor:move">&#9776;</span>
      <button class="btn btn-sm btn-danger remove-table-btn">&minus;</button>
    </th></tr>
    <tr><th>Service</th><th>Service Details</th><th class="text-center">Payment Term</th><th class="text-center">Total Cost USD</th><th class="text-center egp-header">Cost EGP</th><th></th></tr>
  </thead><tbody></tbody><tfoot></tfoot>`;
  tablesContainer.appendChild(table);
  new Sortable(table.querySelector('tbody'),{animation:150});
  table.querySelector('.remove-table-btn').addEventListener('click',()=>{
    table.remove();
    currentTable=tablesContainer.querySelector('table.quote-table');
  });
  currentTable=table;
  return table;
}

function addRow(service, desc, usd, egp, table=currentTable){
  if(!table) table=currentTable||createTable();
  const tbody=table.querySelector('tbody');
  const tr=document.createElement('tr');
  const term='one-time';
  tr.innerHTML='<td><strong>'+service+'</strong></td>'+
    '<td contenteditable="true">'+desc.replace(/\n/g,'<br>')+'</td>'+
    '<td class="text-center"><select class="form-select form-select-sm term-select"><option value="one-time">One-time</option><option value="monthly">Monthly</option></select></td>'+
    '<td class="usd text-center" data-usd="'+usd+'">$'+formatNum(usd)+'</td><td class="egp text-center" data-egp="'+egp+'">EGP '+formatNum(egp)+'</td>'+

    '<td><button class="btn btn-sm btn-danger">&times;</button></td>';
  tr.querySelector('.term-select').value=term;
  tr.querySelector('button').addEventListener('click', ()=>{tr.remove();updateTotals(table);});
  tr.querySelector('.term-select').addEventListener('change',()=>updateTotals(table));
  tbody.appendChild(tr);
  updateTotals(table);
}
function updateTotals(table=currentTable){
  const totals={usd:0,egp:0};
  table.querySelectorAll('tbody tr').forEach(tr=>{
    totals.usd+=parseFloat(tr.querySelector('.usd').dataset.usd);
    totals.egp+=parseFloat(tr.querySelector('.egp').dataset.egp);
  });
  const tfoot=table.querySelector('tfoot');
  tfoot.innerHTML='';
  if(totals.usd!==0 || totals.egp!==0){
    const vat=totals.egp*0.14;
    tfoot.innerHTML=`<tr class="table-secondary fw-bold"><th colspan="3" class="text-end">Total</th><th class="bg-warning text-black text-center">$${formatNum(totals.usd)}</th><th class="bg-warning text-black text-center egp">EGP ${formatNum(totals.egp)}</th><th></th></tr>`+
      `<tr class="vat-row"><th colspan="3" class="text-end">VAT 14%</th><th></th><th class="egp text-center bg-danger text-white">EGP ${formatNum(vat)}</th><th></th></tr>`+
      `<tr class="total-vat-row"><th colspan="3" class="text-end">Total + VAT</th><th></th><th class="egp text-center" style="background:#14633c;color:#fff;">EGP ${formatNum(totals.egp+vat)}</th><th></th></tr>`;
  }
  if(table.querySelectorAll('tbody tr').length===0){
    table.remove();
    currentTable=tablesContainer.querySelector('table.quote-table');

  }
}
document.querySelectorAll('.add-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    addRow(btn.dataset.service, btn.dataset.desc, parseFloat(btn.dataset.usd), parseFloat(btn.dataset.egp));
  });
});
document.getElementById('clientName').addEventListener('input', updateHeader);
updateHeader();

document.getElementById('addTableBtn').addEventListener('click', ()=>{
  createTable();
});
tablesContainer.addEventListener('click',e=>{
  const tbl=e.target.closest('table.quote-table');
  if(tbl) currentTable=tbl;
});
document.getElementById('toggleEGP').addEventListener('change',e=>{
  document.getElementById('quote-area').classList.toggle('hide-egp',e.target.checked);
});
function downloadPDF(){
  const { jsPDF } = window.jspdf;
  const quoteArea=document.getElementById('quote-area');
  const hidden=[];
  document.querySelectorAll('.remove-table-btn, .quote-table thead tr.bg-light, .quote-table td:last-child, .quote-table th:last-child').forEach(el=>{
    hidden.push({el,display:el.style.display});
    el.style.display='none';
  });
  html2canvas(quoteArea).then(canvas=>{
    hidden.forEach(h=>h.el.style.display=h.display);
    const img=canvas.toDataURL('image/png');
    const pdf=new jsPDF({orientation:'portrait', unit:'mm', format:'a4'});
    const margin=20;
    const pageWidth=pdf.internal.pageSize.getWidth()-margin*2;
    const pageHeight=canvas.height*pageWidth/canvas.width;
    pdf.addImage(img,'PNG',margin,margin,pageWidth,pageHeight);
    const client=document.getElementById('clientName').value.trim()||'Client';
    pdf.save(`Table of Prices - ${client}.pdf`);
  });
}
</script>
<?php include 'footer.php'; ?>