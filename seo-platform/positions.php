<?php
require_once __DIR__ . '/session.php';
require 'config.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$country = strtolower(trim($_GET['country'] ?? ''));
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
    m12 FLOAT DEFAULT NULL,
    m13 FLOAT DEFAULT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
for ($i = 1; $i <= 13; $i++) {
    $pdo->exec("ALTER TABLE keyword_positions ADD COLUMN IF NOT EXISTS m{$i} FLOAT DEFAULT NULL");
}
$pdo->exec("ALTER TABLE keyword_positions ADD COLUMN IF NOT EXISTS sort_order INT");
$pdo->exec("ALTER TABLE keyword_positions ADD COLUMN IF NOT EXISTS country VARCHAR(3) NOT NULL DEFAULT ''");
$pdo->exec("CREATE INDEX IF NOT EXISTS kp_client_country_idx ON keyword_positions (client_id, country)");
$pdo->exec("CREATE TABLE IF NOT EXISTS sc_domains (
    client_id INT PRIMARY KEY,
    domain VARCHAR(255)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS sc_countries (
    client_id INT,
    country VARCHAR(3),
    PRIMARY KEY (client_id, country)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$scDomainStmt = $pdo->prepare("SELECT domain FROM sc_domains WHERE client_id = ?");
$scDomainStmt->execute([$client_id]);
$scDomain = $scDomainStmt->fetchColumn() ?: '';



if (isset($_POST['add_position_keywords'])) {
    $text = trim($_POST['pos_keywords'] ?? '');
    $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $text)), 'strlen'));
    $ins = $pdo->prepare("INSERT INTO keyword_positions (client_id, keyword, country) VALUES (?, ?, ?)");
    foreach ($lines as $kw) {
        $ins->execute([$client_id, $kw, $country]);
    }
    $dup = $pdo->prepare("DELETE kp1 FROM keyword_positions kp1
                  JOIN keyword_positions kp2
                    ON kp1.keyword = kp2.keyword AND kp1.id > kp2.id
                 WHERE kp1.client_id = ?
                   AND kp2.client_id = ?
                   AND kp1.country = ?
                   AND kp2.country = ?");
    $dup->execute([$client_id, $client_id, $country, $country]);
}

if (isset($_POST['update_positions'])) {
    $deleteIds = array_keys(array_filter($_POST['delete_pos'] ?? [], fn($v) => $v == '1'));
    if ($deleteIds) {
        $in = implode(',', array_fill(0, count($deleteIds), '?'));
        $del = $pdo->prepare("DELETE FROM keyword_positions WHERE client_id = ? AND country = ? AND id IN ($in)");
        $del->execute(array_merge([$client_id, $country], $deleteIds));
    }
}

if (isset($_POST['import_positions']) && isset($_FILES['csv_file']['tmp_name'])) {
    $monthIndex = (int)($_POST['position_month'] ?? 0);
    $col = 'm' . ($monthIndex + 2);
    $tmp = $_FILES['csv_file']['tmp_name'];
    $rawRows = [];
    if (is_uploaded_file($tmp)) {
        require_once __DIR__ . '/lib/SimpleXLSX.php';
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($tmp)) {
            $rawRows = $xlsx->rows();
        }
    }
    if ($rawRows) {

        $mapStmt = $pdo->prepare("SELECT id, keyword, sort_order FROM keyword_positions WHERE client_id = ? AND country = ?");
        $mapStmt->execute([$client_id, $country]);
        $kwMap = [];
        while ($r = $mapStmt->fetch(PDO::FETCH_ASSOC)) {
            $kwMap[strtolower(trim($r['keyword']))] = ['id' => $r['id'], 'sort_order' => $r['sort_order']];
        }

        $pdo->prepare("UPDATE keyword_positions SET `$col` = NULL, sort_order = NULL WHERE client_id = ? AND country = ?")->execute([$client_id, $country]);

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

        $dup2 = $pdo->prepare("DELETE kp1 FROM keyword_positions kp1
                      JOIN keyword_positions kp2
                        ON kp1.keyword = kp2.keyword AND kp1.id > kp2.id
                     WHERE kp1.client_id = ?
                       AND kp2.client_id = ?
                       AND kp1.country = ?
                       AND kp2.country = ?");
        $dup2->execute([$client_id, $client_id, $country, $country]);
    }
}

