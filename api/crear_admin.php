<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';

header('Content-Type: application/json; charset=utf-8');

// Solo superadmin puede llamar este endpoint
if (($_SESSION['rol'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

// Solo aceptamos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

// Leer body JSON
$body   = json_decode(file_get_contents('php://input'), true);
$nombre = trim($body['nombre']   ?? '');
$email  = trim($body['email']    ?? '');
$pass   = $body['password']      ?? '';

// ── Validaciones ─────────────────────────────────────────────────────────────

$errores = [];

if ($nombre === '') {
    $errores['nombre'] = 'El nombre es obligatorio.';
}

if ($email === '') {
    $errores['email'] = 'El correo es obligatorio.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores['email'] = 'El correo no tiene un formato válido.';
}

if ($pass === '') {
    $errores['password'] = 'La contraseña es obligatoria.';
} elseif (strlen($pass) < 8) {
    $errores['password'] = 'La contraseña debe tener al menos 8 caracteres.';
} elseif (!preg_match('/[A-Z]/', $pass)) {
    $errores['password'] = 'La contraseña debe tener al menos una mayúscula.';
} elseif (!preg_match('/[0-9]/', $pass)) {
    $errores['password'] = 'La contraseña debe tener al menos un número.';
}

if (!empty($errores)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

// ── Verificar duplicado ───────────────────────────────────────────────────────

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

// ── Insertar admin ────────────────────────────────────────────────────────────

$hash = password_hash($pass, PASSWORD_BCRYPT);

$stmt = $conn->prepare(
    "INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'admin')"
);
$stmt->bind_param("sss", $nombre, $email, $hash);

if ($stmt->execute()) {
    $nuevoId = $stmt->insert_id;
    $stmt->close();
    echo json_encode([
        'ok'      => true,
        'mensaje' => "Admin «{$nombre}» creado correctamente.",
        'id'      => $nuevoId,
    ]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al crear el admin. Intentá de nuevo.']);
}
