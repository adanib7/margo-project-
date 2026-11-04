<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/sesion.php';
// Solo sesiones temporales propias; no necesita MySQL ni cuentas reales.
$carpeta = __DIR__ . '/.tmp-sesion-' . bin2hex(random_bytes(6));
mkdir($carpeta);
session_save_path($carpeta);
$_SERVER['SCRIPT_NAME'] = '/margo-project-/dashboards/user.php';
$total = 0;
function comprobarSesion(bool $ok, string $detalle): void
{
    if (!$ok) { throw new RuntimeException($detalle); }
    $GLOBALS['total']++;
}
try {
    iniciarSesion();
    $cookie = session_get_cookie_params();
    comprobarSesion($cookie['lifetime'] === 0 && $cookie['httponly'] && $cookie['samesite'] === 'Lax', 'Cookie sin persistencia y sin acceso desde JS.');
    comprobarSesion($cookie['path'] === '/margo-project-/', 'Cookie dentro de la carpeta del proyecto.');
    $idAnonimo = session_id();
    $_SESSION['dato_anterior'] = 'no conservar';
    autenticarSesion(['id' => 7, 'nombre' => 'Prueba', 'rol' => 'admin']);
    comprobarSesion(session_id() !== $idAnonimo, 'Rotar ID al iniciar sesión.');
    comprobarSesion(!isset($_SESSION['dato_anterior']) && sesionAutenticada(), 'Crear un contexto autenticado limpio.');
    $inicio = $_SESSION['sesion_inicio'];
    comprobarSesion(validarSesion(false, $inicio + 60), 'Aceptar una sesión reciente.');
    comprobarSesion($_SESSION['sesion_actividad'] === $inicio, 'Las consultas de fondo no renuevan actividad.');
    comprobarSesion(validarSesion(true, $inicio + 100), 'Aceptar actividad real.');
    comprobarSesion($_SESSION['sesion_actividad'] === $inicio + 100, 'Actualizar última actividad.');
    comprobarSesion(!validarSesion(false, $inicio + 100 + SESION_INACTIVIDAD), 'Vencer exactamente al alcanzar el límite de inactividad.');
    comprobarSesion(!sesionAutenticada() && !isset($_SESSION['usuario_id'], $_SESSION['rol']) && !empty($_SESSION['sesion_vencida']), 'Eliminar todos los permisos al vencer.');
    autenticarSesion(['id' => 8, 'nombre' => 'Google', 'rol' => 'usuario']);
    $inicio = $_SESSION['sesion_inicio'];
    $_SESSION['sesion_actividad'] = $inicio + SESION_DURACION_MAXIMA - 1;
    comprobarSesion(!validarSesion(true, $inicio + SESION_DURACION_MAXIMA), 'Vencer a las ocho horas aunque haya actividad.');
    autenticarSesion(['id' => 9, 'nombre' => 'Actual', 'rol' => 'usuario']);
    unset($_SESSION['sesion_inicio']);
    comprobarSesion(!validarSesion(), 'Las sesiones antiguas sin reloj requieren nuevo acceso.');
    $_SESSION['registro_email'] = 'pendiente@example.test';
    comprobarSesion(!validarSesion() && isset($_SESSION['registro_email']), 'No borrar el registro pendiente anónimo.');
    autenticarSesion(['id' => 10, 'nombre' => 'Salir', 'rol' => 'superadmin']);
    $idSalir = session_id();
    cerrarSesion();
    comprobarSesion($_SESSION === [] && session_status() !== PHP_SESSION_ACTIVE && session_id() === '', 'Cerrar elimina los datos, ID y sesión activa.');
    comprobarSesion(!file_exists($carpeta . '/sess_' . $idSalir), 'Cerrar elimina el archivo de la sesión del servidor.');
    iniciarSesion();
    comprobarSesion(!sesionAutenticada() && session_id() !== $idSalir, 'La siguiente sesión es anónima y tiene un ID nuevo.');
    cerrarSesion();
    $_SERVER['HTTPS'] = 'on';
    iniciarSesion();
    comprobarSesion(session_get_cookie_params()['secure'], 'En HTTPS la cookie requiere conexión segura.');
    cerrarSesion();
} finally {
    cerrarSesion();
    foreach (glob($carpeta . '/sess_*') as $archivo) { unlink($archivo); }
    rmdir($carpeta);
}
echo "OK: {$total} comprobaciones del ciclo de sesiones.\n";
