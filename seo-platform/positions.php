<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$isAdmin = $_SESSION['is_admin'] ?? false;
if (!$isAdmin) {
    $allowed = $_SESSION['client_ids'] ?? [];
    if ($allowed) {
        if (!in_array($client_id, $allowed)) {
            header('Location: login.php');
            exit;
        }
        $_SESSION['client_id'] = $client_id;
    } elseif (!isset($_SESSION['client_id']) || $_SESSION['client_id'] != $client_id) {
        header('Location: login.php');
        exit;
    }
}
$slugify = function(string $name): string {
    $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    $name = preg_replace('/[^a-zA-Z0-9]+/', '-', $name);
    return strtolower(trim($name, '-'));
};


$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) die("Client not found");

$slug = $slugify($client['name']);
$breadcrumb_client = [
    'name' => $client['name'],
    'url'  => "dashboard.php?client_id=$client_id&slug=$slug",
];

$title = $client['name'] . ' Keyword Positions';

$pdo->exec("CREATE TABLE IF NOT EXISTS keyword_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    keyword VARCHAR(255),
    sort_order INT DEFAULT NULL,
    m1 FLOAT DEFAULT NULL,
    m2 FLOAT DEFAULT NULL,
    m3 FLOAT DEFAULT NULL,
    m4 FLOAT DEFAULT NULL,
    m5 FLOAT DEFAULT NULL,
    m6 FLOAT DEFAULT NULL,
    m7 FLOAT DEFAULT NULL,
    m8 FLOAT DEFAULT NULL,
    m9 FLOAT DEFAULT NULL,
    m10 FLOAT DEFAULT NULL,
    m11 FLOAT DEFAULT NULL,
    m12 FLOAT DEFAULT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
for ($i = 1; $i <= 12; $i++) {
    $pdo->exec("ALTER TABLE keyword_positions ADD COLUMN IF NOT EXISTS m{$i} FLOAT DEFAULT NULL");
}
$pdo->exec("ALTER TABLE keyword_positions ADD COLUMN IF NOT EXISTS sort_order INT");
$pdo->exec("CREATE TABLE IF NOT EXISTS sc_domains (
    client_id INT PRIMARY KEY,
    domain VARCHAR(255)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$scDomainStmt = $pdo->prepare("SELECT domain FROM sc_domains WHERE client_id = ?");
$scDomainStmt->execute([$client_id]);
$scDomain = $scDomainStmt->fetchColumn() ?: '';



if (isset($_POST['add_position_keywords'])) {
    $text = trim($_POST['pos_keywords'] ?? '');
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $text)), 'strlen'));
    $ins = $pdo->prepare("INSERT INTO keyword_positions (client_id, keyword) VALUES (?, ?)");
    foreach ($lines as $kw) {
        $ins->execute([$client_id, $kw]);
    }
    $pdo->query("DELETE kp1 FROM keyword_positions kp1
                  JOIN keyword_positions kp2
                    ON kp1.keyword = kp2.keyword AND kp1.id > kp2.id
                 WHERE kp1.client_id = $client_id
                   AND kp2.client_id = $client_id");
}

if (isset($_POST['update_positions'])) {
    $deleteIds = array_keys(array_filter($_POST['delete_pos'] ?? [], fn($v) => $v == '1'));
    if ($deleteIds) {
        $in = implode(',', array_fill(0, count($deleteIds), '?'));
        $del = $pdo->prepare("DELETE FROM keyword_positions WHERE client_id = ? AND id IN ($in)");
        $del->execute(array_merge([$client_id], $deleteIds));
    }
}

