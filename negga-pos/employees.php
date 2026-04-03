<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$isAdmin = is_admin();

function emp_first_existing(array $columns, array $names): ?string {
    foreach ($names as $name) {
        if (in_array($name, $columns, true)) return $name;
    }
    return null;
}

$userColumns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$salesColumns = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);

$idCol       = emp_first_existing($userColumns, ['id']);
$nameCol     = emp_first_existing($userColumns, ['name', 'full_name']);
$usernameCol = emp_first_existing($userColumns, ['username', 'user_name', 'login']);
$passwordCol = emp_first_existing($userColumns, ['password', 'password_hash']);
$roleCol     = emp_first_existing($userColumns, ['role']);
$statusCol   = emp_first_existing($userColumns, ['status']);
$createdCol  = emp_first_existing($userColumns, ['created_at']);

$salesUserCol = emp_first_existing($salesColumns, ['user_id', 'created_by']);

if (!$idCol || !$usernameCol || !$passwordCol) {
    die('โครงสร้างตาราง users ไม่รองรับหน้า employees.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!$isAdmin) {
        flash('error', 'คุณไม่มีสิทธิ์ดำเนินการนี้');
        redirect('employees.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'staff');
        $status = trim($_POST['status'] ?? 'active');

        if ($username === '' || $password === '') {
            flash('error', 'กรุณากรอก username และ password');
            redirect('employees.php');
            exit;
        }

        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE `$usernameCol` = :username");
        $checkStmt->execute([':username' => $username]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            flash('error', 'username นี้ถูกใช้งานแล้ว');
            redirect('employees.php');
            exit;
        }

        $insertData = [
            $usernameCol => $username,
            $passwordCol => password_hash($password, PASSWORD_DEFAULT),
        ];

        if ($nameCol) $insertData[$nameCol] = $name !== '' ? $name : $username;
        if ($roleCol) $insertData[$roleCol] = $role;
        if ($statusCol) $insertData[$statusCol] = $status;
        if ($createdCol) $insertData[$createdCol] = date('Y-m-d H:i:s');

        $cols = array_keys($insertData);
        $sql = "INSERT INTO users (`" . implode("`,`", $cols) . "`) VALUES (:" . implode(",:", $cols) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($insertData);

        flash('success', 'เพิ่มพนักงานเรียบร้อย');
        redirect('employees.php');
        exit;
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'staff');
        $status = trim($_POST['status'] ?? 'active');

        if ($id <= 0 || $username === '') {
            flash('error', 'ข้อมูลไม่ถูกต้อง');
            redirect('employees.php');
            exit;
        }

        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE `$usernameCol` = :username AND `$idCol` != :id");
        $checkStmt->execute([
            ':username' => $username,
            ':id' => $id
        ]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            flash('error', 'username นี้ถูกใช้งานแล้ว');
            redirect('employees.php');
            exit;
        }

        $updateData = [
            $usernameCol => $username,
        ];

        if ($nameCol) $updateData[$nameCol] = $name !== '' ? $name : $username;
        if ($roleCol) $updateData[$roleCol] = $role;
        if ($statusCol) $updateData[$statusCol] = $status;
        if ($password !== '') $updateData[$passwordCol] = password_hash($password, PASSWORD_DEFAULT);

        $setParts = [];
        foreach (array_keys($updateData) as $col) {
            $setParts[] = "`$col` = :$col";
        }

        $updateData['row_id'] = $id;
        $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE `$idCol` = :row_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateData);

        flash('success', 'แก้ไขพนักงานเรียบร้อย');
        redirect('employees.php');
        exit;
    }

    if ($action === 'deactivate') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $currentUserId = (int)($_SESSION['user']['id'] ?? 0);

            if ($id === $currentUserId) {
                flash('error', 'ไม่สามารถปิดใช้งานบัญชีของตัวเองได้');
                redirect('employees.php');
                exit;
            }

            if (!$statusCol) {
                flash('error', 'ตาราง users ไม่มีคอลัมน์ status');
                redirect('employees.php');
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET `$statusCol` = :status WHERE `$idCol` = :id");
            $stmt->execute([
                ':status' => 'inactive',
                ':id' => $id
            ]);

            flash('success', 'ปิดใช้งานพนักงานเรียบร้อย');
        }

        redirect('employees.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $currentUserId = (int)($_SESSION['user']['id'] ?? 0);

            if ($id === $currentUserId) {
                flash('error', 'ไม่สามารถลบบัญชีของตัวเองได้');
                redirect('employees.php');
                exit;
            }

            $hasSales = false;
            if ($salesUserCol) {
                $salesCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE `$salesUserCol` = :id");
                $salesCheckStmt->execute([':id' => $id]);
                $hasSales = ((int)$salesCheckStmt->fetchColumn() > 0);
            }

            if ($hasSales) {
                if ($statusCol) {
                    $stmt = $pdo->prepare("UPDATE users SET `$statusCol` = :status WHERE `$idCol` = :id");
                    $stmt->execute([
                        ':status' => 'inactive',
                        ':id' => $id
                    ]);
                    flash('error', 'พนักงานนี้มีประวัติการขาย จึงลบไม่ได้ ระบบเปลี่ยนสถานะเป็น inactive แทน');
                } else {
                    flash('error', 'พนักงานนี้มีประวัติการขาย จึงลบไม่ได้');
                }

                redirect('employees.php');
                exit;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE `$idCol` = :id");
                $stmt->execute([':id' => $id]);
                flash('success', 'ลบพนักงานเรียบร้อย');
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) {
                    if ($statusCol) {
                        $stmt = $pdo->prepare("UPDATE users SET `$statusCol` = :status WHERE `$idCol` = :id");
                        $stmt->execute([
                            ':status' => 'inactive',
                            ':id' => $id
                        ]);
                        flash('error', 'ไม่สามารถลบพนักงานได้ เนื่องจากมีข้อมูลอ้างอิงในระบบ จึงเปลี่ยนสถานะเป็น inactive แทน');
                    } else {
                        flash('error', 'ไม่สามารถลบพนักงานได้ เนื่องจากมีข้อมูลอ้างอิงในระบบ');
                    }
                } else {
                    flash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
                }
            }
        }

        redirect('employees.php');
        exit;
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;

