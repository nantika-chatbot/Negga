<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'negga_pos');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'NEGGA POS');
define('VAT_RATE', 0.07);

date_default_timezone_set('Asia/Bangkok');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}
