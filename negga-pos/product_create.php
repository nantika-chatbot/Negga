<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$categories = get_categories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $imagePath = upload_product_image($_FILES['image'] ?? []);

        $vatRate = (float)($_POST['vat_rate'] ?? 7);
        $sellPrice = (float)($_POST['price'] ?? 0);

        if ($sellPrice < 0) {
            throw new Exception('ราคาขายต้องไม่ติดลบ');
        }

        if ($vatRate < 0) {
            throw new Exception('VAT ต้องไม่ติดลบ');
        }

        // คำนวณย้อนจากราคาขายรวม VAT
        if ($vatRate > 0) {
            $costPrice = round($sellPrice / (1 + ($vatRate / 100)), 2);
            $vatAmount = round($sellPrice - $costPrice, 2);
        } else {
            $costPrice = round($sellPrice, 2);
            $vatAmount = 0.00;
        }

        $stmt = $pdo->prepare('
            INSERT INTO products (
                sku, barcode, name, unit_name, category_id,
                cost_price, vat_rate, vat_amount, price,
                min_stock, stock_qty, image_path, status
            ) VALUES (
                :sku, :barcode, :name, :unit_name, :category_id,
                :cost_price, :vat_rate, :vat_amount, :price,
                :min_stock, :stock_qty, :image_path, :status
            )
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
        ]);

        flash('success', 'เพิ่มสินค้าใหม่เรียบร้อยแล้ว');
        redirect('products.php');
        exit;
    } catch (Throwable $e) {
        flash('error', 'บันทึกสินค้าไม่สำเร็จ: ' . $e->getMessage());
        redirect('product_create.php');
        exit;
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <form method="post" enctype="multipart/form-data" class="form-grid form-grid-2">
        <div class="form-group">
            <label>รหัสสินค้า</label>
            <input class="input" name="sku" required>
        </div>

        <div class="form-group">
            <label>Barcode</label>
            <input class="input" name="barcode">
        </div>

        <div class="form-group">
            <label>ชื่อสินค้า</label>
            <input class="input" name="name" required>
        </div>

        <div class="form-group">
            <label>หน่วย</label>
            <input class="input" name="unit_name" placeholder="เช่น กล่อง, ขวด, ตัว" required>
        </div>

        <div class="form-group">
            <label>ประเภท</label>
            <select class="select" name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>VAT (%)</label>
            <input class="input" type="number" step="0.01" min="0" name="vat_rate" value="7.00" required>
        </div>

        <div class="form-group">
            <label>ราคาขาย (รวม VAT แล้ว)</label>
            <input class="input" type="number" step="0.01" min="0" name="price" required>
        </div>

        <div class="form-group">
            <label>สต็อกขั้นต่ำ</label>
            <input class="input" type="number" min="0" name="min_stock" value="100" required>
        </div>

        <div class="form-group">
            <label>คงเหลือ</label>
            <input class="input" type="number" min="0" name="stock_qty" value="100" required>
        </div>

        <div class="form-group">
            <label>รูปสินค้า</label>
            <input class="input" style="padding:10px" type="file" accept="image/*" name="image">
        </div>

        <div class="form-group">
            <label>สถานะ</label>
            <select class="select" name="status">
                <option value="active">ใช้งาน</option>
                <option value="inactive">ปิดใช้งาน</option>
            </select>
        </div>

        <div class="form-group full-row notes-box">
            <div class="mini-text">
                กรุณากรอก “ราคาขายรวม VAT แล้ว” ระบบจะคำนวณราคาก่อน VAT และมูลค่า VAT ให้โดยอัตโนมัติ
            </div>
        </div>

        <div class="actions full-row">
            <a class="btn" href="products.php">ย้อนกลับ</a>
            <button class="btn primary" type="submit">บันทึกสินค้า</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>