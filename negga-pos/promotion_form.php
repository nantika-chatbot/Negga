<?php
require_once __DIR__ . '/includes/auth.php';

function pick_first_existing(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return null;
}

$id = (int)($_GET['id'] ?? 0);

$productColumns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);

$productIdCol    = pick_first_existing($productColumns, ['id']);
$productNameCol  = pick_first_existing($productColumns, ['name', 'product_name', 'title']);
$productSkuCol   = pick_first_existing($productColumns, ['sku', 'code', 'product_code']);
$productUnitCol  = pick_first_existing($productColumns, ['unit']);
$productPriceCol = pick_first_existing($productColumns, ['price', 'sell_price', 'sale_price', 'selling_price', 'retail_price']);

if (!$productIdCol || !$productNameCol) {
    die('ไม่พบคอลัมน์หลักของตาราง products');
}

$productSelects = [
    "`{$productIdCol}` AS id",
    "`{$productNameCol}` AS name",
];

if ($productSkuCol) {
    $productSelects[] = "`{$productSkuCol}` AS sku";
}
if ($productUnitCol) {
    $productSelects[] = "`{$productUnitCol}` AS unit";
}
if ($productPriceCol) {
    $productSelects[] = "`{$productPriceCol}` AS price";
}

$productsSql = "SELECT " . implode(', ', $productSelects) . " FROM products ORDER BY `{$productNameCol}` ASC";
$products = $pdo->query($productsSql)->fetchAll();

$promotion = [
    'id' => 0,
    'name' => '',
    'promo_type' => 'BUY_X_GET_Y',
    'product_id' => '',
    'buy_qty' => 5,
    'free_qty' => 1,
    'start_date' => '',
    'end_date' => '',
    'status' => 'active',
    'note' => '',
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $found = $stmt->fetch();

    if (!$found) {
        header('Location: promotions.php?error=' . urlencode('ไม่พบโปรโมชัน'));
        exit;
    }

    $promotion = $found;
}

require __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:900px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:10px;">
        <div>
            <h2 style="margin:0;"><?= $id > 0 ? 'แก้ไขโปรโมชัน' : 'เพิ่มโปรโมชัน' ?></h2>
            <div style="color:#666; margin-top:4px;">โปรโมชันแบบ ซื้อ X แถม Y</div>
        </div>
        <a href="promotions.php" style="text-decoration:none; font-weight:600;">← กลับหน้ารายการ</a>
    </div>

    <form method="post" action="promotion_save.php" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <input type="hidden" name="id" value="<?= (int)$promotion['id'] ?>">

        <div style="grid-column:1 / -1;">
            <label style="display:block; font-weight:700; margin-bottom:6px;">ชื่อโปรโมชัน</label>
            <input
                type="text"
                name="name"
                required
                value="<?= e($promotion['name']) ?>"
                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
            >
        </div>

        <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">สินค้า</label>
            <select
                name="product_id"
                required
                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
            >
                <option value="">-- เลือกสินค้า --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int)$product['id'] ?>" <?= (string)$promotion['product_id'] === (string)$product['id'] ? 'selected' : '' ?>>
                        <?= e($product['name']) ?>
                        <?php if (!empty($product['sku'])): ?> (<?= e($product['sku']) ?>)<?php endif; ?>
                        <?php if (isset($product['unit']) && $product['unit'] !== ''): ?> - <?= e($product['unit']) ?><?php endif; ?>
                        <?php if (isset($product['price'])): ?> - ฿<?= number_format((float)$product['price'], 2) ?><?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">ประเภทโปรโมชัน</label>
            <select
                name="promo_type"
                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
            >
                <option value="BUY_X_GET_Y" <?= $promotion['promo_type'] === 'BUY_X_GET_Y' ? 'selected' : '' ?>>ซื้อ X แถม Y</option>
            </select>
        </div>

        <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">จำนวนที่ต้องซื้อ</label>
            <input
                type="number"
                name="buy_qty"
                min="1"
                required
                value="<?= (int)$promotion['buy_qty'] ?>"
                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
            >
        </div>

        <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">จำนวนของแถม</label>
            <input
                type="number"
                name="free_qty"
                min="0"
                required
                value="<?= (int)$promotion['free_qty'] ?>"
                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
            >
        </div>

        <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">วันเริ่ม</label>
            <input
                type="date"
                name="start_date"
                value="<?= e($promotion['start_date']) ?>"
                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
            >
        </div>

        <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">วันสิ้นสุด</label>
            <input
                type="date"
                name="end_date"
                value="<?= e($promotion['end_date']) ?>"
                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
            >
        </div>

        <div>
            <label style="display:block; font-weight:700; margin-bottom:6px;">สถานะ</label>
            <select
                name="status"
                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
            >
                <option value="active" <?= $promotion['status'] === 'active' ? 'selected' : '' ?>>เปิดใช้งาน</option>
                <option value="inactive" <?= $promotion['status'] === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option>
            </select>
        </div>

        <div style="grid-column:1 / -1;">
            <label style="display:block; font-weight:700; margin-bottom:6px;">หมายเหตุ</label>
            <textarea
                name="note"
                rows="4"
                style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:10px;"
            ><?= e($promotion['note']) ?></textarea>
        </div>

        <div style="grid-column:1 / -1; display:flex; gap:10px;">
            <button
                type="submit"
                style="padding:11px 16px; background:#2563eb; color:#fff; border:none; border-radius:10px; font-weight:700; cursor:pointer;"
            >
                บันทึก
            </button>
            <a
                href="promotions.php"
                style="padding:11px 16px; border:1px solid #ddd; border-radius:10px; text-decoration:none;"
            >
                ยกเลิก
            </a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>