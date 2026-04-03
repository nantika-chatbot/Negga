<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

function first_existing(array $columns, array $names): ?string {
    foreach ($names as $name) {
        if (in_array($name, $columns, true)) return $name;
    }
    return null;
}

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');

if ($dateFrom > $dateTo) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}

$salesColumns = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);
$userColumns  = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

$grandCol    = first_existing($salesColumns, ['grand_total', 'total', 'net_total']);
$subtotalCol = first_existing($salesColumns, ['subtotal', 'sub_total']);
$discountCol = first_existing($salesColumns, ['discount_total', 'discount']);
$userCol     = first_existing($salesColumns, ['user_id', 'created_by']);
$createdCol  = first_existing($salesColumns, ['created_at']);
$paidCol     = first_existing($salesColumns, ['cash_received', 'received_amount', 'amount_received']);
$changeCol   = first_existing($salesColumns, ['change_amount', 'cash_change', 'change_total']);

$userNameCol = first_existing($userColumns, ['name', 'full_name', 'username', 'email']);

if (!$grandCol || !$createdCol) {
    die('ไม่พบคอลัมน์ที่จำเป็นในตาราง sales');
}

$params = [
    ':date_from' => $dateFrom . ' 00:00:00',
    ':date_to'   => $dateTo . ' 23:59:59',
];

$summarySelect = [
    "COUNT(*) AS bill_count",
    "COALESCE(SUM(s.`$grandCol`),0) AS net_sales",
];

if ($paidCol) {
    $summarySelect[] = "COALESCE(SUM(s.`$paidCol`),0) AS received_total";
} else {
    $summarySelect[] = "COALESCE(SUM(s.`$grandCol`),0) AS received_total";
}

if ($changeCol) {
    $summarySelect[] = "COALESCE(SUM(s.`$changeCol`),0) AS change_total";
} else {
    $summarySelect[] = "0 AS change_total";
}

$summarySql = "
    SELECT " . implode(", ", $summarySelect) . "
    FROM sales s
    WHERE s.`$createdCol` BETWEEN :date_from AND :date_to
";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$staffSales = [];
if ($userCol && $userNameCol) {
    $staffNameExpr = "COALESCE(u.`$userNameCol`, '-')";

    $staffSql = "
        SELECT
            $staffNameExpr AS staff_name,
            COUNT(*) AS bill_count,
            COALESCE(SUM(s.`$grandCol`),0) AS net_sales
        FROM sales s
        LEFT JOIN users u ON u.id = s.`$userCol`
        WHERE s.`$createdCol` BETWEEN :date_from AND :date_to
        GROUP BY s.`$userCol`, $staffNameExpr
        ORDER BY net_sales DESC, bill_count DESC
    ";
    $staffStmt = $pdo->prepare($staffSql);
    $staffStmt->execute($params);
    $staffSales = $staffStmt->fetchAll();
}

$detailSelect = [
    "s.id",
    "s.sale_no",
    "s.`$createdCol` AS created_at",
];

if ($userCol && $userNameCol) {
    $detailSelect[] = "COALESCE(u.`$userNameCol`, '-') AS staff_name";
} else {
    $detailSelect[] = "'-' AS staff_name";
}

if ($subtotalCol) {
    $detailSelect[] = "s.`$subtotalCol` AS subtotal";
} else {
    $detailSelect[] = "0 AS subtotal";
}

if ($discountCol) {
    $detailSelect[] = "s.`$discountCol` AS discount_total";
} else {
    $detailSelect[] = "0 AS discount_total";
}

$detailSelect[] = "s.`$grandCol` AS grand_total";

$detailSql = "
    SELECT " . implode(", ", $detailSelect) . "
    FROM sales s
";

if ($userCol && $userNameCol) {
    $detailSql .= " LEFT JOIN users u ON u.id = s.`$userCol` ";
}

$detailSql .= "
    WHERE s.`$createdCol` BETWEEN :date_from AND :date_to
    ORDER BY s.`$createdCol` DESC, s.id DESC
";

$detailStmt = $pdo->prepare($detailSql);
$detailStmt->execute($params);
$saleRows = $detailStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="toolbar" style="margin-bottom:18px; align-items:flex-end; flex-wrap:wrap;">
    <div>
        <div class="page-title">ข้อมูลทางการเงิน</div>
        <div class="mini-text">ดูยอดขายตามช่วงเวลา สรุปตามพนักงาน และรายการบิลทั้งหมด</div>
    </div>
</div>

<div class="card" style="margin-bottom:18px;">
    <form method="get" class="toolbar" style="gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="min-width:260px;">
            <label>วันที่เริ่มต้น</label>
            <input class="input w-full" type="date" name="date_from" value="<?= e($dateFrom) ?>">
        </div>

        <div class="form-group" style="min-width:260px;">
            <label>วันที่สิ้นสุด</label>
            <input class="input w-full" type="date" name="date_to" value="<?= e($dateTo) ?>">
        </div>

        <div>
            <button class="btn primary" type="submit">ค้นข้อมูล</button>
        </div>
    </form>
</div>

<div class="stats" style="margin-bottom:18px;">
    <div class="card">
        <div class="stat-label">จำนวนบิล</div>
        <div class="stat-value"><?= (int)($summary['bill_count'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">ยอดขายสุทธิ</div>
        <div class="stat-value" style="color:#16a34a;">฿<?= money($summary['net_sales'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">เงินรับ</div>
        <div class="stat-value" style="color:#2563eb;">฿<?= money($summary['received_total'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">เงินทอน</div>
        <div class="stat-value" style="color:#ef4444;">฿<?= money($summary['change_total'] ?? 0) ?></div>
    </div>
</div>

<div class="card" style="margin-bottom:18px;">
    <h3 style="margin-top:0;">สรุปยอดตามพนักงาน</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ชื่อพนักงาน</th>
                    <th class="text-right">จำนวนบิล</th>
                    <th class="text-right">ยอดขายสุทธิ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$staffSales): ?>
                    <tr>
                        <td colspan="3">ไม่พบข้อมูลในช่วงเวลานี้</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staffSales as $row): ?>
                        <tr>
                            <td><?= e($row['staff_name']) ?></td>
                            <td class="text-right"><?= (int)$row['bill_count'] ?></td>
                            <td class="text-right" style="color:#16a34a; font-weight:700;">
                                ฿<?= money($row['net_sales']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0;">รายการบิลทั้งหมด</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>เลขบิล</th>
                    <th>วันที่</th>
                    <th>พนักงาน</th>
                    <th class="text-right">ก่อนลด</th>
                    <th class="text-right">ส่วนลด</th>
                    <th class="text-right">สุทธิ</th>
                    <th class="text-center">ดู</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$saleRows): ?>
                    <tr>
                        <td colspan="7">ไม่พบรายการบิล</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($saleRows as $row): ?>
                        <tr>
                            <td>#<?= e($row['sale_no']) ?></td>
                            <td><?= e($row['created_at']) ?></td>
                            <td><?= e($row['staff_name']) ?></td>
                            <td class="text-right">฿<?= money($row['subtotal']) ?></td>
                            <td class="text-right" style="color:#ef4444;">฿<?= money($row['discount_total']) ?></td>
                            <td class="text-right" style="color:#16a34a; font-weight:700;">฿<?= money($row['grand_total']) ?></td>
                            <td class="text-center">
                                <a class="btn" style="padding:6px 10px;" href="sale_receipt.php?id=<?= (int)$row['id'] ?>">ดู</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>