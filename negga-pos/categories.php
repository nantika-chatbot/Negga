<?php
require_once __DIR__ . '/includes/auth.php';

$isAdmin = (($_SESSION['user']['role'] ?? '') === 'admin');

function category_column_exists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'categories'
          AND COLUMN_NAME = :column
    ");
    $stmt->execute(['column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

$hasShippingFee = category_column_exists($pdo, 'shipping_fee');
$hasFreeShippingMin = category_column_exists($pdo, 'free_shipping_min');
$hasEnableShippingRule = category_column_exists($pdo, 'enable_shipping_rule');

$editingId = (int)($_GET['edit'] ?? 0);
$editingCategory = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        flash('error', 'คุณไม่มีสิทธิ์จัดการประเภทสินค้า');
        redirect('categories.php');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $enableShippingRule = !empty($_POST['enable_shipping_rule']) ? 1 : 0;
        $shippingFee = max(0, (float)($_POST['shipping_fee'] ?? 0));
        $freeShippingMin = max(0, (float)($_POST['free_shipping_min'] ?? 0));

        if ($name === '') {
            flash('error', 'กรุณากรอกชื่อประเภทสินค้า');
            redirect('categories.php');
        }

        if ($hasShippingFee && $hasFreeShippingMin && $hasEnableShippingRule) {
            $stmt = $pdo->prepare("
                INSERT INTO categories (name, shipping_fee, free_shipping_min, enable_shipping_rule)
                VALUES (:name, :shipping_fee, :free_shipping_min, :enable_shipping_rule)
            ");
            $stmt->execute([
                'name' => $name,
                'shipping_fee' => $shippingFee,
                'free_shipping_min' => $freeShippingMin,
                'enable_shipping_rule' => $enableShippingRule,
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO categories (name)
                VALUES (:name)
            ");
            $stmt->execute(['name' => $name]);
        }

        flash('success', 'เพิ่มประเภทสินค้าเรียบร้อยแล้ว');
        redirect('categories.php');
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $enableShippingRule = !empty($_POST['enable_shipping_rule']) ? 1 : 0;
        $shippingFee = max(0, (float)($_POST['shipping_fee'] ?? 0));
        $freeShippingMin = max(0, (float)($_POST['free_shipping_min'] ?? 0));

        if ($id <= 0 || $name === '') {
            flash('error', 'ข้อมูลไม่ถูกต้อง');
            redirect('categories.php');
        }

        if ($hasShippingFee && $hasFreeShippingMin && $hasEnableShippingRule) {
            $stmt = $pdo->prepare("
                UPDATE categories
                SET name = :name,
                    shipping_fee = :shipping_fee,
                    free_shipping_min = :free_shipping_min,
                    enable_shipping_rule = :enable_shipping_rule
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'shipping_fee' => $shippingFee,
                'free_shipping_min' => $freeShippingMin,
                'enable_shipping_rule' => $enableShippingRule,
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE categories
                SET name = :name
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $id,
                'name' => $name,
            ]);
        }

        flash('success', 'แก้ไขประเภทสินค้าเรียบร้อยแล้ว');
        redirect('categories.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            flash('error', 'ข้อมูลไม่ถูกต้อง');
            redirect('categories.php');
        }

        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
        $checkStmt->execute(['id' => $id]);
        $productCount = (int)$checkStmt->fetchColumn();

        if ($productCount > 0) {
            flash('error', 'ไม่สามารถลบประเภทสินค้าได้ เนื่องจากยังมีสินค้าอยู่ในประเภทนี้');
            redirect('categories.php');
        }

        $deleteStmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $deleteStmt->execute(['id' => $id]);

        flash('success', 'ลบประเภทสินค้าเรียบร้อยแล้ว');
        redirect('categories.php');
    }
}

if ($editingId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $editingId]);
    $editingCategory = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editingCategory) {
        flash('error', 'ไม่พบประเภทสินค้าที่ต้องการแก้ไข');
        redirect('categories.php');
    }
}

$categories = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
    FROM categories c
    ORDER BY c.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>

<div class="page-title"></div>

