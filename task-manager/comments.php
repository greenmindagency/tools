<?php
// Display existing comments and provide a form to add new ones.
// Expects $t array with task info and session user.

$cstmt = $pdo->prepare('SELECT c.id, c.content, u.username FROM comments c JOIN users u ON c.user_id=u.id WHERE c.task_id=? ORDER BY c.created_at');
$cstmt->execute([$t['id']]);
$comments = $cstmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php if ($comments): ?>
  <div class="mb-2">
    <?php foreach ($comments as $cm): ?>
      <div class="mb-2">
        <strong><?= htmlspecialchars($cm['username']) ?>:</strong>
        <?= nl2br(htmlspecialchars($cm['content'])) ?>
        <?php
          $fstmt = $pdo->prepare('SELECT file_path, original_name FROM comment_files WHERE comment_id=?');
          $fstmt->execute([$cm['id']]);
          $files = $fstmt->fetchAll(PDO::FETCH_ASSOC);
          if ($files): ?>
          <ul class="list-inline small mb-0 mt-1">
            <?php foreach ($files as $f): ?>
            <li class="list-inline-item"><a href="uploads/<?= htmlspecialchars($f['file_path']) ?>" target="_blank"><?= htmlspecialchars($f['original_name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<form method="post" class="ajax mt-2" enctype="multipart/form-data">
  <input type="hidden" name="add_comment" value="<?= $t['id'] ?>">
  <div class="mb-2"><textarea name="comment" class="form-control form-control-sm" required></textarea></div>
  <div class="mb-2"><input type="file" name="files[]" multiple class="form-control form-control-sm"></div>
  <button type="submit" class="btn btn-secondary btn-sm">Comment</button>
</form>
