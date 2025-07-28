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
<div class="mb-3">
  <label class="form-label">Bank Holidays:</label>
  <div id="bankHolidays">
    <div class="input-group mb-1">
      <input type="date" class="form-control bank-holiday">
      <button type="button" class="btn btn-outline-secondary" onclick="addBankHoliday(this)">+</button>
    </div>
  </div>
</div>
<button class="btn btn-primary" onclick="convert()">Convert</button>
<div class="d-flex justify-content-between align-items-center mt-4 mb-2">
  <div id="monthDays" class="fw-bold"></div>
  <div class="ms-auto">
    <button id="copyBtn" class="btn btn-success btn-sm d-none" onclick="copyTable()">Copy</button>
    <button id="copyMissingBtn" class="btn btn-warning btn-sm d-none ms-2" onclick="copyMissing()">Copy Missing</button>
  </div>
</div>
<div class="table-responsive">
  <table id="outputTable" class="table table-bordered table-striped"></table>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="copyToast" class="toast" role="status" aria-live="assertive" aria-atomic="true">
    <div class="toast-body">Copied to clipboard</div>
  </div>
</div>
<script>
function parseDate(str) {
  const d = new Date(str);
  return isNaN(d.getTime()) ? null : d;
}
function toHours(ms) {
  return (ms / 3600000).toFixed(2);
}
function dateKey(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}
function addBankHoliday(button, value = '') {
  const container = document.getElementById('bankHolidays');
  const group = button.closest('.input-group');
  const clone = group.cloneNode(true);
  clone.querySelector('input').value = value;
  const btn = clone.querySelector('button');
  btn.textContent = '-';
  btn.classList.replace('btn-outline-secondary', 'btn-outline-danger');
  btn.setAttribute('onclick', 'removeBankHoliday(this)');
  container.appendChild(clone);
}

function removeBankHoliday(button) {
  const container = document.getElementById('bankHolidays');
  button.closest('.input-group').remove();
  if (container.children.length === 0) {
    container.innerHTML = '<div class="input-group mb-1"><input type="date" class="form-control bank-holiday"><button type="button" class="btn btn-outline-secondary" onclick="addBankHoliday(this)">+</button></div>';
  }
}
function getBankHolidays() {
  const inputs = document.querySelectorAll('.bank-holiday');
  const values = [];
  inputs.forEach(i => { if (i.value) values.push(i.value); });
  localStorage.setItem('signinBanks', JSON.stringify(values));
  return values.map(v => dateKey(new Date(v)));
}