if ($editId > 0 && $isAdmin) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE `$idCol` = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $editRow = $stmt->fetch();
}

$selectParts = ["u.`$idCol` AS id"];
if ($nameCol) $selectParts[] = "u.`$nameCol` AS name";
$selectParts[] = "u.`$usernameCol` AS username";
if ($roleCol) $selectParts[] = "u.`$roleCol` AS role";
if ($statusCol) $selectParts[] = "u.`$statusCol` AS status";
if ($createdCol) $selectParts[] = "u.`$createdCol` AS created_at";

if ($salesUserCol) {
    $selectParts[] = "(
        SELECT COUNT(*)
        FROM sales s
        WHERE s.`$salesUserCol` = u.`$idCol`
    ) AS sales_count";
} else {
    $selectParts[] = "0 AS sales_count";
}

$listSql = "SELECT " . implode(', ', $selectParts) . " FROM users u ORDER BY u.`$idCol` DESC";
$rows = $pdo->query($listSql)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="card" style="margin-bottom:18px;">
    <div class="toolbar" style="justify-content:space-between; align-items:center; flex-wrap:wrap;">
        <div>
            <div class="page-title">จัดการพนักงาน</div>
            <div class="mini-text">เพิ่ม แก้ไข ลบ หรือปิดใช้งานข้อมูลพนักงานและผู้ใช้งานระบบ</div>
        </div>
    </div>
</div>

