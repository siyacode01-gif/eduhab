<?php
$title = 'Medicines';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require_login();

$error = '';
$form = ['id' => 0, 'code' => '', 'name' => '', 'category' => '', 'supplier_id' => '',
         'quantity' => '', 'price' => '', 'expiry_date' => '', 'low_stock' => 10];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            require_permission('medicines.manage');
            $id       = (int)($_POST['id'] ?? 0);
            $code     = trim($_POST['code'] ?? '');
            $name     = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $supplier = (int)($_POST['supplier_id'] ?? 0) ?: null;
            $qty      = (int)($_POST['quantity'] ?? 0);
            $price    = round((float)($_POST['price'] ?? 0), 2);
            $expiry   = $_POST['expiry_date'] ?? '';
            $low      = (int)($_POST['low_stock'] ?? 0);

            if ($code === '' || $name === '' || $category === '') throw new RuntimeException('Code, name and category are required.');
            if ($price <= 0) throw new RuntimeException('Price must be more than 0.');
            if ($qty < 0 || $low < 0) throw new RuntimeException('Quantity and low-stock level cannot be negative.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) || !strtotime($expiry)) throw new RuntimeException('Please enter a valid expiry date.');

            $data = [$code, $name, $category, $supplier, $qty, $price, $expiry, $low];
            if ($id) {
                $pdo->prepare('UPDATE medicines SET code=?, name=?, category=?, supplier_id=?, quantity=?, price=?, expiry_date=?, low_stock=? WHERE id=? AND active=1')
                    ->execute([...$data, $id]);
            } else {
                $pdo->prepare('INSERT INTO medicines (code, name, category, supplier_id, quantity, price, expiry_date, low_stock) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute($data);
            }
            flash($id ? 'Medicine updated.' : 'Medicine added.');
            redirect('medicines.php');
        }

        if ($action === 'restock') {
            require_permission('medicines.manage');
            $add = (int)($_POST['add'] ?? 0);
            if ($add < 1) throw new RuntimeException('Enter how many units were received (1 or more).');
            $pdo->prepare('UPDATE medicines SET quantity = quantity + ? WHERE id = ? AND active = 1')->execute([$add, (int)$_POST['id']]);
            flash("Stock updated: $add units added.");
            redirect('medicines.php');
        }

        if ($action === 'delete') {
            require_permission('medicines.delete');
            // Soft delete: hidden from lists but old sales still show its name.
            $pdo->prepare('UPDATE medicines SET active = 0 WHERE id = ?')->execute([(int)$_POST['id']]);
            flash('Medicine removed from the list.');
            redirect('medicines.php');
        }
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
        if ($action === 'save') $form = array_merge($form, array_intersect_key($_POST, $form));
    } catch (PDOException $ex) {
        $error = $ex->getCode() === '23000' ? 'That medicine code is already used (maybe by a removed medicine).' : 'Could not save. Please try again.';
        if ($action === 'save') $form = array_merge($form, array_intersect_key($_POST, $form));
    }
} elseif (isset($_GET['edit']) && can('medicines.manage')) {
    $st = $pdo->prepare('SELECT * FROM medicines WHERE id = ? AND active = 1');
    $st->execute([(int)$_GET['edit']]);
    if ($row = $st->fetch()) $form = $row;
}

$q = trim($_GET['q'] ?? '');
$like = "%$q%";
$st = $pdo->prepare('SELECT m.*, s.name AS supplier FROM medicines m LEFT JOIN suppliers s ON s.id = m.supplier_id
                     WHERE m.active = 1 AND (m.name LIKE ? OR m.category LIKE ? OR m.code LIKE ?) ORDER BY m.name');
$st->execute([$like, $like, $like]);
$rows = $st->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="hero"><h1>Medicine Management</h1></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<?php if (can('medicines.manage')): ?>
<div class="card">
  <h2><?= $form['id'] ? 'Edit medicine' : 'Add medicine' ?></h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
    <div><label for="code">Code</label><input id="code" name="code" value="<?= e($form['code']) ?>" required></div>
    <div><label for="name">Name</label><input id="name" name="name" value="<?= e($form['name']) ?>" required></div>
    <div><label for="category">Category</label><input id="category" name="category" value="<?= e($form['category']) ?>" required></div>
    <div><label for="supplier_id">Supplier</label>
      <select id="supplier_id" name="supplier_id">
        <option value="">No supplier</option>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?= $s['id'] ?>" <?= (string)$form['supplier_id'] === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label for="quantity">Quantity in stock</label><input id="quantity" type="number" min="0" name="quantity" value="<?= e($form['quantity']) ?>" required></div>
    <div><label for="price">Price (R)</label><input id="price" type="number" step="0.01" min="0.01" name="price" value="<?= e($form['price']) ?>" required></div>
    <div><label for="expiry_date">Expiry date</label><input id="expiry_date" type="date" name="expiry_date" value="<?= e($form['expiry_date']) ?>" required></div>
    <div><label for="low_stock">Low-stock level</label><input id="low_stock" type="number" min="0" name="low_stock" value="<?= e($form['low_stock']) ?>" required></div>
    <div class="actions" style="align-self:end;margin-bottom:14px">
      <button class="btn"><?= $form['id'] ? 'Save changes' : 'Add medicine' ?></button>
      <?php if ($form['id']): ?><a class="btn light" href="medicines.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<div class="spacer"></div>
<?php endif; ?>

<div class="card">
  <form class="search" method="get">
    <input name="q" value="<?= e($q) ?>" placeholder="Search by name, category or code" aria-label="Search medicines">
    <button class="btn">Search</button>
    <?php if ($q !== ''): ?><a class="btn light" href="medicines.php">Clear</a><?php endif; ?>
  </form>
  <table>
    <tr><th>Code</th><th>Medicine</th><th>Category</th><th>Supplier</th><th>Stock</th><th>Price</th><th>Expiry</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($rows as $r): [$label, $cls] = stock_status($r); ?>
      <tr>
        <td><?= e($r['code']) ?></td>
        <td><?= e($r['name']) ?></td>
        <td><?= e($r['category']) ?></td>
        <td><?= e($r['supplier'] ?? '-') ?></td>
        <td><?= (int)$r['quantity'] ?></td>
        <td><?= money($r['price']) ?></td>
        <td><?= e($r['expiry_date']) ?></td>
        <td><span class="badge <?= $cls ?>"><?= $label ?></span></td>
        <td>
          <div class="actions">
          <?php if (can('medicines.manage')): ?>
            <a class="btn light sm" href="?edit=<?= $r['id'] ?>">Edit</a>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="restock"><input type="hidden" name="id" value="<?= $r['id'] ?>">
              <input type="number" name="add" min="1" placeholder="+ qty" aria-label="Units received">
              <button class="btn secondary sm">Add stock</button>
            </form>
          <?php endif; ?>
          <?php if (can('medicines.delete')): ?>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn danger sm" data-confirm="Remove this medicine from the list?">Remove</button>
            </form>
          <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="muted">No medicines found.</td></tr><?php endif; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
