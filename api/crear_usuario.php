<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$nombre = trim($body['nombre']   ?? '');
$email  = trim($body['email']    ?? '');
$pass   = $body['password']      ?? '';
$rol    = trim($body['rol']      ?? 'usuario');

$errores = [];

if ($nombre === '') {
    $errores['nombre'] = 'El nombre es obligatorio.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores['email'] = 'Ingresá un correo válido.';
}

if (!in_array($rol, ['usuario', 'admin', 'superadmin'], true)) {
    $errores['rol'] = 'Seleccioná un rol válido.';
}

if ($pass === '') {
    $errores['password'] = 'La contraseña es obligatoria.';
} elseif (strlen($pass) < 8) {
    $errores['password'] = 'Mínimo 8 caracteres.';
} elseif (!preg_match('/[A-Z]/', $pass)) {
    $errores['password'] = 'Debe tener al menos una mayúscula.';
} elseif (!preg_match('/[0-9]/', $pass)) {
    $errores['password'] = 'Debe tener al menos un número.';
}

if (!empty($errores)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

// Verificar duplicado
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    http_response_code(409);
    echo json_encode(['ok' => false, 'errores' => ['email' => 'Ese correo ya está registrado.']]);
    exit;
}
$stmt->close();

$hash = password_hash($pass, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $nombre, $email, $hash, $rol);

if ($stmt->execute()) {
    $nuevoId = $stmt->insert_id;
    $stmt->close();
    $labelRol = ['usuario' => 'Usuario', 'admin' => 'Admin', 'superadmin' => 'Superadmin'][$rol] ?? $rol;
    echo json_encode([
        'ok'      => true,
        'mensaje' => "{$labelRol} «{$nombre}» creado correctamente.",
        'id'      => $nuevoId,
    ]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al crear el usuario. Intentá de nuevo.']);
}
