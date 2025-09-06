<?php
require_once __DIR__ . '/session.php';
require 'config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, name, username, pass_hash FROM clients WHERE username = ?');
    $stmt->execute([$user]);
    $matches = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (password_verify($pass, $row['pass_hash'])) {
            if (strcasecmp($row['username'], 'greenmindagency') === 0) {
                $_SESSION['is_admin'] = true;
                header('Location: index.php');
                exit;
            }
            $matches[] = $row;
        }
    }
    if (count($matches) === 1) {
        $client = $matches[0];
        $_SESSION['client_id'] = $client['id'];
        $name = $client['name'];
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8','ASCII//TRANSLIT',$name)), '-'));
        header("Location: content.php?client_id={$client['id']}&slug=$slug");
        exit;
    } elseif (count($matches) > 1) {
        $_SESSION['client_ids'] = array_column($matches, 'id');
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid username or password';
}
$title = 'Login';
include 'header.php';
?>
<div class="container" style="max-width:400px;">
  <?php if ($error): ?>
  <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" class="mt-4">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Login</button>
  </form>
</div>
<?php include 'footer.php'; ?>
