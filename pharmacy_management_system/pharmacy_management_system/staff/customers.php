<?php
$title = 'Customers';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require_permission('customers.manage');

$error = '';
$form = ['id' => 0, 'name' => '', 'phone' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '' || $phone === '') throw new RuntimeException('Name and phone are required.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Please enter a valid email address.');

        if ($id) {
            $pdo->prepare('UPDATE customers SET name=?, phone=?, email=? WHERE id=?')->execute([$name, $phone, $email ?: null, $id]);
        } else {
            $pdo->prepare('INSERT INTO customers (name, phone, email) VALUES (?,?,?)')->execute([$name, $phone, $email ?: null]);
        }
        flash($id ? 'Customer updated.' : 'Customer added.');
        redirect('customers.php');
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
        $form = array_merge($form, array_intersect_key($_POST, $form));
    }
} elseif (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    if ($row = $st->fetch()) $form = $row;
}

$q = trim($_GET['q'] ?? '');
$like = "%$q%";
$st = $pdo->prepare('SELECT c.*, COUNT(s.id) AS purchases FROM customers c LEFT JOIN sales s ON s.customer_id = c.id
                     WHERE c.name LIKE ? OR c.phone LIKE ? GROUP BY c.id ORDER BY c.name');
$st->execute([$like, $like]);
$rows = $st->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="hero"><h1>Customer Management</h1></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <h2><?= $form['id'] ? 'Edit customer' : 'Add customer' ?></h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
    <div><label for="name">Name</label><input id="name" name="name" value="<?= e($form['name']) ?>" required></div>
    <div><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($form['phone']) ?>" required></div>
    <div><label for="email">Email (optional)</label><input id="email" type="email" name="email" value="<?= e($form['email']) ?>"></div>
    <div class="actions" style="align-self:end;margin-bottom:14px">
      <button class="btn"><?= $form['id'] ? 'Save changes' : 'Add customer' ?></button>
      <?php if ($form['id']): ?><a class="btn light" href="customers.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<div class="spacer"></div>

<div class="card">
  <form class="search" method="get">
    <input name="q" value="<?= e($q) ?>" placeholder="Search by name or phone" aria-label="Search customers">
    <button class="btn">Search</button>
  </form>
  <table>
    <tr><th>Name</th><th>Phone</th><th>Email</th><th>Purchases</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['name']) ?></td><td><?= e($r['phone']) ?></td><td><?= e($r['email'] ?? '') ?></td>
        <td><?= (int)$r['purchases'] ?></td>
        <td><a class="btn light sm" href="?edit=<?= $r['id'] ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="muted">No customers found.</td></tr><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
