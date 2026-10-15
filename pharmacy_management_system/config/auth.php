<?php
// Sessions, roles, permissions, CSRF protection and small helper functions.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ROLES = ['admin', 'manager', 'pharmacist', 'assistant', 'cashier', 'stock_manager'];

// Who may do what. Change this list to change the rules for the whole app.
const PERMISSIONS = [
    'medicines.manage'       => ['admin', 'manager', 'pharmacist', 'assistant', 'stock_manager'],
    'medicines.delete'       => ['admin', 'manager', 'stock_manager'],
    'customers.manage'       => ['admin', 'manager', 'pharmacist', 'assistant', 'cashier'],
    'prescriptions.capture'  => ['admin', 'manager', 'pharmacist', 'assistant'],
    'prescriptions.dispense' => ['admin', 'pharmacist'],
    'suppliers.manage'       => ['admin', 'manager', 'stock_manager'],
    'sales.create'           => ['admin', 'manager', 'pharmacist', 'assistant', 'cashier'],
    'reports.view'           => ['admin', 'manager'],
    'users.manage'           => ['admin'],
];

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return 'R' . number_format((float)$n, 2); }
function redirect(string $url): void { header('Location: ' . $url); exit; }

function user(): array { return $_SESSION['user'] ?? []; }
function user_id(): int { return (int)(user()['id'] ?? 0); }
function role(): string { return user()['role'] ?? ''; }
function role_label(string $r = ''): string { return ucwords(str_replace('_', ' ', $r ?: role())); }
function can(string $permission): bool { return in_array(role(), PERMISSIONS[$permission] ?? [], true); }

function require_login(): void {
    if (!user_id()) {
        redirect('../index.php');
    }
}

function require_permission(string $permission): void {
    require_login();
    if (!can($permission)) {
        http_response_code(403);
        die('<div style="font-family:Arial;max-width:480px;margin:80px auto;text-align:center">'
          . '<h2>Access denied</h2><p>Your role (' . e(role_label()) . ') is not allowed to do this.</p>'
          . '<p><a href="dashboard.php">Back to dashboard</a></p></div>');
    }
}

// CSRF: every form carries a secret token so other websites cannot submit it for you.
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('This form expired. Go back, refresh the page and try again.');
    }
}

// One-time message shown after a redirect (e.g. "Medicine saved.").
function flash(string $message, string $type = 'ok'): void { $_SESSION['flash'] = ['msg' => $message, 'type' => $type]; }
function flash_html(): string {
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert' . ($f['type'] === 'error' ? ' error' : '') . '">' . e($f['msg']) . '</div>';
}

// Label + colour for a medicine row: Expired / Out of stock / Expiring soon / Low stock / OK.
function stock_status(array $m): array {
    $today = date('Y-m-d');
    if ($m['expiry_date'] < $today) return ['Expired', 'danger'];
    if ((int)$m['quantity'] === 0)  return ['Out of stock', 'danger'];
    if ($m['expiry_date'] <= date('Y-m-d', strtotime('+30 days'))) return ['Expiring soon', 'warn'];
    if ((int)$m['quantity'] <= (int)$m['low_stock']) return ['Low stock', 'warn'];
    return ['OK', ''];
}
