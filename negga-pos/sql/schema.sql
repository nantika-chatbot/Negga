CREATE DATABASE IF NOT EXISTS negga_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE negga_pos;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    barcode VARCHAR(100) DEFAULT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    unit_name VARCHAR(50) NOT NULL DEFAULT 'ชิ้น',
    category_id INT NOT NULL,
    cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 7.00,
    vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    min_stock INT NOT NULL DEFAULT 0,
    stock_qty INT NOT NULL DEFAULT 0,
    image_path VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    points INT NOT NULL DEFAULT 0,
    total_spent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_no VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    vat_amount DECIMAL(10,2) NOT NULL,
    grand_total DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','transfer','card','qr') NOT NULL DEFAULT 'cash',
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password_hash, full_name, role)
VALUES
('admin', '$2y$12$snCyvE7lNNC9ctEURlP9YeaIJSYfQgxE2vfTYfkGL9LlJ10Di0bqy', 'Administrator', 'admin')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO categories (name) VALUES
('อาหารเสริม'),
('เครื่องดื่ม'),
('เสื้อผ้า')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (sku, barcode, name, unit_name, category_id, cost_price, vat_rate, vat_amount, price, min_stock, stock_qty, image_path, status) VALUES
('NG001', 'NG001', 'Bito Faem Negga 46', 'กล่อง', 1, 1168.22, 7.00, 81.78, 1250.00, 100, 100, NULL, 'active'),
('NG002', 'NG002', 'Bito Faem Negga 47', 'กล่อง', 1, 831.78, 7.00, 58.22, 890.00, 100, 100, NULL, 'active'),
('NG003', 'NG003', 'Bito Faem Negga 48', 'ขวด', 1, 644.86, 7.00, 45.14, 690.00, 100, 100, NULL, 'active'),
('NG004', 'NG004', 'Nicoplus Nad Small', 'กระปุก', 1, 915.89, 7.00, 64.11, 980.00, 100, 100, NULL, 'active'),
('NG005', 'NG005', 'Nicoplus Nad Big', 'กระปุก', 1, 2663.55, 7.00, 186.45, 2850.00, 100, 100, NULL, 'active'),
('NG006', 'NG006', 'Midass Coffee', 'ถุง', 2, 327.10, 7.00, 22.90, 350.00, 100, 100, NULL, 'active'),
('NG007', 'NG007', 'เสื้อยืดขาว NEGGA', 'ตัว', 3, 149.53, 7.00, 10.47, 160.00, 100, 100, NULL, 'active'),
('NG008', 'NG008', 'เสื้อคอปกเขียว NEGGA', 'ตัว', 3, 233.64, 7.00, 16.36, 250.00, 100, 100, NULL, 'active')
ON DUPLICATE KEY UPDATE
    barcode = VALUES(barcode),
    name = VALUES(name),
    unit_name = VALUES(unit_name),
    category_id = VALUES(category_id),
    cost_price = VALUES(cost_price),
    vat_rate = VALUES(vat_rate),
    vat_amount = VALUES(vat_amount),
    price = VALUES(price),
    min_stock = VALUES(min_stock),
    stock_qty = VALUES(stock_qty),
    status = VALUES(status);

INSERT INTO customers (name, phone, points, total_spent) VALUES
('ลูกค้าทั่วไป', '0000000000', 0, 0.00),
('สมชาย ใจดี', '0812345678', 120, 1200.00),
('วิภา สุขใจ', '0899999999', 200, 2500.00)
ON DUPLICATE KEY UPDATE name = VALUES(name);
