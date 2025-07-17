<?php
$title = 'GAM Ad Units Creator';
include 'header.php';
?>
<style>
  textarea { min-height: 150px; }
  table { margin-top: 20px; }
</style>
<form method="post">
  <div class="mb-3">
    <label class="form-label">Paste URL to DFP mappings (tab separated, include header):</label>
    <textarea name="input" class="form-control" placeholder="English URL\tDFP\n..."><?php if(!empty($_POST['input'])) echo htmlspecialchars($_POST['input']); ?></textarea>
  </div>
  <button type="submit" class="btn btn-primary">Generate</button>
</form>
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
if (!empty($_POST['input'])) {
  $lines = preg_split("/(\r?\n)+/", trim($_POST['input']));
  if (count($lines) > 0) {
    // remove header line
    if (preg_match('/english url/i', $lines[0])) {
      array_shift($lines);
    }
  }
  $pairs = [];
  foreach ($lines as $line) {
    $cols = preg_split("/\t+/", trim($line));
    if (count($cols) >= 2) {
      $pairs[] = [trim($cols[0]), trim($cols[1])];
    }
  }
  $dfps = array_column($pairs, 1);
  $rows = [];
  $sizesMain = '1x1; 2x2; 3x3; 4x4; 5x5; 140x170; 300x225; 300x250; 300x600; 300x2501; 300x2502; 320x50; 320x70; 320x100; 320x470; 320x501; 367x282; 549x392; 549x400; 590x95; 640x480v; 728x90; 728x120; 763x211; 785x370; 800x500v; 970x250; 1000x1000; Out-of-page';
  $sizesHeader = '300x250;fluid';
  $sizesSticky = '320x50';
  foreach ($pairs as $pair) {
    list($url, $dfp) = $pair;
    $name = ($pos = strpos($dfp, '/')) !== false ? substr($dfp, $pos + 1) : $dfp;
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
