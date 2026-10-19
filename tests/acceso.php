<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Prueba el controlador real contra una base temporal; no usa datos del restaurante.
// Usa MySQL local de pruebas en 3317, configurable con TEST_MYSQL_PORT.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../includes/check_auth.php';

function buildUrl(string $ruta, bool $absoluta = false): string { return $ruta; }

if (($argv[1] ?? '') === '--worker') {
    if (!preg_match('/^margo_acceso_test_[a-f0-9]{12}$/', $argv[2] ?? '')) { exit(1); }
    ini_set('session.use_cookies', '0');
    session_save_path($argv[5]);
    session_start();
    $conn = new mysqli('127.0.0.1', 'root', '', $argv[2], (int) (getenv('TEST_MYSQL_PORT') ?: 3317));
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = ['modo' => 'login'];
    $_POST = ['accion' => 'login', 'usuario' => $argv[3], 'contraseña' => $argv[4]];
    // El controlador redirige y termina con exit si el acceso es correcto.
    register_shutdown_function(function () {
        echo json_encode([
            'usuario' => $_SESSION['usuario_logueado'] ?? null,
            'id' => $_SESSION['usuario_id'] ?? null,
            'rol' => $_SESSION['rol'] ?? null,
            'mensaje' => $GLOBALS['mensaje'] ?? '',
        ]);
    });
    require __DIR__ . '/../includes/auth.php';
    exit;
}

$conn = new mysqli('127.0.0.1', 'root', '', '', (int) (getenv('TEST_MYSQL_PORT') ?: 3317));
$base = 'margo_acceso_test_' . bin2hex(random_bytes(6));
$sesiones = __DIR__ . '/.tmp-acceso-' . bin2hex(random_bytes(6));
mkdir($sesiones);
$conn->query("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db($base);
$total = 0;
function comprobarAcceso(bool $condicion, string $mensaje): void
{
    if (!$condicion) { throw new RuntimeException($mensaje); }
    $GLOBALS['total']++;
}
function intentar(string $identificador, string $password): array
{
    $comando = [PHP_BINARY, __FILE__, '--worker', $GLOBALS['base'], $identificador, $password, $GLOBALS['sesiones']];
    $proceso = proc_open($comando, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $salida = stream_get_contents($pipes[1]);
    $errores = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $codigo = proc_close($proceso);
    $resultado = json_decode($salida, true);
    comprobarAcceso($codigo === 0 && is_array($resultado), 'El controlador debe terminar sin errores: ' . $errores . $salida);
    return $resultado;
}

try {
    $conn->query("CREATE TABLE usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE, password VARCHAR(255) DEFAULT NULL,
        rol VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $stmt = $conn->prepare('INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)');
    foreach ([['ana', 'ana@example.test', 'Clave1!', 'usuario'], ['beto', 'beto@example.test', 'Clave123', 'admin'], ['antiguo', 'antiguo@example.test', 'simple', 'usuario'], ['google', 'google@example.test', null, 'usuario']] as [$nombre, $email, $password, $rol]) {
        $hash = $password !== null ? password_hash($password, PASSWORD_BCRYPT) : null;
        $stmt->bind_param('ssss', $nombre, $email, $hash, $rol);
        $stmt->execute();
    }
    $stmt->close();

    foreach ([['ana', 'Clave1!', 'ana', 'usuario'], ['ana@example.test', 'Clave1!', 'ana', 'usuario'], [' ANA@EXAMPLE.TEST ', 'Clave1!', 'ana', 'usuario'], ['beto@example.test', 'Clave123', 'beto', 'admin'], ['antiguo', 'simple', 'antiguo', 'usuario']] as [$entrada, $password, $esperado, $rol]) {
        $resultado = intentar($entrada, $password);
        comprobarAcceso($resultado['usuario'] === $esperado && $resultado['rol'] === $rol && $resultado['id'] !== null, 'Guardar el nombre real, ID y rol al acceder por usuario o correo.');
    }
    foreach ([['ana', 'Incorrecta1!'], ['ana@example.test', 'Incorrecta1!'], ['google@example.test', 'Clave1!'], ["' OR 1=1 --", 'Clave1!'], ['', 'Clave1!'], ['ana', '']] as [$entrada, $password]) {
        $resultado = intentar($entrada, $password);
        comprobarAcceso($resultado['usuario'] === null && $resultado['mensaje'] !== '', 'Rechazar credenciales inválidas sin iniciar sesión.');
    }

    $conn->query("INSERT INTO usuarios (nombre,email,password,rol) SELECT 'ana@example.test','colision@example.test',password,'usuario' FROM usuarios WHERE nombre = 'ana'");
    comprobarAcceso(intentar('ana@example.test', 'Clave1!')['usuario'] === null, 'No elegir una cuenta arbitraria si el correo coincide con otro nombre.');
    comprobarAcceso(intentar('ana', 'Clave1!')['usuario'] === 'ana', 'Mantener acceso por el nombre no ambiguo.');

    foreach (['Clave1', 'Clave1!', 'Clave1@#$%', 'Clave1\'"<>', 'Clave1 con espacios', str_repeat('a', 70) . 'A1'] as $password) {
        comprobarAcceso(validarPassword($password) === '', 'Aceptar contraseñas con y sin símbolos.');
    }
    foreach (['Ab1', 'clave123!', 'Claveabc!', str_repeat('a', 71) . 'A1', "Clave1\0!"] as $password) {
        comprobarAcceso(validarPassword($password) !== '', 'Mantener longitud, mayúscula, número y límites de bcrypt.');
    }
    echo "OK: {$total} comprobaciones de acceso y contraseñas con MariaDB.\n";
} finally {
    if (preg_match('/^margo_acceso_test_[a-f0-9]{12}$/', $base)) { $conn->query("DROP DATABASE `{$base}`"); }
    foreach (glob($sesiones . '/sess_*') as $archivo) { unlink($archivo); }
    rmdir($sesiones);
    $conn->close();
}
