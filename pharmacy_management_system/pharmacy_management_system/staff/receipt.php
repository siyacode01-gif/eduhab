<?php
$title = 'Receipt';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require_permission('sales.create');

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT s.*, c.name AS customer, c.phone, u.name AS staff FROM sales s
                     LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id WHERE s.id = ?');
$st->execute([$id]);
$sale = $st->fetch();

if (!$sale) {
    flash('That receipt was not found.', 'error');
    redirect('sales.php');
}

$st = $pdo->prepare('SELECT si.*, m.name, m.code FROM sale_items si JOIN medicines m ON m.id = si.medicine_id WHERE si.sale_id = ?');
$st->execute([$id]);
$items = $st->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="card receipt">
  <h1>PharmaCare Pharmacy</h1>
  <p class="muted" style="text-align:center">Receipt #<?= (int)$sale['id'] ?></p>
  <div class="meta">
    <span>Date: <?= e($sale['created_at']) ?></span>
    <span>Served by: <?= e($sale['staff'] ?? '-') ?></span>
  </div>
  <div class="meta"><span>Customer: <?= e($sale['customer'] ?? 'Walk-in customer') ?></span><span>Payment: <?= e(role_label($sale['payment_method'])) ?></span></div>

  <table>
    <tr><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Line total</th></tr>
    <?php foreach ($items as $i): ?>
      <tr>
        <td><?= e($i['name']) ?> <span class="muted">(<?= e($i['code']) ?>)</span></td>
        <td class="right"><?= (int)$i['quantity'] ?></td>
        <td class="right"><?= money($i['unit_price']) ?></td>
        <td class="right"><?= money($i['quantity'] * $i['unit_price']) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr><th colspan="3" class="right">Total</th><th class="right"><?= money($sale['total']) ?></th></tr>
  </table>
  <p style="text-align:center" class="muted">Thank you. Please keep this receipt.</p>
  <div class="actions no-print" style="justify-content:center">
    <button class="btn" onclick="window.print()">Print receipt</button>
    <a class="btn light" href="sales.php">New sale</a>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
