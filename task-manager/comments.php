<?php
// Display existing comments and provide a form to add new ones.
// Expects $t array with task info and session user.

$cstmt = $pdo->prepare('SELECT c.id, c.content, c.user_id, u.username FROM comments c JOIN users u ON c.user_id=u.id WHERE c.task_id=? ORDER BY c.created_at');
$cstmt->execute([$t['id']]);
$comments = $cstmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="comments-wrapper">
  <div class="comments-list mb-2">
    <?php foreach ($comments as $cm): ?>
      <div class="mb-2 comment-item" data-id="<?= $cm['id'] ?>">
        <span class="comment-text"><strong><?= htmlspecialchars($cm['username']) ?>:</strong> <?= nl2br(htmlspecialchars($cm['content'])) ?></span>
        <?php if ($cm['user_id'] == $_SESSION['user_id']): ?>
        <span class="float-end ms-2">
          <a href="#" class="text-decoration-none text-muted edit-comment" data-id="<?= $cm['id'] ?>" data-content="<?= htmlspecialchars($cm['content'], ENT_QUOTES) ?>"><i class="bi bi-pencil"></i></a>
          <a href="#" class="text-decoration-none text-danger ms-2 delete-comment" data-id="<?= $cm['id'] ?>"><i class="bi bi-trash"></i></a>
        </span>
        <?php endif; ?>
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
  <form method="post" class="ajax mt-2" enctype="multipart/form-data">
    <input type="hidden" name="add_comment" value="<?= $t['id'] ?>">
    <div class="mb-2 position-relative">
      <textarea name="comment" class="form-control form-control-sm ps-4 pe-5" placeholder="Comment now" required></textarea>
      <input type="file" name="files[]" multiple class="d-none" id="file-<?= $t['id'] ?>">
      <i class="bi bi-paperclip position-absolute top-0 start-0 m-1 upload-trigger" data-target="file-<?= $t['id'] ?>" style="cursor:pointer;"></i>
      <button type="submit" class="btn p-0 border-0 position-absolute bottom-0 end-0 m-1 text-secondary"><i class="bi bi-arrow-right-circle"></i></button>
    </div>
  </form>
</div>