function convert() {
  const raw = document.getElementById('input').value.trim();
  const bankHolidays = getBankHolidays();
  localStorage.setItem('signinRaw', raw);
  if (!raw) return;

  const lines = raw.split(/\n+/);
  lines.shift();

  const data = {};
  const allDates = [];

  lines.forEach(line => {
    if (!line.trim()) return;
    const cols = line.split(/\t/);
    const name = cols[0].split(',').pop().trim();
    const clockIn = cols[1] ? cols[1].trim() : '';
    const clockOut = cols[2] ? cols[2].trim() : '';
    const dateObj = parseDate(clockIn) || parseDate(clockOut);
    if (!data[name]) data[name] = {};
    if (dateObj) {
      const key = dateKey(dateObj);
      allDates.push(dateObj);
      data[name][key] = {in: clockIn, out: clockOut, shift: cols[3] ? cols[3].trim() : ''};
    }
  });

  const names = Object.keys(data).sort((a,b) => a.localeCompare(b));
  if (!allDates.length) return;

  const earliest = new Date(Math.min.apply(null, allDates.map(d => d.getTime())));
  let start = earliest.getDate() >= 26 ? new Date(earliest.getFullYear(), earliest.getMonth(), 26) : new Date(earliest.getFullYear(), earliest.getMonth()-1, 26);
  const end = new Date(start);
  end.setMonth(end.getMonth() + 1);
  end.setDate(25);

  // 5 = Friday, 6 = Saturday
  const weekend = [5, 6];
  const dateKeys = [];
  for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    if (!weekend.includes(d.getDay())) {
      dateKeys.push(dateKey(new Date(d)));
    }
  }
  document.getElementById('monthDays').textContent = `Working Days: ${dateKeys.length}`;

  const rows = [];
  names.forEach(name => {
    dateKeys.forEach(dk => {
      const entry = (data[name] && data[name][dk]) || {in:'', out:'', shift:''};
      const inDate = parseDate(entry.in);
      const outDate = parseDate(entry.out);
      let invalidIn = !inDate;
      if (inDate) {
        const mins = inDate.getHours() * 60 + inDate.getMinutes();
        if (mins > 13 * 60) invalidIn = true;
      }
      let invalidOut = !outDate || (inDate && outDate < inDate);
      let nextDayShift = false;
      if (!invalidOut && inDate && outDate && dateKey(inDate) !== dateKey(outDate)) {
        nextDayShift = true;
        const nextDayEight = new Date(inDate);
        nextDayEight.setDate(nextDayEight.getDate() + 1);
        nextDayEight.setHours(8, 0, 0, 0);
        if (outDate > nextDayEight) {
          invalidOut = true;
        }
      }
      const stdIn = inDate ? new Date(inDate) : null;
      if (stdIn) stdIn.setHours(9,15,0,0);
      const stdOut = outDate ? new Date(outDate) : null;
      if (stdOut) stdOut.setHours(17,0,0,0);
      let note = '';
      if (/annual\s*leave/i.test(entry.in) || /annual\s*leave/i.test(entry.out) || /annual\s*leave/i.test(entry.shift)) {
        note = 'Annual Leave';
      } else if (bankHolidays.includes(dk)) {
        note = 'Bank Holiday';
      } else if (invalidIn && invalidOut) {
        note = 'Missing Sign In/Out';
      } else if (invalidIn) {
        note = 'Missing Sign In';
      } else if (invalidOut) {
        note = 'Missing Sign Out';
      }
      let net = 8;
      if (note === "Annual Leave") {
        net = 0;
      } else if (note === "Bank Holiday") {
        net = 8;
      } else if (!invalidIn && !invalidOut) {
        if (inDate > stdIn) {
          net -= (inDate - stdIn) / 3600000;
        }
        if (!nextDayShift && outDate < stdOut) {
          net -= (stdOut - outDate) / 3600000;
        }
        if (net < 0) net = 0;
      }
      rows.push({
        Date: dk,
        Name: name,
        'Clock In': entry.in,
        'Clock Out': entry.out,
        Net: net.toFixed(2),
        Note: note,
        _invalidIn: invalidIn,
        _invalidOut: invalidOut
      });
    });
  });

  renderTable(['Date','Name','Clock In','Clock Out','Net','Note'], rows);
  localStorage.setItem('signinTable', document.getElementById('outputTable').outerHTML);
  document.getElementById('copyBtn').classList.remove('d-none');
  document.getElementById('copyMissingBtn').classList.remove('d-none');
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

function showCopiedToast(msg) {
  const el = document.getElementById('copyToast');
  el.querySelector('.toast-body').textContent = msg;
  const toast = bootstrap.Toast.getOrCreateInstance(el);
  toast.show();
}

function copyTable() {
  const rows = [];
  document.querySelectorAll('#outputTable tr').forEach(tr => {
    const cells = Array.from(tr.children).map(td => td.textContent);
    rows.push(cells.join('\t'));
  });
  navigator.clipboard.writeText(rows.join('\n')).then(() => showCopiedToast('Table copied'));
}

function copyMissing() {
  const rows = [];
  document.querySelectorAll('#outputTable tbody tr').forEach(tr => {
    const cells = Array.from(tr.children).map(td => td.textContent);
    const note = cells[5] || '';
    if (/Missing/i.test(note)) {
      rows.push([cells[0], cells[1], note].join('\t'));
    }
  });
  if (rows.length) {
    navigator.clipboard.writeText(rows.join('\n')).then(() => showCopiedToast('Missing rows copied'));
  }
}
window.addEventListener('load', () => {
  const saved = localStorage.getItem('signinRaw');
  if (saved) document.getElementById('input').value = saved;
  const banks = JSON.parse(localStorage.getItem('signinBanks') || '[]');
  const container = document.getElementById('bankHolidays');
  container.innerHTML = '<div class="input-group mb-1"><input type="date" class="form-control bank-holiday"><button type="button" class="btn btn-outline-secondary" onclick="addBankHoliday(this)">+</button></div>';
  if (banks.length) {
    const first = container.querySelector('input');
    first.value = banks[0];
    for (let i = 1; i < banks.length; i++) {
      addBankHoliday(container.querySelector('button'), banks[i]);
    }
  }
  if (saved) convert();
});
</script>
<?php include 'footer.php'; ?>
