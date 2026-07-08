<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check_auth.php';

if (!isset($_POST['credential'])) {
    header("Location: " . buildUrl('/index.php?error=google'));
    exit;
}

$id_token = $_POST['credential'];

$url      = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);
$response = @file_get_contents($url);

if ($response === false) {
    header("Location: " . buildUrl('/index.php?error=google'));
    exit;
}

$datos = json_decode($response, true);

if (!isset($datos['sub']) || $datos['aud'] !== GOOGLE_CLIENT_ID) {
    header("Location: " . buildUrl('/index.php?error=token'));
    exit;
}

$google_id = $datos['sub'];
$email     = $datos['email'];
$nombre    = $datos['name'];
$foto      = $datos['picture'] ?? null;

// Si $conn es null, no podemos procesar
if ($conn === null) {
    header("Location: " . buildUrl('/index.php?error=database'));
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre, google_id, rol FROM usuarios WHERE google_id = ? OR email = ? LIMIT 1");
if (!$stmt) {
    header("Location: " . buildUrl('/index.php?error=database'));
    exit;
}
$stmt->bind_param("ss", $google_id, $email);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $user   = $resultado->fetch_assoc();
    $nombre = $user['nombre'];
    $_SESSION['rol'] = $user['rol'] ?? 'usuario';

    // Vincular google_id si el usuario existía solo por email
    if (empty($user['google_id'])) {
        $upd = $conn->prepare("UPDATE usuarios SET google_id = ?, foto = ? WHERE id = ?");
        $upd->bind_param("ssi", $google_id, $foto, $user['id']);
        $upd->execute();
        $upd->close();
    }
} else {
    // Nuevo usuario: generar nombre único a partir del email
    $base_nombre = preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]);
    $nombre      = $base_nombre;
    $i           = 1;

    $check = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ?");
    while (true) {
        $check->bind_param("s", $nombre);
        $check->execute();
        if ($check->get_result()->num_rows === 0) break;
        $nombre = $base_nombre . $i++;
    }
    $check->close();

    $ins = $conn->prepare("INSERT INTO usuarios (google_id, nombre, email, foto, rol) VALUES (?, ?, ?, ?, 'usuario')");
    $ins->bind_param("ssss", $google_id, $nombre, $email, $foto);
    $ins->execute();
    $ins->close();
    $_SESSION['rol'] = 'usuario';
}

$_SESSION['usuario_logueado'] = $nombre;

// Guardar ID en sesión para gestión de usuarios
$stmt_id = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ? LIMIT 1");
$stmt_id->bind_param("s", $nombre);
$stmt_id->execute();
$row_id = $stmt_id->get_result()->fetch_assoc();
$stmt_id->close();
$_SESSION['usuario_id'] = $row_id['id'] ?? null;

redirectToDashboard();
