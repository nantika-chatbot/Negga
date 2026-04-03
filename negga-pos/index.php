<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$stats = dashboard_stats($pdo);

$incomeStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN grand_total ELSE 0 END), 0) AS daily_income,
        COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) 
                          AND MONTH(created_at) = MONTH(CURDATE()) THEN grand_total ELSE 0 END), 0) AS monthly_income,
        COALESCE(SUM(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) THEN grand_total ELSE 0 END), 0) AS yearly_income
    FROM sales
");
$incomeStmt->execute();
$incomeStats = $incomeStmt->fetch();

$salesSummaryStmt = $pdo->prepare("
    SELECT 
        p.name,
        SUM(si.qty) AS total_qty
    FROM sales s
    JOIN sale_items si ON s.id = si.sale_id
    JOIN products p ON si.product_id = p.id
    WHERE DATE(s.created_at) = CURDATE()
    GROUP BY si.product_id, p.name
    ORDER BY total_qty DESC, p.name ASC
");
$salesSummaryStmt->execute();
$salesSummary = $salesSummaryStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="stats">
    <div class="card">
        <div class="stat-label">ยอดขายวันนี้</div>
        <div class="stat-value">฿<?= money($stats['today_sales'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">จำนวนบิลวันนี้</div>
        <div class="stat-value"><?= (int)($stats['bill_count'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">สินค้าคงเหลือรวม</div>
        <div class="stat-value"><?= (int)($stats['stock_count'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">จำนวนสินค้าที่ขายได้</div>
        <div class="stat-value"><?= (int)($stats['product_count'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">รายได้วันนี้</div>
        <div class="stat-value">฿<?= money($incomeStats['daily_income'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">รายได้เดือนนี้</div>
        <div class="stat-value">฿<?= money($incomeStats['monthly_income'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">รายได้ปีนี้</div>
        <div class="stat-value">฿<?= money($incomeStats['yearly_income'] ?? 0) ?></div>
    </div>
</div>

<div class="card" style="margin-top:18px;">
    <h3 style="margin-top:0;">สรุปยอดขายวันนี้ (แยกสินค้า)</h3>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>สินค้า</th>
                    <th class="text-right">จำนวนที่ขาย</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$salesSummary): ?>
                    <tr>
                        <td colspan="2">ยังไม่มีการขายวันนี้</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($salesSummary as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td class="text-right"><?= (int)$row['total_qty'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>