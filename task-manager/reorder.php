<?php
require __DIR__ . '/config.php';
$pdo = get_pdo();
$order = $_POST['order'] ?? '';
$stmt = $pdo->prepare('UPDATE tasks SET order_index=? WHERE id=?');
foreach (explode(',', $order) as $pair) {
    if (!$pair) continue;
    [$id, $idx] = explode(':', $pair);
    $stmt->execute([(int)$idx, (int)$id]);
}
$redirect = $_POST['redirect'] ?? 'alltasks.php';
$redirect .= (strpos($redirect, '?') === false ? '?' : '&') . 'saved=1';
header('Location: ' . $redirect);
exit;