if (isset($_POST['import_positions']) && isset($_FILES['csv_file']['tmp_name'])) {
    $monthIndex = (int)($_POST['position_month'] ?? 0);
    $col = 'm' . ($monthIndex + 1);
    $tmp = $_FILES['csv_file']['tmp_name'];
    $rawRows = [];
    if (is_uploaded_file($tmp)) {
        require_once __DIR__ . '/lib/SimpleXLSX.php';
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($tmp)) {
            $rawRows = $xlsx->rows();
        }
    }
    if ($rawRows) {

        $mapStmt = $pdo->prepare("SELECT id, keyword, sort_order FROM keyword_positions WHERE client_id = ?");
        $mapStmt->execute([$client_id]);
        $kwMap = [];
        while ($r = $mapStmt->fetch(PDO::FETCH_ASSOC)) {
            $kwMap[strtolower(trim($r['keyword']))] = ['id' => $r['id'], 'sort_order' => $r['sort_order']];
        }

        $pdo->prepare("UPDATE keyword_positions SET `$col` = NULL, sort_order = NULL WHERE client_id = ?")->execute([$client_id]);

        $update = $pdo->prepare("UPDATE keyword_positions SET `$col` = ?, sort_order = ? WHERE id = ?");

        $rows = [];
        $parseRow = function(array $data) {
            $kw = strtolower(trim($data[0] ?? ''));
            if ($kw === '') return null;
            $imprRaw = $data[2] ?? '';
            $impr = $imprRaw !== '' ? (float)str_replace(',', '', $imprRaw) : 0;
            $posRaw = $data[4] ?? '';
            $pos = $posRaw !== '' ? (float)str_replace(',', '.', $posRaw) : null;
            return ['kw' => $kw, 'impr' => $impr, 'pos' => $pos];
        };

        $firstData = $rawRows[0];
        $hasHeader = false;
        if ($firstData) {
            $imprCheck = str_replace(',', '', $firstData[2] ?? '');
            if (!is_numeric($imprCheck)) {
                $hasHeader = true;
            }
        }
        $startIdx = $hasHeader ? 1 : 0;
        for ($i = $startIdx; $i < count($rawRows); $i++) {
            if ($row = $parseRow($rawRows[$i])) {
                $rows[] = $row;
            }
        }

        usort($rows, fn($a, $b) => $b['impr'] <=> $a['impr']);

        $orderIdx = 1;
        foreach ($rows as $r) {
            if (isset($kwMap[$r['kw']])) {
                $update->execute([$r['pos'], $orderIdx, $kwMap[$r['kw']]['id']]);
            }
            $orderIdx++;
        }

        $pdo->query("DELETE kp1 FROM keyword_positions kp1
                      JOIN keyword_positions kp2
                        ON kp1.keyword = kp2.keyword AND kp1.id > kp2.id
                     WHERE kp1.client_id = $client_id
                       AND kp2.client_id = $client_id");
    }
}

$posQ = trim($_GET['pos_q'] ?? '');
$posSql = "SELECT * FROM keyword_positions WHERE client_id = ?";
$posParams = [$client_id];
$posTerms = array_values(array_filter(array_map('trim', explode('|', $posQ)), 'strlen'));
if ($posTerms) {
    $conds = [];
    foreach ($posTerms as $t) {
        $conds[] = "keyword LIKE ?";
        $posParams[] = "%$t%";
    }
    $posSql .= " AND (" . implode(' OR ', $conds) . ")";
}
$posSql .= " ORDER BY sort_order IS NULL, sort_order, id DESC";
$posStmt = $pdo->prepare($posSql);
$posStmt->execute($posParams);
$positions = $posStmt->fetchAll(PDO::FETCH_ASSOC);

$firstPagePerc = [];
for ($i = 1; $i <= 12; $i++) {
    $good = 0;
    $count = 0;
    foreach ($positions as $row) {
        $val = $row['m'.$i];
        if ($val === null || $val === '') continue;
        $count++;
        if ((float)$val <= 10) {
            $good++;
        }
    }
    $firstPagePerc[$i] = $count ? round($good * 100 / $count) : 0;
}

$months = [];
for ($i = 0; $i < 12; $i++) {
    $months[] = date('M Y', strtotime("-$i month"));
}

$kwAllStmt = $pdo->prepare("SELECT keyword FROM keywords WHERE client_id = ?");
$kwAllStmt->execute([$client_id]);
$allKeywords = array_map('strtolower', array_map('trim', $kwAllStmt->fetchAll(PDO::FETCH_COLUMN)));
$allKeywords = array_values(array_unique($allKeywords));

