<?php
require_once __DIR__ . '/rutas.php';

const SESION_INACTIVIDAD = 30 * 60;
const SESION_DURACION_MAXIMA = 8 * 60 * 60;

function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) SESION_DURACION_MAXIMA);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (baseDesdeScript($_SERVER['SCRIPT_NAME'] ?? '/index.php') ?: '') . '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function cerrarSesion(): void
{
    $_SESSION = [];
    if (session_status() !== PHP_SESSION_ACTIVE) { return; }
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600, 'path' => $cookie['path'],
        'domain' => $cookie['domain'], 'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'], 'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
    session_destroy();
    session_id('');
}

function autenticarSesion(array $usuario): void
{
    iniciarSesion();
    session_regenerate_id(true);
    $_SESSION = [
        'usuario_id' => (int) $usuario['id'],
        'usuario_logueado' => $usuario['nombre'],
        'rol' => $usuario['rol'] ?? 'usuario',
        'sesion_inicio' => time(),
        'sesion_actividad' => time(),
    ];
}

function sesionAutenticada(): bool
{
    return !empty($_SESSION['usuario_id']) && isset($_SESSION['usuario_logueado'], $_SESSION['rol'], $_SESSION['sesion_inicio'], $_SESSION['sesion_actividad']);
}

function validarSesion(bool $actualizar = true, ?int $ahora = null): bool
{
    $ahora = $ahora ?? time();
    $tieneAcceso = isset($_SESSION['usuario_id']) || isset($_SESSION['usuario_logueado']) || isset($_SESSION['rol']);
    if (!$tieneAcceso) { return false; }
    if (!sesionAutenticada()
        || $ahora - (int) $_SESSION['sesion_actividad'] >= SESION_INACTIVIDAD
        || $ahora - (int) $_SESSION['sesion_inicio'] >= SESION_DURACION_MAXIMA) {
        cerrarSesion();
        iniciarSesion();
        $_SESSION['sesion_vencida'] = true;
        return false;
    }
    if ($actualizar) { $_SESSION['sesion_actividad'] = $ahora; }
    return true;
}

function comprobarSesionRequest(): void
{
    iniciarSesion();
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $esEstado = str_ends_with($script, '/api/sesion.php');
    $esAviso = str_ends_with($script, '/api/reservas_nuevas.php');
    $actualizar = !$esAviso && (!$esEstado || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
    validarSesion($actualizar);
    if (sesionAutenticada()) {
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
    }
    $esApi = str_contains($script, '/api/') && !str_ends_with($script, '/api/recuperar_password.php');
    $esPlano = (str_ends_with($script, '/dashboards/admin_plano.php') || str_ends_with(rtrim($script, '/'), '/plano'))
        && (isset($_GET['action']) || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'));
    if (($esApi || $esPlano) && !sesionAutenticada()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => false, 'mensaje' => 'Tu sesión terminó. Volvé a iniciar sesión.', 'codigo' => 'sesion_expirada']);
        exit;
    }
}
