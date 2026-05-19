<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check_auth.php';

if (!isset($_POST['credential'])) {
    header("Location: " . BASE_URL . "/login.php?error=google");
    exit;
}

$id_token = $_POST['credential'];

$url      = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);
$response = @file_get_contents($url);

if ($response === false) {
    header("Location: " . BASE_URL . "/login.php?error=google");
    exit;
}

$datos = json_decode($response, true);

if (!isset($datos['sub']) || $datos['aud'] !== GOOGLE_CLIENT_ID) {
    header("Location: " . BASE_URL . "/login.php?error=token");
    exit;
}

$google_id = $datos['sub'];
$email     = $datos['email'];
$nombre    = $datos['name'];
$foto      = $datos['picture'] ?? null;

$stmt = $conn->prepare("SELECT id, nombre, google_id FROM usuarios WHERE google_id = ? OR email = ? LIMIT 1");
$stmt->bind_param("ss", $google_id, $email);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $user   = $resultado->fetch_assoc();
    $nombre = $user['nombre'];

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

    $ins = $conn->prepare("INSERT INTO usuarios (google_id, nombre, email, foto) VALUES (?, ?, ?, ?)");
    $ins->bind_param("ssss", $google_id, $nombre, $email, $foto);
    $ins->execute();
    $ins->close();
}

$_SESSION['usuario_logueado'] = $nombre;

redirectToDashboard();
