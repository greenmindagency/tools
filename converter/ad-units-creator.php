<?php
$title = 'GAM Ad Units Creator';
include 'header.php';
?>
<style>
  table input, table textarea { width: 100%; border: none; background: transparent; }
</style>
<form method="post" id="adForm">
  <table class="table table-bordered">
    <thead>
      <tr><th>Parent</th><th>Paths (one per line)</th><th>Names (one per line)</th></tr>
    </thead>
    <tbody>
      <tr>
        <td><input type="text" name="parent" class="form-control" value="<?php echo isset($_POST['parent']) ? htmlspecialchars($_POST['parent']) : ''; ?>"></td>
        <td><textarea name="paths" class="form-control" rows="10"><?php echo isset($_POST['paths']) ? htmlspecialchars($_POST['paths']) : ''; ?></textarea></td>
        <td><textarea name="names" class="form-control" rows="10"><?php echo isset($_POST['names']) ? htmlspecialchars($_POST['names']) : ''; ?></textarea></td>
      </tr>
    </tbody>
  </table>
  <button type="submit" class="btn btn-primary">Generate</button>
</form>
<script>
const parentInput = document.querySelector('input[name="parent"]');
const pathsInput = document.querySelector('textarea[name="paths"]');
const namesInput = document.querySelector('textarea[name="names"]');
function deriveNames() {
  const paths = pathsInput.value.split(/\r?\n/);
  const names = namesInput.value.split(/\r?\n/);
  const newNames = paths.map((p, i) => {
    const path = p.trim();
    if (!path) return '';
    const existing = names[i] ? names[i].trim() : '';
    if (existing) return existing;
    const parts = path.split('/');
    return parts[parts.length - 1] || '';
  });
  namesInput.value = newNames.join('\n');
}
function saveInputs() {
  localStorage.setItem('auc_parent', parentInput.value);
  localStorage.setItem('auc_paths', pathsInput.value);
  localStorage.setItem('auc_names', namesInput.value);
}
pathsInput.addEventListener('input', () => { deriveNames(); saveInputs(); });
namesInput.addEventListener('input', saveInputs);
parentInput.addEventListener('input', saveInputs);
window.addEventListener('load', () => {
  const p = localStorage.getItem('auc_parent');
  const pa = localStorage.getItem('auc_paths');
  const n = localStorage.getItem('auc_names');
  if (p !== null) parentInput.value = p;
  if (pa !== null) pathsInput.value = pa;
  if (n !== null) namesInput.value = n;
  deriveNames();
});
document.getElementById('adForm').addEventListener('submit', () => { deriveNames(); saveInputs(); });
</script>
<?php
function is_parent($dfp, $all) {
  $prefix = rtrim($dfp, '/');
  foreach ($all as $p) {
    if ($p !== $dfp && strpos($p, $prefix . '/') === 0) {
      return true;
    }
  }
  return false;
}
if (!empty($_POST['paths'])) {
  $parent = isset($_POST['parent']) ? trim($_POST['parent']) : '';
  $paths = preg_split("/\r\n|\n|\r/", $_POST['paths']);
  $names = preg_split("/\r\n|\n|\r/", $_POST['names'] ?? '');
  $pairs = [];
  foreach ($paths as $i => $path) {
    $path = trim($path);
    if ($path === '') continue;
    $full = rtrim($parent, '/') . '/' . ltrim($path, '/');
    $name = isset($names[$i]) && trim($names[$i]) !== '' ? trim($names[$i]) : (($pos = strrpos($full, '/')) !== false ? substr($full, $pos + 1) : $full);
    $pairs[] = [$full, $name];
  }
  $dfps = array_column($pairs, 0);
  $rows = [];
  $sizesMain = '1x1; 2x2; 3x3; 4x4; 5x5; 140x170; 300x225; 300x250; 300x600; 300x2501; 300x2502; 320x50; 320x70; 320x100; 320x470; 320x501; 367x282; 549x392; 549x400; 590x95; 640x480v; 728x90; 728x120; 763x211; 785x370; 800x500v; 970x250; 1000x1000; Out-of-page';
  $sizesHeader = '300x250;fluid';
  $sizesSticky = '320x50';
  foreach ($pairs as $pair) {
    list($dfp, $name) = $pair;
    $rows[] = [$dfp, $name, $sizesMain, '', '', '', '', '', ''];
    if (!is_parent($dfp, $dfps)) {
      $rows[] = [$dfp . '/header_ad', 'header_ad', $sizesHeader, '', '', '', '', '', ''];
      $rows[] = [$dfp . '/middle_ad', 'middle_ad', $sizesHeader, '', '', '', '', '', ''];
      $rows[] = [$dfp . '/sticky_ad', 'sticky_ad', $sizesSticky, '', '', '', '', '', ''];
    }
  }
  $headers = ['#','Name','Sizes','Description','Placements','Target window','Teams','Labels','Special ad unit'];
  echo '<table class="table table-bordered table-striped"><thead><tr>';
  foreach ($headers as $h) echo '<th>'.htmlspecialchars($h).'</th>';
  echo '</tr></thead><tbody>';
  foreach ($rows as $r) {
    echo '<tr>'; foreach ($r as $c) echo '<td>'.htmlspecialchars($c).'</td>'; echo '</tr>';
  }
  echo '</tbody></table>';
  $csv = [];
  $csv[] = implode(',', $headers);
  foreach ($rows as $r) {
    $csv[] = implode(',', array_map(function($v){return '"'.str_replace('"','""',$v).'"';}, $r));
  }
  $csvContent = implode("\r\n", $csv);
  $encoded = rawurlencode($csvContent);
  echo '<a class="btn btn-success mt-3" href="data:text/csv;charset=utf-8,'. $encoded .'" download="ad_units.csv">Download CSV</a>';
}
?>
<?php include 'footer.php'; ?>
