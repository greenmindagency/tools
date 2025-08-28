<?php
$title = 'GAM Ad Units Creator';
include 'header.php';
?>
<style>
  table input { width: 100%; border: none; background: transparent; }
</style>
<form method="post">
  <table id="inputTable" class="table table-bordered">
    <thead>
      <tr><th>Parent</th><th>Path</th><th>Name</th><th></th></tr>
    </thead>
    <tbody>
      <tr>
        <td><input type="text" name="parent[]" class="form-control" value="<?php echo isset($_POST['parent'][0]) ? htmlspecialchars($_POST['parent'][0]) : ''; ?>"></td>
        <td><input type="text" name="path[]" class="form-control" value="<?php echo isset($_POST['path'][0]) ? htmlspecialchars($_POST['path'][0]) : ''; ?>"></td>
        <td><input type="text" name="name[]" class="form-control" value="<?php echo isset($_POST['name'][0]) ? htmlspecialchars($_POST['name'][0]) : ''; ?>"></td>
        <td><button type="button" class="btn btn-outline-secondary" onclick="addRow(this)">+</button></td>
      </tr>
    </tbody>
  </table>
  <button type="submit" class="btn btn-primary">Generate</button>
</form>
<script>
function addRow(btn) {
  const tr = btn.closest('tr');
  const clone = tr.cloneNode(true);
  const parentVal = tr.querySelector('input[name="parent[]"]').value;
  clone.querySelectorAll('input').forEach(i => i.value = '');
  clone.querySelector('input[name="parent[]"]').value = parentVal;
  const button = clone.querySelector('button');
  button.textContent = '-';
  button.classList.replace('btn-outline-secondary', 'btn-outline-danger');
  button.setAttribute('onclick', 'removeRow(this)');
  tr.parentNode.appendChild(clone);
}
function removeRow(btn) {
  const tr = btn.closest('tr');
  const tbody = tr.parentNode;
  if (tbody.children.length === 1) {
    tr.querySelectorAll('input').forEach(i => i.value = '');
    return;
  }
  tr.remove();
}
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
if (!empty($_POST['path'])) {
  $parents = $_POST['parent'] ?? [];
  $paths = $_POST['path'] ?? [];
  $names = $_POST['name'] ?? [];
  $pairs = [];
  $count = max(count($paths), count($parents));
  for ($i = 0; $i < $count; $i++) {
    $parent = isset($parents[$i]) ? trim($parents[$i]) : '';
    $path = isset($paths[$i]) ? trim($paths[$i]) : '';
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
