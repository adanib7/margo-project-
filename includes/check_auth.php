<?php
function requireLogin(): void {
    if (!isset($_SESSION['usuario_logueado'])) {
        header('Location: ' . buildUrl('/public/login.php', true));
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
        'superadmin' => buildUrl('/dashboards/superadmin.php', true),
        'admin'      => buildUrl('/dashboards/admin.php', true),
        'usuario'    => buildUrl('/dashboards/user.php', true),
    ];
    $rol = $_SESSION['rol'] ?? 'usuario';
    header('Location: ' . ($map[$rol] ?? buildUrl('/dashboards/user.php', true)));
    exit;
}
