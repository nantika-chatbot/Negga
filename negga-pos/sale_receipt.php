<?php
require_once __DIR__ . '/includes/auth.php';

$id = (int)($_GET['id'] ?? 0);

$salesColumns = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);
$customerColumns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);

$hasShippingAmount = in_array('shipping_amount', $salesColumns, true);
$hasDeliveryMethod = in_array('delivery_method', $salesColumns, true);
$hasDiscountTotal  = in_array('discount_total', $salesColumns, true);
$hasMemberCode     = in_array('member_code', $customerColumns, true);

$shippingSelect   = $hasShippingAmount ? "COALESCE(s.shipping_amount, 0) AS shipping_amount" : "0 AS shipping_amount";
$deliverySelect   = $hasDeliveryMethod ? "COALESCE(s.delivery_method, 'pickup') AS delivery_method" : "'pickup' AS delivery_method";
$discountSelect   = $hasDiscountTotal ? "COALESCE(s.discount_total, 0) AS discount_total" : "0 AS discount_total";
$memberCodeSelect = $hasMemberCode ? "c.member_code AS member_code," : "'' AS member_code,";

$stmt = $pdo->prepare("
    SELECT 
        s.*,
        {$shippingSelect},
        {$deliverySelect},
        {$discountSelect},
        {$memberCodeSelect}
        COALESCE(s.customer_name_snapshot, c.name, 'ลูกค้าทั่วไป') AS customer_name,
        COALESCE(s.customer_phone_snapshot, c.phone, '-') AS customer_phone,
        COALESCE(s.customer_address_snapshot, c.address, '-') AS customer_address,
        COALESCE(u.full_name, 'Administrator') AS full_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    INNER JOIN users u ON u.id = s.user_id
    WHERE s.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die('ไม่พบบิล');
}

