<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$promoId        = (int)($_GET['promo_id'] ?? 0);
$promoProductId = (int)($_GET['product_id'] ?? 0);
$promoBuyQty    = (int)($_GET['buy_qty'] ?? 0);
$promoFreeQty   = (int)($_GET['free_qty'] ?? 0);
$promoSetQty    = max(1, (int)($_GET['set_qty'] ?? 0));

if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_customer') {
    header('Content-Type: application/json; charset=utf-8');

    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
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
}

function calculateShipping(array $items, string $deliveryMethod = 'pickup'): float
{
    if ($deliveryMethod !== 'delivery') {
        return 0;
    }

    $categorySubtotal = [];
    $categoryRules = [];

    foreach ($items as $item) {
        $product = $item['product'];
        $categoryId = (int)($product['category_id'] ?? 0);

        if ($categoryId <= 0) {
            continue;
        }

        if (!isset($categorySubtotal[$categoryId])) {
            $categorySubtotal[$categoryId] = 0;
        }

        $categorySubtotal[$categoryId] += (float)$item['line_total'];

        if (!isset($categoryRules[$categoryId])) {
            $categoryRules[$categoryId] = [
                'enable_shipping_rule' => (int)($product['enable_shipping_rule'] ?? 0),
                'shipping_fee' => (float)($product['shipping_fee'] ?? 0),
                'free_shipping_min' => (float)($product['free_shipping_min'] ?? 0),
            ];
        }
    }

    $shipping = 0;

    foreach ($categorySubtotal as $categoryId => $subtotal) {
        $rule = $categoryRules[$categoryId] ?? null;

        if (!$rule || (int)$rule['enable_shipping_rule'] !== 1) {
            continue;
        }

        $freeMin = (float)$rule['free_shipping_min'];
        $fee = (float)$rule['shipping_fee'];

        if ($subtotal > 0 && $subtotal < $freeMin) {
            $shipping += $fee;
        }
    }

    return $shipping;
}

