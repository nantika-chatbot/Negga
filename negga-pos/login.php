<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

function login_first_existing(array $columns, array $names): ?string {
    foreach ($names as $name) {
        if (in_array($name, $columns, true)) {
            return $name;
        }
    }
    return null;
}

$userColumns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

$idCol       = login_first_existing($userColumns, ['id']);
$usernameCol = login_first_existing($userColumns, ['username', 'user_name', 'login']);
$passwordCol = login_first_existing($userColumns, ['password_hash', 'password']);
$nameCol     = login_first_existing($userColumns, ['full_name', 'name']);
$roleCol     = login_first_existing($userColumns, ['role']);
$statusCol   = login_first_existing($userColumns, ['status']);

if (!$idCol || !$usernameCol || !$passwordCol) {
    die('โครงสร้างตาราง users ไม่รองรับการเข้าสู่ระบบ');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE `$usernameCol` = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($statusCol && (($user[$statusCol] ?? 'active') !== 'active')) {
            $error = 'บัญชีนี้ถูกปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
        } else {
            $storedPassword = $user[$passwordCol] ?? '';
            $passwordOk = false;

            if ($storedPassword !== '') {
                if (password_verify($password, $storedPassword)) {
                    $passwordOk = true;
                } elseif ($password === $storedPassword) {
                    $passwordOk = true;
                }
            }

            if ($passwordOk) {
                $_SESSION['user'] = [
                    'id' => (int)$user[$idCol],
                    'username' => $user[$usernameCol] ?? '',
                    'full_name' => $nameCol ? ($user[$nameCol] ?? ($user[$usernameCol] ?? '')) : ($user[$usernameCol] ?? ''),
                    'role' => $roleCol ? ($user[$roleCol] ?? 'staff') : 'staff',
                ];

                redirect('index.php');
                exit;
            }
        }
    }

    if ($error === '') {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ - NEGGA POS</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
    <form method="post" class="login-card">
        <div class="login-title">NEGGA POS</div>
        <div class="login-sub">เข้าสู่ระบบเพื่อใช้งานระบบขายสินค้าและสต็อก</div>

        <?php if ($error): ?>
            <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="form-group mb-3">
            <label>ชื่อผู้ใช้</label>
            <input class="input w-full" type="text" name="username" required value="<?= e($_POST['username'] ?? 'admin') ?>">
        </div>

        <div class="form-group mb-4">
            <label>รหัสผ่าน</label>
            <input class="input w-full" type="password" name="password" required placeholder="กรอกรหัสผ่าน">
        </div>

        <button class="btn primary w-full" type="submit">เข้าสู่ระบบ</button>
        
    </form>
</div>
</body>
</html>