include 'header.php';
?>
<style>
  .highlight-cell { background-color: #e9e9e9 !important; }
</style>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="dashboard.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>">Keywords</a></li>
  <li class="nav-item"><a class="nav-link active" href="positions.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>">Keyword Position</a></li>
  <li class="nav-item"><a class="nav-link" href="clusters.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>">Clusters</a></li>
</ul>

<div class="mb-4">
  <div class="row g-2">
    <div class="col-sm"><div class="d-flex"><input type="text" id="scDomain" value="<?= htmlspecialchars($scDomain) ?>" class="form-control me-2" readonly placeholder="Connect Search Console"><button type="button" id="changeScDomain" class="btn btn-outline-secondary btn-sm"><?= $scDomain ? 'Change' : 'Connect' ?></button></div></div>
    <div class="col-sm">
      <select id="scMonth" class="form-select">
        <?php
        for ($i = 0; $i < 12; $i++) {
            $ts = strtotime("first day of -$i month");
            $label = date('M Y', $ts);
            $start = date('Y-m-d', $ts);
            $end = date('Y-m-d', strtotime("last day of -$i month"));
            echo "<option data-start='$start' data-end='$end' value='$i'>$label</option>";
        }
        ?>
      </select>
    </div>
    <div class="col-sm d-flex">
      <select id="scCountry" class="form-select me-2">
        <option value="">Worldwide</option>
        <option value="egy">Egypt</option>
        <option value="sau">Saudi Arabia</option>
        <option value="are">United Arab Emirates</option>
      </select>
      <button type="button" id="fetchGsc" class="btn btn-primary btn-sm">Fetch</button>
    </div>
  </div>
</div>

<div class="accordion mb-3" id="posChartAcc">
  <div class="accordion-item">
    <h2 class="accordion-header" id="posChartHead">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#posChartCollapse" aria-expanded="false" aria-controls="posChartCollapse">
        Monthly % Chart
      </button>
    </h2>
    <div id="posChartCollapse" class="accordion-collapse collapse" aria-labelledby="posChartHead" data-bs-parent="#posChartAcc">
      <div class="accordion-body">
        <canvas id="posChart" height="150"></canvas>
      </div>
    </div>
  </div>
</div>

<form method="POST" id="addPosForm" class="mb-4" style="display:none;">
  <textarea name="pos_keywords" class="form-control" rows="6" placeholder="One keyword per line"></textarea>
  <button type="submit" name="add_position_keywords" class="btn btn-primary btn-sm mt-2">Add Keywords</button>
</form>

<form method="POST" id="importPosForm" enctype="multipart/form-data" class="mb-4" style="display:none;">
  <select name="position_month" class="form-select mb-2">
    <?php foreach ($months as $idx => $m): ?>
      <option value="<?= $idx ?>"><?= $m ?></option>
    <?php endforeach; ?>
  </select>
  <input type="file" name="csv_file" accept=".xlsx" class="form-control">
  <button type="submit" name="import_positions" class="btn btn-primary btn-sm mt-2">Import Positions</button>
</form>

<div class="d-flex justify-content-between mb-2 sticky-controls">
  <div class="d-flex">
    <button type="submit" form="updatePosForm" name="update_positions" class="btn btn-success btn-sm me-2">Update</button>
    <button type="button" id="toggleAddPosForm" class="btn btn-warning btn-sm me-2">Update Keywords</button>
    <button type="button" id="toggleImportPosForm" class="btn btn-primary btn-sm me-2">Import Positions</button>
    <button type="button" id="openImportKw" class="btn btn-info btn-sm me-2">Import Keywords</button>
    <button type="button" id="copyPosKeywords" class="btn btn-secondary btn-sm me-2">Copy Keywords</button>
  </div>
  <form id="posFilterForm" method="GET" class="d-flex">
    <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <input type="hidden" name="slug" value="<?= $slug ?>">
    <input type="text" name="pos_q" value="<?= htmlspecialchars($posQ) ?>" class="form-control form-control-sm w-auto" placeholder="Filter..." style="max-width:200px;">
    <button type="submit" class="btn btn-outline-secondary btn-sm ms-1"><i class="bi bi-search"></i></button>
    <a href="positions.php?client_id=<?= $client_id ?>&slug=<?= $slug ?>" class="btn btn-outline-secondary btn-sm ms-1 d-flex align-items-center" title="Clear filter"><i class="bi bi-x-lg"></i></a>
  </form>
</div>

<form method="POST" id="updatePosForm">
  <input type="hidden" name="client_id" value="<?= $client_id ?>">
  <table class="table table-bordered table-sm">
    <thead class="table-light">
      <tr>
        <th style="width:1px;"><button type="button" id="removeAllPositions" class="btn btn-sm btn-outline-danger" title="Remove all on page">-</button></th>
        <th>Keyword</th>
        <?php foreach (array_reverse($months) as $m): ?>
        <th class="text-center"><?= $m ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody id="posTableBody">
    <tr class="table-info">
      <td></td>
      <td><strong>% in Top 10</strong></td>
      <?php for ($i=11;$i>=0;$i--): $p = $firstPagePerc[$i+1]; ?>
        <td class="text-center fw-bold"><?= $p ?>%</td>
      <?php endfor; ?>
    </tr>
    <?php foreach ($positions as $row): ?>
      <tr data-id="<?= $row['id'] ?>">
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">-</button><input type="hidden" name="delete_pos[<?= $row['id'] ?>]" value="0" class="delete-flag"></td>
        <td><?= htmlspecialchars($row['keyword']) ?></td>
        <?php for ($i=11;$i>=0;$i--): $col = 'm'.($i+1); ?>
          <?php $val = $row[$col]; $bg = ''; if ($val !== null && $val !== '') { $bg = ((float)$val <= 10) ? '#d4edda' : '#f8d7da'; } ?>
          <td class="text-center" style="background-color: <?= $bg ?>;"><?= $val !== null ? htmlspecialchars($val) : '' ?></td>
        <?php endfor; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</form>

<p><a href="index.php">&larr; Back to Clients</a></p>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="copyToast" class="toast" role="status" aria-live="assertive" aria-atomic="true">
    <div class="toast-body">Copied to clipboard</div>
  </div>
</div>

<div class="modal fade" id="gscModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Search Console Property</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <select id="gscSiteSelect" class="form-select"></select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="connectGsc">Connect</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="gscKwModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import Keywords</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="kwFilter" class="form-control mb-2" placeholder="Filter keywords...">
        <div class="table-responsive" style="max-height:60vh;">
          <table class="table table-sm table-hover" id="kwTable">
            <thead class="table-light">
              <tr>
                <th data-sort="keyword">Keyword</th>
                <th data-sort="clicks" class="text-end">Clicks</th>
                <th data-sort="impressions" class="text-end">Impressions</th>
                <th data-sort="ctr" class="text-end">CTR</th>
                <th data-sort="position" class="text-end">Position</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="doImportKeywords">Import</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('remove-row')) {
    const tr = e.target.closest('tr');
    const flag = tr.querySelector('.delete-flag');
    const marked = flag.value === '1';
    flag.value = marked ? '0' : '1';
    tr.classList.toggle('text-decoration-line-through', !marked);
  }
});
const removeAllPosBtn = document.getElementById('removeAllPositions');
if (removeAllPosBtn) {
  removeAllPosBtn.addEventListener('click', () => {
    const rows = document.querySelectorAll('#posTableBody tr[data-id]');
    if (rows.length && confirm('Remove all keywords on this page?')) {
      rows.forEach(tr => {
        const flag = tr.querySelector('.delete-flag');
        if (flag) {
          flag.value = '1';
          tr.classList.add('text-decoration-line-through');
        }
      });
    }
  });
}