$posQ = trim($_GET['pos_q'] ?? '');
$posSql = "SELECT * FROM keyword_positions WHERE client_id = ? AND country = ?";
$posParams = [$client_id, $country];
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
for ($i = 2; $i <= 13; $i++) {
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
for ($i = 1; $i <= 12; $i++) {
    $months[] = date('M Y', strtotime("-$i month"));
}

$kwAllStmt = $pdo->prepare("SELECT keyword FROM keywords WHERE client_id = ?");
$kwAllStmt->execute([$client_id]);
$allKeywords = array_map('strtolower', array_map('trim', $kwAllStmt->fetchAll(PDO::FETCH_COLUMN)));
$allKeywords = array_values(array_unique($allKeywords));

$posKwStmt = $pdo->prepare("SELECT keyword FROM keyword_positions WHERE client_id = ? AND country = ?");
$posKwStmt->execute([$client_id, $country]);
$countryStmt = $pdo->prepare("SELECT country FROM sc_countries WHERE client_id = ?");
$countryStmt->execute([$client_id]);
$dbCountries = array_filter($countryStmt->fetchAll(PDO::FETCH_COLUMN), 'strlen');
$posKeywords = array_map('strtolower', array_map('trim', $posKwStmt->fetchAll(PDO::FETCH_COLUMN)));
$posKeywords = array_values(array_unique($posKeywords));

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
    <div class="col-sm">
      <div class="d-flex">
        <input type="text" id="scDomain" value="<?= htmlspecialchars($scDomain) ?>" class="form-control me-2" readonly placeholder="Connect Search Console">
        <button type="button" id="changeScDomain" class="btn btn-outline-secondary btn-sm"><?= $scDomain ? 'Change' : 'Connect' ?></button>
      </div>
    </div>
    <div class="col-sm-auto d-flex align-items-start">
      <button type="button" id="fetchGsc" class="btn btn-primary btn-sm ms-auto">Fetch</button>
    </div>
  </div>
</div>

<hr>
<div class="d-flex align-items-center mb-3">
  <div id="countryGroup" class="btn-group me-2"></div>
  <button type="button" id="importCountryBtn" class="btn btn-outline-primary btn-sm">Import Country</button>
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
    <button type="button" id="toggleImportPosForm" class="btn btn-primary btn-sm me-2" style="display:none;">Import Positions</button>
    <button type="button" id="openImportKw" class="btn btn-info btn-sm me-2">Import Keywords</button>
    <button type="button" id="copyPosKeywords" class="btn btn-secondary btn-sm me-2">Copy Keywords</button>
    <?php if ($country): ?>
    <button type="button" id="removeCountry" class="btn btn-danger btn-sm me-2">Remove Country</button>
    <?php endif; ?>
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
      <?php for ($i=12;$i>=1;$i--): $p = $firstPagePerc[$i+1]; ?>
        <td class="text-center fw-bold"><?= $p ?>%</td>
      <?php endfor; ?>
    </tr>
    <?php foreach ($positions as $row): ?>
      <tr data-id="<?= $row['id'] ?>">
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row">-</button><input type="hidden" name="delete_pos[<?= $row['id'] ?>]" value="0" class="delete-flag"></td>
        <td><?= htmlspecialchars($row['keyword']) ?></td>
        <?php for ($i=12;$i>=1;$i--): $col = 'm'.($i+1); ?>
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

<div class="modal fade" id="gscCountryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select Countries</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="text" id="countryFilter" class="form-control mb-2" placeholder="Filter countries...">
        <div class="table-responsive" style="max-height:60vh;">
          <table class="table table-sm table-hover" id="countryTable">
            <thead class="table-light">
              <tr>
                <th><input type="checkbox" id="countrySelectAll"></th>
                <th>Country</th>
                <th class="text-end">Impressions</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="saveCountries">Import Selected</button>
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
                <th><input type="checkbox" id="kwSelectAll"></th>
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

const removeBtn = document.getElementById('removeCountry');
if (removeBtn) {
  removeBtn.addEventListener('click', () => {
    if (!currentCountry) return;
    const name = countryNames[currentCountry] || currentCountry.toUpperCase();
    if (!confirm(`Remove ${name} and all its keywords?`)) return;
    selectedCountries.delete(currentCountry);
    fetch('gsc_countries.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        client_id: '<?= $client_id ?>',
        countries: JSON.stringify(Array.from(selectedCountries))
      })
    }).then(r=>r.json()).then(data=>{
      if (data.status === 'ok') {
        const params = new URLSearchParams(window.location.search);
        params.delete('country');
        window.location.search = params.toString();
      } else {
        alert(data.error || 'Remove failed');
      }
    }).catch(err=>alert('Error: '+err));
  });
}

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

