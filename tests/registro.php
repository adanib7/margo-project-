<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Integración con una base temporal y correo simulado: no envía mensajes reales.
// TEST_MYSQL_PORT=3317 php tests/registro.php (root local sin contraseña).
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function mailHabilitado(): bool { return $GLOBALS['correo_habilitado'] ?? true; }
function correoCodigoRegistro(string $nombre, string $codigo, int $minutos): array { return [$codigo, $nombre]; }
function enviarCorreoBrevo(string $email, string $nombre, string $asunto, string $html, bool $copia = true): array
{
    if ($copia) { throw new RuntimeException('Un código no debe copiarse a BCC.'); }
    $GLOBALS['codigos'][$email] = $asunto;
    return [$GLOBALS['correo_exitoso'] ?? true, 'transporte simulado'];
}

// Se sustituye únicamente la dependencia de transporte al cargar el código real.
$fuente = file_get_contents(__DIR__ . '/../includes/registro.php');
$fuente = str_replace("require_once __DIR__ . '/mailer.php';", '', $fuente);
eval(substr($fuente, 5));

if (($argv[1] ?? '') === '--worker') {
    if (!preg_match('/^margo_registro_test_[a-f0-9]{12}$/', $argv[2] ?? '')) { exit(1); }
    $worker = new mysqli('127.0.0.1', 'root', '', $argv[2], (int) (getenv('TEST_MYSQL_PORT') ?: 3317));
    $_SERVER['REMOTE_ADDR'] = $argv[6];
    $_SESSION = ['registro_solicitud' => $argv[5]];
    echo json_encode(registroConfirmar($worker, $argv[3], $argv[4]));
    exit;
}

