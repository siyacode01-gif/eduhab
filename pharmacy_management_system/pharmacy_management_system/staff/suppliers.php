<?php
$title = 'Suppliers';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require_permission('suppliers.manage');

$error = '';
$form = ['id' => 0, 'name' => '', 'contact_person' => '', 'phone' => '', 'email' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    try {
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM suppliers WHERE id = ?')->execute([(int)$_POST['id']]);
            flash('Supplier deleted. Their medicines now show "No supplier".');
            redirect('suppliers.php');
        }
        $id = (int)($_POST['id'] ?? 0);
        $f = array_map(fn($k) => trim($_POST[$k] ?? ''), ['name', 'contact_person', 'phone', 'email', 'address']);
        if ($f[0] === '') throw new RuntimeException('Supplier name is required.');
        if ($f[3] !== '' && !filter_var($f[3], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Please enter a valid email address.');
        if ($id) {
            $pdo->prepare('UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?')->execute([...$f, $id]);
        } else {
            $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?,?,?,?,?)')->execute($f);
        }
        flash($id ? 'Supplier updated.' : 'Supplier added.');
        redirect('suppliers.php');
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
        $form = array_merge($form, array_intersect_key($_POST, $form));
    }
} elseif (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    if ($row = $st->fetch()) $form = $row;
}

$rows = $pdo->query('SELECT s.*, COUNT(m.id) AS medicines FROM suppliers s LEFT JOIN medicines m ON m.supplier_id = s.id AND m.active = 1 GROUP BY s.id ORDER BY s.name')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="hero"><h1>Supplier Management</h1></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <h2><?= $form['id'] ? 'Edit supplier' : 'Add supplier' ?></h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
    <div><label for="name">Company name</label><input id="name" name="name" value="<?= e($form['name']) ?>" required></div>
    <div><label for="contact_person">Contact person</label><input id="contact_person" name="contact_person" value="<?= e($form['contact_person']) ?>"></div>
    <div><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($form['phone']) ?>"></div>
    <div><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($form['email']) ?>"></div>
    <div><label for="address">Address</label><input id="address" name="address" value="<?= e($form['address']) ?>"></div>
    <div class="actions" style="align-self:end;margin-bottom:14px">
      <button class="btn"><?= $form['id'] ? 'Save changes' : 'Add supplier' ?></button>
      <?php if ($form['id']): ?><a class="btn light" href="suppliers.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<div class="spacer"></div>

<div class="card">
  <table>
    <tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Email</th><th>Medicines</th><th>Actions</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['name']) ?></td><td><?= e($r['contact_person']) ?></td><td><?= e($r['phone']) ?></td>
        <td><?= e($r['email']) ?></td><td><?= (int)$r['medicines'] ?></td>
        <td><div class="actions">
          <a class="btn light sm" href="?edit=<?= $r['id'] ?>">Edit</a>
          <form method="post" class="inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn danger sm" data-confirm="Delete this supplier?">Delete</button>
          </form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted">No suppliers yet.</td></tr><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
