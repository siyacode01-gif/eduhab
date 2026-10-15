<?php
$title = 'Dashboard';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require_login();

$one = fn(string $sql) => $pdo->query($sql)->fetch();

$today    = $one("SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM sales WHERE DATE(created_at) = CURDATE()");
$medicines = (int)$pdo->query('SELECT COUNT(*) FROM medicines WHERE active = 1')->fetchColumn();
$low       = (int)$pdo->query('SELECT COUNT(*) FROM medicines WHERE active = 1 AND quantity <= low_stock')->fetchColumn();
$expiry    = (int)$pdo->query('SELECT COUNT(*) FROM medicines WHERE active = 1 AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')->fetchColumn();
$pending   = (int)$pdo->query("SELECT COUNT(*) FROM prescriptions WHERE status = 'pending'")->fetchColumn();
$customers = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>
<div class="hero">
  <div>
    <h1>Dashboard</h1>
    <p class="muted">Welcome, <?= e(user()['name']) ?> (<?= e(role_label()) ?>).</p>
  </div>
  <?php if (can('sales.create')): ?><a class="btn" href="sales.php">New sale</a><?php endif; ?>
</div>

<div class="grid">
  <a class="card" href="<?= can('reports.view') ? 'reports.php' : 'sales.php' ?>"><div class="muted">Sales today</div><div class="stat"><?= money($today['t']) ?></div><div class="muted"><?= (int)$today['n'] ?> transactions</div></a>
  <a class="card" href="medicines.php"><div class="muted">Medicines in stock list</div><div class="stat"><?= $medicines ?></div></a>
  <a class="card" href="alerts.php"><div class="muted">Low or out of stock</div><div class="stat"><?= $low ?></div></a>
  <a class="card" href="alerts.php"><div class="muted">Expired or expiring in 30 days</div><div class="stat"><?= $expiry ?></div></a>
  <?php if (can('prescriptions.capture')): ?>
    <a class="card" href="prescriptions.php"><div class="muted">Prescriptions waiting</div><div class="stat"><?= $pending ?></div></a>
  <?php endif; ?>
  <?php if (can('customers.manage')): ?>
    <a class="card" href="customers.php"><div class="muted">Customers</div><div class="stat"><?= $customers ?></div></a>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
