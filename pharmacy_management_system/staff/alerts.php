<?php
$title = 'Alerts';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require_login();

$run = fn(string $where, string $order) =>
    $pdo->query("SELECT * FROM medicines WHERE active = 1 AND $where ORDER BY $order")->fetchAll();

$expired  = $run('expiry_date < CURDATE()', 'expiry_date');
$expiring = $run('expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)', 'expiry_date');
$out      = $run('quantity = 0', 'name');
$low      = $run('quantity > 0 AND quantity <= low_stock', 'quantity');

require __DIR__ . '/../includes/header.php';

function alert_table(array $rows, string $empty, string $col, string $cls): void {
    if (!$rows) { echo '<p class="muted">' . e($empty) . '</p>'; return; }
    echo '<table><tr><th>Code</th><th>Medicine</th><th>' . e($col === 'expiry_date' ? 'Expiry date' : 'Left in stock') . '</th></tr>';
    foreach ($rows as $m) {
        echo '<tr><td>' . e($m['code']) . '</td><td>' . e($m['name']) . '</td><td><span class="' . $cls . '">' . e($m[$col]) . '</span></td></tr>';
    }
    echo '</table>';
}
?>
<div class="hero"><h1>Medicine Alerts</h1></div>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
  <div class="card"><h2>Expired (remove from shelf)</h2><?php alert_table($expired, 'No expired medicines.', 'expiry_date', 'expired'); ?></div>
  <div class="card"><h2>Expiring within 30 days</h2><?php alert_table($expiring, 'Nothing expires in the next 30 days.', 'expiry_date', 'low'); ?></div>
  <div class="card"><h2>Out of stock</h2><?php alert_table($out, 'Nothing is out of stock.', 'quantity', 'expired'); ?></div>
  <div class="card"><h2>Low stock</h2><?php alert_table($low, 'No low-stock medicines.', 'quantity', 'low'); ?></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
