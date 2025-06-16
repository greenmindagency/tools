<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
$client_id = (int)($_GET['client_id'] ?? 0);
$search = trim($_GET['q'] ?? '');
$field = $_GET['field'] ?? 'keyword';

$allowed = ['keyword', 'group_name', 'cluster_name', 'content_link'];

if (!in_array($field, $allowed, true)) {
    $field = 'keyword';
}

$perPage = 100;
$page = max(1, (int)($_GET['page'] ?? 1));

$query = "SELECT * FROM keywords WHERE client_id = ?";
$params = [$client_id];
if ($search !== '') {
    $query .= " AND {$field} LIKE ?";
    $params[] = "%$search%";
}
$countStmt = $pdo->prepare(str_replace('*', 'COUNT(*)', $query));
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = $search === '' ? (int)ceil($total / $perPage) : 1;
if ($search === '') {
    $offset = ($page - 1) * $perPage;
    $query .= " ORDER BY volume DESC, form ASC LIMIT $perPage OFFSET $offset";
} else {
    $query .= " ORDER BY volume DESC, form ASC";
}
$stmt = $pdo->prepare($query);
$stmt->execute($params);

$rows = '';
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $volume = $row['volume'];
    $form   = $row['form'];
    $formBg = '';
    if (is_numeric($form)) {
        $f = (int)$form;
        if ($f < 33) {
            $formBg = '#beddce';
        } elseif ($f < 66) {
            $formBg = '#f9e9b4';
        } else {
            $formBg = '#f5c6c2';
        }
    }
    $volBg = '';
    if (is_numeric($volume)) {
        $v = (int)$volume;
        if ($v >= 5000000) {
            $volBg = '#9b9797';
        } elseif ($v >= 500000) {
            $volBg = '#b9b3c4';
        } elseif ($v >= 50000) {
            $volBg = '#cccccd';
        } elseif ($v >= 5000) {
            $volBg = '#d7dad9';
        } elseif ($v >= 500) {
            $volBg = '#f2ecf0';
        } else {
            $volBg = '';
        }
    }
    $rows .= "<tr data-id='{$row['id']}'>";
    $rows .= "<td class='text-center'><button type='button' class='btn btn-sm btn-outline-danger remove-row'>-</button></td>";
    $rows .= "<td>" . htmlspecialchars($row['keyword']) . "</td>";
    $rows .= "<td class='text-center' style='background-color: $volBg'>" . $volume . "</td>";
    $rows .= "<td class='text-center' style='background-color: $formBg'>" . $form . "</td>";
    $rows .= "<td><input type='text' name='link[{$row['id']}]' value='" . htmlspecialchars($row['content_link']) . "' class='form-control form-control-sm' style='max-width:200px;'></td>";
    $rows .= "<td class='text-center'>" . htmlspecialchars($row['page_type']) . "</td>";
    $rows .= "<td>" . htmlspecialchars($row['group_name']) . "</td>";
    $rows .= "<td class='text-center'>" . $row['group_count'] . "</td>";
    $rows .= "<td>" . htmlspecialchars($row['cluster_name']) . "</td>";
    $rows .= "</tr>";
}

$pagination = '';
if ($search === '' && $totalPages > 1) {
    $pagination .= '<ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $page ? ' active' : '';
        $pagination .= "<li class='page-item$active'><a class='page-link' href='#' data-page='$i'>$i</a></li>";
    }
    $pagination .= '</ul>';
}

echo json_encode(['rows' => $rows, 'pagination' => $pagination]);

