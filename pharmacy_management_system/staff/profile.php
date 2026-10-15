<?php
$title = 'My account';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/auth.php';
require_login();

$error = '';
$st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([user_id()]);
$me = $st->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($_POST['current_password'] ?? '', $me['password'])) throw new RuntimeException('Your current password is wrong.');
        if (strlen($new) < 8) throw new RuntimeException('The new password must be at least 8 characters.');
        if ($new !== ($_POST['confirm_password'] ?? '')) throw new RuntimeException('The new passwords do not match.');
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), user_id()]);
        flash('Password changed.');
        redirect('profile.php');
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="hero"><h1>My account</h1></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
  <div class="card">
    <h2>Details</h2>
    <p><strong>Name:</strong> <?= e($me['name']) ?></p>
    <p><strong>Email:</strong> <?= e($me['email']) ?></p>
    <p><strong>Role:</strong> <?= e(role_label($me['role'])) ?></p>
  </div>
  <div class="card">
    <h2>Change password</h2>
    <form method="post">
      <?= csrf_field() ?>
      <label for="current_password">Current password</label><input id="current_password" type="password" name="current_password" required>
      <label for="new_password">New password (8+ characters)</label><input id="new_password" type="password" name="new_password" minlength="8" required>
      <label for="confirm_password">Repeat new password</label><input id="confirm_password" type="password" name="confirm_password" required>
      <button class="btn">Change password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
