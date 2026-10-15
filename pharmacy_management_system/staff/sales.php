<?php
$title = 'Sales';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../includes/sale_service.php';
require_permission('sales.create');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        // Merge lines: the same medicine chosen twice becomes one line with the quantities added.
        $items = [];
        foreach ($_POST['medicine_id'] ?? [] as $i => $mid) {
            $mid = (int)$mid;
            $qty = (int)($_POST['quantity'][$i] ?? 0);
            if ($mid > 0 && $qty > 0) $items[$mid] = ($items[$mid] ?? 0) + $qty;
        }
        if (!$items) throw new RuntimeException('Add at least one medicine with a quantity.');

        $customerId = (int)($_POST['customer_id'] ?? 0) ?: null; // empty = walk-in customer
        $saleId = create_sale($pdo, $customerId, user_id(), $_POST['payment_method'] ?? 'cash', $items);
        flash('Sale completed successfully.');
        redirect('receipt.php?id=' . $saleId); // redirect so refreshing cannot repeat the sale
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        $error = 'Something went wrong and the sale was not saved. Please try again.';
    }
}

// Only medicines that can really be sold: active, in stock, not expired.
$meds = $pdo->query('SELECT id, name, quantity, price FROM medicines WHERE active = 1 AND quantity > 0 AND expiry_date >= CURDATE() ORDER BY name')->fetchAll();
$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$recent = $pdo->query('SELECT s.*, c.name AS customer, u.name AS staff FROM sales s
                       LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id
                       ORDER BY s.created_at DESC, s.id DESC LIMIT 15')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="hero"><h1>Sales &amp; Billing</h1></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <h2>New sale</h2>
  <?php if (!$meds): ?>
    <p class="muted">No medicines are available to sell (all are out of stock or expired).</p>
  <?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label for="customer_id">Customer</label>
        <select id="customer_id" name="customer_id">
          <option value="">Walk-in customer</option>
          <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div><label for="payment_method">Payment method (record only)</label>
        <select id="payment_method" name="payment_method">
          <option value="cash">Cash</option><option value="card">Card</option><option value="medical_aid">Medical aid</option>
        </select></div>
    </div>

    <label>Medicines</label>
    <div id="lines">
      <div class="line">
        <select name="medicine_id[]" aria-label="Medicine">
          <?php foreach ($meds as $m): ?>
            <option value="<?= $m['id'] ?>" data-price="<?= e($m['price']) ?>"><?= e($m['name']) ?> - <?= (int)$m['quantity'] ?> available - <?= money($m['price']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="number" name="quantity[]" min="1" value="1" aria-label="Quantity">
        <button type="button" class="btn light remove-line" aria-label="Remove line">Remove</button>
      </div>
    </div>
    <p><button type="button" class="btn light" id="add-line">+ Add another medicine</button></p>

    <div class="hero" style="margin-bottom:0">
      <div>Total: <span class="total-box" id="total">R0.00</span></div>
      <button class="btn">Complete sale</button>
    </div>
  </form>
  <?php endif; ?>
</div>
<div class="spacer"></div>

<div class="card">
  <h2>Recent transactions</h2>
  <table>
    <tr><th>Receipt</th><th>Date</th><th>Customer</th><th>Staff</th><th>Payment</th><th>Total</th></tr>
    <?php foreach ($recent as $s): ?>
      <tr>
        <td><a href="receipt.php?id=<?= $s['id'] ?>">#<?= $s['id'] ?></a></td>
        <td><?= e($s['created_at']) ?></td>
        <td><?= e($s['customer'] ?? 'Walk-in') ?></td>
        <td><?= e($s['staff'] ?? '') ?></td>
        <td><?= e(role_label($s['payment_method'])) ?></td>
        <td><?= money($s['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="6" class="muted">No sales yet.</td></tr><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
