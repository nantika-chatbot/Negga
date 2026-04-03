<?php
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$flashSuccess = flash('success');
$flashError = flash('error');

$pageName = match ($currentPage) {
    'index.php' => 'ภาพรวมระบบ',
    'products.php' => 'รายการสินค้าและสต็อก',
    'product_create.php' => 'เพิ่มสินค้าใหม่',
    'product_edit.php' => 'แก้ไขสินค้า',
    'pos.php' => 'ขายหน้าร้าน (POS)',
    'customers.php' => 'ระบบสมาชิก',
    'sales.php' => 'ประวัติการขาย',
    'sale_receipt.php' => 'ใบเสร็จ',
    'reports.php' => 'รายงานกราฟยอดขาย',
    'employees.php' => 'จัดการพนักงาน',
    'categories.php' => 'จัดการประเภทสินค้า',
    'finance.php' => 'ข้อมูลทางการเงิน',
    default => 'NEGGA POS'
};
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="layout">
<div class="sidebar">
  <div class="sidebar-top">
    <div class="brand-box">
      <img src="assets/logo.png" alt="Logo" class="brand-logo">
      <h2 class="brand-title">Negga Lucky</h2>
      <div class="mini-text">ผู้ใช้งาน: <?= e($_SESSION['user']['username'] ?? 'Admin') ?></div>
    </div>

    <nav class="nav-menu">
      <a href="index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-gauge-high"></i><span>Dashboard</span>
      </a>

      <?php if (is_admin()): ?>
      <a href="employees.php" class="nav-link <?= $currentPage === 'employees.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-users"></i><span>จัดการพนักงาน</span>
      </a>
      <?php endif; ?>

      <a href="pos.php" class="nav-link <?= $currentPage === 'pos.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-cart-shopping"></i><span>ขายหน้าร้าน (POS)</span>
      </a>
      <a href="products.php" class="nav-link <?= $currentPage === 'products.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-box-open"></i><span>จัดการสินค้า/สต็อก</span>
      </a>
      <a href="categories.php" class="nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-tags"></i><span>จัดการประเภทสินค้า</span>
      </a>
      <a href="customers.php" class="nav-link <?= $currentPage === 'customers.php' ? 'active' : '' ?>">
        <i class="fa-regular fa-id-card"></i><span>ระบบสมาชิก/แต้ม</span>
      </a>
      <a href="finance.php" class="nav-link <?= $currentPage === 'finance.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-money-bill-wave"></i><span>ข้อมูลทางการเงิน</span>
      </a>
      <a href="reports.php" class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-line"></i><span>สรุปยอดขาย</span>
      </a>
    </nav>

    <div class="sidebar-bottom">
      <a href="logout.php" class="nav-link logout-link">
        <i class="fa-solid fa-right-from-bracket"></i><span>ออกจากระบบ</span>
      </a>
    </div>
  </div>
</div>

<main class="main">
    <div class="topbar">
        <div class="page-title"><?= e($pageName) ?></div>
        <div class="topbar-right">
            <span class="badge success">ออนไลน์</span>
            <span class="mini-text"><?= date('d/m/Y H:i') ?></span>
        </div>
    </div>
    <div class="content">
        <?php if ($flashSuccess): ?>
            <div class="alert success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <div class="alert error"><?= e($flashError) ?></div>
        <?php endif; ?>