<?php
function requireLogin(): void {
    if (!isset($_SESSION['usuario_logueado'])) {
        header('Location: ' . buildUrl('/index.php'));
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['rol'] ?? 'usuario', $roles, true)) {
        redirectToDashboard();
    }
}

function redirectToDashboard(): void {
    $map = [
        'superadmin' => buildUrl('/dashboards/superadmin.php'),
        'admin'      => buildUrl('/dashboards/admin.php'),
        'usuario'    => buildUrl('/dashboards/user.php'),
    ];
    $rol = $_SESSION['rol'] ?? 'usuario';
    header('Location: ' . ($map[$rol] ?? buildUrl('/dashboards/user.php')));
    exit;
}
