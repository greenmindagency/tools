<?php
session_start();
if (!($_SESSION['is_admin'] ?? false) && empty($_SESSION['client_ids']) && !isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}
$slugify = function(string $name): string {
    $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);
    $name = preg_replace('/[^a-zA-Z0-9]+/', '-', $name);
    return strtolower(trim($name, '-'));
};
require 'config.php';
$pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS username VARCHAR(255)");
$pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS pass_hash VARCHAR(255)");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['is_admin'] ?? false)) {
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

    if (isset($_POST['set_credentials'], $_POST['username'])) {
        $cid = (int)$_POST['set_credentials'];
        $username = trim($_POST['username']);
        $password = $_POST['password'] ?? '';
        $fields = [];
        $params = [];
        if ($username !== '') {
            $fields[] = 'username = ?';
            $params[] = $username;
        }
        if ($password !== '') {
            $fields[] = 'pass_hash = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($fields) {
            $params[] = $cid;
            $sql = 'UPDATE clients SET '.implode(',', $fields).' WHERE id = ?';
            $pdo->prepare($sql)->execute($params);
        }
        header('Location: index.php');
        exit;
    }
}

$title = 'SEO Platform';
include 'header.php';
?>

<div class="justify-content-between align-items-center mb-4">

<div class="d-flex d-flex justify-content-between align-items-center mb-4">
  <h5 class="mb-0">Select a Client</h5>
  <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
  </div>
  
  <ul class="list-group">
  <?php
  if ($_SESSION['is_admin'] ?? false) {
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY name ASC");
  } else {
    $ids = $_SESSION['client_ids'] ?? [$_SESSION['client_id']];
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id IN ($place) ORDER BY name ASC");
    $stmt->execute($ids);
  }
    foreach ($stmt as $client) {
        $id = $client['id'];
        $name = htmlspecialchars($client['name']);
        $slug = $slugify($client['name']);
        $escName = htmlspecialchars($client['name'], ENT_QUOTES);
        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
        echo "<a class='me-auto' href='dashboard.php?client_id=$id&slug=$slug'>$name</a>";
        if ($_SESSION['is_admin'] ?? false) {
            $user = htmlspecialchars($client['username'] ?? '', ENT_QUOTES);
            echo "<div class='btn-group btn-group-sm' role='group'>";
            echo "<button type='button' class='btn btn-outline-secondary rename-btn' data-id='$id' data-name='$escName'>Rename</button>";
            echo "<button type='button' class='btn btn-outline-primary cred-btn ms-1' data-id='$id' data-username='$user'>Set Login</button>";
            echo "<form method='POST' class='d-inline ms-1' onsubmit=\"return confirm('Delete this client and all data?');\">";
            echo "<input type='hidden' name='delete_client' value='$id'>";
            echo "<button type='submit' class='btn btn-outline-danger'>Remove</button>";
            echo "</form></div>";
        }
        echo "</li>";
    }
  ?>
  </ul>
</div>

<?php if ($_SESSION['is_admin'] ?? false): ?>
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
<?php endif; ?>
<?php if ($_SESSION['is_admin'] ?? false): ?>
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
document.querySelectorAll('.cred-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const currentUser = btn.dataset.username || '';
    const username = prompt('Username', currentUser);
    if (username === null) return;
    const password = prompt('Password (leave blank to keep current)');
    if (password === null) return;
    const form = document.createElement('form');
    form.method = 'POST';
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'set_credentials';
    idInput.value = id;
    form.appendChild(idInput);
    const userInput = document.createElement('input');
    userInput.type = 'hidden';
    userInput.name = 'username';
    userInput.value = username;
    form.appendChild(userInput);
    const passInput = document.createElement('input');
    passInput.type = 'hidden';
    passInput.name = 'password';
    passInput.value = password;
    form.appendChild(passInput);
    document.body.appendChild(form);
    form.submit();
});
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
