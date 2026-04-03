<?php
require_once __DIR__ . '/includes/auth.php';

function redirect_error(string $msg): void
{
    header('Location: promotions.php?error=' . urlencode($msg));
    exit;
}

function pick_first_existing(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return null;
}

function get_logged_in_user_id(PDO $pdo): ?int
{
    $possibleIds = [];

    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        if (isset($_SESSION['user']['id'])) {
            $possibleIds[] = (int)$_SESSION['user']['id'];
        }
        if (isset($_SESSION['user']['user_id'])) {
            $possibleIds[] = (int)$_SESSION['user']['user_id'];
        }
    }

    if (isset($_SESSION['user_id'])) {
        $possibleIds[] = (int)$_SESSION['user_id'];
    }

    if (isset($_SESSION['id'])) {
        $possibleIds[] = (int)$_SESSION['id'];
    }

    $possibleIds = array_values(array_unique(array_filter($possibleIds, fn($v) => $v > 0)));

    foreach ($possibleIds as $uid) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $uid]);
        $found = $stmt->fetchColumn();
        if ($found) {
            return (int)$found;
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_error('คำขอไม่ถูกต้อง');
}

$promotionId = (int)($_POST['promotion_id'] ?? 0);
$setQty = max(1, (int)($_POST['set_qty'] ?? 1));

if ($promotionId <= 0) {
    redirect_error('ไม่พบโปรโมชัน');
}

