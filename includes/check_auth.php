<?php
require_once __DIR__ . '/sesion.php';
function requireLogin(): void {
    if (!sesionAutenticada()) {
        header('Location: ' . buildUrl('/public/login.php' . (!empty($_SESSION['sesion_vencida']) ? '?sesion=expirada' : ''), true));
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
    // Bcrypt admite hasta 72 bytes y no acepta el byte nulo.
    if (strlen($p) > 72)                     return "La contraseña es demasiado larga (máximo 72 bytes).";
    if (str_contains($p, "\0"))              return "La contraseña contiene un carácter no permitido.";
    if (strlen($p) < 6)                       return "La contraseña debe tener mínimo 6 caracteres.";
    if (!preg_match('/[A-Z]/', $p))           return "La contraseña debe tener al menos una mayúscula.";
    if (!preg_match('/[0-9]/', $p))           return "La contraseña debe tener al menos un número.";
    return "";
}
