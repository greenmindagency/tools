<?php
require 'config.php';
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
      echo "<li class='list-group-item'><a href='dashboard.php?client_id=$id'>$name</a></li>";
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
        <button type="submit" class="btn btn-primary">Add Client</button>
      </div>
  </form>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['client_name'];
    $insert = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
    $insert->execute([$name]);
    header("Location: index.php");
}

include 'footer.php';
?>
