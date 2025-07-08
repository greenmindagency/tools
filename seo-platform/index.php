<?php
$slugify = function(string $name): string {
    $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    $name = preg_replace('/[^a-zA-Z0-9]+/', '-', $name);
    return strtolower(trim($name, '-'));
};
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // add new client
    if (isset($_POST['client_name'])) {
        $name = trim($_POST['client_name']);
        if ($name !== '') {
            $insert = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
            $insert->execute([$name]);
        }
        header('Location: index.php');
        exit;
    }

    // delete client and all related data
    if (isset($_POST['delete_client'])) {
        $cid = (int)$_POST['delete_client'];
        $pdo->prepare("DELETE FROM keyword_positions WHERE client_id = ?")->execute([$cid]);
        $pdo->prepare("DELETE FROM keywords WHERE client_id = ?")->execute([$cid]);
        $pdo->prepare("DELETE FROM sc_domains WHERE client_id = ?")->execute([$cid]);
        $pdo->prepare("DELETE FROM keyword_stats WHERE client_id = ?")->execute([$cid]);
        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$cid]);
        $bdir = __DIR__ . "/backups/client_{$cid}";
        if (is_dir($bdir)) {
            foreach (glob("$bdir/*") as $f) unlink($f);
            rmdir($bdir);
        }
        header('Location: index.php');
        exit;
    }

    // rename client
    if (isset($_POST['rename_client'], $_POST['new_name'])) {
        $cid = (int)$_POST['rename_client'];
        $new = trim($_POST['new_name']);
        if ($new !== '') {
            $up = $pdo->prepare("UPDATE clients SET name = ? WHERE id = ?");
            $up->execute([$new, $cid]);
        }
        header('Location: index.php');
        exit;
    }
}

$title = 'SEO Platform';
include 'header.php';
?>

<div class="mb-4">
  <h5>Select a Client</h5>
  <ul class="list-group">
  <?php
  $stmt = $pdo->query("SELECT * FROM clients ORDER BY name ASC");
    foreach ($stmt as $client) {
        $id = $client['id'];
        $name = htmlspecialchars($client['name']);
        $slug = $slugify($client['name']);
        $escName = htmlspecialchars($client['name'], ENT_QUOTES);
        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
        echo "<a class='me-auto' href='dashboard.php?client_id=$id&slug=$slug'>$name</a>";
        echo "<div class='btn-group btn-group-sm' role='group'>";
        echo "<button type='button' class='btn btn-outline-secondary rename-btn' data-id='$id' data-name='$escName'>Rename</button>";
        echo "<form method='POST' class='d-inline ms-1' onsubmit=\"return confirm('Delete this client and all data?');\">";
        echo "<input type='hidden' name='delete_client' value='$id'>";
        echo "<button type='submit' class='btn btn-outline-danger'>Remove</button>";
        echo "</form></div></li>";
    }
  ?>
  </ul>
</div>

<div class="border-top pt-3">
  <form method="POST" class="row g-2">
      <div class="col-auto flex-grow-1">
        <input type="text" name="client_name" class="form-control" placeholder="Add new client..." required>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary btn-sm">Add Client</button>
      </div>
  </form>
</div>
<script>
document.querySelectorAll('.rename-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const current = btn.dataset.name;
    const name = prompt('Enter new client name', current);
    if (name && name.trim() !== '' && name !== current) {
      const form = document.createElement('form');
      form.method = 'POST';
      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'rename_client';
      idInput.value = id;
      form.appendChild(idInput);
      const nameInput = document.createElement('input');
      nameInput.type = 'hidden';
      nameInput.name = 'new_name';
      nameInput.value = name;
      form.appendChild(nameInput);
      document.body.appendChild(form);
      form.submit();
    }
  });
});
</script>

<?php include 'footer.php'; ?>
