<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$rangeType = $_GET['range_type'] ?? 'day';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$dateUntil = $_GET['date_until'] ?? date('Y-m-d');

$where = [];
$params = [];

switch ($rangeType) {
    case 'month':
        $where[] = "YEAR(s.created_at) = YEAR(CURDATE())";
        $where[] = "MONTH(s.created_at) = MONTH(CURDATE())";
        break;

    case 'year':
        $where[] = "YEAR(s.created_at) = YEAR(CURDATE())";
        break;

    case 'today_to_date':
        if ($dateUntil < date('Y-m-d')) {
            $dateUntil = date('Y-m-d');
        }
        $where[] = "DATE(s.created_at) BETWEEN CURDATE() AND :date_until";
        $params[':date_until'] = $dateUntil;
        break;

    case 'custom':
        if ($dateFrom > $dateTo) {
            $tmp = $dateFrom;
            $dateFrom = $dateTo;
            $dateTo = $tmp;
        }
        $where[] = "DATE(s.created_at) BETWEEN :date_from AND :date_to";
        $params[':date_from'] = $dateFrom;
        $params[':date_to'] = $dateTo;
        break;

    case 'day':
    default:
        $where[] = "DATE(s.created_at) = CURDATE()";
        break;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$salesColumns = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);
$hasShipping = in_array('shipping_amount', $salesColumns, true);

$shippingField = $hasShipping ? "COALESCE(SUM(s.shipping_amount),0)" : "0";

$summarySql = "
    SELECT
        COUNT(*) AS bill_count,
        COALESCE(SUM(s.subtotal), 0) AS total_subtotal,
        {$shippingField} AS total_shipping,
        COALESCE(SUM(s.grand_total), 0) AS total_sales,
        COALESCE(AVG(s.grand_total), 0) AS avg_bill
    FROM sales s
    $whereSql
";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$topSql = "
    SELECT
        si.product_name,
        SUM(si.qty) AS total_qty,
        SUM(si.line_total) AS total_amount
    FROM sale_items si
    INNER JOIN sales s ON s.id = si.sale_id
    $whereSql
    GROUP BY si.product_name
    ORDER BY total_qty DESC, total_amount DESC, si.product_name ASC
    LIMIT 10
";
$topStmt = $pdo->prepare($topSql);
$topStmt->execute($params);
$topProducts = $topStmt->fetchAll();

$chartSql = "
    SELECT
        DATE(s.created_at) AS sale_date,
        COALESCE(SUM(s.grand_total), 0) AS total_amount
    FROM sales s
    $whereSql
    GROUP BY DATE(s.created_at)
    ORDER BY DATE(s.created_at) ASC
";
$chartStmt = $pdo->prepare($chartSql);
$chartStmt->execute($params);
$chartRows = $chartStmt->fetchAll();

$labels = [];
$totals = [];
foreach ($chartRows as $row) {
    $labels[] = $row['sale_date'];
    $totals[] = (float)$row['total_amount'];
}

$bestProduct = $topProducts[0]['product_name'] ?? '-';

require __DIR__ . '/includes/header.php';
?>

<div class="toolbar" style="margin-bottom:18px; align-items:flex-end; flex-wrap:wrap;">
    <div>
        
        <div class="mini-text">ดูรายได้รายวัน รายเดือน รายปี และเลือกช่วงวันที่ได้</div>
    </div>
</div>

<div class="card" style="margin-bottom:18px;">
    <form method="get" class="toolbar" style="gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="min-width:220px;">
            <label>ประเภทช่วงเวลา</label>
            <select class="select w-full" name="range_type" id="rangeType" onchange="toggleRangeFields()">
                <option value="day" <?= $rangeType === 'day' ? 'selected' : '' ?>>วันนี้</option>
                <option value="month" <?= $rangeType === 'month' ? 'selected' : '' ?>>เดือนนี้</option>
                <option value="year" <?= $rangeType === 'year' ? 'selected' : '' ?>>ปีนี้</option>
                <option value="today_to_date" <?= $rangeType === 'today_to_date' ? 'selected' : '' ?>>ตั้งแต่วันนี้ถึงวันที่เลือก</option>
                <option value="custom" <?= $rangeType === 'custom' ? 'selected' : '' ?>>กำหนดช่วงเอง</option>
            </select>
        </div>

        <div class="form-group" id="todayToDateWrap" style="min-width:200px; display:none;">
            <label>ถึงวันที่</label>
            <input class="input w-full" type="date" name="date_until" value="<?= e($dateUntil) ?>">
        </div>

        <div class="form-group" id="dateFromWrap" style="min-width:200px; display:none;">
            <label>วันที่เริ่ม</label>
            <input class="input w-full" type="date" name="date_from" value="<?= e($dateFrom) ?>">
        </div>

        <div class="form-group" id="dateToWrap" style="min-width:200px; display:none;">
            <label>วันที่สิ้นสุด</label>
            <input class="input w-full" type="date" name="date_to" value="<?= e($dateTo) ?>">
        </div>

        <div>
            <button class="btn primary" type="submit">ดูรายงาน</button>
        </div>

        <div>
            <a class="btn" href="reports.php">รีเซ็ต</a>
        </div>
    </form>
