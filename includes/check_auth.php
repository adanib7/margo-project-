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

// Reglas de contraseña (registro, Mi perfil y recuperar contraseña).
function validarPassword(string $p): string {
    if (strlen($p) < 6)                       return "La contraseña debe tener mínimo 6 caracteres.";
    if (!preg_match('/[A-Z]/', $p))           return "La contraseña debe tener al menos una mayúscula.";
    if (!preg_match('/[0-9]/', $p))           return "La contraseña debe tener al menos un número.";
    if (!preg_match('/^[a-zA-Z0-9]+$/', $p)) return "Solo se permiten letras y números.";
    return "";
}