const existingCountries = <?= json_encode($dbCountries) ?>;
let currentCountry = '<?= $country ?>';

const countryNames = {
    "afg": "Afghanistan",
    "ala": "Åland Islands",
    "alb": "Albania",
    "dza": "Algeria",
    "asm": "American Samoa",
    "and": "Andorra",
    "ago": "Angola",
    "aia": "Anguilla",
    "ata": "Antarctica",
    "atg": "Antigua and Barbuda",
    "arg": "Argentina",
    "arm": "Armenia",
    "abw": "Aruba",
    "aus": "Australia",
    "aut": "Austria",
    "aze": "Azerbaijan",
    "bhs": "Bahamas",
    "bhr": "Bahrain",
    "bgd": "Bangladesh",
    "brb": "Barbados",
    "blr": "Belarus",
    "bel": "Belgium",
    "blz": "Belize",
    "ben": "Benin",
    "bmu": "Bermuda",
    "btn": "Bhutan",
    "bol": "Bolivia, Plurinational State of",
    "bes": "Bonaire, Sint Eustatius and Saba",
    "bih": "Bosnia and Herzegovina",
    "bwa": "Botswana",
    "bvt": "Bouvet Island",
    "bra": "Brazil",
    "iot": "British Indian Ocean Territory",
    "brn": "Brunei Darussalam",
    "bgr": "Bulgaria",
    "bfa": "Burkina Faso",
    "bdi": "Burundi",
    "cpv": "Cabo Verde",
    "khm": "Cambodia",
    "cmr": "Cameroon",
    "can": "Canada",
    "cym": "Cayman Islands",
    "caf": "Central African Republic",
    "tcd": "Chad",
    "chl": "Chile",
    "chn": "China",
    "cxr": "Christmas Island",
    "cck": "Cocos (Keeling) Islands",
    "col": "Colombia",
    "com": "Comoros",
    "cog": "Congo",
    "cod": "Congo, Democratic Republic of the",
    "cok": "Cook Islands",
    "cri": "Costa Rica",
    "civ": "Côte d'Ivoire",
    "hrv": "Croatia",
    "cub": "Cuba",
    "cuw": "Curaçao",
    "cyp": "Cyprus",
    "cze": "Czechia",
    "dnk": "Denmark",
    "dji": "Djibouti",
    "dma": "Dominica",
    "dom": "Dominican Republic",
    "ecu": "Ecuador",
    "egy": "Egypt",
    "slv": "El Salvador",
    "gnq": "Equatorial Guinea",
    "eri": "Eritrea",
    "est": "Estonia",
    "swz": "Eswatini",
    "eth": "Ethiopia",
    "flk": "Falkland Islands (Malvinas)",
    "fro": "Faroe Islands",
    "fji": "Fiji",
    "fin": "Finland",
    "fra": "France",
    "guf": "French Guiana",
    "pyf": "French Polynesia",
    "atf": "French Southern Territories",
    "gab": "Gabon",
    "gmb": "Gambia",
    "geo": "Georgia",
    "deu": "Germany",
    "gha": "Ghana",
    "gib": "Gibraltar",
    "grc": "Greece",
    "grl": "Greenland",
    "grd": "Grenada",
    "glp": "Guadeloupe",
    "gum": "Guam",
    "gtm": "Guatemala",
    "ggy": "Guernsey",
    "gin": "Guinea",
    "gnb": "Guinea-Bissau",
    "guy": "Guyana",
    "hti": "Haiti",
    "hmd": "Heard Island and McDonald Islands",
    "vat": "Holy See",
    "hnd": "Honduras",
    "hkg": "Hong Kong",
    "hun": "Hungary",
    "isl": "Iceland",
    "ind": "India",
    "idn": "Indonesia",
    "irn": "Iran, Islamic Republic of",
    "irq": "Iraq",
    "irl": "Ireland",
    "imn": "Isle of Man",
    "isr": "Israel",
    "ita": "Italy",
    "jam": "Jamaica",
    "jpn": "Japan",
    "jey": "Jersey",
    "jor": "Jordan",
    "kaz": "Kazakhstan",
    "ken": "Kenya",
    "kir": "Kiribati",
    "prk": "Korea, Democratic People's Republic of",
    "kor": "Korea, Republic of",
    "kwt": "Kuwait",
    "kgz": "Kyrgyzstan",
    "lao": "Lao People's Democratic Republic",
    "lva": "Latvia",
    "lbn": "Lebanon",
    "lso": "Lesotho",
    "lbr": "Liberia",
    "lby": "Libya",
    "lie": "Liechtenstein",
    "ltu": "Lithuania",
    "lux": "Luxembourg",
    "mac": "Macao",
    "mdg": "Madagascar",
    "mwi": "Malawi",
    "mys": "Malaysia",
    "mdv": "Maldives",
    "mli": "Mali",
    "mlt": "Malta",
    "mhl": "Marshall Islands",
    "mtq": "Martinique",
    "mrt": "Mauritania",
    "mus": "Mauritius",
    "myt": "Mayotte",
    "mex": "Mexico",
    "fsm": "Micronesia, Federated States of",
    "mda": "Moldova, Republic of",
    "mco": "Monaco",
    "mng": "Mongolia",
    "mne": "Montenegro",
    "msr": "Montserrat",
    "mar": "Morocco",
    "moz": "Mozambique",
    "mmr": "Myanmar",
    "nam": "Namibia",
    "nru": "Nauru",
    "npl": "Nepal",
    "nld": "Netherlands, Kingdom of the",
    "ncl": "New Caledonia",
    "nzl": "New Zealand",
    "nic": "Nicaragua",
    "ner": "Niger",
    "nga": "Nigeria",
    "niu": "Niue",
    "nfk": "Norfolk Island",
    "mkd": "North Macedonia",
    "mnp": "Northern Mariana Islands",
    "nor": "Norway",
    "omn": "Oman",
    "pak": "Pakistan",
    "plw": "Palau",
    "pse": "Palestine, State of",
    "pan": "Panama",
    "png": "Papua New Guinea",
    "pry": "Paraguay",
    "per": "Peru",
    "phl": "Philippines",
    "pcn": "Pitcairn",
    "pol": "Poland",
    "prt": "Portugal",
    "pri": "Puerto Rico",
    "qat": "Qatar",
    "reu": "Réunion",
    "rou": "Romania",
    "rus": "Russian Federation",
    "rwa": "Rwanda",
    "blm": "Saint Barthélemy",
    "shn": "Saint Helena, Ascension and Tristan da Cunha",
    "kna": "Saint Kitts and Nevis",
    "lca": "Saint Lucia",
    "maf": "Saint Martin (French part)",
    "spm": "Saint Pierre and Miquelon",
    "vct": "Saint Vincent and the Grenadines",
    "wsm": "Samoa",
    "smr": "San Marino",
    "stp": "Sao Tome and Principe",
    "sau": "Saudi Arabia",
    "sen": "Senegal",
    "srb": "Serbia",
    "syc": "Seychelles",
    "sle": "Sierra Leone",
    "sgp": "Singapore",
    "sxm": "Sint Maarten (Dutch part)",
    "svk": "Slovakia",
    "svn": "Slovenia",
    "slb": "Solomon Islands",
    "som": "Somalia",
    "zaf": "South Africa",
    "sgs": "South Georgia and the South Sandwich Islands",
    "ssd": "South Sudan",
    "esp": "Spain",
    "lka": "Sri Lanka",
    "sdn": "Sudan",
    "sur": "Suriname",
    "sjm": "Svalbard and Jan Mayen",
    "swe": "Sweden",
    "che": "Switzerland",
    "syr": "Syrian Arab Republic",
    "twn": "Taiwan, Province of China",
    "tjk": "Tajikistan",
    "tza": "Tanzania, United Republic of",
    "tha": "Thailand",
    "tls": "Timor-Leste",
    "tgo": "Togo",
    "tkl": "Tokelau",
    "ton": "Tonga",
    "tto": "Trinidad and Tobago",
    "tun": "Tunisia",
    "tur": "Türkiye",
    "tkm": "Turkmenistan",
    "tca": "Turks and Caicos Islands",
    "tuv": "Tuvalu",
    "uga": "Uganda",
    "ukr": "Ukraine",
    "are": "United Arab Emirates",
    "gbr": "United Kingdom of Great Britain and Northern Ireland",
    "usa": "United States of America",
    "umi": "United States Minor Outlying Islands",
    "ury": "Uruguay",
    "uzb": "Uzbekistan",
    "vut": "Vanuatu",
    "ven": "Venezuela, Bolivarian Republic of",
    "vnm": "Viet Nam",
    "vgb": "Virgin Islands (British)",
    "vir": "Virgin Islands (U.S.)",
    "wlf": "Wallis and Futuna",
    "esh": "Western Sahara",
    "yem": "Yemen",
    "zmb": "Zambia",
    "zwe": "Zimbabwe",
};