$conn = new mysqli('127.0.0.1', 'root', '', '', (int) (getenv('TEST_MYSQL_PORT') ?: 3317));
$conn->set_charset('utf8mb4');
$base = 'margo_registro_test_' . bin2hex(random_bytes(6));
$conn->query("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db($base);
$_SESSION = [];
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
$comprobaciones = 0;
function comprobar(bool $condicion, string $detalle): void
{
    if (!$condicion) { throw new RuntimeException($detalle); }
    $GLOBALS['comprobaciones']++;
}
function pendiente(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare('SELECT * FROM registro_pendientes WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
function empezar(mysqli $conn, string $email, string $nombre): string
{
    [$ok, $mensaje] = registroSolicitar($conn, $email, ['nombre' => $nombre, 'password' => 'Prueba123']);
    comprobar($ok, $mensaje);
    return $GLOBALS['codigos'][$email];
}

try {
    $conn->query("CREATE TABLE usuarios (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE, password VARCHAR(255) DEFAULT NULL,
        google_id VARCHAR(255) DEFAULT NULL UNIQUE, foto VARCHAR(500) DEFAULT NULL,
        rol VARCHAR(20) DEFAULT 'usuario'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    registroPreparar($conn);
    // El SQL de instalación manual debe crear el mismo esquema que PHP.
    $schemaPhp = $conn->query('SHOW CREATE TABLE registro_pendientes')->fetch_row()[1];
    $conn->query('DROP TABLE registro_pendientes, registro_limites');
    foreach (explode(';', file_get_contents(__DIR__ . '/../base de datos/registro_verificacion.sql')) as $sql) {
        if (trim($sql) !== '') { $conn->query($sql); }
    }
    comprobar($conn->query('SHOW CREATE TABLE registro_pendientes')->fetch_row()[1] === $schemaPhp, 'Los esquemas manual y automático deben coincidir.');

    $codigo = empezar($conn, 'ana@example.test', 'Ana');
    comprobar((int) $conn->query('SELECT COUNT(*) FROM usuarios')->fetch_row()[0] === 0, 'No crear cuenta antes de verificar.');
    $p = pendiente($conn, 'ana@example.test');
    comprobar($p['codigo_hash'] !== $codigo && password_verify($codigo, $p['codigo_hash']), 'Guardar un hash del código.');
    comprobar(password_verify('Prueba123', $p['password_hash']), 'Guardar la contraseña como hash.');
    comprobar(!registroSolicitar($conn, 'ana@example.test')[0], 'Aplicar espera de reenvío.');
    comprobar(registroConfirmar($conn, 'ana@example.test', $codigo)[0], 'Aceptar código correcto.');
    comprobar(pendiente($conn, 'ana@example.test') === null, 'Consumir el código.');
    comprobar(!registroConfirmar($conn, 'ana@example.test', $codigo)[0], 'Impedir reutilización.');

    $codigo = empezar($conn, 'bruno@example.test', 'Bruno');
    $incorrecto = $codigo === '000000' ? '111111' : '000000';
    for ($i = 0; $i < 5; $i++) { comprobar(!registroConfirmar($conn, 'bruno@example.test', $incorrecto)[0], 'Rechazar código incorrecto.'); }
    comprobar(!registroConfirmar($conn, 'bruno@example.test', $codigo)[0], 'Bloquear código correcto tras cinco errores.');
    $conn->query("UPDATE registro_pendientes SET enviado_en = enviado_en - 61 WHERE email = 'bruno@example.test'");
    comprobar(registroSolicitar($conn, 'bruno@example.test')[0], 'Permitir reenviar después de un minuto.');
    comprobar(pendiente($conn, 'bruno@example.test')['intentos'] == 0, 'Reiniciar intentos con código nuevo.');
    $codigo = $GLOBALS['codigos']['bruno@example.test'];
    $conn->query("UPDATE registro_pendientes SET expira = 1 WHERE email = 'bruno@example.test'");
    comprobar(!registroConfirmar($conn, 'bruno@example.test', $codigo)[0], 'Rechazar código vencido.');
    $conn->query("UPDATE registro_pendientes SET enviado_en = 1, envios = 5 WHERE email = 'bruno@example.test'");
    comprobar(!registroSolicitar($conn, 'bruno@example.test')[0], 'Limitar envíos por email aunque se reinicie la sesión.');

    $_SERVER['REMOTE_ADDR'] = '192.0.2.2';
    $codigo = empezar($conn, 'carla@example.test', 'Carla');
    $sesionCarla = $_SESSION;
    $_SESSION = [];
    comprobar(!registroConfirmar($conn, 'carla@example.test', $codigo)[0], 'Un código está vinculado al navegador iniciador.');
    comprobar(!registroSolicitar($conn, 'carla@example.test')[0], 'Otra sesión no puede reenviar el registro.');
    $_SESSION = $sesionCarla;
    comprobar(registroConfirmar($conn, 'carla@example.test', $codigo)[0], 'El navegador original sí puede confirmar.');

    // Si otro registro reemplaza la solicitud, la sesión anterior no debe confirmar
    // por accidente las credenciales elegidas por ese segundo solicitante.
    $codigo = empezar($conn, 'compartido@example.test', 'Original');
    $sesionOriginal = $_SESSION;
    $conn->query("UPDATE registro_pendientes SET enviado_en = 1 WHERE email = 'compartido@example.test'");
    $_SESSION = [];
    $codigoNuevo = empezar($conn, 'compartido@example.test', 'Otro');
    $_SESSION = $sesionOriginal;
    comprobar(!registroConfirmar($conn, 'compartido@example.test', $codigoNuevo)[0], 'No activar credenciales ajenas con un correo posterior.');

    $GLOBALS['correo_exitoso'] = false;
    comprobar(!registroSolicitar($conn, 'falla@example.test', ['nombre' => 'Falla', 'password' => 'Prueba123'])[0], 'Informar fallo del correo.');
    comprobar(pendiente($conn, 'falla@example.test')['expira'] == 0, 'Invalidar código cuyo correo falló.');
    $GLOBALS['correo_exitoso'] = true;
    $GLOBALS['correo_habilitado'] = false;
    comprobar(!registroSolicitar($conn, 'sinservicio@example.test', ['nombre' => 'SinServicio', 'password' => 'Prueba123'])[0], 'No aceptar registro sin correo configurado.');
    comprobar(pendiente($conn, 'sinservicio@example.test') === null, 'No guardar una cuenta pendiente si no hay servicio.');
    $GLOBALS['correo_habilitado'] = true;

    // El controlador real debe rechazar formularios sin el token de la sesión.
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = ['modo' => 'registro'];
    $_POST = ['accion' => 'registro', 'usuario' => ['dato-invalido'], 'email' => 'csrf@example.test'];
    require __DIR__ . '/../includes/check_auth.php';
    include __DIR__ . '/../includes/auth.php';
    comprobar(strpos($mensaje, 'formulario') !== false, 'Rechazar registro sin token CSRF, incluso con campos mal formados.');
    comprobar(pendiente($conn, 'csrf@example.test') === null, 'Un formulario rechazado no genera un pendiente.');
    $_POST = ['accion' => 'verificar_registro', 'codigo' => '123456'];
    include __DIR__ . '/../includes/auth.php';
    comprobar(strpos($mensaje, 'formulario') !== false, 'Rechazar confirmación sin token CSRF.');

    $_SERVER['REMOTE_ADDR'] = '192.0.2.3';
    for ($i = 0; $i < 10; $i++) { comprobar(registroLimiteIp($conn, 'enviar', 10), 'Aceptar hasta diez solicitudes por IP.'); }
    $_SESSION = [];
    comprobar(!registroLimiteIp($conn, 'enviar', 10), 'Cambiar sesión no reinicia el límite por IP.');
    $conn->query('UPDATE registro_limites SET inicio = inicio - 3600');
    comprobar(registroLimiteIp($conn, 'enviar', 10), 'Renovar límite en la siguiente hora.');

    $_SERVER['REMOTE_ADDR'] = '192.0.2.4';
    comprobar(registroSolicitar($conn, 'google@example.test', ['nombre' => 'GoogleNuevo', 'google_id' => 'sub-nuevo'])[0], 'Preparar un alta con Google.');
    comprobar(registroConfirmar($conn, 'google@example.test', $GLOBALS['codigos']['google@example.test'])[0], 'Confirmar alta Google.');
    $u = $conn->query("SELECT * FROM usuarios WHERE email = 'google@example.test'")->fetch_assoc();
    comprobar($u['google_id'] === 'sub-nuevo' && $u['password'] === null && $u['rol'] === 'usuario', 'Google no inventa una contraseña ni concede permisos.');
    $hashOriginal = password_hash('Original123', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre,email,password,rol) VALUES ('AdminExistente','existente@example.test',?,'admin')");
    $stmt->bind_param('s', $hashOriginal);
    $stmt->execute();
    comprobar(registroSolicitar($conn, 'existente@example.test', ['nombre' => 'OtroNombre', 'google_id' => 'sub-vincular'])[0], 'Preparar vinculación con Google.');
    comprobar(registroConfirmar($conn, 'existente@example.test', $GLOBALS['codigos']['existente@example.test'])[0], 'Vincular solo después de verificar.');
    $u = $conn->query("SELECT * FROM usuarios WHERE email = 'existente@example.test'")->fetch_assoc();
    comprobar($u['rol'] === 'admin' && $u['password'] === $hashOriginal && $u['nombre'] === 'AdminExistente', 'Conservar contraseña, nombre y permisos al vincular.');

    $codigo = empezar($conn, 'ocupado@example.test', 'AdminExistente');
    comprobar(!registroConfirmar($conn, 'ocupado@example.test', $codigo)[0], 'No duplicar un nombre ocupado durante la espera.');
    $conn->query("UPDATE registro_pendientes SET creado_en = 1 WHERE email = 'ocupado@example.test'");
    registroPreparar($conn);
    comprobar(pendiente($conn, 'ocupado@example.test') === null, 'Limpiar registros abandonados.');

    // Dos procesos intentan consumir el mismo código sobre conexiones distintas.
    $_SERVER['REMOTE_ADDR'] = '192.0.2.5';
    $codigo = empezar($conn, 'paralelo@example.test', 'Paralelo');
    $procesos = [];
    for ($i = 0; $i < 2; $i++) {
        $comando = [PHP_BINARY, __FILE__, '--worker', $base, 'paralelo@example.test', $codigo, $_SESSION['registro_solicitud'], '198.51.100.' . ($i + 1)];
        $proceso = proc_open($comando, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $procesos[] = [$proceso, $pipes];
    }
    $exitos = 0;
    foreach ($procesos as [$proceso, $pipes]) {
        $resultado = json_decode(stream_get_contents($pipes[1]), true);
        $errores = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        comprobar(proc_close($proceso) === 0 && is_array($resultado), 'La prueba paralela debe terminar: ' . $errores);
        $exitos += $resultado[0] ? 1 : 0;
    }
    comprobar($exitos === 1, 'Solo una petición puede consumir el código.');
    comprobar((int) $conn->query("SELECT COUNT(*) FROM usuarios WHERE email = 'paralelo@example.test'")->fetch_row()[0] === 1, 'Crear una sola cuenta ante confirmaciones simultáneas.');
    echo "OK: {$comprobaciones} comprobaciones de registro con MariaDB y transporte simulado.\n";
} finally {
    // Solo borrar la base aleatoria creada por esta ejecución, nunca la del proyecto.
    if (preg_match('/^margo_registro_test_[a-f0-9]{12}$/', $base)) {
        $conn->query("DROP DATABASE `{$base}`");
    }
    $conn->close();
}
