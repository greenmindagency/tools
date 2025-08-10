<?php
$title = 'Quotation Admin';
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: login.php');
    exit;
}
include 'header.php';
require_once __DIR__ . '/config.php';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: index.php');
    exit;
}
$pdo->exec("CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    html MEDIUMTEXT,
    slug VARCHAR(255) UNIQUE,
    published TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$clients = $pdo->query('SELECT id, name, slug, updated_at FROM clients ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
$monthly = $pdo->query("SELECT DATE_FORMAT(updated_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM clients GROUP BY ym ORDER BY ym")->fetchAll(PDO::FETCH_ASSOC);
$monthlyLabels = array_map(fn($m) => date('M Y', strtotime($m['ym'].'-01')), $monthly);
$monthlyCounts = array_map(fn($m) => (int)$m['cnt'], $monthly);
?>
<div class="mt-4">
<h1>Clients</h1>
<a href="builder.php" class="btn btn-primary btn-sm mb-3">Create New</a>
<div class="accordion mb-3" id="quoteStatsAcc">
  <div class="accordion-item">
    <h2 class="accordion-header" id="quoteStatsHead">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#quoteStatsCollapse" aria-expanded="false" aria-controls="quoteStatsCollapse">
        Monthly Quotes Summary
      </button>
    </h2>
    <div id="quoteStatsCollapse" class="accordion-collapse collapse" aria-labelledby="quoteStatsHead" data-bs-parent="#quoteStatsAcc">
      <div class="accordion-body">
        <div class="d-flex justify-content-end mb-2">
          <button id="copyQuoteStats" class="btn btn-sm btn-secondary">Copy</button>
        </div>
        <canvas id="quoteChart" height="100"></canvas>
        <table id="quoteStatsTable" class="table table-sm mt-3">
          <thead>
            <tr><th>Month</th><th>Quotes</th></tr>
          </thead>
          <tbody>
            <?php foreach ($monthly as $m): ?>
            <tr><td><?= date('M Y', strtotime($m['ym'].'-01')) ?></td><td><?= $m['cnt'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<table class="table table-bordered">
<tr><th>ID</th><th>Name</th><th>Date</th><th>Actions</th></tr>
<?php foreach($clients as $c): ?>
<tr>
<td><?= $c['id'] ?></td>
<td><?= htmlspecialchars($c['name']) ?></td>
<td><?= date('Y-m-d', strtotime($c['updated_at'])) ?></td>
<td>
<a href="builder.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
<a href="view.php?client=<?= urlencode($c['slug']) ?>" class="btn btn-sm btn-success" target="_blank">View</a>
<a href="index.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this client?');">Delete</a>
</td>
</tr>
<?php endforeach; ?>
</table>
<a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="copyToast" class="toast" role="status" aria-live="assertive" aria-atomic="true">
    <div class="toast-body">Copied</div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let quoteChart;
document.getElementById('quoteStatsCollapse').addEventListener('shown.bs.collapse', function () {
  if (quoteChart) return;
  const ctx = document.getElementById('quoteChart').getContext('2d');
  quoteChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($monthlyLabels) ?>,
      datasets: [{
        data: <?= json_encode($monthlyCounts) ?>,
        backgroundColor: '#0d6efd'
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
  });
});
function showCopiedToast(msg) {
  const el = document.getElementById('copyToast');
  el.querySelector('.toast-body').textContent = msg;
  bootstrap.Toast.getOrCreateInstance(el).show();
}
document.getElementById('copyQuoteStats').addEventListener('click', function () {
  const rows = document.querySelectorAll('#quoteStatsTable tbody tr');
  const lines = Array.from(rows).map(r => {
    const cols = r.querySelectorAll('td');
    return `${cols[0].innerText}\t${cols[1].innerText}`;
  });
  navigator.clipboard.writeText(lines.join('\n')).then(() => {
    showCopiedToast('Monthly stats copied');
  });
});
</script>
<?php include 'footer.php'; ?>
