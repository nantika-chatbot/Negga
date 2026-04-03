<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$product = get_product_by_id($pdo, $id);

if (!$product) {
    flash('error', 'ไม่พบสินค้า');
    redirect('products.php');
}

$categories = get_categories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $imagePath = upload_product_image($_FILES['image'] ?? [], $product['image_path'] ?? null);

        $vatRate = (float)($_POST['vat_rate'] ?? 7);
        $sellPrice = (float)($_POST['price'] ?? 0);

        if ($sellPrice < 0) {
            throw new Exception('ราคาขายต้องไม่ติดลบ');
        }

        if ($vatRate < 0) {
            throw new Exception('VAT ต้องไม่ติดลบ');
        }

        // ✅ คำนวณย้อนจากราคาขายรวม VAT
        if ($vatRate > 0) {
            $costPrice = round($sellPrice / (1 + ($vatRate / 100)), 2);
            $vatAmount = round($sellPrice - $costPrice, 2);
        } else {
            $costPrice = round($sellPrice, 2);
            $vatAmount = 0.00;
        }

        $stmt = $pdo->prepare('
            UPDATE products SET
                sku = :sku,
                barcode = :barcode,
                name = :name,
                unit_name = :unit_name,
                category_id = :category_id,
                cost_price = :cost_price,
                vat_rate = :vat_rate,
                vat_amount = :vat_amount,
                price = :price,
                min_stock = :min_stock,
                stock_qty = :stock_qty,
                image_path = :image_path,
                status = :status
            WHERE id = :id
        ');

        $stmt->execute([
            'sku' => trim($_POST['sku']),
            'barcode' => trim($_POST['barcode']) !== '' ? trim($_POST['barcode']) : null,
            'name' => trim($_POST['name']),
            'unit_name' => trim($_POST['unit_name']) ?: 'ชิ้น',
            'category_id' => (int)$_POST['category_id'],
            'cost_price' => $costPrice,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'price' => round($sellPrice, 2),
            'min_stock' => (int)$_POST['min_stock'],
            'stock_qty' => (int)$_POST['stock_qty'],
            'image_path' => $imagePath,
            'status' => $_POST['status'] === 'inactive' ? 'inactive' : 'active',
            'id' => $id,
        ]);

        flash('success', 'แก้ไขสินค้าเรียบร้อยแล้ว');
        redirect('products.php');
        exit;

    } catch (Throwable $e) {
        flash('error', 'แก้ไขสินค้าไม่สำเร็จ: ' . $e->getMessage());
        redirect('product_edit.php?id=' . $id);
        exit;
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <form method="post" enctype="multipart/form-data" class="form-grid form-grid-2">
        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">

        <div class="form-group">
            <label>รหัสสินค้า</label>
            <input class="input" name="sku" value="<?= e($product['sku']) ?>" required>
        </div>

        <div class="form-group">
            <label>Barcode</label>
            <input class="input" name="barcode" value="<?= e($product['barcode'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>ชื่อสินค้า</label>
            <input class="input" name="name" value="<?= e($product['name']) ?>" required>
        </div>

        <div class="form-group">
            <label>หน่วย</label>
            <input class="input" name="unit_name" value="<?= e($product['unit_name']) ?>" required>
        </div>

        <div class="form-group">
            <label>ประเภท</label>
            <select class="select" name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$product['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>VAT (%)</label>
            <input class="input" type="number" step="0.01" min="0" name="vat_rate" value="<?= e($product['vat_rate']) ?>" required>
        </div>

        <div class="form-group">
            <label>ราคาขาย (รวม VAT แล้ว)</label>
            <input class="input" type="number" step="0.01" min="0" name="price" value="<?= e($product['price']) ?>" required>
        </div>

        <div class="form-group">
            <label>สต็อกขั้นต่ำ</label>
            <input class="input" type="number" min="0" name="min_stock" value="<?= e($product['min_stock']) ?>" required>
        </div>

        <div class="form-group">
            <label>คงเหลือ</label>
            <input class="input" type="number" min="0" name="stock_qty" value="<?= e($product['stock_qty']) ?>" required>
        </div>

        <div class="form-group">
            <label>รูปสินค้า</label>
            <input class="input" style="padding:10px" type="file" accept="image/*" name="image">
        </div>

        <div class="form-group">
            <label>สถานะ</label>
            <select class="select" name="status">
                <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>ใช้งาน</option>
                <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option>
            </select>
        </div>

        <div class="form-group full-row notes-box">
            <?php if (!empty($product['image_path'])): ?>
                <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>" class="product-thumb" style="width:84px;height:84px">
            <?php endif; ?>
            <div class="mini-text">
                กรุณาแก้ “ราคาขายรวม VAT” ระบบจะคำนวณราคาก่อน VAT และ VAT ให้อัตโนมัติ
            </div>
        </div>

        <div class="actions full-row">
            <a class="btn" href="products.php">ย้อนกลับ</a>
            <button class="btn primary" type="submit">บันทึกการแก้ไข</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>