<div class="two-col">
    <?php if ($isAdmin): ?>
    <div class="card">
        <h3 style="margin-top:0;"><?= $editRow ? 'แก้ไขพนักงาน' : 'เพิ่มพนักงาน' ?></h3>

        <form method="post">
            <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
            <?php if ($editRow): ?>
                <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
            <?php endif; ?>

            <?php if ($nameCol): ?>
                <div class="form-group mb-3">
                    <label>ชื่อพนักงาน</label>
                    <input
                        class="input w-full"
                        type="text"
                        name="name"
                        value="<?= e($editRow[$nameCol] ?? $editRow['name'] ?? '') ?>"
                        placeholder="ชื่อพนักงาน"
                    >
                </div>
            <?php endif; ?>

            <div class="form-group mb-3">
                <label>Username</label>
                <input
                    class="input w-full"
                    type="text"
                    name="username"
                    required
                    value="<?= e($editRow[$usernameCol] ?? $editRow['username'] ?? '') ?>"
                    placeholder="Username"
                >
            </div>

            <div class="form-group mb-3">
                <label>Password <?= $editRow ? '(เว้นว่างได้ ถ้าไม่เปลี่ยน)' : '' ?></label>
                <input
                    class="input w-full"
                    type="password"
                    name="password"
                    <?= $editRow ? '' : 'required' ?>
                    placeholder="Password"
                >
            </div>

            <?php if ($roleCol): ?>
                <div class="form-group mb-3">
                    <label>สิทธิ์การใช้งาน</label>
                    <select class="select w-full" name="role">
                        <option value="admin" <?= (($editRow[$roleCol] ?? $editRow['role'] ?? '') === 'admin') ? 'selected' : '' ?>>admin</option>
                        <option value="staff" <?= (($editRow[$roleCol] ?? $editRow['role'] ?? 'staff') === 'staff') ? 'selected' : '' ?>>staff</option>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($statusCol): ?>
                <div class="form-group mb-3">
                    <label>สถานะ</label>
                    <select class="select w-full" name="status">
                        <option value="active" <?= (($editRow[$statusCol] ?? $editRow['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>active</option>
                        <option value="inactive" <?= (($editRow[$statusCol] ?? $editRow['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>inactive</option>
                    </select>
                </div>
            <?php endif; ?>

            <div class="toolbar" style="gap:10px;">
                <button class="btn primary" type="submit">
                    <?= $editRow ? 'บันทึกการแก้ไข' : 'เพิ่มพนักงาน' ?>
                </button>

                <?php if ($editRow): ?>
                    <a class="btn" href="employees.php">ยกเลิก</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top:0;">รายการพนักงาน</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php if ($nameCol): ?><th>ชื่อ</th><?php endif; ?>
                        <th>Username</th>
                        <?php if ($roleCol): ?><th>สิทธิ์</th><?php endif; ?>
                        <?php if ($statusCol): ?><th>สถานะ</th><?php endif; ?>
                        <th>จำนวนบิล</th>
                        <?php if ($createdCol): ?><th>วันที่สร้าง</th><?php endif; ?>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="8">ยังไม่มีข้อมูลพนักงาน</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $rowId = (int)$row['id'];
                            $salesCount = (int)($row['sales_count'] ?? 0);
                            $isSelf = $rowId === (int)($_SESSION['user']['id'] ?? 0);
                            $isInactive = ($row['status'] ?? '') === 'inactive';
                            ?>
                            <tr>
                                <td><?= $rowId ?></td>
                                <?php if ($nameCol): ?><td><?= e($row['name'] ?? '-') ?></td><?php endif; ?>
                                <td><?= e($row['username']) ?></td>
                                <?php if ($roleCol): ?><td><?= e($row['role'] ?? '-') ?></td><?php endif; ?>
                                <?php if ($statusCol): ?><td><?= e($row['status'] ?? '-') ?></td><?php endif; ?>
                                <td><?= $salesCount ?></td>
                                <?php if ($createdCol): ?><td><?= e($row['created_at'] ?? '-') ?></td><?php endif; ?>
                                <td class="text-center">
                                    <?php if ($isAdmin): ?>
                                        <div class="toolbar" style="gap:8px; justify-content:center; flex-wrap:wrap;">
                                            <a class="btn" href="employees.php?edit=<?= $rowId ?>">แก้ไข</a>

                                            <?php if ($isSelf): ?>
                                                <span style="color:#999;">ลบบัญชีตัวเองไม่ได้</span>

                                            <?php elseif ($salesCount > 0): ?>
                                                <?php if (!$isInactive && $statusCol): ?>
                                                    <form method="post" onsubmit="return confirm('พนักงานนี้มีประวัติการขาย ระบบจะปิดใช้งานแทนการลบ ต้องการดำเนินการต่อหรือไม่?');" style="display:inline;">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="id" value="<?= $rowId ?>">
                                                        <button class="btn" type="submit" style="color:#d97706;">ปิดใช้งาน</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="color:#999;">มีประวัติขายแล้ว</span>
                                                <?php endif; ?>

                                            <?php else: ?>
                                                <form method="post" onsubmit="return confirm('ยืนยันการลบพนักงานนี้?');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $rowId ?>">
                                                    <button class="btn" type="submit" style="color:#dc2626;">ลบ</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:#999;">ไม่มีสิทธิ์</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>