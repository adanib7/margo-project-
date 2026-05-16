<?php
function requireLogin(): void {
    if (!isset($_SESSION['usuario_logueado'])) {
        header('Location: ' . BASE_URL . '/login.php');
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
        'superadmin' => BASE_URL . '/dashboards/superadmin.php',
        'admin'      => BASE_URL . '/dashboards/admin.php',
        'usuario'    => BASE_URL . '/dashboards/user.php',
    ];
    $rol = $_SESSION['rol'] ?? 'usuario';
    header('Location: ' . ($map[$rol] ?? BASE_URL . '/dashboards/user.php'));
    exit;
}
