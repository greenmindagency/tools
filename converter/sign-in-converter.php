<?php
$title = 'Sign In Converter';
include 'header.php';
?>
<style>
  textarea { min-height: 150px; }
  table input { width: 100%; border: none; background: transparent; }
  .invalid { background-color: #f8d7da; }
</style>
<div class="mb-3">
  <label class="form-label">Paste Sign In Data (tab separated):</label>
  <textarea id="input" class="form-control" placeholder="Name\tClock In\tClock Out\tShift Total\n..."></textarea>
</div>
<button class="btn btn-primary" onclick="convert()">Convert</button>
<div class="table-responsive mt-4">
  <table id="outputTable" class="table table-bordered table-striped"></table>
</div>
<script>
function parseDate(str) {
  const d = new Date(str);
  return isNaN(d.getTime()) ? null : d;
}
function toHours(ms) {
  return (ms / 3600000).toFixed(2);
}
function convert() {
  const raw = document.getElementById('input').value.trim();
  localStorage.setItem('signinRaw', raw);
  if (!raw) return;
  const lines = raw.split(/\n+/);
  const header = lines.shift().split(/\t/).map(s => s.trim());
  const rows = [];
  const bufferTotals = {};
  lines.forEach(line => {
    if (!line.trim()) return;
    const cols = line.split(/\t/);
    const row = { Name: cols[0].trim(), 'Clock In': cols[1], 'Clock Out': cols[2], 'Shift Total': cols[3] };
    const inDate = parseDate(row['Clock In']);
    const outDate = parseDate(row['Clock Out']);
    let invalidIn = !inDate;
    let invalidOut = !outDate || (inDate && outDate < inDate);
    const stdIn = inDate ? new Date(inDate) : null;
    if (stdIn) { stdIn.setHours(9,15,0,0); }
    const stdOut = outDate ? new Date(outDate) : null;
    if (stdOut) { stdOut.setHours(17,0,0,0); }
    let working = 0;
    if (!invalidIn && !invalidOut) {
      const start = inDate > stdIn ? inDate : stdIn;
      const end = outDate < stdOut ? outDate : stdOut;
      working = Math.max(0, end - start);
    }
    const tardy = (!invalidIn && inDate > stdIn) ? (inDate - stdIn) : 0;
    const nameKey = row.Name.toLowerCase();
    bufferTotals[nameKey] = (bufferTotals[nameKey] || 0) + tardy;
    row['Working Hours'] = toHours(working);
    row['Buffer Used (hrs)'] = toHours(bufferTotals[nameKey]);
    row._invalidIn = invalidIn;
    row._invalidOut = invalidOut;
    rows.push(row);
  });
  rows.sort((a,b) => a.Name.localeCompare(b.Name));
  renderTable(header.concat(['Working Hours','Buffer Used (hrs)']), rows);
  localStorage.setItem('signinTable', document.getElementById('outputTable').outerHTML);
}
function renderTable(headers, rows) {
  const table = document.getElementById('outputTable');
  table.innerHTML = '';
  const thead = document.createElement('thead');
  const trh = document.createElement('tr');
  headers.forEach(h => { const th = document.createElement('th'); th.textContent = h; trh.appendChild(th); });
  thead.appendChild(trh); table.appendChild(thead);
  const tbody = document.createElement('tbody');
  rows.forEach(r => {
    const tr = document.createElement('tr');
    headers.forEach(h => {
      const td = document.createElement('td');
      const val = r[h] || '';
      td.textContent = val;
      if ((h==='Clock In' && r._invalidIn) || (h==='Clock Out' && r._invalidOut)) td.classList.add('invalid');
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
}
window.addEventListener('load', () => {
  const saved = localStorage.getItem('signinRaw');
  if (saved) { document.getElementById('input').value = saved; convert(); }
});
</script>
<?php include 'footer.php'; ?>
