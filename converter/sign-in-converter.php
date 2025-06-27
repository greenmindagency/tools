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
<div class="mb-3" id="bankHolidaysContainer">
  <label class="form-label">Bank Holidays:</label>
  <div class="input-group mb-1">
    <input type="date" class="form-control bank-holiday">
    <button type="button" class="btn btn-outline-secondary" onclick="addBankHoliday()">+</button>
  </div>
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
function dateKey(d) {
  return d.toISOString().slice(0,10);
}
function addBankHoliday(value = '') {
  const container = document.getElementById('bankHolidaysContainer');
  const div = document.createElement('div');
  div.className = 'input-group mb-1';
  div.innerHTML = `<input type="date" class="form-control bank-holiday" value="${value}">` +
    '<button type="button" class="btn btn-outline-secondary" onclick="addBankHoliday()">+</button>';
  container.appendChild(div);
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

  const dateKeys = [];
  for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    if (d.getDay() !== 5 && d.getDay() !== 6) {
      dateKeys.push(dateKey(new Date(d)));
    }
  }

  const rows = [];
  names.forEach(name => {
    dateKeys.forEach(dk => {
      const entry = (data[name] && data[name][dk]) || {in:'', out:'', shift:''};
      const inDate = parseDate(entry.in);
      const outDate = parseDate(entry.out);
      let invalidIn = !inDate;
      let invalidOut = !outDate || (inDate && outDate < inDate);
      if (!invalidOut && inDate && outDate && dateKey(inDate) !== dateKey(outDate)) {
        invalidOut = true;
      }
      const stdIn = inDate ? new Date(inDate) : null;
      if (stdIn) stdIn.setHours(9,15,0,0);
      const stdOut = outDate ? new Date(outDate) : null;
      if (stdOut) stdOut.setHours(17,0,0,0);
      let working = 0;
      if (!invalidIn && !invalidOut) {
        const startW = inDate > stdIn ? inDate : stdIn;
        const endW = outDate < stdOut ? outDate : stdOut;
        working = Math.max(0, endW - startW);
      }
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
      rows.push({
        Date: dk,
        Name: name,
        'Clock In': entry.in,
        'Clock Out': entry.out,
        Net: toHours(working),
        Note: note,
        _invalidIn: invalidIn,
        _invalidOut: invalidOut
      });
    });
  });

  renderTable(['Date','Name','Clock In','Clock Out','Net','Note'], rows);
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
  if (saved) document.getElementById('input').value = saved;
  const banks = JSON.parse(localStorage.getItem('signinBanks') || '[]');
  const container = document.getElementById('bankHolidaysContainer');
  container.innerHTML = '<label class="form-label">Bank Holidays:</label>';
  if (banks.length) {
    banks.forEach(b => {
      const div = document.createElement('div');
      div.className = 'input-group mb-1';
      div.innerHTML = `<input type="date" class="form-control bank-holiday" value="${b}">` +
        '<button type="button" class="btn btn-outline-secondary" onclick="addBankHoliday()">+</button>';
      container.appendChild(div);
    });
  } else {
    addBankHoliday();
  }
  if (saved) convert();
});
</script>
<?php include 'footer.php'; ?>
