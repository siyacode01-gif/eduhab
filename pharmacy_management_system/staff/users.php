<?php
$title = 'Users';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require_permission('users.manage');

$error = '';
$form = ['name' => '', 'email' => '', 'role' => 'assistant'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['action'] ?? '') === 'toggle') {
            // You cannot switch off your own account (so you can never lock yourself out).
            $pdo->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ? AND id <> ?")
                ->execute([(int)$_POST['id'], user_id()]);
            flash('User status changed.');
            redirect('users.php');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a name and a valid email.');
        if (!in_array($role, ROLES, true)) throw new RuntimeException('Choose a valid role.');
        if (strlen($password) < 8) throw new RuntimeException('The password must be at least 8 characters.');

        $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)')
            ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        flash('User created.');
        redirect('users.php');
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
        $form = array_merge($form, array_intersect_key($_POST, $form));
    } catch (PDOException $ex) {
        $error = $ex->getCode() === '23000' ? 'That email is already registered.' : 'Could not save the user.';
        $form = array_merge($form, array_intersect_key($_POST, $form));
    }
}

$rows = $pdo->query('SELECT id, name, email, role, status, created_at FROM users ORDER BY name')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="hero"><h1>Staff Accounts</h1></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <h2>Add a staff member</h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <div><label for="name">Full name</label><input id="name" name="name" value="<?= e($form['name']) ?>" required></div>
    <div><label for="email">Email (used to log in)</label><input id="email" type="email" name="email" value="<?= e($form['email']) ?>" required></div>
    <div><label for="role">Role</label>
      <select id="role" name="role">
        <?php foreach (ROLES as $r): ?><option value="<?= $r ?>" <?= $form['role'] === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="password">Password (8+ characters)</label><input id="password" type="password" name="password" minlength="8" required></div>
    <div><label>&nbsp;</label><button class="btn">Create user</button></div>
  </form>
</div>
<div class="spacer"></div>

<div class="card">
  <table>
    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr>
    <?php foreach ($rows as $u): ?>
      <tr>
        <td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><?= e(role_label($u['role'])) ?></td>
        <td><span class="badge <?= $u['status'] === 'active' ? '' : 'danger' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
        <td>
          <?php if ((int)$u['id'] !== user_id()): ?>
          <form method="post" class="inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button class="btn light sm"><?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <?php else: ?><span class="muted">You</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
