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

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$productColumns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
$promotionColumns = $pdo->query("SHOW COLUMNS FROM promotions")->fetchAll(PDO::FETCH_COLUMN);

$productIdCol    = pick_first_existing($productColumns, ['id']);
$productNameCol  = pick_first_existing($productColumns, ['name', 'product_name', 'title']);
$productSkuCol   = pick_first_existing($productColumns, ['sku', 'code', 'product_code']);
$productUnitCol  = pick_first_existing($productColumns, ['unit']);
$productPriceCol = pick_first_existing($productColumns, ['price', 'sell_price', 'sale_price', 'selling_price', 'retail_price']);
$productStockCol = pick_first_existing($productColumns, ['stock', 'stock_qty', 'qty', 'quantity']);

if (!$productIdCol || !$productNameCol) {
    die('ไม่พบคอลัมน์หลักของตาราง products เช่น id หรือ name');
}

$selects = [
    "pr.id",
    "pr.name",
    "pr.promo_type",
    "pr.product_id",
    "pr.buy_qty",
    "pr.free_qty",
    "pr.start_date",
    "pr.end_date",
    "pr.status",
    "pr.note",
    "p.`{$productNameCol}` AS product_name",
];

if ($productSkuCol) {
    $selects[] = "p.`{$productSkuCol}` AS sku";
}
if ($productUnitCol) {
    $selects[] = "p.`{$productUnitCol}` AS unit";
}
if ($productPriceCol) {
    $selects[] = "p.`{$productPriceCol}` AS price";
}
if ($productStockCol) {
    $selects[] = "p.`{$productStockCol}` AS stock";
}

$sql = "
    SELECT " . implode(",\n           ", $selects) . "
    FROM promotions pr
    INNER JOIN products p ON p.`{$productIdCol}` = pr.product_id
    ORDER BY pr.id DESC
";

$stmt = $pdo->query($sql);
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <div>
            <h2 style="margin:0;">โปรโมชัน</h2>
            <div style="color:#666; margin-top:4px;">เพิ่ม แก้ไข ลบ และซื้อผ่านโปรโมชัน</div>
        </div>

        <div style="display:flex; gap:10px; align-items:center;">
            <a href="promotion_form.php" style="padding:10px 14px; background:#2563eb; color:#fff; text-decoration:none; border-radius:10px; font-weight:700;">
                + เพิ่มโปรโมชัน
            </a>
            <a href="pos.php" style="text-decoration:none; font-weight:600;">← กลับหน้า POS</a>
        </div>
    </div>

    <?php if ($success !== ''): ?>
        <div style="margin-bottom:16px; padding:12px 14px; border-radius:12px; background:#ecfdf5; color:#166534; border:1px solid #bbf7d0;">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div style="margin-bottom:16px; padding:12px 14px; border-radius:12px; background:#fef2f2; color:#b91c1c; border:1px solid #fecaca;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ชื่อโปรโมชัน</th>
                    <th>สินค้า</th>
                    <th class="text-center">รูปแบบ</th>
                    <th class="text-center">ช่วงเวลา</th>
                    <th class="text-center">สถานะ</th>
                    <th class="text-center">จัดการ</th>
                    <th class="text-center">ซื้อ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$promotions): ?>
                    <tr>
                        <td colspan="7">ยังไม่มีโปรโมชัน</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($promotions as $row): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700;"><?= e($row['name']) ?></div>
                                <?php if (!empty($row['note'])): ?>
                                    <div style="font-size:13px; color:#666; margin-top:4px;"><?= e($row['note']) ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div><?= e($row['product_name'] ?? '-') ?></div>
                                <div style="font-size:13px; color:#666;">
                                    SKU: <?= e($row['sku'] ?? '-') ?>
                                    <?php if (isset($row['unit'])): ?>
                                        | หน่วย: <?= e($row['unit'] ?? '-') ?>
                                    <?php endif; ?>
                                    <?php if (isset($row['price'])): ?>
                                        | ราคา: ฿<?= number_format((float)$row['price'], 2) ?>
                                    <?php endif; ?>
                                    <?php if (isset($row['stock'])): ?>
                                        | คงเหลือ: <?= (int)$row['stock'] ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="text-center">
                                ซื้อ <?= (int)$row['buy_qty'] ?> แถม <?= (int)$row['free_qty'] ?>
                            </td>

                            <td class="text-center">
                                <?= e($row['start_date'] ?: '-') ?> ถึง <?= e($row['end_date'] ?: '-') ?>
                            </td>

                            <td class="text-center">
                                <?php if (($row['status'] ?? '') === 'active'): ?>
                                    <span style="color:#166534; font-weight:700;">เปิดใช้งาน</span>
                                <?php else: ?>
                                    <span style="color:#991b1b; font-weight:700;">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                                    <a href="promotion_form.php?id=<?= (int)$row['id'] ?>" style="padding:8px 12px; border:1px solid #ddd; border-radius:10px; text-decoration:none;">
                                        แก้ไข
                                    </a>
                                    <a href="promotion_delete.php?id=<?= (int)$row['id'] ?>"
                                       onclick="return confirm('ยืนยันการลบโปรโมชันนี้?');"
                                       style="padding:8px 12px; border:1px solid #fecaca; color:#b91c1c; border-radius:10px; text-decoration:none;">
                                        ลบ
                                    </a>
                                </div>
                            </td>

                            <td class="text-center">
                                <?php if (($row['status'] ?? '') === 'active'): ?>
                                    <form method="get" action="pos.php" style="display:flex; gap:8px; align-items:center; justify-content:center;">
                                        <input type="hidden" name="promo_id" value="<?= (int)$row['id'] ?>">
                                        <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                                        <input type="hidden" name="buy_qty" value="<?= (int)$row['buy_qty'] ?>">
                                        <input type="hidden" name="free_qty" value="<?= (int)$row['free_qty'] ?>">

                                        <input
                                            type="number"
                                            name="set_qty"
                                            value="1"
                                            min="1"
                                            style="width:80px; padding:8px 10px; border:1px solid #ddd; border-radius:10px;"
                                        >

                                        <button
                                            type="submit"
                                            style="padding:9px 14px; background:#2563eb; color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:700;"
                                        >
                                            ซื้อโปรนี้
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#999;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>