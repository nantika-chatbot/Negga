<?php
require_once __DIR__ . '/includes/auth.php';

$isAdmin = is_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$isAdmin) {
        flash('error', 'คุณไม่มีสิทธิ์ดำเนินการนี้');
        redirect('products.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_quick') {
        $stmt = $pdo->prepare('UPDATE products SET cost_price = :cost_price, vat_amount = :vat_amount, price = :price, min_stock = :min_stock, stock_qty = :stock_qty, status = :status WHERE id = :id');
        $stmt->execute([
            'cost_price' => (float)$_POST['cost_price'],
            'vat_amount' => (float)$_POST['vat_amount'],
            'price' => (float)$_POST['price'],
            'min_stock' => (int)$_POST['min_stock'],
            'stock_qty' => (int)$_POST['stock_qty'],
            'status' => $_POST['status'] === 'inactive' ? 'inactive' : 'active',
            'id' => (int)$_POST['id'],
        ]);
        flash('success', 'อัปเดตสินค้าเรียบร้อยแล้ว');
        redirect('products.php');
    }

    if ($action === 'delete') {
        $product = get_product_by_id($pdo, (int)$_POST['id']);
        if ($product && !empty($product['image_path'])) {
            $file = __DIR__ . '/' . ltrim($product['image_path'], '/');
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => (int)$_POST['id']]);
        flash('success', 'ลบสินค้าเรียบร้อยแล้ว');
        redirect('products.php');
    }
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);

$products = get_products($pdo, $search, $status, $categoryId);
$categories = get_categories($pdo);

require __DIR__ . '/includes/header.php';
?>

<div class="card mb-4">
    <form class="toolbar" method="get">
        <div class="filters">
            <input class="input" type="text" name="search" placeholder="ค้นหาชื่อสินค้า, รหัสสินค้า หรือ Barcode" value="<?= e($search) ?>">
            <select class="select" name="status">
                <option value="">ทุกสถานะ</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>ใช้งาน</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option>
            </select>
            <select class="select" name="category_id">
                <option value="0">ทุกประเภท</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn primary" type="submit">กรอง</button>
            <a class="btn" href="products.php">รีเซ็ต</a>
        </div>

        <!-- 🔒 ปุ่มเพิ่มสินค้า: admin เท่านั้น -->
        <?php if ($isAdmin): ?>
            <a class="btn primary" href="product_create.php">+ เพิ่มสินค้าใหม่</a>
        <?php endif; ?>
    </form>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>รหัสสินค้า</th>
                <th>ชื่อสินค้า</th>
                <th>หน่วย</th>
                <th>ราคาก่อน VAT</th>
                <th>VAT 7%</th>
                <th>ราคาขาย</th>
                <th>สต็อกขั้นต่ำ</th>
                <th>คงเหลือ</th>
                <th>สถานะ</th>
                <th>จัดการ</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$products): ?>
            <tr><td colspan="10">ไม่พบข้อมูลสินค้า</td></tr>
        <?php endif; ?>

        <?php foreach ($products as $item): 
            $badge = product_status_badge((int)$item['stock_qty'], $item['status'], (int)$item['min_stock']); 
        ?>
            <tr>
                <td>
                    <div style="font-weight:700"><?= e($item['sku']) ?></div>
                    <div class="mini-text">Barcode: <?= e($item['barcode'] ?: '-') ?></div>
                </td>
                <td>
                    <div style="font-weight:700"><?= e($item['name']) ?></div>
                    <div class="mini-text">ประเภท: <?= e($item['category_name']) ?></div>
                </td>
                <td><?= e($item['unit_name']) ?></td>
                <td>฿<?= money($item['cost_price']) ?></td>
                <td>฿<?= money($item['vat_amount']) ?></td>
                <td>฿<?= money($item['price']) ?></td>
                <td><?= (int)$item['min_stock'] ?></td>
                <td><?= (int)$item['stock_qty'] ?></td>
                <td><span class="status <?= e($badge['class']) ?>"><?= e($badge['label']) ?></span></td>

                <td>
                    <?php if ($isAdmin): ?>
                        <div class="actions">
                            <a class="btn soft" href="product_edit.php?id=<?= (int)$item['id'] ?>">แก้ไข</a>

                            <form method="post" onsubmit="return confirm('ยืนยันการลบสินค้า?');" style="display:inline-flex;gap:8px">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button class="btn danger" type="submit">ลบ</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span style="color:#999;">ไม่มีสิทธิ์</span>
                    <?php endif; ?>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>