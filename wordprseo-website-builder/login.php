<?php
session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    $hash = '$2y$10$DrSjBqdNtRRd9/r8JpIpse5Bgot9hLnKHsZrIGSTSUlkcL1RhXPSG';
    if ($user === 'greenmindagency' && password_verify($pass, $hash)) {
        $_SESSION['user'] = $user;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
$title = 'Login';
require __DIR__ . '/../header.php';
?>
<h1 class="mb-4">Login</h1>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form method="post" class="mx-auto" style="max-width:320px">
  <div class="mb-3">
    <label class="form-label">Username</label>
    <input type="text" name="username" class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Password</label>
    <input type="password" name="password" class="form-control" required>
  </div>
  <button type="submit" class="btn btn-primary w-100">Login</button>
</form>
<?php include __DIR__ . '/../footer.php'; ?>
