<?php
function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($amount): string
{
    return number_format((float)$amount, 2);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }

    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    return null;
}

function generate_sale_no(PDO $pdo): string
{
    $prefix = 'SALE' . date('Ymd');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn() + 1;
    return $prefix . '-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
}

function get_categories(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
}

function get_customers(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM customers ORDER BY name ASC')->fetchAll();
}

function get_products(PDO $pdo, string $search = '', string $status = '', int $categoryId = 0): array
{
    $sql = "SELECT p.*, c.name AS category_name
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search OR COALESCE(p.barcode,'') LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }

    if ($status !== '') {
        $sql .= " AND p.status = :status";
        $params['status'] = $status;
    }

    if ($categoryId > 0) {
        $sql .= " AND p.category_id = :category_id";
        $params['category_id'] = $categoryId;
    }

    $sql .= " ORDER BY p.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_product_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function dashboard_stats(PDO $pdo): array
{
    $todaySales = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $billCount = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stockCount = $pdo->query("SELECT COALESCE(SUM(stock_qty), 0) FROM products WHERE status = 'active'")->fetchColumn();
    $productCount = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();

    return [
        'today_sales' => $todaySales,
        'bill_count' => $billCount,
        'stock_count' => $stockCount,
        'product_count' => $productCount,
    ];
}

function payment_method_label(string $method): string
{
    return match ($method) {
        'cash' => 'เงินสด',
        'transfer' => 'โอนเงิน',
        'card' => 'บัตร',
        'qr' => 'QR',
        default => $method,
    };
}

function product_status_badge(int $stockQty, string $status, int $minStock = 0): array
{
    if ($status === 'inactive') {
        return ['class' => 'inactive', 'label' => 'ปิดใช้งาน'];
    }
    if ($stockQty <= 0) {
        return ['class' => 'inactive', 'label' => 'หมด'];
    }
    if ($minStock > 0 && $stockQty <= $minStock) {
        return ['class' => 'low', 'label' => 'ต่ำกว่าขั้นต่ำ'];
    }
    return ['class' => 'normal', 'label' => 'ปกติ'];
}

function upload_product_image(array $file, ?string $current = null): ?string
{
    if (empty($file['name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $current;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดรูปสินค้าไม่สำเร็จ');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $mime = mime_content_type($file['tmp_name']) ?: '';
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('รองรับเฉพาะไฟล์รูป JPG, PNG, WEBP หรือ GIF');
    }

    $uploadDir = __DIR__ . '/../uploads/products';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้');
    }

    $filename = 'product_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('ไม่สามารถบันทึกรูปสินค้าได้');
    }

    if ($current) {
        $oldFile = __DIR__ . '/../' . ltrim($current, '/');
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    return 'uploads/products/' . $filename;
}
