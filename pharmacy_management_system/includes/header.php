<?php
require_once __DIR__ . '/../config/auth.php';
require_login();

$current = basename($_SERVER['PHP_SELF']);
$links = [
    ['dashboard.php',     'Dashboard',     null],
    ['medicines.php',     'Medicines',     null],
    ['customers.php',     'Customers',     'customers.manage'],
    ['prescriptions.php', 'Prescriptions', 'prescriptions.capture'],
    ['sales.php',         'Sales',         'sales.create'],
    ['suppliers.php',     'Suppliers',     'suppliers.manage'],
    ['alerts.php',        'Alerts',        null],
    ['reports.php',       'Reports',       'reports.view'],
    ['users.php',         'Users',         'users.manage'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($title ?? 'Pharmacy Management System') ?> - PharmaCare</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div><strong>PharmaCare</strong><span> Pharmacy Management System</span></div>
  <nav>
    <?php foreach ($links as [$file, $label, $perm]): ?>
      <?php if ($perm === null || can($perm)): ?>
        <a href="<?= $file ?>"<?= $file === $current ? ' class="active"' : '' ?>><?= $label ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    <a href="profile.php"<?= $current === 'profile.php' ? ' class="active"' : '' ?>><?= e(user()['name']) ?> (<?= e(role_label()) ?>)</a>
    <a href="logout.php">Logout</a>
  </nav>
</header>
<main class="container">
<?= flash_html() ?>
