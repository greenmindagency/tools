<?php
session_start();
$error='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $hash = '$2y$10$DrSjBqdNtRRd9/r8JpIpse5Bgot9hLnKHsZrIGSTSUlkcL1RhXPSG';
    if ($user === 'greenmindagency' && password_verify($pass, $hash)) {
        $_SESSION['is_admin'] = true;
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
