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
    if (!$usuario) {
        $stmt = $conn->prepare('SELECT id, nombre, rol, google_id FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $existente = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $foto = is_string($datos['picture'] ?? null) ? $datos['picture'] : null;

        if ($existente) {
            // Vincular por email solo si Google administra ese correo (Gmail/Workspace).
            // Para correos externos, email_verified por si solo no prueba su propiedad actual.
            $correoGoogle = str_ends_with(strtolower($email), '@gmail.com') || !empty($datos['hd']);
            if (!$correoGoogle || (!empty($existente['google_id']) && $existente['google_id'] !== $googleId)) {
                header('Location: ' . buildUrl('/public/login.php?error=google_cuenta', true));
                exit;
            }
            $stmt = $conn->prepare('UPDATE usuarios SET google_id = ?, foto = ? WHERE id = ?');
            $stmt->bind_param('ssi', $googleId, $foto, $existente['id']);
            $stmt->execute();
            $stmt->close();
            $usuario = $existente;
        } else {
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
            $stmt = $conn->prepare("INSERT INTO usuarios (google_id, nombre, email, foto, rol) VALUES (?, ?, ?, ?, 'usuario')");
            $stmt->bind_param('ssss', $googleId, $nombre, $email, $foto);
            $stmt->execute();
            $usuario = ['id' => $stmt->insert_id, 'nombre' => $nombre, 'rol' => 'usuario'];
            $stmt->close();
        }
    }
    session_regenerate_id(true);
    $_SESSION['usuario_logueado'] = $usuario['nombre'];
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['rol'] = $usuario['rol'] ?? 'usuario';
    unset($_SESSION['registro_email'], $_SESSION['registro_solicitud'], $_SESSION['registro_mensaje'], $_SESSION['registro_tipo']);
    redirectToDashboard();
} catch (Throwable $e) {
    error_log('Google: no se pudo completar el acceso.');
    header('Location: ' . buildUrl('/public/login.php?error=database', true));
} finally {
    mysqli_report($reportAnterior);
}
exit;
