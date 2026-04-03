<?php
require_once 'config/db.php'; // แก้ path ให้ตรงระบบคุณ

function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('ไม่พบเลขที่เอกสาร');
}

/* =========================
   โหลดข้อมูลการขาย
========================= */
$stmt = $conn->prepare("
    SELECT 
        s.*,
        c.name AS customer_name,
        c.phone AS customer_phone,
        c.member_code
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die('ไม่พบข้อมูลการขาย');
}

/* =========================
   โหลดรายการสินค้า
========================= */
$stmt = $conn->prepare("
    SELECT 
        product_id,
        product_name,
        price,
        qty,
        line_total
    FROM sale_items
    WHERE sale_id = ?
    ORDER BY id ASC
");
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   ข้อมูลบริษัท
========================= */
$company_name = 'บริษัทเนกก้า ลัคกี้ จำกัด';
$company_address = '69/79 หมู่ที่ 6 ตำบลลำลูกกา อำเภอลำลูกกา จังหวัดปทุมธานี 12150';
$company_tax_id = '0105563004651';
$company_branch = 'สำนักงานใหญ่';

/* =========================
   ข้อมูลเอกสาร
========================= */
$customer_name = !empty($sale['customer_name']) ? $sale['customer_name'] : 'ลูกค้าทั่วไป';
$customer_address = '-';
$member_no = !empty($sale['member_code']) ? $sale['member_code'] : '';
$document_no = !empty($sale['sale_no']) ? $sale['sale_no'] : '';
$document_date = !empty($sale['created_at']) ? date('j/n/Y', strtotime($sale['created_at'])) : date('j/n/Y');

/* =========================
   ยอดเงิน
========================= */
$grand_total = (float)($sale['grand_total'] ?? 0);
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

/* =========================
   ให้ครบ 6 แถว
========================= */
$fixed_rows = 6;
while (count($items) < $fixed_rows) {
    $items[] = [
        'product_id' => '',
        'product_name' => '',
        'price' => '',
        'qty' => '',
        'line_total' => ''
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ใบเสร็จรับเงิน/ใบกำกับภาษี/ใบส่งของ</title>
<style>
    *{
        box-sizing:border-box;
    }
    body{
        margin:0;
        background:#f3f3f3;
        font-family:"TH Sarabun New","Sarabun",Tahoma,sans-serif;
        color:#000;
    }
    .page-wrap{
        width:900px;
        margin:20px auto;
    }
    .toolbar{
        text-align:right;
        margin-bottom:10px;
    }
    .btn{
        display:inline-block;
        padding:8px 14px;
        border:1px solid #999;
        background:#fff;
        cursor:pointer;
        font-size:16px;
    }
    .sheet{
        width:794px;
        min-height:560px;
        margin:0 auto;
        background:#fff;
        padding:10px 14px 16px;
        border:1px solid #bbb;
    }

    .header{
        text-align:center;
        line-height:1.2;
    }
    .company-name{
        font-size:22px;
        font-weight:700;
    }
    .company-address{
        font-size:16px;
        font-weight:700;
        margin-top:4px;
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
        margin-top:8px;
        font-size:16px;
    }
    .meta td{
        border:1px solid #bfbfbf;
        height:28px;
        padding:2px 6px;
        vertical-align:middle;
    }
    .meta .noborder{
        border:none !important;
        padding:0 4px;
    }
    .meta .label{
        width:56px;
        font-weight:700;
        border-right:none;
    }
    .meta .field{
        border-left:none;
    }
    .meta .center{
        text-align:center;
        font-weight:700;
    }

    .items{
        margin-top:0;
        font-size:16px;
    }
    .items th,
    .items td{
        border:1px solid #bfbfbf;
        padding:3px 6px;
        height:30px;
        vertical-align:top;
    }
    .items th{
        text-align:center;
        font-weight:700;
    }

    .c-no{width:40px; text-align:center;}
    .c-code{width:90px; text-align:center;}
    .c-name{width:300px;}
    .c-qty{width:70px; text-align:center;}
    .c-price{width:120px; text-align:right;}
    .c-total{width:120px; text-align:right;}

    .summary-row td{
        border-top:none;
    }

    .sum-label{
        text-align:right;
        font-weight:700;
        padding-right:10px !important;
    }
    .sum-value{
        text-align:right;
        padding-right:12px !important;
    }

    .signatures{
        width:100%;
        margin-top:28px;
        font-size:16px;
    }
    .signatures td{
        border:none;
        text-align:center;
        vertical-align:top;
        padding-top:8px;
    }
    .sign-line{
        display:inline-block;
        min-width:210px;
        border-bottom:1px dotted #000;
        height:18px;
        vertical-align:bottom;
    }

    @media print{
        @page{
            size:A4 landscape;
            margin:8mm;
        }
        body{
            background:#fff;
        }
        .page-wrap{
            width:auto;
            margin:0;
        }
        .toolbar{
            display:none !important;
        }
        .sheet{
            width:100%;
            min-height:auto;
            border:none;
            margin:0;
            padding:0;
        }
    }
</style>
</head>
<body>
    <div class="page-wrap">
        <div class="toolbar">
            <button class="btn" onclick="window.print()">พิมพ์เอกสาร</button>
        </div>

        <div class="sheet">
            <div class="header">
                <div class="company-name"><?= e($company_name) ?></div>
                <div class="company-address">
                    <?= e($company_address) ?>
                    เลขประจำตัวผู้เสียภาษี <?= e($company_tax_id) ?>
                    &nbsp;&nbsp;&nbsp;
                    <?= e($company_branch) ?>
                </div>
                <div class="doc-title">ใบเสร็จรับเงิน/ใบกำกับภาษี/ใบส่งของ</div>
            </div>

            <table class="meta">
                <tr>
                    <td class="label noborder">ชื่อ :</td>
                    <td class="field" style="width:250px;"><?= e($customer_name) ?></td>
                    <td class="center" style="width:110px;">เลขสมาชิก</td>
                    <td style="width:130px;"><?= e($member_no) ?></td>
                    <td class="center" style="width:130px;">เลขที่เอกสาร</td>
                    <td><?= e($document_no) ?></td>
                </tr>
                <tr>
                    <td class="label noborder">ที่อยู่ :</td>
                    <td class="field" colspan="3"><?= e($customer_address) ?></td>
                    <td class="center">วันที่</td>
                    <td><?= e($document_date) ?></td>
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
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td class="c-no"><?= $i + 1 ?></td>
                        <td class="c-code"><?= e($item['product_id']) ?></td>
                        <td class="c-name"><?= e($item['product_name']) ?></td>
                        <td class="c-qty"><?= $item['qty'] !== '' ? e($item['qty']) : '' ?></td>
                        <td class="c-price"><?= $item['price'] !== '' ? number_format((float)$item['price'], 2) : '' ?></td>
                        <td class="c-total"><?= $item['line_total'] !== '' ? number_format((float)$item['line_total'], 2) : '' ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <tr>
                        <td colspan="4" rowspan="5" style="border-top:none;"></td>
                        <td class="sum-label">รวม</td>
                        <td class="sum-value"><?= number_format($subtotal, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="sum-label">ส่วนลด</td>
                        <td class="sum-value"><?= number_format($discount, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="sum-label">มูลค่าก่อนภาษีมูลค่าเพิ่ม</td>
                        <td class="sum-value"><?= number_format($before_vat, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="sum-label">ภาษีมูลค่าเพิ่ม 7%</td>
                        <td class="sum-value"><?= number_format($vat, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="sum-label">มูลค่ารวมสุทธิ</td>
                        <td class="sum-value" style="font-weight:700;"><?= number_format($grand_total, 2) ?></td>
                    </tr>
                </tbody>
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