<?php
require_once __DIR__ . '/includes/auth.php';

function customer_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
    ");
    $stmt->execute([
        'table' => $table,
        'column' => $column,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function generate_member_code(PDO $pdo): string
{
    do {
        $code = 'MB' . date('Ymd') . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE member_code = :code');
        $stmt->execute(['code' => $code]);
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists);

    return $code;
}

$hasMemberCode = customer_column_exists($pdo, 'customers', 'member_code');

$editId = (int)($_GET['edit'] ?? 0);
$editingCustomer = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $memberCode = trim($_POST['member_code'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');

    if ($action === 'delete') {
        $deleteId = (int)($_POST['id'] ?? 0);

        if ($deleteId <= 0) {
            flash('error', 'ข้อมูลไม่ถูกต้อง');
            redirect('customers.php');
        }

        $stmt = $pdo->prepare('DELETE FROM customers WHERE id = :id');
        $stmt->execute(['id' => $deleteId]);

        flash('success', 'ลบข้อมูลลูกค้าเรียบร้อยแล้ว');
        redirect('customers.php');
    }

    if ($name === '') {
        flash('error', 'กรุณากรอกชื่อลูกค้า');
        redirect($action === 'update' ? 'customers.php?edit=' . (int)($_POST['id'] ?? 0) : 'customers.php');
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            flash('error', 'ข้อมูลไม่ถูกต้อง');
            redirect('customers.php');
        }

        if ($hasMemberCode) {
            if ($memberCode === '') {
                $memberCode = generate_member_code($pdo);
            } else {
                $checkStmt = $pdo->prepare('
                    SELECT COUNT(*)
                    FROM customers
                    WHERE member_code = :member_code
                      AND id <> :id
                ');
                $checkStmt->execute([
                    'member_code' => $memberCode,
                    'id' => $id,
                ]);

                if ((int)$checkStmt->fetchColumn() > 0) {
                    flash('error', 'เลขสมาชิกนี้ถูกใช้งานแล้ว');
                    redirect('customers.php?edit=' . $id);
                }
            }

            $stmt = $pdo->prepare('
                UPDATE customers
                SET member_code = :member_code,
                    name = :name,
                    phone = :phone,
                    address = :address
                WHERE id = :id
            ');
            $stmt->execute([
                'id' => $id,
                'member_code' => $memberCode,
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
            ]);
        } else {
            $stmt = $pdo->prepare('
                UPDATE customers
                SET name = :name,
                    phone = :phone,
                    address = :address
                WHERE id = :id
            ');
            $stmt->execute([
                'id' => $id,
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
            ]);
        }

        flash('success', 'แก้ไขข้อมูลลูกค้าเรียบร้อยแล้ว');
        redirect('customers.php');
    }

    if ($action === 'create') {
        if ($hasMemberCode) {
            if ($memberCode === '') {
                $memberCode = generate_member_code($pdo);
            } else {
                $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE member_code = :member_code');
                $checkStmt->execute(['member_code' => $memberCode]);

                if ((int)$checkStmt->fetchColumn() > 0) {
                    flash('error', 'เลขสมาชิกนี้ถูกใช้งานแล้ว');
                    redirect('customers.php');
                }
            }

            $stmt = $pdo->prepare('
                INSERT INTO customers (member_code, name, phone, address, points, total_spent)
                VALUES (:member_code, :name, :phone, :address, 0, 0)
            ');
            $stmt->execute([
                'member_code' => $memberCode,
                'name'        => $name,
                'phone'       => $phone,
                'address'     => $address,
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO customers (name, phone, address, points, total_spent)
                VALUES (:name, :phone, :address, 0, 0)
            ');
            $stmt->execute([
                'name'    => $name,
                'phone'   => $phone,
                'address' => $address,
            ]);
        }

        flash('success', 'เพิ่มสมาชิกเรียบร้อยแล้ว');
        redirect('customers.php');
    }
}

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $editId]);
    $editingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editingCustomer) {
        flash('error', 'ไม่พบข้อมูลลูกค้าที่ต้องการแก้ไข');
        redirect('customers.php');
    }
}

$search = trim($_GET['search'] ?? '');

$sql = 'SELECT * FROM customers WHERE 1=1';
$params = [];

if ($search !== '') {
    if ($hasMemberCode) {
        $sql .= ' AND (member_code LIKE :search OR name LIKE :search OR phone LIKE :search OR address LIKE :search)';
    } else {
        $sql .= ' AND (name LIKE :search OR phone LIKE :search OR address LIKE :search)';
    }

    $params['search'] = '%' . $search . '%';
}

$sql .= ' ORDER BY id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>

<div class="card mb-4">
    <form class="toolbar" method="get">
        <div class="filters">
            <input
                class="input"
                type="text"
                name="search"
                placeholder="ค้นหาเลขสมาชิก ชื่อลูกค้า เบอร์โทร หรือที่อยู่"
                value="<?= e($search) ?>"
            >
            <button class="btn primary" type="submit">ค้นหา</button>
        </div>
    </form>
</div>

<div class="card mb-4">
    <h3 style="margin-top:0"><?= $editingCustomer ? 'แก้ไขข้อมูลลูกค้า' : 'เพิ่มสมาชิกใหม่' ?></h3>

    <form method="post" class="form-grid form-grid-2">
        <input type="hidden" name="action" value="<?= $editingCustomer ? 'update' : 'create' ?>">
        <?php if ($editingCustomer): ?>
            <input type="hidden" name="id" value="<?= (int)$editingCustomer['id'] ?>">
        <?php endif; ?>

        <?php if ($hasMemberCode): ?>
            <div class="form-group">
                <label>เลขสมาชิก</label>
                <input
                    class="input"
                    name="member_code"
                    value="<?= e($editingCustomer['member_code'] ?? ($_POST['member_code'] ?? '')) ?>"
                    placeholder="กรอกเลขสมาชิก หรือปล่อยว่างเพื่อสร้างอัตโนมัติ"
                >
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label>ชื่อลูกค้า</label>
            <input
                class="input"
                name="name"
                required
                value="<?= e($editingCustomer['name'] ?? ($_POST['name'] ?? '')) ?>"
            >
        </div>

        <div class="form-group">
            <label>เบอร์โทรศัพท์</label>
            <input
                class="input"
                name="phone"
                value="<?= e($editingCustomer['phone'] ?? ($_POST['phone'] ?? '')) ?>"
            >
        </div>

        <div class="form-group full-row">
            <label for="address">ที่อยู่</label>
            <textarea
                id="address"
                name="address"
                class="input"
                rows="3"
                style="height:auto;padding:12px 14px;"
            ><?= e($editingCustomer['address'] ?? ($_POST['address'] ?? '')) ?></textarea>
        </div>

        <div class="form-group full-row" style="display:flex;align-items:end;gap:10px;flex-wrap:wrap;">
            <button class="btn primary" type="submit">
                <?= $editingCustomer ? 'บันทึกการแก้ไข' : 'เพิ่มสมาชิก' ?>
            </button>

            <?php if ($editingCustomer): ?>
                <a class="btn" href="customers.php">ยกเลิก</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <?php if ($hasMemberCode): ?>
                    <th>เลขสมาชิก</th>
                <?php endif; ?>
                <th>ชื่อ</th>
                <th>โทรศัพท์</th>
                <th>ที่อยู่</th>
                <th>คะแนน</th>
                <th class="text-right">ยอดใช้จ่ายรวม</th>
                <th class="text-center">จัดการ</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $customer): ?>
            <tr>
                <?php if ($hasMemberCode): ?>
                    <td><?= e($customer['member_code'] ?? '-') ?></td>
                <?php endif; ?>
                <td><?= e($customer['name']) ?></td>
                <td><?= e($customer['phone']) ?></td>
                <td><?= nl2br(e($customer['address'] ?? '-')) ?></td>
                <td><?= (int)$customer['points'] ?></td>
                <td class="text-right">฿<?= money($customer['total_spent']) ?></td>
                <td class="text-center">
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                        <a
                            href="customers.php?edit=<?= (int)$customer['id'] ?>"
                            class="btn"
                            style="text-decoration:none;"
                        >
                            แก้ไข
                        </a>

                        <form method="post" onsubmit="return confirm('ยืนยันการลบข้อมูลลูกค้าคนนี้?');" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$customer['id'] ?>">
                            <button class="btn danger" type="submit">ลบ</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (!$customers): ?>
            <tr>
                <td colspan="<?= $hasMemberCode ? 7 : 6 ?>" class="text-center">ไม่พบข้อมูลลูกค้า</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>