try {
    $pdo->beginTransaction();

    $productColumns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    $productIdCol    = pick_first_existing($productColumns, ['id']);
    $productNameCol  = pick_first_existing($productColumns, ['name', 'product_name', 'title']);
    $productSkuCol   = pick_first_existing($productColumns, ['sku', 'code', 'product_code']);
    $productUnitCol  = pick_first_existing($productColumns, ['unit']);
    $productPriceCol = pick_first_existing($productColumns, ['price', 'sell_price', 'sale_price', 'selling_price', 'retail_price']);
    $productStockCol = pick_first_existing($productColumns, ['stock', 'stock_qty', 'qty', 'quantity']);

    if (!$productIdCol || !$productNameCol || !$productPriceCol || !$productStockCol) {
        throw new Exception('โครงสร้างตาราง products ไม่รองรับ');
    }

    $promoSql = "
        SELECT 
            pr.*,
            p.`{$productIdCol}` AS product_id,
            p.`{$productNameCol}` AS product_name,
            " . ($productSkuCol ? "p.`{$productSkuCol}` AS sku," : "") . "
            " . ($productUnitCol ? "p.`{$productUnitCol}` AS unit," : "") . "
            p.`{$productPriceCol}` AS product_price,
            p.`{$productStockCol}` AS product_stock
        FROM promotions pr
        INNER JOIN products p ON p.`{$productIdCol}` = pr.product_id
        WHERE pr.id = :id
        LIMIT 1
        FOR UPDATE
    ";

    $stmt = $pdo->prepare($promoSql);
    $stmt->execute([':id' => $promotionId]);
    $promo = $stmt->fetch();

    if (!$promo) {
        throw new Exception('ไม่พบโปรโมชัน');
    }

    if (($promo['status'] ?? '') !== 'active') {
        throw new Exception('โปรโมชันยังไม่เปิดใช้งาน');
    }

    $today = date('Y-m-d');
    if (!empty($promo['start_date']) && $today < $promo['start_date']) {
        throw new Exception('ยังไม่ถึงวันเริ่มโปรโมชัน');
    }
    if (!empty($promo['end_date']) && $today > $promo['end_date']) {
        throw new Exception('โปรโมชันหมดอายุแล้ว');
    }

    $buyQty = (int)$promo['buy_qty'];
    $freeQty = (int)$promo['free_qty'];

    if ($buyQty <= 0) {
        throw new Exception('ข้อมูลโปรโมชันไม่ถูกต้อง');
    }

    $paidQty = $buyQty * $setQty;
    $freeQtyAll = $freeQty * $setQty;
    $totalQty = $paidQty + $freeQtyAll;

    $stock = (int)$promo['product_stock'];
    $unitPrice = (float)$promo['product_price'];

    if ($stock < $totalQty) {
        throw new Exception('สต็อกไม่พอสำหรับโปรโมชันนี้');
    }

    $userId = get_logged_in_user_id($pdo);
    if (!$userId) {
        throw new Exception('ไม่พบผู้ใช้งานในระบบ กรุณาออกจากระบบแล้วเข้าใหม่');
    }

    $grandTotal = $unitPrice * $paidQty;
    $discountTotal = $unitPrice * $freeQtyAll;
    $subtotalExVat = round($grandTotal / 1.07, 2);
    $vatAmount = round($grandTotal - $subtotalExVat, 2);

    $saleCountStmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE()");
    $saleCountStmt->execute();
    $running = ((int)$saleCountStmt->fetchColumn()) + 1;
    $saleNo = 'SALE' . date('Ymd') . '-' . str_pad((string)$running, 4, '0', STR_PAD_LEFT);

    $salesColumns = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);
    $saleData = [];

    if (in_array('sale_no', $salesColumns, true)) {
        $saleData['sale_no'] = $saleNo;
    }
    if (in_array('customer_id', $salesColumns, true)) {
        $saleData['customer_id'] = null;
    }

    $subtotalCol = pick_first_existing($salesColumns, ['subtotal', 'sub_total', 'subtotal_ex_vat']);
    $vatCol = pick_first_existing($salesColumns, ['vat_total', 'vat', 'tax', 'tax_total']);
    $discountCol = pick_first_existing($salesColumns, ['discount_total', 'discount']);
    $grandCol = pick_first_existing($salesColumns, ['grand_total', 'total', 'net_total']);

    if ($subtotalCol) {
        $saleData[$subtotalCol] = $subtotalExVat;
    }
    if ($vatCol) {
        $saleData[$vatCol] = $vatAmount;
    }
    if ($discountCol) {
        $saleData[$discountCol] = $discountTotal;
    }
    if ($grandCol) {
        $saleData[$grandCol] = $grandTotal;
    }

    if (in_array('payment_method', $salesColumns, true)) {
        $saleData['payment_method'] = 'promotion';
    }
    if (in_array('note', $salesColumns, true)) {
        $saleData['note'] = 'ซื้อผ่านโปรโมชัน: ' . $promo['name'] . ' / จำนวนชุด ' . $setQty;
    }
    if (in_array('created_at', $salesColumns, true)) {
        $saleData['created_at'] = date('Y-m-d H:i:s');
    }

    if (in_array('user_id', $salesColumns, true)) {
        $saleData['user_id'] = $userId;
    } elseif (in_array('created_by', $salesColumns, true)) {
        $saleData['created_by'] = $userId;
    }

    if (empty($saleData)) {
        throw new Exception('ไม่สามารถบันทึกข้อมูลการขายได้ เพราะไม่พบคอลัมน์ที่รองรับในตาราง sales');
    }

    $insertCols = array_keys($saleData);
    $insertSql = "INSERT INTO sales (" . implode(', ', $insertCols) . ")
                  VALUES (:" . implode(', :', $insertCols) . ")";
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute($saleData);

    $saleId = (int)$pdo->lastInsertId();

    $saleItemColumns = $pdo->query("SHOW COLUMNS FROM sale_items")->fetchAll(PDO::FETCH_COLUMN);
    $saleIdCol       = pick_first_existing($saleItemColumns, ['sale_id']);
    $itemProductIdCol = pick_first_existing($saleItemColumns, ['product_id']);
    $itemNameCol     = pick_first_existing($saleItemColumns, ['product_name', 'name']);
    $itemPriceCol    = pick_first_existing($saleItemColumns, ['price']);
    $itemQtyCol      = pick_first_existing($saleItemColumns, ['qty', 'quantity']);
    $itemTotalCol    = pick_first_existing($saleItemColumns, ['line_total', 'total']);
    $itemNoteCol     = pick_first_existing($saleItemColumns, ['note', 'remark', 'description']);
    $itemCreatedCol  = pick_first_existing($saleItemColumns, ['created_at']);

    if (!$saleIdCol || !$itemProductIdCol || !$itemNameCol || !$itemPriceCol || !$itemQtyCol || !$itemTotalCol) {
        throw new Exception('โครงสร้างตาราง sale_items ไม่รองรับ');
    }

    $insertSaleItem = function(array $data) use ($pdo) {
        $cols = array_keys($data);
        $sql = "INSERT INTO sale_items (" . implode(', ', $cols) . ")
                VALUES (:" . implode(', :', $cols) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
    };

    $paidItem = [
        $saleIdCol        => $saleId,
        $itemProductIdCol => $promo['product_id'],
        $itemNameCol      => $promo['product_name'] . ' (จำนวนที่คิดเงิน)',
        $itemPriceCol     => $unitPrice,
        $itemQtyCol       => $paidQty,
        $itemTotalCol     => $unitPrice * $paidQty,
    ];
    if ($itemNoteCol) {
        $paidItem[$itemNoteCol] = 'โปรโมชัน ' . $promo['name'];
    }
    if ($itemCreatedCol) {
        $paidItem[$itemCreatedCol] = date('Y-m-d H:i:s');
    }
    $insertSaleItem($paidItem);

    if ($freeQtyAll > 0) {
        $freeItem = [
            $saleIdCol        => $saleId,
            $itemProductIdCol => $promo['product_id'],
            $itemNameCol      => $promo['product_name'] . ' (ของแถม)',
            $itemPriceCol     => 0,
            $itemQtyCol       => $freeQtyAll,
            $itemTotalCol     => 0,
        ];
        if ($itemNoteCol) {
            $freeItem[$itemNoteCol] = 'ของแถมจากโปรโมชัน ' . $promo['name'];
        }
        if ($itemCreatedCol) {
            $freeItem[$itemCreatedCol] = date('Y-m-d H:i:s');
        }
        $insertSaleItem($freeItem);
    }

    $updateStockSql = "UPDATE products
                       SET `{$productStockCol}` = `{$productStockCol}` - :qty
                       WHERE `{$productIdCol}` = :id";
    $updateStockStmt = $pdo->prepare($updateStockSql);
    $updateStockStmt->execute([
        ':qty' => $totalQty,
        ':id'  => $promo['product_id'],
    ]);

    $pdo->commit();

    header('Location: sale_receipt.php?id=' . $saleId);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_error($e->getMessage());
}