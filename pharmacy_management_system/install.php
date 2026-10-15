<?php
// Run this ONCE in the browser to create the demo staff accounts, then DELETE this file.
require __DIR__ . '/config/database.php';

$accounts = [
    ['System Administrator', 'admin@pharmacy.local',      'Admin@123',      'admin'],
    ['Mandla Manager',       'manager@pharmacy.local',    'Manager@123',    'manager'],
    ['Palesa Pharmacist',    'pharmacist@pharmacy.local', 'Pharmacist@123', 'pharmacist'],
    ['Andile Assistant',     'assistant@pharmacy.local',  'Assistant@123',  'assistant'],
    ['Carol Cashier',        'cashier@pharmacy.local',    'Cashier@123',    'cashier'],
    ['Sipho Stock',          'stock@pharmacy.local',      'Stock@123',      'stock_manager'],
];

$created = [];
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
    $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)');
    foreach ($accounts as [$name, $email, $password, $role]) {
        $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $created[] = [$email, $password, $role];
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install - PharmaCare</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><main class="container">
<div class="card receipt">
  <h1>PharmaCare setup</h1>
  <?php if ($created): ?>
    <div class="alert">Done! These demo accounts were created:</div>
    <table><tr><th>Email</th><th>Password</th><th>Role</th></tr>
    <?php foreach ($created as [$email, $password, $role]): ?>
      <tr><td><?= htmlspecialchars($email) ?></td><td><?= htmlspecialchars($password) ?></td><td><?= htmlspecialchars($role) ?></td></tr>
    <?php endforeach; ?></table>
  <?php else: ?>
    <div class="alert error">Accounts already exist, so nothing was changed.</div>
  <?php endif; ?>
  <p><strong>Now delete <code>install.php</code> from your project folder.</strong> Anyone who finds it could see this page.</p>
  <a class="btn" href="index.php">Go to login</a>
</div></main></body></html>
