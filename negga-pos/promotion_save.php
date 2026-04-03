<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: promotions.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$promoType = trim($_POST['promo_type'] ?? 'BUY_X_GET_Y');
$productId = (int)($_POST['product_id'] ?? 0);
$buyQty = (int)($_POST['buy_qty'] ?? 0);
$freeQty = (int)($_POST['free_qty'] ?? 0);
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$status = trim($_POST['status'] ?? 'inactive');
$note = trim($_POST['note'] ?? '');

if ($name === '' || $productId <= 0 || $buyQty <= 0 || $freeQty < 0) {
    header('Location: promotions.php?error=' . urlencode('กรอกข้อมูลไม่ครบ'));
    exit;
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'inactive';
}

if ($startDate === '') $startDate = null;
if ($endDate === '') $endDate = null;

if ($id > 0) {
    $stmt = $pdo->prepare("
        UPDATE promotions SET
            name = :name,
            promo_type = :promo_type,
            product_id = :product_id,
            buy_qty = :buy_qty,
            free_qty = :free_qty,
            start_date = :start_date,
            end_date = :end_date,
            status = :status,
            note = :note
        WHERE id = :id
    ");
    $stmt->execute([
        ':name' => $name,
        ':promo_type' => $promoType,
        ':product_id' => $productId,
        ':buy_qty' => $buyQty,
        ':free_qty' => $freeQty,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
        ':status' => $status,
        ':note' => $note,
        ':id' => $id,
    ]);

    header('Location: promotions.php?success=' . urlencode('แก้ไขโปรโมชันเรียบร้อย'));
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO promotions (
        name, promo_type, product_id, buy_qty, free_qty, start_date, end_date, status, note
    ) VALUES (
        :name, :promo_type, :product_id, :buy_qty, :free_qty, :start_date, :end_date, :status, :note
    )
");
$stmt->execute([
    ':name' => $name,
    ':promo_type' => $promoType,
    ':product_id' => $productId,
    ':buy_qty' => $buyQty,
    ':free_qty' => $freeQty,
    ':start_date' => $startDate,
    ':end_date' => $endDate,
    ':status' => $status,
    ':note' => $note,
]);

header('Location: promotions.php?success=' . urlencode('เพิ่มโปรโมชันเรียบร้อย'));
exit;