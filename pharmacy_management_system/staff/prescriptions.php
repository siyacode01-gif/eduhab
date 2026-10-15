<?php
$title = 'Prescriptions';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require __DIR__ . '/../includes/sale_service.php';
require_permission('prescriptions.capture');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'capture') {
            $customer = (int)($_POST['customer_id'] ?? 0);
            $medicine = (int)($_POST['medicine_id'] ?? 0);
            $qty      = (int)($_POST['quantity'] ?? 0);
            $doctor   = trim($_POST['doctor_name'] ?? '');
            if (!$customer || !$medicine || $qty < 1 || $doctor === '') throw new RuntimeException('Customer, doctor, medicine and quantity are all required.');
            $pdo->prepare('INSERT INTO prescriptions (customer_id, doctor_name, medicine_id, quantity, instructions, created_by) VALUES (?,?,?,?,?,?)')
                ->execute([$customer, $doctor, $medicine, $qty, trim($_POST['instructions'] ?? ''), user_id()]);
            flash('Prescription captured. A pharmacist can now dispense it.');
            redirect('prescriptions.php');
        }

        if ($action === 'cancel') {
            $pdo->prepare("UPDATE prescriptions SET status = 'cancelled' WHERE id = ? AND status = 'pending'")->execute([(int)$_POST['id']]);
            flash('Prescription cancelled.');
            redirect('prescriptions.php');
        }

        if ($action === 'dispense') {
            require_permission('prescriptions.dispense');
            $st = $pdo->prepare("SELECT * FROM prescriptions WHERE id = ? AND status = 'pending'");
            $st->execute([(int)$_POST['id']]);
            $rx = $st->fetch();
            if (!$rx) throw new RuntimeException('This prescription is no longer pending.');
            $saleId = create_sale($pdo, (int)$rx['customer_id'], user_id(), $_POST['payment_method'] ?? 'cash',
                                  [(int)$rx['medicine_id'] => (int)$rx['quantity']], (int)$rx['id']);
            flash('Prescription dispensed and sale recorded.');
            redirect('receipt.php?id=' . $saleId);
        }
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    } catch (Throwable $ex) {
        $error = 'Something went wrong. Nothing was saved.';
    }
}

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$meds = $pdo->query('SELECT id, name FROM medicines WHERE active = 1 ORDER BY name')->fetchAll();
$rows = $pdo->query("SELECT p.*, c.name AS customer, m.name AS medicine FROM prescriptions p
                     JOIN customers c ON c.id = p.customer_id JOIN medicines m ON m.id = p.medicine_id
                     ORDER BY FIELD(p.status,'pending','dispensed','cancelled'), p.created_at DESC LIMIT 50")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="hero"><h1>Prescriptions</h1></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <h2>Capture a prescription</h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?><input type="hidden" name="action" value="capture">
    <div><label for="customer_id">Customer</label>
      <select id="customer_id" name="customer_id" required>
        <option value="">Select customer</option>
        <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="doctor_name">Doctor</label><input id="doctor_name" name="doctor_name" required></div>
    <div><label for="medicine_id">Medicine</label>
      <select id="medicine_id" name="medicine_id" required>
        <option value="">Select medicine</option>
        <?php foreach ($meds as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="quantity">Quantity</label><input id="quantity" type="number" min="1" name="quantity" required></div>
    <div style="grid-column:1/-1"><label for="instructions">Instructions (e.g. 1 tablet twice a day)</label><input id="instructions" name="instructions" maxlength="255"></div>
    <div><button class="btn">Save prescription</button></div>
  </form>
</div>
<div class="spacer"></div>

<div class="card">
  <table>
    <tr><th>Date</th><th>Customer</th><th>Doctor</th><th>Medicine</th><th>Qty</th><th>Instructions</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['created_at']) ?></td><td><?= e($r['customer']) ?></td><td><?= e($r['doctor_name']) ?></td>
        <td><?= e($r['medicine']) ?></td><td><?= (int)$r['quantity'] ?></td><td><?= e($r['instructions']) ?></td>
        <td><span class="badge <?= $r['status'] === 'pending' ? 'warn' : ($r['status'] === 'cancelled' ? 'danger' : '') ?>"><?= e(ucfirst($r['status'])) ?></span></td>
        <td>
          <?php if ($r['status'] === 'pending'): ?>
          <div class="actions">
            <?php if (can('prescriptions.dispense')): ?>
              <form method="post" class="inline">
                <?= csrf_field() ?><input type="hidden" name="action" value="dispense"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                <select name="payment_method" aria-label="Payment method"><option value="cash">Cash</option><option value="card">Card</option><option value="medical_aid">Medical aid</option></select>
                <button class="btn sm">Dispense</button>
              </form>
            <?php endif; ?>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn danger sm" data-confirm="Cancel this prescription?">Cancel</button>
            </form>
          </div>
          <?php elseif ($r['sale_id']): ?>
            <a class="btn light sm" href="receipt.php?id=<?= (int)$r['sale_id'] ?>">Receipt</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="muted">No prescriptions yet.</td></tr><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
