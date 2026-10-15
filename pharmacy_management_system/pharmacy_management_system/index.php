<?php
require __DIR__ . '/config/database.php';
require __DIR__ . '/config/auth.php';

if (user_id()) {
    redirect('staff/dashboard.php');
}

$error = '';
$needsInstall = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Simple brute-force guard: 5 wrong tries = wait 60 seconds.
    if (($_SESSION['fails'] ?? 0) >= 5 && time() < ($_SESSION['locked_until'] ?? 0)) {
        $error = 'Too many attempts. Please wait a minute and try again.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([trim($_POST['email'] ?? '')]);
        $u = $stmt->fetch();

        if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role']];
            unset($_SESSION['fails'], $_SESSION['locked_until']);
            redirect('staff/dashboard.php');
        }
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) {
            $_SESSION['locked_until'] = time() + 60;
            $_SESSION['fails'] = 5;
        }
        $error = 'Wrong email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login - PharmaCare</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login">
  <div class="login-box">
    <h1>PharmaCare</h1>
    <p class="muted">Pharmacy Management System. Staff sign in.</p>
    <?php if ($needsInstall): ?>
      <div class="alert error">No staff accounts yet. Open <a href="install.php">install.php</a> once to create them.</div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required autofocus>
      <label for="password">Password</label>
      <input id="password" type="password" name="password" required>
      <button class="btn" style="width:100%">Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