<div class="two-col" style="align-items:start;">
<div class="card">
    <?php if ($isAdmin): ?>
        <?php if ($editingCategory): ?>
            <h3 style="margin-top:0">แก้ไขประเภทสินค้า</h3>
            <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$editingCategory['id'] ?>">

                <div class="form-group">
                    <label>ชื่อประเภทสินค้า</label>
                    <input
                        class="input w-full"
                        type="text"
                        name="name"
                        value="<?= e($editingCategory['name']) ?>"
                        required
                    >
                </div>

                <?php if ($hasShippingFee && $hasFreeShippingMin && $hasEnableShippingRule): ?>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px;">
                            <input
                                type="checkbox"
                                name="enable_shipping_rule"
                                value="1"
                                <?= !empty($editingCategory['enable_shipping_rule']) ? 'checked' : '' ?>
                            >
                            เปิดใช้กฎค่าส่งสำหรับประเภทนี้
                        </label>
                    </div>

                    <div class="form-group">
                        <label>ค่าส่ง (บาท)</label>
                        <input
                            class="input w-full"
                            type="number"
                            step="0.01"
                            min="0"
                            name="shipping_fee"
                            value="<?= e($editingCategory['shipping_fee'] ?? '0') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>ยอดขั้นต่ำส่งฟรี (บาท)</label>
                        <input
                            class="input w-full"
                            type="number"
                            step="0.01"
                            min="0"
                            name="free_shipping_min"
                            value="<?= e($editingCategory['free_shipping_min'] ?? '0') ?>"
                        >
                    </div>
                <?php endif; ?>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button class="btn primary" type="submit">บันทึกการแก้ไข</button>
                    <a class="btn" href="categories.php">ยกเลิก</a>
                </div>
            </form>
        <?php else: ?>
            <h3 style="margin-top:0">เพิ่มประเภทสินค้า</h3>
            <form method="post">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label>ชื่อประเภทสินค้า</label>
                    <input
                        class="input w-full"
                        type="text"
                        name="name"
                        placeholder="ชื่อประเภทสินค้า"
                        required
                    >
                </div>

                <?php if ($hasShippingFee && $hasFreeShippingMin && $hasEnableShippingRule): ?>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="enable_shipping_rule" value="1">
                            เปิดใช้กฎค่าส่งสำหรับประเภทนี้
                        </label>
                    </div>

                    <div class="form-group">
                        <label>ค่าส่ง (บาท)</label>
                        <input
                            class="input w-full"
                            type="number"
                            step="0.01"
                            min="0"
                            name="shipping_fee"
                            value="0"
                        >
                    </div>

                    <div class="form-group">
                        <label>ยอดขั้นต่ำส่งฟรี (บาท)</label>
                        <input
                            class="input w-full"
                            type="number"
                            step="0.01"
                            min="0"
                            name="free_shipping_min"
                            value="0"
                        >
                    </div>
                <?php endif; ?>

                <button class="btn primary" type="submit">เพิ่ม</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <h3 style="margin-top:0">จัดการประเภทสินค้า</h3>
        <div class="mini-text">บัญชีพนักงานสามารถดูข้อมูลได้อย่างเดียว</div>
    <?php endif; ?>
</div>

    <div class="card">
        <h3 style="margin-top:0">รายการประเภทสินค้า</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ชื่อ</th>
                        <?php if ($hasEnableShippingRule): ?>
                            <th>กฎค่าส่ง</th>
                        <?php endif; ?>
                        <?php if ($hasShippingFee): ?>
                            <th>ค่าส่ง</th>
                        <?php endif; ?>
                        <?php if ($hasFreeShippingMin): ?>
                            <th>ขั้นต่ำส่งฟรี</th>
                        <?php endif; ?>
                        <th>จำนวนสินค้า</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= (int)$category['id'] ?></td>
                            <td><?= e($category['name']) ?></td>

                            <?php if ($hasEnableShippingRule): ?>
                                <td>
                                    <?= !empty($category['enable_shipping_rule']) ? 'เปิดใช้' : 'ปิด' ?>
                                </td>
                            <?php endif; ?>

                            <?php if ($hasShippingFee): ?>
                                <td>฿<?= number_format((float)($category['shipping_fee'] ?? 0), 2) ?></td>
                            <?php endif; ?>

                            <?php if ($hasFreeShippingMin): ?>
                                <td>฿<?= number_format((float)($category['free_shipping_min'] ?? 0), 2) ?></td>
                            <?php endif; ?>

                            <td><?= (int)$category['product_count'] ?></td>
                            <td>
                                <?php if ($isAdmin): ?>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                        <a class="btn" href="categories.php?edit=<?= (int)$category['id'] ?>">แก้ไข</a>

                                        <form method="post" onsubmit="return confirm('ยืนยันการลบประเภทสินค้านี้?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                            <button class="btn danger" type="submit">ลบ</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="mini-text">ดูได้อย่างเดียว</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$categories): ?>
                        <tr>
                            <td colspan="<?= ($hasEnableShippingRule ? 1 : 0) + ($hasShippingFee ? 1 : 0) + ($hasFreeShippingMin ? 1 : 0) + 4 ?>" class="text-center">
                                ไม่พบข้อมูลประเภทสินค้า
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>