document.getElementById('toggleAddPosForm').addEventListener('click', function() {
  const form = document.getElementById('addPosForm');
  form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
});

document.getElementById('toggleImportPosForm').addEventListener('click', function() {
  const form = document.getElementById('importPosForm');
  form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
});

function showCopiedToast(msg) {
  const el = document.getElementById('copyToast');
  el.querySelector('.toast-body').textContent = msg;
  const toast = bootstrap.Toast.getOrCreateInstance(el);
  toast.show();
}

document.getElementById('copyPosKeywords').addEventListener('click', function() {
  const rows = document.querySelectorAll('#posTableBody tr[data-id]');
  const keywords = [];
  rows.forEach(tr => {
    const cell = tr.querySelector('td:nth-child(2)');
    if (cell) keywords.push(cell.innerText.trim());
  });
  navigator.clipboard.writeText(keywords.join('\n')).then(() => {
    showCopiedToast('Keywords copied to clipboard');
  });
});

const changeBtn = document.getElementById('changeScDomain');
if (changeBtn) {
  changeBtn.addEventListener('click', function() {
    fetch('gsc_fetch.php?props=1')
      .then(r => r.json())
      .then(data => {
        if (data.status === 'auth' && data.url) {
          window.location = data.url;
        } else if (data.status === 'ok') {
          const sel = document.getElementById('gscSiteSelect');
          sel.innerHTML = '';
          data.sites.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.siteUrl;
            opt.textContent = s.siteUrl + ' [' + s.permissionLevel + ']';
            sel.appendChild(opt);
          });
          const modal = new bootstrap.Modal(document.getElementById('gscModal'));
          modal.show();
        } else {
          alert(data.error || 'Failed to load properties');
        }
      })
      .catch(err => alert('Error: ' + err));
  });
}

