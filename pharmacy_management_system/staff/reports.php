<?php
$title = 'Reports';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require_permission('reports.view');

$valid = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) && strtotime($d);
$from = $valid($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to   = $valid($_GET['to'] ?? '')   ? $_GET['to']   : date('Y-m-d');
$range = [$from, $to];

// CSV download of the sales in the chosen dates (opens in Excel).
if (isset($_GET['export'])) {
    $st = $pdo->prepare('SELECT s.id, s.created_at, COALESCE(c.name, "Walk-in") AS customer, u.name AS staff, s.payment_method, s.total
                         FROM sales s LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id
                         WHERE DATE(s.created_at) BETWEEN ? AND ? ORDER BY s.created_at');
    $st->execute($range);
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=sales_{$from}_to_{$to}.csv");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Receipt', 'Date', 'Customer', 'Staff', 'Payment', 'Total']);
    foreach ($st as $row) fputcsv($out, $row);
    exit;
}

$q = function (string $sql) use ($pdo, $range) { $s = $pdo->prepare($sql); $s->execute($range); return $s->fetchAll(); };

$summary = $q('SELECT COUNT(*) AS n, COALESCE(SUM(total),0) AS t FROM sales WHERE DATE(created_at) BETWEEN ? AND ?')[0];
$perDay  = $q('SELECT DATE(created_at) AS d, COUNT(*) AS n, SUM(total) AS t FROM sales WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY d ORDER BY d DESC');
$top     = $q('SELECT m.name, SUM(si.quantity) AS qty, SUM(si.quantity * si.unit_price) AS rev FROM sale_items si
               JOIN sales s ON s.id = si.sale_id JOIN medicines m ON m.id = si.medicine_id
               WHERE DATE(s.created_at) BETWEEN ? AND ? GROUP BY m.id, m.name ORDER BY qty DESC LIMIT 10');
$byStaff = $q('SELECT u.name, COUNT(*) AS n, SUM(s.total) AS t FROM sales s JOIN users u ON u.id = s.user_id
               WHERE DATE(s.created_at) BETWEEN ? AND ? GROUP BY u.id, u.name ORDER BY t DESC');
$stock   = $pdo->query('SELECT COUNT(*) AS n, COALESCE(SUM(quantity * price),0) AS v FROM medicines WHERE active = 1')->fetch();

require __DIR__ . '/../includes/header.php';
?>
<div class="hero">
  <h1>Reports</h1>
  <div class="actions no-print"><button class="btn light" onclick="window.print()">Print</button></div>
</div>

<div class="card no-print">
  <form method="get" class="form-grid">
    <div><label for="from">From</label><input id="from" type="date" name="from" value="<?= e($from) ?>"></div>
    <div><label for="to">To</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div>
    <div class="actions" style="align-self:end;margin-bottom:14px">
      <button class="btn">Show report</button>
      <a class="btn secondary" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=1">Download CSV</a>
    </div>
  </form>
</div>
<div class="spacer"></div>

<div class="grid">
  <div class="card"><div class="muted">Sales total (<?= e($from) ?> to <?= e($to) ?>)</div><div class="stat"><?= money($summary['t']) ?></div></div>
  <div class="card"><div class="muted">Transactions</div><div class="stat"><?= (int)$summary['n'] ?></div></div>
  <div class="card"><div class="muted">Average sale</div><div class="stat"><?= money($summary['n'] ? $summary['t'] / $summary['n'] : 0) ?></div></div>
  <div class="card"><div class="muted">Stock value (<?= (int)$stock['n'] ?> medicines)</div><div class="stat"><?= money($stock['v']) ?></div></div>
</div>
<div class="spacer"></div>

<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
  <div class="card"><h2>Best-selling medicines</h2>
    <table><tr><th>Medicine</th><th class="right">Units</th><th class="right">Revenue</th></tr>
    <?php foreach ($top as $r): ?><tr><td><?= e($r['name']) ?></td><td class="right"><?= (int)$r['qty'] ?></td><td class="right"><?= money($r['rev']) ?></td></tr><?php endforeach; ?>
    <?php if (!$top): ?><tr><td colspan="3" class="muted">No sales in this period.</td></tr><?php endif; ?></table></div>
  <div class="card"><h2>Sales by staff</h2>
    <table><tr><th>Staff</th><th class="right">Sales</th><th class="right">Total</th></tr>
    <?php foreach ($byStaff as $r): ?><tr><td><?= e($r['name']) ?></td><td class="right"><?= (int)$r['n'] ?></td><td class="right"><?= money($r['t']) ?></td></tr><?php endforeach; ?>
    <?php if (!$byStaff): ?><tr><td colspan="3" class="muted">No sales in this period.</td></tr><?php endif; ?></table></div>
  <div class="card"><h2>Sales per day</h2>
    <table><tr><th>Date</th><th class="right">Sales</th><th class="right">Total</th></tr>
    <?php foreach ($perDay as $r): ?><tr><td><?= e($r['d']) ?></td><td class="right"><?= (int)$r['n'] ?></td><td class="right"><?= money($r['t']) ?></td></tr><?php endforeach; ?>
    <?php if (!$perDay): ?><tr><td colspan="3" class="muted">No sales in this period.</td></tr><?php endif; ?></table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