function renderCountryBtns(list) {
  const group = document.getElementById('countryGroup');
  group.innerHTML = '';
  const makeBtn = (code, label) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-secondary btn-sm' + (code===currentCountry?' active':'');
    btn.textContent = label;
    btn.addEventListener('click', () => {
      const params = new URLSearchParams(window.location.search);
      if (code) params.set('country', code); else params.delete('country');
      window.location.search = params.toString();
    });
    group.appendChild(btn);
  };
  makeBtn('', 'Worldwide');
  list.filter(c=>c).sort((a,b)=>{
    const na = countryNames[a] || a;
    const nb = countryNames[b] || b;
    return na.localeCompare(nb);
  }).forEach(c=>makeBtn(c, countryNames[c] || c.toUpperCase()));
}

renderCountryBtns(existingCountries);

let countryData = [];
let countryFilterVal = '';
let selectedCountries = new Set(existingCountries);

function renderCountryTable() {
  const tbody = document.querySelector('#countryTable tbody');
  const rows = countryData
    .filter(r => {
      const name = countryNames[r.code] || '';
      return r.code.includes(countryFilterVal) || name.toLowerCase().includes(countryFilterVal);
    })
    .sort((a,b)=>b.impressions - a.impressions);
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="3">No data</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(r=>`
    <tr>
      <td><input type="checkbox" class="country-check" data-code="${r.code}" ${selectedCountries.has(r.code)?'checked':''}></td>
      <td>${countryNames[r.code] || r.code.toUpperCase()}</td>
      <td>${Math.round(r.impressions)}</td>
    </tr>
  `).join('');
  const all = document.getElementById('countrySelectAll');
  const checks = tbody.querySelectorAll('.country-check');
  all.checked = checks.length && Array.from(checks).every(c=>c.checked);
  checks.forEach(cb=>cb.addEventListener('change', () => {
    const code = cb.dataset.code;
    if (cb.checked) selectedCountries.add(code); else selectedCountries.delete(code);
    const visible = Array.from(tbody.querySelectorAll('.country-check'));
    all.checked = visible.length && visible.every(c=>c.checked);
  }));
}