const connectBtn = document.getElementById('connectGsc');
if (connectBtn) {
  connectBtn.addEventListener('click', function() {
    const site = document.getElementById('gscSiteSelect').value;
    if (!site) return;
    fetch('gsc_fetch.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        client_id: '<?= $client_id ?>',
        site: site,
        ajax: 1
      })
    }).then(r => r.json()).then(data => {
      if (data.status === 'ok') {
        document.getElementById('scDomain').value = site;
        changeBtn.textContent = 'Change';
        bootstrap.Modal.getInstance(document.getElementById('gscModal')).hide();
      } else {
        alert(data.error || 'Connect failed');
      }
    }).catch(err => alert('Error: ' + err));
  });
}

const fetchBtn = document.getElementById('fetchGsc');
if (fetchBtn) {
  fetchBtn.addEventListener('click', function() {
    const site = document.getElementById('scDomain').value.trim();
    if (!site) { alert('No Search Console property connected'); return; }
    const sel = document.getElementById('scMonth');
    const start = sel.selectedOptions[0].dataset.start;
    const end = sel.selectedOptions[0].dataset.end;
    const country = document.getElementById('scCountry').value;
    const monthIndex = sel.value;
    fetchBtn.disabled = true;
    fetch('gsc_import.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        client_id: '<?= $client_id ?>',
        site: site,
        start: start,
        end: end,
        country: country,
        month_index: monthIndex
      })
    }).then(r=>r.json()).then(data=>{
      fetchBtn.disabled = false;
      if (data.status === 'ok') {
        location.reload();
      } else {
        alert(data.error || 'Import failed');
      }
    }).catch(err=>{
      fetchBtn.disabled = false;
      alert('Error: '+err);
    });
  });
}