function calculatePromoChargeQty(int $qty, int $promoProductId, int $currentProductId, int $buyQty, int $freeQty): int
{
    if ($qty <= 0) {
        return 0;
    }

    if ($promoProductId <= 0 || $currentProductId !== $promoProductId) {
        return $qty;
    }

    $setQty = $buyQty + $freeQty;
    if ($setQty <= 0 || $buyQty <= 0) {
        return $qty;
    }

    $fullSets = intdiv($qty, $setQty);
    $remain = $qty % $setQty;

    return ($fullSets * $buyQty) + min($remain, $buyQty);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    $productIds = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $deliveryMethod = $_POST['delivery_method'] ?? 'pickup';
    $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $note = trim($_POST['note'] ?? '');
    $cashReceived = (float)($_POST['cash_received'] ?? $_POST['cash_received_sync'] ?? 0);
    $discountAmount = (float)($_POST['discount_amount'] ?? 0);

    $postedPromoId        = (int)($_POST['promo_id'] ?? 0);
    $postedPromoProductId = (int)($_POST['promo_product_id'] ?? 0);
    $postedPromoBuyQty    = (int)($_POST['promo_buy_qty'] ?? 0);
    $postedPromoFreeQty   = (int)($_POST['promo_free_qty'] ?? 0);

    if ($discountAmount < 0) {
        $discountAmount = 0;
    }

    $items = [];
    $grossSubtotal = 0.00;
    $beforeVat = 0.00;
    $vatAmount = 0.00;

    foreach ($productIds as $index => $productId) {
        $productId = (int)$productId;
        $qty = max(0, (int)($qtys[$index] ?? 0));

        if ($productId <= 0 || $qty <= 0) {
            continue;
        }

        $stmt = $pdo->prepare('
            SELECT 
                p.*,
                c.name AS category_name,
                c.shipping_fee,
                c.free_shipping_min,
                c.enable_shipping_rule
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE p.id = :id AND p.status = "active"
            LIMIT 1
        ');
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            continue;
        }

        if ($qty > (int)$product['stock_qty']) {
            flash('error', 'สต็อกไม่พอสำหรับสินค้า: ' . $product['name']);
            redirect('pos.php');
            exit;
        }

        $chargeQty = calculatePromoChargeQty(
            $qty,
            $postedPromoProductId,
            $productId,
            $postedPromoBuyQty,
            $postedPromoFreeQty
        );

        $lineTotal = $chargeQty * (float)$product['price'];
        $lineBeforeVat = $chargeQty * (float)$product['cost_price'];
        $lineVat = $chargeQty * (float)$product['vat_amount'];

        $grossSubtotal += $lineTotal;
        $beforeVat += $lineBeforeVat;
        $vatAmount += $lineVat;

        $items[] = [
            'product' => $product,
            'qty' => $qty,
            'charge_qty' => $chargeQty,
            'line_total' => round($lineTotal, 2),
            'line_before_vat' => round($lineBeforeVat, 2),
            'line_vat' => round($lineVat, 2),
        ];
    }

    if (!$items) {
        flash('error', 'กรุณาเลือกรายการสินค้าอย่างน้อย 1 รายการ');
        redirect('pos.php');
        exit;
    }

    $shipping = calculateShipping($items, $deliveryMethod);

    $beforeVat = round($beforeVat, 2);
    $vatAmount = round($vatAmount, 2);
    $grossSubtotal = round($grossSubtotal, 2);

    $grandTotal = round($grossSubtotal + $shipping - $discountAmount, 2);
    if ($grandTotal < 0) {
        $grandTotal = 0;
    }

    if ($paymentMethod === 'cash') {
        if ($cashReceived < $grandTotal) {
            flash('error', 'จำนวนเงินที่รับมาไม่พอ');
            redirect('pos.php');
            exit;
        }
        $changeAmount = round($cashReceived - $grandTotal, 2);
    } else {
        $cashReceived = $grandTotal;
        $changeAmount = 0.00;
    }

    $saleNo = generate_sale_no($pdo);
    $saleNo = preg_replace('/^SALE/i', 'EG', $saleNo);

    $customerSnapshotName = 'ลูกค้าทั่วไป';
    $customerSnapshotPhone = '-';
    $customerSnapshotAddress = '-';

    if ($customerId) {
        $stmtCustomer = $pdo->prepare("
            SELECT name, phone, address
            FROM customers
            WHERE id = :id
            LIMIT 1
        ");
        $stmtCustomer->execute(['id' => $customerId]);
        $customerRow = $stmtCustomer->fetch(PDO::FETCH_ASSOC);

        if ($customerRow) {
            $customerSnapshotName = $customerRow['name'] ?: 'ลูกค้าทั่วไป';
            $customerSnapshotPhone = $customerRow['phone'] ?: '-';
            $customerSnapshotAddress = $customerRow['address'] ?: '-';
        }
    }

    try {
        $pdo->beginTransaction();

        $salesColumns = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);
        $hasDiscountTotal = in_array('discount_total', $salesColumns, true);

        if ($hasDiscountTotal) {
            $saleStmt = $pdo->prepare('
                INSERT INTO sales (
                    sale_no,
                    customer_id,
                    customer_name_snapshot,
                    customer_phone_snapshot,
                    customer_address_snapshot,
                    user_id,
                    subtotal,
                    shipping_amount,
                    discount_total,
                    vat_amount,
                    grand_total,
                    delivery_method,
                    payment_method,
                    cash_received,
                    change_amount,
                    note
                ) VALUES (
                    :sale_no,
                    :customer_id,
                    :customer_name_snapshot,
                    :customer_phone_snapshot,
                    :customer_address_snapshot,
                    :user_id,
                    :subtotal,
                    :shipping_amount,
                    :discount_total,
                    :vat_amount,
                    :grand_total,
                    :delivery_method,
                    :payment_method,
                    :cash_received,
                    :change_amount,
                    :note
                )
            ');

            $saleStmt->execute([
                'sale_no' => $saleNo,
                'customer_id' => $customerId,
                'customer_name_snapshot' => $customerSnapshotName,
                'customer_phone_snapshot' => $customerSnapshotPhone,
                'customer_address_snapshot' => $customerSnapshotAddress,
                'user_id' => (int)$_SESSION['user']['id'],
                'subtotal' => $beforeVat,
                'shipping_amount' => $shipping,
                'discount_total' => $discountAmount,
                'vat_amount' => $vatAmount,
                'grand_total' => $grandTotal,
                'delivery_method' => $deliveryMethod,
                'payment_method' => $paymentMethod,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'note' => $note !== '' ? $note : null,
            ]);
        } else {
            $saleStmt = $pdo->prepare('
                INSERT INTO sales (
                    sale_no,
                    customer_id,
                    customer_name_snapshot,
                    customer_phone_snapshot,
                    customer_address_snapshot,
                    user_id,
                    subtotal,
                    shipping_amount,
                    vat_amount,
                    grand_total,
                    delivery_method,
                    payment_method,
                    cash_received,
                    change_amount,
                    note
                ) VALUES (
                    :sale_no,
                    :customer_id,
                    :customer_name_snapshot,
                    :customer_phone_snapshot,
                    :customer_address_snapshot,
                    :user_id,
                    :subtotal,
                    :shipping_amount,
                    :vat_amount,
                    :grand_total,
                    :delivery_method,
                    :payment_method,
                    :cash_received,
                    :change_amount,
                    :note
                )
            ');

            $saleStmt->execute([
                'sale_no' => $saleNo,
                'customer_id' => $customerId,
                'customer_name_snapshot' => $customerSnapshotName,
                'customer_phone_snapshot' => $customerSnapshotPhone,
                'customer_address_snapshot' => $customerSnapshotAddress,
                'user_id' => (int)$_SESSION['user']['id'],
                'subtotal' => $beforeVat,
                'shipping_amount' => $shipping,
                'vat_amount' => $vatAmount,
                'grand_total' => $grandTotal,
                'delivery_method' => $deliveryMethod,
                'payment_method' => $paymentMethod,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'note' => $note !== '' ? $note : null,
            ]);
        }

        $saleId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare('
            INSERT INTO sale_items (sale_id, product_id, product_name, price, qty, line_total)
            VALUES (:sale_id, :product_id, :product_name, :price, :qty, :line_total)
        ');

        $stockStmt = $pdo->prepare('
            UPDATE products
            SET stock_qty = stock_qty - :qty
            WHERE id = :id
        ');

        foreach ($items as $item) {
            $itemStmt->execute([
                'sale_id' => $saleId,
                'product_id' => $item['product']['id'],
                'product_name' => $item['product']['name'],
                'price' => $item['product']['price'],
                'qty' => $item['qty'],
                'line_total' => $item['line_total'],
            ]);

            $stockStmt->execute([
                'qty' => $item['qty'],
                'id' => $item['product']['id'],
            ]);
        }

        if ($customerId) {
            $points = (int)floor($grandTotal / 10);

            $customerStmt = $pdo->prepare('
                UPDATE customers
                SET points = points + :points,
                    total_spent = total_spent + :spent
                WHERE id = :id
            ');

            $customerStmt->execute([
                'points' => $points,
                'spent' => $grandTotal,
                'id' => $customerId,
            ]);
        }

        $pdo->commit();
        flash('success', 'ชำระเงินสำเร็จ เลขที่บิล: ' . $saleNo);
        redirect('sale_receipt.php?id=' . $saleId);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        redirect('pos.php');
        exit;
    }
}

$products = $pdo->query("
    SELECT 
        p.*,
        c.name AS category_name,
        c.shipping_fee,
        c.free_shipping_min,
        c.enable_shipping_rule
    FROM products p
    INNER JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'active'
    ORDER BY p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$customers = get_customers($pdo);

require __DIR__ . '/includes/header.php';
?>

<form method="post" class="two-col" id="pos-form">
    <input type="hidden" name="action" value="checkout">
    <input type="hidden" name="promo_id" value="<?= (int)$promoId ?>">
    <input type="hidden" name="promo_product_id" id="promoProductId" value="<?= (int)$promoProductId ?>">
    <input type="hidden" name="promo_buy_qty" id="promoBuyQty" value="<?= (int)$promoBuyQty ?>">
    <input type="hidden" name="promo_free_qty" id="promoFreeQty" value="<?= (int)$promoFreeQty ?>">

    <div>
        <div class="toolbar">
            <div>
                <div class="page-title" style="font-size:22px">เลือกรายการสินค้า</div>
                <div class="mini-text">ราคาขายในหน้า POS อ้างอิงตามราคาขายรวม VAT จากหน้าสินค้า</div>
            </div>

            <div style="display:flex; gap:10px; margin-bottom:18px; align-items:center; flex-wrap:wrap;">
                <input
                    id="productSearch"
                    type="text"
                    placeholder="ค้นหาสินค้า..."
                    style="padding:10px; border:1px solid #ddd; border-radius:10px; min-width:250px;"
                >

                <input
                    id="barcodeInput"
                    type="text"
                    placeholder="ยิงบาร์โค้ด/พิมพ์บาร์โค้ดแล้วกด Enter"
                    style="padding:10px; border:1px solid #ddd; border-radius:10px; min-width:260px;"
                >

                <button
                    id="startScannerBtn"
                    type="button"
                    style="padding:10px 16px; border:1px solid #cfd8e3; background:#f8fbff; color:#2563eb; border-radius:10px; cursor:pointer; font-weight:600;"
                >
                    สแกนบาร์โค้ด
                </button>

                <a
                    href="promotions.php"
                    style="padding:10px 16px; border:1px solid #cfd8e3; background:#fff7ed; color:#ea580c; border-radius:10px; text-decoration:none; font-weight:600; display:inline-block;"
                >
                    โปรโมชัน
                </a>
            </div>
        </div>

        <div id="scannerPanel" class="card mb-4" style="display:none">
            <div class="toolbar" style="margin-bottom:12px">
                <strong>กล้องสแกนบาร์โค้ด</strong>
                <button class="btn" type="button" id="stopScannerBtn">ปิดกล้อง</button>
            </div>
            <video id="scannerVideo" class="scanner-video" playsinline></video>
            <div class="mini-text" style="margin-top:8px">เบราว์เซอร์ที่รองรับ BarcodeDetector จะสามารถสแกนจากกล้องได้ทันที</div>
        </div>

        <div class="product-grid" id="productGrid">
            <?php foreach ($products as $product): ?>
                <div
                    class="product-card js-product-card"
                    data-product-id="<?= (int)$product['id'] ?>"
                    data-search="<?= e(mb_strtolower($product['name'] . ' ' . $product['sku'] . ' ' . ($product['barcode'] ?? ''), 'UTF-8')) ?>"
                    data-barcode="<?= e($product['barcode'] ?? '') ?>"
                    data-category-id="<?= (int)$product['category_id'] ?>"
                    data-category="<?= e($product['category_name'] ?? '') ?>"
                    data-price="<?= (float)$product['price'] ?>"
                    data-cost-price="<?= (float)$product['cost_price'] ?>"
                    data-vat-amount="<?= (float)$product['vat_amount'] ?>"
                    data-enable-shipping-rule="<?= (int)$product['enable_shipping_rule'] ?>"
                    data-shipping-fee="<?= (float)$product['shipping_fee'] ?>"
                    data-free-shipping-min="<?= (float)$product['free_shipping_min'] ?>"
                >
                    <?php if (!empty($product['image_path'])): ?>
                        <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>" class="product-card-image">
                    <?php else: ?>
                        <div class="product-card-image placeholder">NO IMAGE</div>
                    <?php endif; ?>

                    <div class="meta">SKU: <?= e($product['sku']) ?> · <?= e($product['category_name']) ?></div>
                    <h4><?= e($product['name']) ?></h4>
                    <div class="mini-text">Barcode: <?= e($product['barcode'] ?: '-') ?></div>
                    <div class="price">฿<?= money($product['price']) ?></div>
                    <div class="mini-text">
                        หน่วย: <?= e($product['unit_name'] ?? 'ชิ้น') ?>
                    </div>
                    <div class="stock">คงเหลือ: <?= (int)$product['stock_qty'] ?></div>

                    <?php if ((int)$product['enable_shipping_rule'] === 1): ?>
                        <div class="mini-text" style="margin-top:6px; color:#475569;">
                            ค่าส่ง <?= money($product['shipping_fee']) ?> /
                            ฟรีเมื่อครบ <?= money($product['free_shipping_min']) ?>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="product_id[]" value="<?= (int)$product['id'] ?>">
                    <input
                        class="input w-full product-qty"
                        type="number"
                        name="qty[]"
                        min="0"
                        max="<?= (int)$product['stock_qty'] ?>"
                        value="0"
                    >
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div>
        <div class="cart-box" style="position:relative;">
            <h3 style="margin-top:0">สรุปการขาย</h3>

            <?php if ($promoProductId > 0 && $promoBuyQty > 0): ?>
                <div style="margin-bottom:12px; padding:10px 12px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; color:#1d4ed8;">
                    ใช้โปรโมชัน: ซื้อ <?= (int)$promoBuyQty ?> แถม <?= (int)$promoFreeQty ?> × <?= (int)$promoSetQty ?> ชุด
                </div>
            <?php endif; ?>

            <div class="form-group mb-3" style="position:relative;">
                <label>ค้นหาลูกค้าจากเลขสมาชิก</label>

                <input
                    type="text"
                    id="memberSearch"
                    class="input w-full"
                    placeholder="พิมพ์เลขสมาชิก / ชื่อลูกค้า / เบอร์โทร"
                    autocomplete="off"
                >

                <input type="hidden" name="customer_id" id="customerId">

                <div
                    id="customerSearchResult"
                    style="
                        position:absolute;
                        top:100%;
                        left:0;
                        right:0;
                        background:#fff;
                        border:1px solid #dbe2ea;
                        border-radius:12px;
                        margin-top:6px;
                        box-shadow:0 10px 30px rgba(15, 23, 42, .08);
                        z-index:20;
                        display:none;
                        max-height:240px;
                        overflow-y:auto;
                    "
                ></div>
            </div>

            <div class="form-group mb-2">
                <label>วิธีรับสินค้า</label>
                <div style="display:flex; gap:14px; flex-wrap:wrap; margin-top:6px;">
                    <label style="display:flex; align-items:center; gap:6px;">
                        <input type="radio" name="delivery_method" value="pickup" checked>
                        รับเอง
                    </label>
                    <label style="display:flex; align-items:center; gap:6px;">
                        <input type="radio" name="delivery_method" value="delivery">
                        ส่งถึงบ้าน
                    </label>
                </div>
            </div>

            <div class="form-group mb-2">
                <label>วิธีชำระเงิน</label>
                <select class="select w-full" name="payment_method" id="paymentMethod">
                    <option value="cash">เงินสด</option>
                    <option value="transfer">โอนเงิน</option>
                    <option value="card">บัตร</option>
                    <option value="qr">QR</option>
                </select>
            </div>

            <div class="form-group mb-3">
                <label>หมายเหตุ</label>
                <input class="input w-full" type="text" name="note" placeholder="เช่น ลูกค้ารับเอง">
            </div>

            <div class="form-group mb-3">
                <label>ส่วนลด</label>
                <input
                    class="input w-full"
                    type="number"
                    step="0.01"
                    min="0"
                    name="discount_amount"
                    id="discountAmount"
                    value="0"
                    placeholder="ใส่ส่วนลด เช่น 50"
                >
            </div>

            <div style="background:#f6f8fc; border-radius:14px; padding:14px 16px; margin-bottom:16px;">
                <div class="summary-row"><span>จำนวนสินค้า</span><span id="summaryQty">0 ชิ้น</span></div>
                <div class="summary-row"><span>ยอดก่อนภาษี</span><span id="summarySub">฿0.00</span></div>
                <div class="summary-row"><span>ค่าส่ง</span><span id="summaryShipping">฿0.00</span></div>
                <div class="summary-row"><span>VAT 7%</span><span id="summaryVat">฿0.00</span></div>
                <div class="summary-row"><span>ส่วนลด</span><span id="summaryDiscount">฿0.00</span></div>
                <div class="summary-total"><span>ยอดรวมสุทธิ</span><span id="summaryGrand">฿0.00</span></div>
            </div>

            <div class="form-group mb-2" id="cashReceivedWrap">
                <label>รับเงินสด</label>
                <input class="input w-full" type="number" step="0.01" min="0" name="cash_received" id="cashReceived" value="0">
            </div>
            <input type="hidden" name="cash_received_sync" id="cashReceivedSync" value="0">

            <div class="summary-row">
                <span>เงินทอน</span>
                <span id="summaryChange" style="color:#16a34a; font-weight:700;">฿0.00</span>
            </div>

            <button class="btn primary w-full" type="submit" id="checkoutBtn">ยืนยันการขาย</button>
        </div>
    </div>
</form>

<script>
const cards = Array.from(document.querySelectorAll('.js-product-card'));
const productSearch = document.getElementById('productSearch');
const barcodeInput = document.getElementById('barcodeInput');
const paymentMethod = document.getElementById('paymentMethod');
const cashReceivedInput = document.getElementById('cashReceived');
const cashReceivedWrap = document.getElementById('cashReceivedWrap');
const cashReceivedSync = document.getElementById('cashReceivedSync');
const posForm = document.getElementById('pos-form');
const checkoutBtn = document.getElementById('checkoutBtn');
const discountAmountInput = document.getElementById('discountAmount');

const summaryQty = document.getElementById('summaryQty');
const summarySub = document.getElementById('summarySub');
const summaryShipping = document.getElementById('summaryShipping');
const summaryVat = document.getElementById('summaryVat');
const summaryDiscount = document.getElementById('summaryDiscount');
const summaryGrand = document.getElementById('summaryGrand');
const summaryChange = document.getElementById('summaryChange');

const deliveryMethodInputs = document.querySelectorAll('input[name="delivery_method"]');

const scannerPanel = document.getElementById('scannerPanel');
const scannerVideo = document.getElementById('scannerVideo');

const memberSearch = document.getElementById('memberSearch');
const customerId = document.getElementById('customerId');
const customerSearchResult = document.getElementById('customerSearchResult');

const selectedPromo = {
    promoId: <?= (int)$promoId ?>,
    productId: <?= (int)$promoProductId ?>,
    buyQty: <?= (int)$promoBuyQty ?>,
    freeQty: <?= (int)$promoFreeQty ?>,
    setQty: <?= (int)$promoSetQty ?>
};

let scannerStream = null;
let detector = null;
let scanTimer = null;
let currentGrandTotal = 0;
let customerSearchTimer = null;

function toMoney(num) {
    return '฿' + Number(num).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function getDeliveryMethod() {
    const checked = document.querySelector('input[name="delivery_method"]:checked');
    return checked ? checked.value : 'pickup';
}

function syncCashReceived() {
    if (cashReceivedSync) {
        cashReceivedSync.value = cashReceivedInput.value || '0';
    }
}

function calculatePromoChargeQty(lineQty, productId) {
    let chargeQty = lineQty;

    if (
        selectedPromo.productId > 0 &&
        selectedPromo.buyQty > 0 &&
        Number(productId) === Number(selectedPromo.productId)
    ) {
        const set = selectedPromo.buyQty + selectedPromo.freeQty;

        if (set > 0) {
            const full = Math.floor(lineQty / set);
            const remain = lineQty % set;
            chargeQty = (full * selectedPromo.buyQty) + Math.min(remain, selectedPromo.buyQty);
        }
    }

    return chargeQty;
}

function updateSummary() {
    let qty = 0;
    let beforeVat = 0;
    let grossSub = 0;
    let vat = 0;

    const deliveryMethod = getDeliveryMethod();
    const categorySummary = {};

    cards.forEach(card => {
        const qtyInput = card.querySelector('.product-qty');
        const lineQty = parseInt(qtyInput.value || '0', 10) || 0;

        const price = parseFloat(card.dataset.price || '0') || 0;
        const costPrice = parseFloat(card.dataset.costPrice || '0') || 0;
        const vatAmount = parseFloat(card.dataset.vatAmount || '0') || 0;
        const categoryId = card.dataset.categoryId || '0';
        const productId = card.dataset.productId || '0';

        const chargeQty = calculatePromoChargeQty(lineQty, productId);

        const lineTotal = chargeQty * price;
        const lineBeforeVat = chargeQty * costPrice;
        const lineVat = chargeQty * vatAmount;

        qty += lineQty;
        grossSub += lineTotal;
        beforeVat += lineBeforeVat;
        vat += lineVat;

        if (!categorySummary[categoryId]) {
            categorySummary[categoryId] = {
                subtotal: 0,
                enableShippingRule: parseInt(card.dataset.enableShippingRule || '0', 10) || 0,
                shippingFee: parseFloat(card.dataset.shippingFee || '0') || 0,
                freeShippingMin: parseFloat(card.dataset.freeShippingMin || '0') || 0
            };
        }

        categorySummary[categoryId].subtotal += lineTotal;
    });

    let shipping = 0;

    if (deliveryMethod === 'delivery') {
        Object.values(categorySummary).forEach(category => {
            if (category.enableShippingRule !== 1) {
                return;
            }

            if (category.subtotal > 0 && category.subtotal < category.freeShippingMin) {
                shipping += category.shippingFee;
            }
        });
    }

    let discount = parseFloat(discountAmountInput.value || '0') || 0;
    if (discount < 0) {
        discount = 0;
    }

    let grand = grossSub + shipping - discount;
    if (grand < 0) {
        grand = 0;
    }

    currentGrandTotal = grand;

    summaryQty.textContent = qty + ' ชิ้น';
    summarySub.textContent = toMoney(beforeVat);
    summaryShipping.textContent = toMoney(shipping);
    summaryVat.textContent = toMoney(vat);
    summaryDiscount.textContent = toMoney(discount);
    summaryGrand.textContent = toMoney(grand);

    if (paymentMethod.value !== 'cash') {
        cashReceivedInput.value = grand.toFixed(2);
    }

    syncCashReceived();
    updateChange();
}

function updateChange() {
    const received = parseFloat(cashReceivedInput.value || '0') || 0;
    let change = 0;

    if (paymentMethod.value === 'cash') {
        change = received - currentGrandTotal;
        checkoutBtn.disabled = received < currentGrandTotal || currentGrandTotal <= 0;
    } else {
        change = 0;
        checkoutBtn.disabled = currentGrandTotal <= 0;
    }

    summaryChange.textContent = toMoney(change > 0 ? change : 0);
}

function toggleCashInput() {
    const isCash = paymentMethod.value === 'cash';
    cashReceivedWrap.style.display = isCash ? '' : 'none';

    if (!isCash) {
        cashReceivedInput.value = currentGrandTotal.toFixed(2);
    }

    syncCashReceived();
    updateChange();
}

function filterProducts() {
    const q = (productSearch.value || '').trim().toLowerCase();
    cards.forEach(card => {
        const target = card.dataset.search.toLowerCase();
        card.style.display = (!q || target.includes(q)) ? '' : 'none';
    });
}

function addByBarcode(code) {
    const cleanCode = String(code || '').trim();
    if (!cleanCode) return;

    const card = cards.find(item => item.dataset.barcode === cleanCode);
    if (!card) {
        alert('ไม่พบบาร์โค้ด: ' + cleanCode);
        return;
    }

    const qtyInput = card.querySelector('.product-qty');
    const max = parseInt(qtyInput.getAttribute('max') || '0', 10);
    let next = (parseInt(qtyInput.value || '0', 10) || 0) + 1;

    if (next > max) {
        next = max;
    }

    qtyInput.value = next;
    card.scrollIntoView({behavior: 'smooth', block: 'center'});
    card.classList.add('highlight-product');

    setTimeout(() => card.classList.remove('highlight-product'), 1200);

    updateSummary();
}

function renderCustomerResults(items) {
    if (!items.length) {
        customerSearchResult.style.display = 'block';
        customerSearchResult.innerHTML = `<div style="padding:10px 12px; color:#64748b;">ไม่พบลูกค้า</div>`;
        return;
    }

    customerSearchResult.style.display = 'block';
    customerSearchResult.innerHTML = items.map(item => `
        <button
            type="button"
            class="customer-search-item"
            data-id="${item.id}"
            data-code="${(item.member_code || '').replace(/"/g, '&quot;')}"
            data-name="${(item.name || '').replace(/"/g, '&quot;')}"
            style="
                display:block;
                width:100%;
                text-align:left;
                padding:10px 12px;
                border:none;
                background:#fff;
                cursor:pointer;
                border-bottom:1px solid #eef2f7;
            "
        >
            <strong>${item.member_code ? item.member_code : '-'}</strong>
            - ${item.name || '-'}
            ${item.phone ? ` (${item.phone})` : ''}
        </button>
    `).join('');
}

function searchCustomer(keyword) {
    fetch('pos.php?ajax=search_customer&q=' + encodeURIComponent(keyword))
        .then(res => res.json())
        .then(data => {
            renderCustomerResults(Array.isArray(data) ? data : []);
        })
        .catch(() => {
            customerSearchResult.style.display = 'block';
            customerSearchResult.innerHTML = `<div style="padding:10px 12px; color:#dc2626;">ค้นหาลูกค้าไม่สำเร็จ</div>`;
        });
}

if (memberSearch) {
    memberSearch.addEventListener('input', function () {
        const keyword = this.value.trim();

        customerId.value = '';
        clearTimeout(customerSearchTimer);

        if (keyword.length < 2) {
            customerSearchResult.style.display = 'none';
            customerSearchResult.innerHTML = '';
            return;
        }

        customerSearchTimer = setTimeout(() => {
            searchCustomer(keyword);
        }, 250);
    });
}

document.addEventListener('click', function (e) {
    const item = e.target.closest('.customer-search-item');

    if (item) {
        const id = item.dataset.id || '';
        const code = item.dataset.code || '';
        const name = item.dataset.name || '';

        customerId.value = id;
        memberSearch.value = code ? `${code} - ${name}` : name;

        customerSearchResult.style.display = 'none';
        customerSearchResult.innerHTML = '';
        return;
    }

    if (!e.target.closest('#customerSearchResult') && e.target !== memberSearch) {
        customerSearchResult.style.display = 'none';
    }
});

if (productSearch) {
    productSearch.addEventListener('input', filterProducts);
}

if (barcodeInput) {
    barcodeInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addByBarcode(barcodeInput.value);
            barcodeInput.value = '';
        }
    });
}

cards.forEach(card => {
    card.querySelector('.product-qty').addEventListener('input', updateSummary);
});

deliveryMethodInputs.forEach(input => {
    input.addEventListener('change', updateSummary);
});

paymentMethod.addEventListener('change', toggleCashInput);

cashReceivedInput.addEventListener('input', () => {
    syncCashReceived();
    updateChange();
});

discountAmountInput.addEventListener('input', updateSummary);

updateSummary();
toggleCashInput();
syncCashReceived();

if (posForm) {
    posForm.addEventListener('submit', function () {
        syncCashReceived();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    if (selectedPromo.productId > 0 && selectedPromo.buyQty > 0) {
        const totalQty = (selectedPromo.buyQty + selectedPromo.freeQty) * selectedPromo.setQty;

        const targetCard = cards.find(card => Number(card.dataset.productId) === Number(selectedPromo.productId));

        if (targetCard) {
            const qtyInput = targetCard.querySelector('.product-qty');
            const max = parseInt(qtyInput.getAttribute('max') || '0', 10);

            qtyInput.value = Math.min(totalQty, max);
            updateSummary();

            targetCard.scrollIntoView({behavior: 'smooth', block: 'center'});
            targetCard.classList.add('highlight-product');
            setTimeout(() => targetCard.classList.remove('highlight-product'), 1500);
        }
    }
});

async function startScanner() {
    if (!('mediaDevices' in navigator)) {
        alert('อุปกรณ์นี้ไม่รองรับการเปิดกล้อง');
        return;
    }

    if (!('BarcodeDetector' in window)) {
        alert('เบราว์เซอร์นี้ยังไม่รองรับ BarcodeDetector กรุณาใช้การยิงบาร์โค้ดเข้าช่องข้อความแทน');
        return;
    }

    detector = new BarcodeDetector({
        formats: ['ean_13', 'ean_8', 'code_128', 'qr_code', 'upc_a', 'upc_e']
    });

    scannerStream = await navigator.mediaDevices.getUserMedia({
        video: {facingMode: 'environment'}
    });

    scannerVideo.srcObject = scannerStream;
    await scannerVideo.play();
    scannerPanel.style.display = '';

    scanTimer = setInterval(async () => {
        try {
            const barcodes = await detector.detect(scannerVideo);

            if (barcodes.length) {
                const code = barcodes[0].rawValue;
                addByBarcode(code);
                stopScanner();
            }
        } catch (err) {
            console.error(err);
        }
    }, 700);
}

function stopScanner() {
    if (scanTimer) clearInterval(scanTimer);
    scanTimer = null;

    if (scannerStream) {
        scannerStream.getTracks().forEach(track => track.stop());
    }

    scannerStream = null;
    scannerVideo.srcObject = null;
    scannerPanel.style.display = 'none';
}

document.getElementById('startScannerBtn').addEventListener('click', startScanner);
document.getElementById('stopScannerBtn').addEventListener('click', stopScanner);
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>