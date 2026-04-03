<?php
require_once __DIR__ . '/includes/auth.php';

$q = trim($_GET['q'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$payment = trim($_GET['payment_method'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(s.sale_no LIKE :q OR COALESCE(c.name, '-') LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

if ($dateFrom !== '') {
    $where[] = "DATE(s.created_at) >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "DATE(s.created_at) <= :date_to";
    $params[':date_to'] = $dateTo;
}

if ($payment !== '') {
    $where[] = "s.payment_method = :payment_method";
    $params[':payment_method'] = $payment;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "
    SELECT COUNT(*)
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    $whereSql
";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "
    SELECT 
        s.id,
        s.sale_no,
        s.grand_total,
        s.payment_method,
        s.created_at,
        COALESCE(c.name, '-') AS customer_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    $whereSql
    ORDER BY s.id DESC
    LIMIT :limit OFFSET :offset
";
$listStmt = $pdo->prepare($listSql);

foreach ($params as $key => $value) {
    $listStmt->bindValue($key, $value);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$sales = $listStmt->fetchAll();

$paymentMethods = $pdo->query("
    SELECT DISTINCT payment_method
    FROM sales
    WHERE payment_method IS NOT NULL AND payment_method <> ''
    ORDER BY payment_method ASC
")->fetchAll(PDO::FETCH_COLUMN);

function build_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;
    return 'sales.php?' . http_build_query($query);
}

require __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
        <div>
            <h2 style="margin:0;">ประวัติการขาย</h2>
            <div style="color:#666; margin-top:4px;">ค้นหา กรอง และดูใบเสร็จย้อนหลัง</div>
        </div>
    </div>

    <form method="get" style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr auto auto; gap:10px; margin-bottom:18px;">
        <input 
            type="text" 
            name="q" 
            value="<?= e($q) ?>" 
            placeholder="ค้นหาเลขที่บิล / ชื่อลูกค้า"
            style="padding:10px; border:1px solid #ddd; border-radius:10px;"
        >

        <input 
            type="date" 
            name="date_from" 
            value="<?= e($dateFrom) ?>"
            style="padding:10px; border:1px solid #ddd; border-radius:10px;"
        >

        <input 
            type="date" 
            name="date_to" 
            value="<?= e($dateTo) ?>"
            style="padding:10px; border:1px solid #ddd; border-radius:10px;"
        >

        <select 
            name="payment_method"
            style="padding:10px; border:1px solid #ddd; border-radius:10px;"
        >
            <option value="">ทุกวิธีชำระเงิน</option>
            <?php foreach ($paymentMethods as $method): ?>
                <option value="<?= e($method) ?>" <?= $payment === $method ? 'selected' : '' ?>>
                    <?= e($method) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" style="padding:10px 16px; border:none; border-radius:10px; cursor:pointer;">
            ค้นหา
        </button>

        <a href="sales.php" style="padding:10px 16px; border:1px solid #ddd; border-radius:10px; text-decoration:none; text-align:center;">
            ล้างค่า
        </a>
    </form>

    <div style="margin-bottom:12px; color:#666;">
        พบทั้งหมด <?= number_format($totalRows) ?> รายการ
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>เลขที่บิล</th>
                    <th>ลูกค้า</th>
                    <th>ชำระเงิน</th>
                    <th>เวลา</th>
                    <th class="text-right">ยอดรวม</th>
                    <th class="text-center">ใบเสร็จ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$sales): ?>
                    <tr>
                        <td colspan="6">ไม่พบข้อมูลการขาย</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales as $row): ?>
                        <tr>
                            <td><?= e($row['sale_no']) ?></td>
                            <td><?= e($row['customer_name']) ?></td>
                            <td><?= e($row['payment_method']) ?></td>
                            <td><?= e($row['created_at']) ?></td>
                            <td class="text-right">฿<?= money($row['grand_total']) ?></td>
                            <td class="text-center">
                                <a href="sale_receipt.php?id=<?= (int)$row['id'] ?>" target="_blank">ดูใบเสร็จ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:8px; margin-top:18px; flex-wrap:wrap;">
            <?php if ($page > 1): ?>
                <a href="<?= e(build_page_url($page - 1)) ?>" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; text-decoration:none;">
                    ← ก่อนหน้า
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a 
                    href="<?= e(build_page_url($i)) ?>"
                    style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; text-decoration:none; <?= $i === $page ? 'font-weight:700; background:#f4f4f4;' : '' ?>"
                >
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= e(build_page_url($page + 1)) ?>" style="padding:8px 12px; border:1px solid #ddd; border-radius:8px; text-decoration:none;">
                    ถัดไป →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>