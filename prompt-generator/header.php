<?php
if (!isset($active)) $active = '';
?>
<nav class="navbar fixed-top navbar-expand-lg navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Green Mind</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link<?php echo $active === 'longtail' ? ' active' : ''; ?>" href="longtail-generator.php">Add Longtails</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php echo $active === 'keyword' ? ' active' : ''; ?>" href="keyword-structuring-tool.php">Keywords Breaker</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php echo $active === 'clustering' ? ' active' : ''; ?>" href="clustering.php">Clustering</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php echo $active === 'content' ? ' active' : ''; ?>" href="content-prompt-generator.php">Content Creation</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
