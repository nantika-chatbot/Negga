<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

if ($q === '') {
    echo json_encode([]);
    exit;
}

$customerColumns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
$hasMemberCode = in_array('member_code', $customerColumns, true);

if ($hasMemberCode) {
    $stmt = $pdo->prepare("
        SELECT id, member_code, name, phone
        FROM customers
        WHERE member_code LIKE :q
           OR name LIKE :q
           OR phone LIKE :q
        ORDER BY id DESC
        LIMIT 10
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT id, '' AS member_code, name, phone
        FROM customers
        WHERE name LIKE :q
           OR phone LIKE :q
        ORDER BY id DESC
        LIMIT 10
    ");
}

$stmt->execute(['q' => '%' . $q . '%']);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
exit;