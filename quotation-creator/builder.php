<?php
$title = 'Quotation Creator';
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: login.php');
    exit;
}
include 'header.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$existingHtml = '';
$clientName = '';
$existingSlug = '';
if ($id) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255),
        html MEDIUMTEXT,
        slug VARCHAR(255) UNIQUE,
        published TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $stmt = $pdo->prepare('SELECT name, html, slug FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $clientName = $row['name'];
        $existingHtml = $row['html'];
        $existingSlug = $row['slug'];
    }
}

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
.quote-table th, .quote-table td { vertical-align: middle; }
.add-btn { cursor: pointer; }
.package-selector .dropdown-menu{min-width:260px;}
.table thead th{background:#000;color:#fff;font-weight:bold;}
.table-bordered{border-color:#000;}
.table-bordered th,.table-bordered td{border-color:#000; border: 1px;}
.term-select{min-width:110px;border:none;background-color:transparent;box-shadow:none;padding:0;}
.term-select:focus{box-shadow:none;}
.table-handle{cursor:move;}
/* larger space between tables */
.quote-table{margin-bottom:3rem;table-layout:fixed;width:100%;}
.quote-table th:nth-child(1),.quote-table td:nth-child(1){width:30%;}
.quote-table th:nth-child(2),.quote-table td:nth-child(2){width:45%;}
.quote-table th:nth-child(3),.quote-table td:nth-child(3){width:8%;text-align:center;}
.quote-table th:nth-child(4),.quote-table td:nth-child(4){width:8%;text-align:center;}
.quote-table th:nth-child(5),.quote-table td:nth-child(5){width:9%;text-align:center;}
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
/* hide usd column when toggle class present */
.hide-usd .usd,
.hide-usd .usd-header{
  display:none;
}
.hide-usd .vat-row th:nth-child(2),
.hide-usd .total-vat-row th:nth-child(2){
  display:none;
}
.content-block{border:1px dashed #ccc;padding:10px;min-height:60px;margin-bottom:1rem;}
.content-toolbar{display:flex;justify-content:flex-end;gap:2px;margin-bottom:4px;}
.remove-row-btn{display:block;margin-top:4px;}
</style>

<div class="package-selector mb-3 d-flex flex-wrap gap-2">
<?php if(!$packages): ?>
  <div class="alert alert-warning">Unable to retrieve pricing packages.</div>
<?php else: ?>
  <?php foreach($packages as $sIndex => $svc): ?>
    <?php $svcName = trim(preg_replace('/\bPrices\b/i', '', $svc['name'])); ?>
    <div class="dropdown">
      <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <?= htmlspecialchars($svcName) ?>
      </button>
      <div class="dropdown-menu p-2 shadow">
        <?php foreach($svc['packages'] as $i => $p): ?>
        <div class="add-btn d-flex justify-content-between align-items-center mb-1" data-service="<?= htmlspecialchars($svcName) ?>" data-usd="<?= $p['usd_val'] ?>" data-egp="<?= $p['egp_val'] ?>" data-desc="<?= htmlspecialchars(implode("\n", $p['details'])) ?>" style="cursor:pointer;">
          <span><strong>Pack <?= $i + 1 ?></strong> - $<?= number_format($p['usd_val'],0) ?> <span class="text-muted">(EGP <?= number_format($p['egp_val'],0) ?>)</span></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<div id="client-input" class="mb-3" style="max-width:300px;">
  <div class="input-group input-group-sm">
    <input type="text" id="clientName" class="form-control" placeholder="Client Name" value="<?= htmlspecialchars($clientName) ?>">
  </div>
</div>

<div id="quote-area">
<?php if ($existingHtml): ?>
  <?= $existingHtml ?>
<?php else: ?>
  <div class="row align-items-center mb-3 text-center text-md-start">
    <div class="col-md-5 mb-3 mb-md-0">
      <h1 class="mt-2 mb-0">Green Mind Agency</h1>
      <p class="h4 my-2">Quotation Offer</p>
    </div>
    <div class="col-md-5">
      <h2 id="clientDisplay" class="text-start"></h2>
      <p class="mb-1 text-start"><small>These prices are valid for 1 month until <span id="validUntil"></span></small></p>
    </div>
    <div class="col-md-2">
      <p class="mb-1 text-start">Date</p>
      <p id="currentDate" class="mb-0 text-start"></p>
    </div>
  </div>

  <div id="tablesContainer"></div>
  <div id="contentContainer"></div>
<?php endif; ?>
  <button id="addTableBtn" class="btn btn-primary btn-sm mb-3 me-2">&#43; Add Table</button>
  <button id="addContentBtn" class="btn btn-secondary btn-sm mb-3">&#43; Add Content</button>
</div>
</div>
</div>
<div class="d-flex align-items-center gap-3 mt-3">
  <div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" id="toggleEGP">
    <label class="form-check-label" for="toggleEGP">Hide EGP</label>
  </div>
  <div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" id="toggleUSD">
    <label class="form-check-label" for="toggleUSD">Hide USD</label>
  </div>
  <button class="btn btn-success" onclick="saveQuote(true)">Save &amp; Publish</button>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const validUntilEl=document.getElementById('validUntil');
const currentDateEl=document.getElementById('currentDate');
const today=new Date();
const validUntilDate=new Date(today.getFullYear(),today.getMonth()+1,today.getDate());
validUntilEl.textContent=validUntilDate.toLocaleDateString('en-GB',{timeZone:'Africa/Cairo'});
currentDateEl.textContent=today.toLocaleDateString('en-GB',{timeZone:'Africa/Cairo'});
function updateHeader(){
  const name=document.getElementById('clientName').value.trim();
  document.getElementById('clientDisplay').textContent=name;
}

function formatNum(num){
  return Number(num).toLocaleString('en-US');
}

function cleanServiceName(name){
  return name.replace(/\bPrices\b/i,'').trim();
}

let tablesContainer=document.getElementById('tablesContainer');
let contentContainer=document.getElementById('contentContainer');
new Sortable(tablesContainer,{animation:150,handle:'.table-handle'});
let currentTable=null;
const editingExisting = <?= $existingHtml ? 'true' : 'false' ?>;
let clientSlug='<?= $existingSlug ?>';
const defaultContent=`<ul>
<li>Payment Terms: A 50% advance payment is required upon confirmation, with the remaining 50% due upon final delivery.</li>
<li>Website Additional Costs: An annual server fee of $400 will be added to the total amount.</li>
<li>Website Estimated Timeline: The estimated completion time is 1 month</li>
<li>Branding Estimated Timeline: The estimated completion time is 1 month</li>
<li>VAT Policy: A 0% VAT applies if payment is made from outside Egypt in USD. A 14% VAT will be applied if payment is made within Egypt by an Egyptian company.</li>
</ul>
<p>Bank Details<br>
Bank Name: Commercial International Bank (CIB)<br>
Account Number (EGP): 100024157727<br>
IBAN Account Number (EGP): EG390010011900000100024157727<br>
Account Number (USD): 100024157754<br>
IBAN Account Number (USD): EG860010011900000100024157754<br>
Name: Green Mind Company<br>
Bank Address: Financial Area – Zone F10 B211 km 28 Cairo Alex Road, Egypt<br>
Swift Code: CIBEEGCX119</p>`;

const colgroupTemplate=`<colgroup>
  <col style="width:20%">
  <col style="width:35%">
  <col style="width:15%">
  <col style="width:15%">
  <col style="width:15%">
</colgroup>`;

function createTable(){
  const table=document.createElement('table');
  table.className='table table-bordered quote-table mb-5';
  table.innerHTML=`${colgroupTemplate}<thead>
    <tr class="bg-light"><th colspan="5" class="text-end">
      <span class="table-handle me-2" style="cursor:move">&#9776;</span>
      <button class="btn btn-sm btn-danger remove-table-btn">&minus;</button>
    </th></tr>
    <tr><th>Service</th><th>Service Details</th><th class="text-center">Payment Term</th><th class="text-center usd-header">Total Cost USD</th><th class="text-center egp-header">Cost EGP</th></tr>
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

function createContentBlock(html=''){
  if(!html) html = defaultContent;
  const block=document.createElement('div');
  block.className='content-block';
  block.innerHTML=`<div class="content-toolbar">
    <button type="button" class="btn btn-sm btn-light" data-cmd="bold"><b>B</b></button>
    <button type="button" class="btn btn-sm btn-light" data-cmd="italic"><i>I</i></button>
    <button type="button" class="btn btn-sm btn-light" data-cmd="underline"><u>U</u></button>
    <button type="button" class="btn btn-sm btn-light" data-cmd="insertUnorderedList">&bull; List</button>
    <button type="button" class="btn btn-sm btn-danger remove-content-btn">&times;</button>
  </div><div class="editable" contenteditable="true">${html}</div>`;
  block.querySelector('.remove-content-btn').addEventListener('click',()=>block.remove());
  block.querySelectorAll('[data-cmd]').forEach(btn=>{
    btn.addEventListener('click',()=>{document.execCommand(btn.dataset.cmd,false,null);});
  });
  contentContainer.appendChild(block);
  return block;
}

function addRow(service, desc, usd, egp, table=currentTable){
  if(!table) table=currentTable||createTable();
  const tbody=table.querySelector('tbody');
  const tr=document.createElement('tr');
  const term='one-time';
  tr.innerHTML='<td><strong>'+cleanServiceName(service)+'</strong><br><button class="btn btn-sm btn-danger mt-1 remove-row-btn">&times;</button></td>'+
    '<td contenteditable="true">'+desc.replace(/\n/g,'<br>')+'</td>'+
    '<td class="text-center"><select class="form-select form-select-sm term-select"><option value="one-time">One-time</option><option value="monthly">Monthly</option></select></td>'+
    '<td class="usd text-center" data-usd="'+usd+'">$'+formatNum(usd)+'</td>'+
    '<td class="egp text-center" data-egp="'+egp+'">EGP '+formatNum(egp)+'</td>';
  tr.querySelector('.term-select').value=term;
  tr.querySelector('.remove-row-btn').addEventListener('click', ()=>{tr.remove();updateTotals(table);});
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
    tfoot.innerHTML=`<tr class="table-secondary fw-bold"><th colspan="3" class="text-end">Total</th><th class="usd bg-warning text-black text-center">$${formatNum(totals.usd)}</th><th class="egp bg-warning text-black text-center">EGP ${formatNum(totals.egp)}</th></tr>`+
      `<tr class="vat-row"><th colspan="3" class="text-end">VAT 14%</th><th></th><th class="egp text-center bg-danger text-white">EGP ${formatNum(vat)}</th></tr>`+
      `<tr class="total-vat-row"><th colspan="3" class="text-end">Total + VAT</th><th></th><th class="egp text-center" style="background:#14633c;color:#fff;">EGP ${formatNum(totals.egp+vat)}</th></tr>`;
  }
  if(table.querySelectorAll('tbody tr').length===0){
    table.remove();
    currentTable=tablesContainer.querySelector('table.quote-table');

  }
}
document.querySelectorAll('.add-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const lastTable=tablesContainer.querySelector('table.quote-table:last-of-type')||createTable();
    addRow(btn.dataset.service, btn.dataset.desc, parseFloat(btn.dataset.usd), parseFloat(btn.dataset.egp), lastTable);
  });
});
document.getElementById('clientName').addEventListener('input', updateHeader);
updateHeader();

document.getElementById('addTableBtn').addEventListener('click', ()=>{
  createTable();
});
document.getElementById('addContentBtn').addEventListener('click', ()=>{
  createContentBlock();
});
tablesContainer.addEventListener('click',e=>{
  const tbl=e.target.closest('table.quote-table');
  if(tbl) currentTable=tbl;
});
document.getElementById('toggleEGP').addEventListener('change',e=>{
  document.getElementById('quote-area').classList.toggle('hide-egp',e.target.checked);
});
document.getElementById('toggleUSD').addEventListener('change',e=>{
  document.getElementById('quote-area').classList.toggle('hide-usd',e.target.checked);
});
if(editingExisting) restoreExisting();
if(editingExisting){
  const qa=document.getElementById('quote-area');
  document.getElementById('toggleEGP').checked=qa.classList.contains('hide-egp');
  document.getElementById('toggleUSD').checked=qa.classList.contains('hide-usd');
}

function restoreExisting(){
  const qa=document.getElementById('quote-area');
  const wrapper=qa.querySelector('.saved-quote');
  if(wrapper){
    qa.classList.toggle('hide-usd', wrapper.classList.contains('hide-usd'));
    qa.classList.toggle('hide-egp', wrapper.classList.contains('hide-egp'));
    document.getElementById('toggleUSD').checked=wrapper.classList.contains('hide-usd');
    document.getElementById('toggleEGP').checked=wrapper.classList.contains('hide-egp');
    qa.innerHTML = wrapper.innerHTML;
  }
  // restore container reference and add table button
  tablesContainer=document.getElementById('tablesContainer');
  contentContainer=document.getElementById('contentContainer');
  const addBtn=document.createElement('button');
  addBtn.id='addTableBtn';
  addBtn.className='btn btn-primary btn-sm mb-3 me-2';
  addBtn.innerHTML='&#43; Add Table';
  if(!contentContainer){
    contentContainer=document.createElement('div');
    contentContainer.id='contentContainer';
    qa.appendChild(contentContainer);
  }
  qa.appendChild(addBtn);
  addBtn.addEventListener('click',()=>{createTable();});
  const addContent=document.createElement('button');
  addContent.id='addContentBtn';
  addContent.className='btn btn-secondary btn-sm mb-3';
  addContent.innerHTML='&#43; Add Content';
  qa.appendChild(addContent);
  addContent.addEventListener('click',()=>{createContentBlock();});
  new Sortable(tablesContainer,{animation:150,handle:'.table-handle'});
  tablesContainer.addEventListener('click',e=>{
    const tbl=e.target.closest('table.quote-table');
    if(tbl) currentTable=tbl;
  });
  document.querySelectorAll('#quote-area table').forEach(table=>{
    table.classList.add('table','table-bordered','quote-table','mb-5');
    if(!table.querySelector('colgroup')){
      table.insertAdjacentHTML('afterbegin', colgroupTemplate);
    }
    const thead=table.querySelector('thead');
    const headRow=document.createElement('tr');
    headRow.className='bg-light';
    headRow.innerHTML='<th colspan="5" class="text-end"><span class="table-handle me-2" style="cursor:move">&#9776;</span><button class="btn btn-sm btn-danger remove-table-btn">&minus;</button></th>';
    thead.prepend(headRow);
    const headerCells=thead.querySelectorAll('tr:nth-child(2) th');
    if(headerCells[3]) headerCells[3].classList.add('usd-header');
    if(headerCells[4]) headerCells[4].classList.add('egp-header');
    headRow.querySelector('.remove-table-btn').addEventListener('click',()=>{table.remove();currentTable=tablesContainer.querySelector("table.quote-table");});
    new Sortable(table.querySelector('tbody'),{animation:150});
    table.querySelectorAll('tbody tr').forEach(tr=>{
      tr.cells[1].setAttribute('contenteditable','true');
      const serviceCell=tr.cells[0];
      serviceCell.innerHTML = '<strong>'+cleanServiceName(serviceCell.textContent.trim())+'</strong><br>';
      const removeBtn=document.createElement('button');
      removeBtn.className='btn btn-sm btn-danger mt-1 remove-row-btn';
      removeBtn.innerHTML='&times;';
      removeBtn.addEventListener('click',()=>{tr.remove();updateTotals(table);});
      serviceCell.appendChild(removeBtn);
      const termCell=tr.cells[2];
      const termText=termCell.textContent.trim().toLowerCase().includes('month')?'monthly':'one-time';
      termCell.innerHTML='<select class="form-select form-select-sm term-select"><option value="one-time">One-time</option><option value="monthly">Monthly</option></select>';
      termCell.querySelector('select').value=termText;
      termCell.querySelector('select').addEventListener('change',()=>updateTotals(table));
    });
    updateTotals(table);
  });
  currentTable=document.querySelector('table.quote-table');

  document.querySelectorAll('#quote-area .content-block').forEach(block=>{
    const html=block.innerHTML;
    block.innerHTML='';
    const toolbar=document.createElement('div');
    toolbar.className='content-toolbar';
    toolbar.innerHTML='<button type="button" class="btn btn-sm btn-light" data-cmd="bold"><b>B</b></button>'+
      '<button type="button" class="btn btn-sm btn-light" data-cmd="italic"><i>I</i></button>'+
      '<button type="button" class="btn btn-sm btn-light" data-cmd="underline"><u>U</u></button>'+
      '<button type="button" class="btn btn-sm btn-light" data-cmd="insertUnorderedList">&bull; List</button>'+
      '<button type="button" class="btn btn-sm btn-danger remove-content-btn">&times;</button>';
    const editable=document.createElement('div');
    editable.className='editable';
    editable.contentEditable='true';
    editable.innerHTML=html;
    block.appendChild(toolbar);
    block.appendChild(editable);
    toolbar.querySelector('.remove-content-btn').addEventListener('click',()=>block.remove());
    toolbar.querySelectorAll('[data-cmd]').forEach(btn=>btn.addEventListener('click',()=>{document.execCommand(btn.dataset.cmd,false,null);}));
  });
}

let clientId = <?= isset($_GET['id']) ? (int)$_GET['id'] : 0 ?>;
function saveQuote(publish){
  const quoteArea=document.getElementById('quote-area');
  const clone=quoteArea.cloneNode(true);
  const origSelects=quoteArea.querySelectorAll('.term-select');
  const clonedSelects=clone.querySelectorAll('.term-select');
  clonedSelects.forEach((sel,i)=>{sel.value=origSelects[i].value;});
  clone.querySelectorAll('.remove-table-btn, .quote-table thead tr.bg-light, .remove-row-btn, #addTableBtn, #addContentBtn, .content-toolbar, colgroup').forEach(el=>el.remove());
  clone.querySelectorAll('.term-select').forEach(sel=>{
    const td=sel.parentElement;
    td.textContent=sel.options[sel.selectedIndex].textContent;
  });
  clone.querySelectorAll('[contenteditable]').forEach(el=>el.removeAttribute('contenteditable'));
  clone.querySelectorAll('.quote-table').forEach(tbl=>{
    const header=tbl.querySelector('thead tr:last-child th:last-child');
    if(header && header.textContent.trim()==='') header.remove();
  });
  const wrapper=document.createElement('div');
  wrapper.className='saved-quote';
  if(document.getElementById('toggleUSD').checked) wrapper.classList.add('hide-usd');
  if(document.getElementById('toggleEGP').checked) wrapper.classList.add('hide-egp');
  wrapper.innerHTML=clone.innerHTML;
  const data={id:clientId,name:document.getElementById('clientName').value.trim(),html:wrapper.outerHTML,publish:publish};
  fetch('save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(r=>r.json())
    .then(res=>{
      if(res.success){
        clientId=res.id;
        if(res.slug) clientSlug=res.slug;
        if(publish && res.link){
          window.open(res.link,'_blank');
        }else{
          alert('Saved');
        }
      }
    });
}
</script>
<?php include 'footer.php'; ?>
