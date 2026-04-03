<?php
require_once __DIR__ . '/includes/auth.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: promotions.php?error=' . urlencode('ข้อมูลไม่ถูกต้อง'));
    exit;
}

$stmt = $pdo->prepare("DELETE FROM promotions WHERE id = :id");
$stmt->execute([':id' => $id]);

header('Location: promotions.php?success=' . urlencode('ลบโปรโมชันเรียบร้อย'));
exit;