$itemStmt = $pdo->prepare("
    SELECT 
        si.*,
        p.id AS product_code
    FROM sale_items si
    LEFT JOIN products p ON p.id = si.product_id
    WHERE si.sale_id = :sale_id
    ORDER BY si.id ASC
");
$itemStmt->execute(['sale_id' => $id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$company_name = 'บริษัทเนกก้า ลัคกี้ จำกัด';
$company_address = '69/79 หมู่ที่ 6 ตำบลลำลูกกา อำเภอลำลูกกา จังหวัดปทุมธานี 12150';
$company_tax_id = '0105563004651';
$company_branch = 'สำนักงานใหญ่';

$customer_name = $sale['customer_name'] ?: 'ลูกค้าทั่วไป';
$customer_address = $sale['customer_address'] ?: '-';
$customer_phone = $sale['customer_phone'] ?: '-';
$member_no = !empty($sale['member_code']) ? $sale['member_code'] : '';
$document_no = !empty($sale['sale_no'])
    ? preg_replace('/^SALE/i', 'EG', $sale['sale_no'])
    : '';
$document_date = !empty($sale['created_at']) ? date('j/n/Y', strtotime($sale['created_at'])) : date('j/n/Y');

$grand_total = (float)($sale['grand_total'] ?? 0);
$shipping_amount = (float)($sale['shipping_amount'] ?? 0);
$discount = (float)($sale['discount_total'] ?? 0);
$vat = (float)($sale['vat_amount'] ?? 0);

if (isset($sale['subtotal']) && $sale['subtotal'] !== null && $sale['subtotal'] !== '') {
    $subtotal = (float)$sale['subtotal'];
} elseif ($discount > 0) {
    $subtotal = $grand_total + $discount;
} else {
    $subtotal = $grand_total;
}

$before_vat = $subtotal;
$delivery_method = $sale['delivery_method'] ?? 'pickup';
$payment_method = $sale['payment_method'] ?? '';
$note = trim((string)($sale['note'] ?? ''));

$fixed_rows = 8;
$a4Items = $items;
while (count($a4Items) < $fixed_rows) {
    $a4Items[] = [
        'product_id' => '',
        'product_name' => '',
        'price' => '',
        'qty' => '',
        'line_total' => '',
        'product_code' => ''
    ];
}

function th_money($value): string
{
    if ($value === '' || $value === null) {
        return '';
    }
    return number_format((float)$value, 2);
}

function payment_method_text($method): string
{
    return match ($method) {
        'cash' => 'เงินสด',
        'transfer' => 'โอนเงิน',
        'card' => 'บัตร',
        'qr' => 'QR',
        default => '-',
    };
}

function delivery_method_text($method): string
{
    return $method === 'delivery' ? 'ส่งถึงบ้าน' : 'รับเอง';
}

function baht_text($number): string
{
    $number = number_format((float)$number, 2, '.', '');
    [$integerPart, $decimalPart] = explode('.', $number);

    $number_call = ["ศูนย์","หนึ่ง","สอง","สาม","สี่","ห้า","หก","เจ็ด","แปด","เก้า"];
    $position_call = ["","สิบ","ร้อย","พัน","หมื่น","แสน","ล้าน"];

    $readNumber = function ($num) use (&$readNumber, $number_call, $position_call) {
        $num = ltrim((string)$num, '0');
        if ($num === '') {
            return '';
        }

        $len = strlen($num);

        if ($len > 6) {
            $prefix = substr($num, 0, $len - 6);
            $suffix = substr($num, -6);
            return $readNumber($prefix) . "ล้าน" . $readNumber($suffix);
        }

        $result = '';
        for ($i = 0; $i < $len; $i++) {
            $digit = (int)$num[$i];
            if ($digit === 0) {
                continue;
            }

            $pos = $len - $i - 1;

            if ($pos === 0) {
                if ($digit === 1 && $len > 1) {
                    $result .= "เอ็ด";
                } else {
                    $result .= $number_call[$digit];
                }
            } elseif ($pos === 1) {
                if ($digit === 1) {
                    $result .= "สิบ";
                } elseif ($digit === 2) {
                    $result .= "ยี่สิบ";
                } else {
                    $result .= $number_call[$digit] . "สิบ";
                }
            } else {
                $result .= $number_call[$digit] . $position_call[$pos];
            }
        }

        return $result;
    };

    $baht = $readNumber($integerPart);
    if ($baht === '') {
        $baht = 'ศูนย์';
    }

    if ($decimalPart === '00') {
        return $baht . 'บาทถ้วน';
    }

    $satang = $readNumber($decimalPart);
    if ($satang === '') {
        $satang = 'ศูนย์';
    }

    return $baht . 'บาท' . $satang . 'สตางค์';
}

$grand_total_text = baht_text($grand_total);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ใบเสร็จ <?= e($document_no) ?></title>
    <style>
        *{ box-sizing:border-box; }
        body{
            margin:0;
            background:#eef1f6;
            font-family:"TH Sarabun New","Sarabun",Tahoma,sans-serif;
            color:#000;
        }
        .page-wrap{
            width:210mm;
            margin:10mm auto;
        }
        .toolbar{
            display:flex;
            justify-content:flex-end;
            gap:10px;
            margin-bottom:12px;
            flex-wrap:wrap;
        }
        .btn{
            appearance:none;
            border:none;
            outline:none;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            min-width:150px;
            height:44px;
            padding:0 18px;
            border-radius:12px;
            font-size:16px;
            font-weight:700;
            cursor:pointer;
            transition:all .18s ease;
            box-shadow:0 4px 14px rgba(20, 30, 50, .08);
        }
        .btn-back{
            background:#ffffff;
            color:#24324a;
            border:1px solid #d8dee9;
        }
        .btn-print{
            background:#2563eb;
            color:#fff;
            border:1px solid #2563eb;
        }
        .sheet{
            margin:0 auto;
            background:#fff;
            border:1px solid #cfcfcf;
        }
        .sheet-a4{
            width:210mm;
            min-height:297mm;
            padding:10mm 12mm 12mm;
        }
        .header{
            text-align:center;
            line-height:1.25;
        }
        .company-name{
            font-size:20px;
            font-weight:700;
        }
        .company-address{
            font-size:15px;
            font-weight:700;
            margin-top:2px;
        }
        .doc-title{
            font-size:18px;
            font-weight:700;
            text-decoration:underline;
            margin-top:4px;
        }
        table{
            width:100%;
            border-collapse:collapse;
            table-layout:fixed;
        }
        .meta{
            margin-top:10px;
            font-size:15px;
        }
        .meta td{
            border:none;
            padding:3px 4px;
            vertical-align:top;
        }
        .meta .label{
            width:52px;
            font-weight:700;
        }
        .meta .center{
            text-align:center;
            font-weight:700;
            white-space:nowrap;
        }
        .items{
            margin-top:8px;
            font-size:15px;
            font-variant-numeric: tabular-nums;
            border:none;
        }
        .items th,
        .items td{
            border:none;
            padding:5px 6px;
            height:28px;
        }
        .items thead th{
            font-weight:700;
            text-align:center;
            border-bottom:1px solid #000;
        }
        .items tbody tr{
            border:none;
        }
        .c-no{ width:42px; text-align:center; }
        .c-code{ width:92px; text-align:center; }
        .c-name{ width:auto; }
        .c-qty{ width:70px; text-align:center; }
        .c-price,
        .c-total{
            width:95px;
            text-align:right;
            padding-right:10px !important;
            font-variant-numeric: tabular-nums;
        }
        .items th.c-price,
        .items th.c-total{
            text-align:right;
            padding-right:10px !important;
        }
        .items td.c-price,
        .items td.c-total{
            text-align:right;
            padding-right:10px !important;
        }
        .summary-table{
            margin-top:6px;
            width:100%;
            font-size:15px;
            font-variant-numeric: tabular-nums;
        }
        .summary-table td{
            border:none;
            padding:3px 6px;
            vertical-align:top;
        }
        .sum-left{
            width:58%;
            text-align:left;
        }
        .sum-label{
            text-align:right;
            font-weight:700;
            white-space:nowrap;
        }
        .sum-value{
            width:120px;
            text-align:right;
            padding-right:10px !important;
        }
        .signatures{
            width:100%;
            margin-top:26px;
            font-size:15px;
        }
        .signatures td{
            border:none;
            text-align:center;
            vertical-align:top;
            padding-top:8px;
        }
        .sign-line{
            display:inline-block;
            min-width:160px;
            border-bottom:1px dotted #000;
            height:18px;
            vertical-align:bottom;
        }
        @media print{
            body{ background:#fff; }
            .toolbar{ display:none !important; }
            .page-wrap{
                width:auto;
                margin:0;
            }
            .sheet{
                border:none;
                margin:0 auto;
            }
            @page{
                size:A4 portrait;
                margin:8mm;
            }
            .sheet-a4{
                width:100%;
                min-height:auto;
                padding:0;
                border:none;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="toolbar">
            <a class="btn btn-back" href="pos.php">
                <span>←</span>
                <span>กลับไปหน้าการขาย</span>
            </a>

            <button class="btn btn-print" onclick="window.print()">
                <span>🖨</span>
                <span>พิมพ์เอกสาร</span>
            </button>
        </div>

        <div class="sheet sheet-a4">
            <div class="header">
                <div class="company-name"><?= e($company_name) ?></div>
                <div class="company-address">
                    <?= e($company_address) ?> เลขประจำตัวผู้เสียภาษี <?= e($company_tax_id) ?> <?= e($company_branch) ?>
                </div>
                <div class="doc-title">ใบเสร็จรับเงิน/ใบกำกับภาษี/ใบส่งของ</div>
            </div>

            <table class="meta">
                <tr>
                    <td class="label">ชื่อ :</td>
                    <td style="width:230px;"><?= e($customer_name) ?></td>
                    <td class="center" style="width:90px;">เลขสมาชิก</td>
                    <td style="width:90px;"><?= e($member_no) ?></td>
                    <td class="center" style="width:110px;">เลขที่เอกสาร</td>
                    <td><?= e($document_no) ?></td>
                </tr>
                <tr>
                    <td class="label">ที่อยู่ :</td>
                    <td colspan="3"><?= e($customer_address) ?></td>
                    <td class="center">วันที่</td>
                    <td><?= e($document_date) ?></td>
                </tr>
                <tr>
                    <td class="label">รับสินค้า :</td>
                    <td><?= e(delivery_method_text($delivery_method)) ?></td>
                    <td class="center">ชำระเงิน</td>
                    <td><?= e(payment_method_text($payment_method)) ?></td>
                    <td class="center">พนักงานขาย</td>
                    <td><?= e($sale['full_name']) ?></td>
                </tr>
            </table>

            <table class="items">
                <thead>
                    <tr>
                        <th class="c-no">ลำดับ</th>
                        <th class="c-code">รหัสสินค้า</th>
                        <th class="c-name">ชื่อสินค้า</th>
                        <th class="c-qty">จำนวน</th>
                        <th class="c-price">ราคาต่อหน่วย</th>
                        <th class="c-total">ราคารวม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($a4Items as $i => $item): ?>
                    <?php $hasItem = trim((string)($item['product_name'] ?? '')) !== ''; ?>
                    <tr>
                        <td class="c-no"><?= $hasItem ? ($i + 1) : '' ?></td>
                        <td class="c-code"><?= e($item['product_code']) ?></td>
                        <td class="c-name"><?= e($item['product_name']) ?></td>
                        <td class="c-qty"><?= $item['qty'] !== '' ? e($item['qty']) : '' ?></td>
                        <td class="c-price"><?= $item['price'] !== '' ? th_money($item['price']) : '' ?></td>
                        <td class="c-total"><?= $item['line_total'] !== '' ? th_money($item['line_total']) : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="summary-table">
                <tr>
                    <td class="sum-left"></td>
                    <td class="sum-label">ยอดก่อนภาษี</td>
                    <td class="sum-value"><?= th_money($before_vat) ?></td>
                </tr>
                <tr>
                    <td class="sum-left"></td>
                    <td class="sum-label">ภาษีมูลค่าเพิ่ม 7%</td>
                    <td class="sum-value"><?= th_money($vat) ?></td>
                </tr>
                <tr>
                    <td class="sum-left"></td>
                    <td class="sum-label">ค่าส่ง</td>
                    <td class="sum-value"><?= th_money($shipping_amount) ?></td>
                </tr>
                <tr>
                    <td class="sum-left"><strong>หมายเหตุ:</strong> <?= e($note !== '' ? $note : '-') ?></td>
                    <td class="sum-label">ส่วนลด</td>
                    <td class="sum-value"><?= th_money($discount) ?></td>
                </tr>
                <tr>
                    <td class="sum-left" style="font-weight:700;"><?= e($grand_total_text) ?></td>
                    <td class="sum-label">มูลค่ารวมสุทธิ</td>
                    <td class="sum-value" style="font-weight:700;"><?= th_money($grand_total) ?></td>
                </tr>
            </table>

            <table class="signatures">
                <tr>
                    <td>
                        ลงชื่อ <span class="sign-line"></span><br>
                        (ผู้รับเงิน/ผู้มีอำนาจ)
                    </td>
                    <td>
                        ลงชื่อ <span class="sign-line"></span><br>
                        (ผู้จัดสินค้า/ตรวจสอบสินค้า)
                    </td>
                    <td>
                        ลงชื่อ <span class="sign-line"></span><br>
                        (ผู้รับสินค้า/ลูกค้า)
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <?php if (isset($_GET['autoprint']) && $_GET['autoprint'] == '1'): ?>
    <script>
        window.onload = function () {
            window.print();
        };
    </script>
    <?php endif; ?>
</body>
</html>