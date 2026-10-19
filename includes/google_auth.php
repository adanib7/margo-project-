<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check_auth.php';

// Google usa una cookie y un campo POST iguales para impedir solicitudes falsificadas.
$csrfCookie = $_COOKIE['g_csrf_token'] ?? '';
$csrfPost = $_POST['g_csrf_token'] ?? '';
if (!is_string($csrfCookie) || !is_string($csrfPost) || $csrfCookie === ''
    || !hash_equals($csrfCookie, $csrfPost) || !is_string($_POST['credential'] ?? null)) {
    header('Location: ' . buildUrl('/public/login.php?error=google', true));
    exit;
}

$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($_POST['credential']);
$contexto = stream_context_create(['http' => ['timeout' => 8]]);
$response = @file_get_contents($url, false, $contexto);
$datos = $response === false ? null : json_decode($response, true);
$email = $datos['email'] ?? '';
if (!is_array($datos) || !is_string($datos['sub'] ?? null) || $datos['sub'] === ''
    || ($datos['aud'] ?? '') !== GOOGLE_CLIENT_ID
    || !in_array($datos['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
    || (int) ($datos['exp'] ?? 0) <= time()
    || !filter_var($datos['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
    || !is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . buildUrl('/public/login.php?error=token', true));
    exit;
}
if ($conn === null) {
    header('Location: ' . buildUrl('/public/login.php?error=database', true));
    exit;
}

$reportAnterior = (new mysqli_driver())->report_mode;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $googleId = $datos['sub'];
    // Una cuenta ya vinculada se identifica por sub, que no depende del email.
    $stmt = $conn->prepare('SELECT id, nombre, rol FROM usuarios WHERE google_id = ? LIMIT 1');
    $stmt->bind_param('s', $googleId);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($usuario) {
        session_regenerate_id(true);
        $_SESSION['usuario_logueado'] = $usuario['nombre'];
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['rol'] = $usuario['rol'] ?? 'usuario';
        redirectToDashboard();
    }

    // Confirmar el correo antes de crear una cuenta o vincular una cuenta local.
    // También cubre cuentas Google que usan un correo de otro proveedor.
    require_once __DIR__ . '/registro.php';
    registroPreparar($conn);
    $base = preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]) ?: 'usuario';
    $base = substr($base, 0, 90);
    $nombre = $base;
    $numero = 1;
    $stmt = $conn->prepare('SELECT id FROM usuarios WHERE nombre = ?');
    do {
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $ocupado = $stmt->get_result()->num_rows > 0;
        if ($ocupado) { $nombre = $base . $numero++; }
    } while ($ocupado);
    $stmt->close();
    [$ok, $mensaje] = registroSolicitar($conn, $email, [
        'nombre' => $nombre,
        'google_id' => $googleId,
        'foto' => is_string($datos['picture'] ?? null) ? $datos['picture'] : null,
    ]);
    $_SESSION['registro_email'] = $email;
    $_SESSION['registro_mensaje'] = $mensaje;
    $_SESSION['registro_tipo'] = $ok ? 'success' : 'error';
    header('Location: ' . buildUrl('/public/login.php?modo=verificar', true));
} catch (Throwable $e) {
    error_log('Google: no se pudo completar el acceso o iniciar la verificación.');
    header('Location: ' . buildUrl('/public/login.php?error=database', true));
} finally {
    mysqli_report($reportAnterior);
}
exit;