document.getElementById('importCountryBtn').addEventListener('click', () => {
  const site = document.getElementById('scDomain').value.trim();
  if (!site) { alert('No Search Console property connected'); return; }
  const modalEl = document.getElementById('gscCountryModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
  const tbody = document.querySelector('#countryTable tbody');
  tbody.innerHTML = '<tr><td colspan="3">Loading...</td></tr>';
  countryData = [];
  selectedCountries = new Set(existingCountries);
  document.getElementById('countryFilter').value = '';
  countryFilterVal = '';
  fetch('gsc_countries.php?site=' + encodeURIComponent(site))
    .then(r=>r.json()).then(data=>{
      if (data.status === 'ok') {
        countryData = data.countries;
        renderCountryTable();
      } else {
        tbody.innerHTML = '<tr><td colspan="3">Failed to load</td></tr>';
        alert(data.error || 'Failed to load');
      }
    }).catch(err=>{
      tbody.innerHTML = '<tr><td colspan="3">Error</td></tr>';
      alert('Error: '+err);
    });
});

document.getElementById('countryFilter').addEventListener('input', e => {
  countryFilterVal = e.target.value.trim().toLowerCase();
  renderCountryTable();
});

document.getElementById('countrySelectAll').addEventListener('change', e => {
  const checked = e.target.checked;
  const checks = document.querySelectorAll('#countryTable tbody .country-check');
  checks.forEach(cb => {
    cb.checked = checked;
    const code = cb.dataset.code;
    if (checked) selectedCountries.add(code); else selectedCountries.delete(code);
  });
});

document.getElementById('saveCountries').addEventListener('click', () => {
  fetch('gsc_countries.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      client_id: '<?= $client_id ?>',
      countries: JSON.stringify(Array.from(selectedCountries))
    })
  }).then(r=>r.json()).then(data=>{
    if (data.status === 'ok') {
      if (!selectedCountries.has(currentCountry)) {
        const params = new URLSearchParams(window.location.search);
        params.delete('country');
        window.location.search = params.toString();
      } else {
        location.reload();
      }
    } else {
      alert(data.error || 'Save failed');
    }
  }).catch(err=>alert('Error: '+err));
});

