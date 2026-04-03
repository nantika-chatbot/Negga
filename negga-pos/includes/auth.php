<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_login();

function auth_first_existing(array $columns, array $names): ?string {
    foreach ($names as $name) {
        if (in_array($name, $columns, true)) {
            return $name;
        }
    }
    return null;
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function current_user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function is_admin(): bool {
    return current_user_role() === 'admin';
}

function require_admin(): void {
    if (!is_admin()) {
        flash('error', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        redirect('index.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| ตรวจสอบสถานะ user จากฐานข้อมูลทุกครั้ง
|--------------------------------------------------------------------------
| ถ้าผู้ใช้ถูกปิดใช้งาน หรือถูกลบระหว่างที่ยังมี session อยู่
| ให้ logout อัตโนมัติ
*/
if (isset($_SESSION['user']['id']) && isset($pdo)) {
    try {
        $userColumns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

        $idCol     = auth_first_existing($userColumns, ['id']);
        $roleCol   = auth_first_existing($userColumns, ['role']);
        $statusCol = auth_first_existing($userColumns, ['status']);
        $nameCol   = auth_first_existing($userColumns, ['full_name', 'name']);
        $userCol   = auth_first_existing($userColumns, ['username', 'user_name', 'login']);

        if ($idCol) {
            $selectParts = ["`$idCol` AS id"];

            if ($roleCol) {
                $selectParts[] = "`$roleCol` AS role";
            }

            if ($statusCol) {
                $selectParts[] = "`$statusCol` AS status";
            }

            if ($nameCol) {
                $selectParts[] = "`$nameCol` AS full_name";
            }

            if ($userCol) {
                $selectParts[] = "`$userCol` AS username";
            }

            $stmt = $pdo->prepare(
                "SELECT " . implode(', ', $selectParts) . " FROM users WHERE `$idCol` = :id LIMIT 1"
            );
            $stmt->execute([
                ':id' => (int)$_SESSION['user']['id']
            ]);

            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$dbUser) {
                session_unset();
                session_destroy();
                session_start();
                flash('error', 'ไม่พบบัญชีผู้ใช้นี้ในระบบ');
                redirect('login.php');
                exit;
            }

            if ($statusCol && (($dbUser['status'] ?? 'active') !== 'active')) {
                session_unset();
                session_destroy();
                session_start();
                flash('error', 'บัญชีนี้ถูกปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
                redirect('login.php');
                exit;
            }

            $_SESSION['user']['id'] = (int)$dbUser['id'];

            if (isset($dbUser['username'])) {
                $_SESSION['user']['username'] = $dbUser['username'];
            }

            if (isset($dbUser['full_name'])) {
                $_SESSION['user']['full_name'] = $dbUser['full_name'];
            }

            if (isset($dbUser['role'])) {
                $_SESSION['user']['role'] = $dbUser['role'];
            }
        }
    } catch (Throwable $e) {
        // กันหน้าเว็บพังถ้ามีปัญหาฐานข้อมูล
    }
}