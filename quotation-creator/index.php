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
$clients = $pdo->query('SELECT id, name, published, slug FROM clients ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="mt-4">
<h1>Clients</h1>
<a href="builder.php" class="btn btn-primary btn-sm mb-3">Create New</a>
<table class="table table-bordered">
<tr><th>ID</th><th>Name</th><th>Published</th><th>Actions</th></tr>
<?php foreach($clients as $c): ?>
<tr>
<td><?= $c['id'] ?></td>
<td><?= htmlspecialchars($c['name']) ?></td>
<td><?= $c['published'] ? 'Yes' : 'No' ?></td>
<td>
<a href="builder.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
<?php if($c['published']): ?>
<a href="view.php?client=<?= urlencode($c['slug']) ?>" class="btn btn-sm btn-success" target="_blank">View</a>
<?php endif; ?>
<a href="index.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this client?');">Delete</a>
</td>
</tr>
<?php endforeach; ?>
</table>
<a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
</div>
<?php include 'footer.php'; ?>
