<?php
session_start();
require __DIR__ . '/config.php';
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    core_text LONGTEXT,
    instructions TEXT,
    sitemap TEXT
)");

$defaultInstr = <<<TXT
in wordprseo already we have below:

- pages: home, about, careers, contact us
- tags (sub service only)
- category (latest work, clients, blog, news) any thing that we can attach singles too
- single: a case studies or blog pages, news etc..

so you have to follow the above instructions to make the new sitemapmap, no need to copy past but to have the instructions.

important notes:

- sitemapmap starting with Home and About us
- sitemapmap last item will be Contact us

you can't merge to categories in one menu items, like News & Blog, it has to be seperated we can have something like insights and under it blog and highlights for example but not to merge both 

make the sitemapmap items from 1 to 3 words max not more than that.

try to make an online reasearch in the same filed to find the best menu items it should be, with ofcourse checking the materials we uploaded.


TXT;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['client_name'])) {
        $name = trim($_POST['client_name']);
        if ($name !== '') {
            $stmt = $pdo->prepare("INSERT INTO clients (name, core_text, instructions, sitemap) VALUES (?,?,?,?)");
            $stmt->execute([$name, '', $defaultInstr, '']);
        }
        header('Location: index.php');
        exit;
    }
    if (isset($_POST['delete_client'])) {
        $id = (int)$_POST['delete_client'];
        $pdo->prepare("DELETE FROM clients WHERE id=?")->execute([$id]);
        header('Location: index.php');
        exit;
    }
}

$title = 'Wordprseo Builder Clients';
require __DIR__ . '/../header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="mb-0">Select a Client</h5>
  <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
</div>
<ul class="list-group mb-4">
<?php
$stmt = $pdo->query('SELECT * FROM clients ORDER BY name ASC');
foreach ($stmt as $client) {
    $id = $client['id'];
    $name = htmlspecialchars($client['name']);
    echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
    echo "<span class='me-auto'>$name</span>";
    echo "<a class='btn btn-sm btn-outline-primary me-2' href='sitemap.php?client_id=$id'>Sitemap</a>";
    echo "<a class='btn btn-sm btn-outline-secondary me-2' href='builder.php?client_id=$id'>Content</a>";
    echo "<form method='POST' class='d-inline ms-1' onsubmit=\"return confirm('Delete this client?');\">";
    echo "<input type='hidden' name='delete_client' value='$id'>";
    echo "<button class='btn btn-sm btn-outline-danger'>Remove</button>";
    echo "</form></li>";
}
?>
</ul>
<h5>Add New Client</h5>
<form method="post" class="mb-5">
  <div class="mb-3">
    <label class="form-label">Client Name</label>
    <input type="text" name="client_name" class="form-control" required>
  </div>
  <button type="submit" class="btn btn-primary">Add Client</button>
</form>
<?php include __DIR__ . '/../footer.php'; ?>