let kwData = [];
const openKwBtn = document.getElementById('openImportKw');
if (openKwBtn) {
  openKwBtn.addEventListener('click', function() {
    const site = document.getElementById('scDomain').value.trim();
    if (!site) { alert('No Search Console property connected'); return; }
    const modalEl = document.getElementById('gscKwModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    const tbody = document.querySelector('#kwTable tbody');
    tbody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
    fetch('gsc_keywords.php?client_id=<?= $client_id ?>&site=' + encodeURIComponent(site))
      .then(r=>r.json()).then(data=>{
        if (data.status === 'ok') {
          kwData = data.rows;
          renderKwTable(kwData);
        } else {
          tbody.innerHTML = '<tr><td colspan="5">Failed to load</td></tr>';
          alert(data.error || 'Failed to load');
        }
      }).catch(err=>{
        tbody.innerHTML = '<tr><td colspan="5">Error</td></tr>';
        alert('Error: '+err);
      });
  });
}

function renderKwTable(rows) {
  const tbody = document.querySelector('#kwTable tbody');
  tbody.innerHTML = '';
  rows.forEach(r=>{
    const kw = r.keys[0] || '';
    const clicks = r.clicks ?? 0;
    const impr = r.impressions ?? 0;
    const ctr = r.ctr ? (r.ctr*100).toFixed(2)+'%' : '';
    const pos = r.position ? r.position.toFixed(2) : '';
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${kw}</td><td class="text-end">${clicks}</td><td class="text-end">${impr}</td><td class="text-end">${ctr}</td><td class="text-end">${pos}</td>`;
    tbody.appendChild(tr);
  });
}

const kwFilter = document.getElementById('kwFilter');
if (kwFilter) {
  kwFilter.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    const rows = kwData.filter(r => (r.keys[0]||'').toLowerCase().includes(q));
    renderKwTable(rows);
  });
}

document.querySelectorAll('#kwTable thead th').forEach(th=>{
  th.addEventListener('click', function(){
    const key = th.dataset.sort;
    const dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
    th.dataset.dir = dir;
    kwData.sort((a,b)=>{
      let va, vb;
      if (key === 'keyword') { va = (a.keys[0]||'').toLowerCase(); vb = (b.keys[0]||'').toLowerCase(); }
      else { va = a[key] || 0; vb = b[key] || 0; }
      if (va < vb) return dir === 'asc' ? -1 : 1;
      if (va > vb) return dir === 'asc' ? 1 : -1;
      return 0;
    });
    renderKwTable(kwData);
  });
}

const fetchBtn = document.getElementById('fetchGsc');
if (fetchBtn) {
  fetchBtn.addEventListener('click', function() {
    const site = document.getElementById('scDomain').value.trim();
    if (!site) { alert('No Search Console property connected'); return; }
    const sel = document.getElementById('scMonth');
    const start = sel.selectedOptions[0].dataset.start;
    const end = sel.selectedOptions[0].dataset.end;
    const country = document.getElementById('scCountry').value;
    const monthIndex = sel.value;
    fetchBtn.disabled = true;
    fetch('gsc_import.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        client_id: '<?= $client_id ?>',
        site: site,
        start: start,
        end: end,
        country: country,
        month_index: monthIndex
      })
    }).then(r=>r.json()).then(data=>{
      fetchBtn.disabled = false;
      if (data.status === 'ok') {
        location.reload();
      } else {
        alert(data.error || 'Import failed');
      }
    }).catch(err=>{
      fetchBtn.disabled = false;
      alert('Error: '+err);
    });
  });
}



const doImportKwBtn = document.getElementById('doImportKeywords');
if (doImportKwBtn) {
  doImportKwBtn.addEventListener('click', function(){
    const site = document.getElementById('scDomain').value.trim();
    if (!site) return;
    doImportKwBtn.disabled = true;
    fetch('gsc_keywords.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({client_id:'<?= $client_id ?>', site: site})
    }).then(r=>r.json()).then(data=>{
      doImportKwBtn.disabled = false;
      if (data.status === 'ok') {
        location.reload();
      } else {
        alert(data.error || 'Import failed');
      }
    }).catch(err=>{
      doImportKwBtn.disabled = false;
      alert('Error: '+err);
    });
  });
}

let posChart;
const barLabelPlugin = {
  id: 'barLabel',
  afterDatasetsDraw(chart) {
    const {ctx} = chart;
    ctx.save();
    ctx.fillStyle = '#000';
    ctx.textAlign = 'center';
    ctx.font = '20px sans-serif';
    chart.getDatasetMeta(0).data.forEach((bar, i) => {
      const val = chart.data.datasets[0].data[i] + '%';
      ctx.fillText(val, bar.x, bar.y - 2);
    });
    ctx.restore();
  }
};

document.getElementById('posChartCollapse').addEventListener('shown.bs.collapse', function () {
  if (posChart) return;
  const ctx = document.getElementById('posChart').getContext('2d');
  posChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_reverse($months)) ?>,
      datasets: [{
        label: '% in Top 10',
        data: <?= json_encode(array_reverse($firstPagePerc)) ?>,
        backgroundColor: '#0d6efd'
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, max: 100 } }
    },
    plugins: [barLabelPlugin]
  });
});

const allKeywords = <?= json_encode($allKeywords) ?>;

document.addEventListener('DOMContentLoaded', () => {
  const posCells = Array.from(document.querySelectorAll('#posTableBody td:nth-child(2)'));
  posCells.forEach(cell => {
    if (allKeywords.includes(cell.innerText.trim().toLowerCase())) {
      cell.classList.add('highlight-cell');
    }
  });
});
</script>
<?php include 'footer.php'; ?>