const fetchBtn = document.getElementById('fetchGsc');
if (fetchBtn) {
  fetchBtn.addEventListener('click', function() {
    const site = document.getElementById('scDomain').value.trim();
    if (!site) { alert('No Search Console property connected'); return; }
    fetchBtn.disabled = true;
    fetch('gsc_import.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        client_id: '<?= $client_id ?>',
        site: site,
        country: currentCountry
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
let kwFilterVal = '';
let selectedKws = new Set();
let sortKey = 'impressions';
let sortDir = 'desc';
document.getElementById('openImportKw')?.addEventListener('click', () => {
  const site = document.getElementById('scDomain').value.trim();
  if (!site) { alert('No Search Console property connected'); return; }
  const modalEl = document.getElementById('gscKwModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
  const tbody = document.querySelector('#kwTable tbody');
  tbody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
  fetch('gsc_keywords.php?client_id=<?= $client_id ?>&site=' + encodeURIComponent(site) + '&country=' + encodeURIComponent(currentCountry))
    .then(r => r.json()).then(data => {
      if (data.status === 'ok') {
        kwData = data.rows;
        renderKwTable();
      } else {
        tbody.innerHTML = '<tr><td colspan="5">Failed to load</td></tr>';
        alert(data.error || 'Failed to load');
      }
    }).catch(err => {
      tbody.innerHTML = '<tr><td colspan="5">Error</td></tr>';
      alert('Error: ' + err);
    });
});

function renderKwTable() {
  const tbody = document.querySelector('#kwTable tbody');
  tbody.innerHTML = '';
  const rows = kwData.filter(r => (r.keys[0]||'').toLowerCase().includes(kwFilterVal));
  rows.forEach(r=>{
    const kw = r.keys[0] || '';
    const checked = selectedKws.has(kw) ? 'checked' : '';
    const clicks = r.clicks ?? 0;
    const impr = r.impressions ?? 0;
    const ctr = r.ctr ? (r.ctr*100).toFixed(2)+'%' : '';
    const pos = r.position ? r.position.toFixed(2) : '';
    const tr = document.createElement('tr');
    if (posKeywords.includes(kw.toLowerCase())) tr.classList.add('table-warning');
    tr.innerHTML = `<td><input type="checkbox" class="kw-check" data-kw="${kw}" ${checked}></td><td>${kw}</td><td class="text-end">${clicks}</td><td class="text-end">${impr}</td><td class="text-end">${ctr}</td><td class="text-end">${pos}</td>`;
    tbody.appendChild(tr);
  });
  const all = document.getElementById('kwSelectAll');
  all.checked = rows.length && rows.every(r=>selectedKws.has(r.keys[0]||''));
}

const kwFilter = document.getElementById('kwFilter');
if (kwFilter) {
  kwFilter.addEventListener('input', function() {
    kwFilterVal = this.value.toLowerCase();
    renderKwTable();
  });
}

document.querySelectorAll('#kwTable thead th[data-sort]').forEach((th) => {
  th.dataset.label = th.textContent;
  th.addEventListener('click', () => {
    const key = th.dataset.sort;
    if (!key) return;
    sortDir = (sortKey === key && sortDir === 'asc') ? 'desc' : 'asc';
    sortKey = key;
    kwData.sort((a, b) => {
      let va, vb;
      if (key === 'keyword') {
        va = (a.keys[0] || '').toLowerCase();
        vb = (b.keys[0] || '').toLowerCase();
      } else {
        va = a[key] || 0;
        vb = b[key] || 0;
      }
      if (va < vb) return sortDir === 'asc' ? -1 : 1;
      if (va > vb) return sortDir === 'asc' ? 1 : -1;
      return 0;
    });
    document.querySelectorAll('#kwTable thead th[data-sort]').forEach((h) => {
      h.textContent = h.dataset.label;
      delete h.dataset.dir;
    });
    th.dataset.dir = sortDir;
    th.textContent = th.dataset.label + (sortDir === 'asc' ? ' \u25B2' : ' \u25BC');
    renderKwTable();
  });
});

document.getElementById('kwSelectAll').addEventListener('change', function(){
  const checked = this.checked;
  document.querySelectorAll('#kwTable tbody .kw-check').forEach(cb=>{
    cb.checked = checked;
    const kw = cb.dataset.kw;
    if (checked) selectedKws.add(kw); else selectedKws.delete(kw);
  });
});

document.addEventListener('change', function(e){
  if (e.target.classList.contains('kw-check')) {
    const kw = e.target.dataset.kw;
    if (e.target.checked) selectedKws.add(kw); else selectedKws.delete(kw);
    const rows = kwData.filter(r => (r.keys[0]||'').toLowerCase().includes(kwFilterVal));
    const all = document.getElementById('kwSelectAll');
    all.checked = rows.length && rows.every(r=>selectedKws.has(r.keys[0]||''));
  }
});

const doImportKwBtn = document.getElementById('doImportKeywords');
if (doImportKwBtn) {
  doImportKwBtn.addEventListener('click', function(){
    const site = document.getElementById('scDomain').value.trim();
    if (!site) return;
    const selected = Array.from(selectedKws);
    if (!selected.length) { alert('No keywords selected'); return; }
    doImportKwBtn.disabled = true;
    fetch('gsc_keywords.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        client_id: '<?= $client_id ?>',
        site: site,
        country: currentCountry,
        keywords: JSON.stringify(selected)
      })
    }).then(r => r.json()).then(data => {
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

const allKeywords = <?= json_encode($allKeywords, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const posKeywords = <?= json_encode($posKeywords, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

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