</div>

<div class="stats">
    <div class="card">
        <div class="stat-label">ยอดรวมสุทธิ</div>
        <div class="stat-value">฿<?= money($summary['total_sales'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">ยอดสินค้า</div>
        <div class="stat-value">฿<?= money($summary['total_subtotal'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">ค่าส่ง</div>
        <div class="stat-value">฿<?= money($summary['total_shipping'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">จำนวนบิล</div>
        <div class="stat-value"><?= (int)($summary['bill_count'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">เฉลี่ย/บิล</div>
        <div class="stat-value">฿<?= money($summary['avg_bill'] ?? 0) ?></div>
    </div>

    <div class="card">
        <div class="stat-label">สินค้าขายดี</div>
        <div class="stat-value"><?= e($bestProduct) ?></div>
    </div>
</div>

<div class="two-col" style="margin-top:18px;">
    <div class="card">
        <h3 style="margin-top:0;">กราฟยอดขาย</h3>
        <canvas id="salesChart" height="140"></canvas>
    </div>

    <div class="card" style="min-height:520px;">
    <h3 style="margin-top:0;">กราฟสินค้าขายดี</h3>
    <div style="position:relative; height:<?= max(320, count($topProducts) * 48) ?>px;">
        <canvas id="topProductsChart"></canvas>
    </div>
</div>
</div>

<div class="card" style="margin-top:18px;">
    <h3 style="margin-top:0;">สรุปสินค้าขาย</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>สินค้า</th>
                    <th class="text-right">จำนวนที่ขาย</th>
                    <th class="text-right">ยอดขายรวม</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$topProducts): ?>
                    <tr>
                        <td colspan="3">ไม่พบข้อมูลในช่วงเวลาที่เลือก</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($topProducts as $row): ?>
                        <tr>
                            <td><?= e($row['product_name']) ?></td>
                            <td class="text-right"><?= (int)$row['total_qty'] ?></td>
                            <td class="text-right">฿<?= money($row['total_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function toggleRangeFields() {
    const type = document.getElementById('rangeType').value;
    document.getElementById('todayToDateWrap').style.display = (type === 'today_to_date') ? '' : 'none';
    document.getElementById('dateFromWrap').style.display = (type === 'custom') ? '' : 'none';
    document.getElementById('dateToWrap').style.display = (type === 'custom') ? '' : 'none';
}
toggleRangeFields();

const salesLabels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
const salesTotals = <?= json_encode($totals, JSON_UNESCAPED_UNICODE) ?>;

new Chart(document.getElementById('salesChart'), {
    type: 'line',
    data: {
        labels: salesLabels,
        datasets: [{
            label: 'ยอดขาย',
            data: salesTotals,
            tension: 0.3,
            fill: true,
            borderColor: '#16a34a',
            backgroundColor: 'rgba(34, 197, 94, 0.15)',
            pointBackgroundColor: '#16a34a',
            pointBorderColor: '#16a34a',
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true,
                labels: {
                    color: '#166534',
                    font: {
                        size: 13,
                        weight: 'bold'
                    }
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: '#166534'
                },
                grid: {
                    color: 'rgba(34, 197, 94, 0.08)'
                }
            },
            y: {
                ticks: {
                    color: '#166534'
                },
                grid: {
                    color: 'rgba(34, 197, 94, 0.08)'
                }
            }
        }
    }
});
new Chart(document.getElementById('topProductsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($topProducts, 'product_name'), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'จำนวนที่ขาย',
            data: <?= json_encode(array_map('intval', array_column($topProducts, 'total_qty')), JSON_UNESCAPED_UNICODE) ?>,
            backgroundColor: '#22c55e',
            borderColor: '#16a34a',
            borderWidth: 1,
            borderRadius: 8,
            barThickness: 24
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                labels: {
                    color: '#166534',
                    font: {
                        size: 13,
                        weight: 'bold'
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    color: '#166534',
                    precision: 0
                },
                grid: {
                    color: 'rgba(34, 197, 94, 0.12)'
                }
            },
            y: {
                ticks: {
                    color: '#166534',
                    font: {
                        size: 12
                    }
                },
                grid: {
                    display: false
                }
            }
        }
    }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>