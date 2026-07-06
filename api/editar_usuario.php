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
$id     = (int)($body['id']       ?? 0);
$nombre = trim($body['nombre']    ?? '');
$email  = trim($body['email']     ?? '');
$rol    = trim($body['rol']       ?? '');
$pass   = $body['password']       ?? '';

// Validar ID
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']);
    exit;
}

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

if ($pass !== '') {
    if (strlen($pass) < 8) {
        $errores['password'] = 'Mínimo 8 caracteres.';
    } elseif (!preg_match('/[A-Z]/', $pass)) {
        $errores['password'] = 'Debe tener al menos una mayúscula.';
    } elseif (!preg_match('/[0-9]/', $pass)) {
        $errores['password'] = 'Debe tener al menos un número.';
    }
}

if (!empty($errores)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

// Verificar email duplicado (excluyendo el propio usuario)
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
$stmt->bind_param("si", $email, $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    http_response_code(409);
    echo json_encode(['ok' => false, 'errores' => ['email' => 'Ese correo ya está registrado.']]);
    exit;
}
$stmt->close();

// Proteger: no bajar de rol al único superadmin
if ($rol !== 'superadmin') {
    $stmt = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $actual = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (($actual['rol'] ?? '') === 'superadmin') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'superadmin'");
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        if ($count <= 1) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'No podés cambiar el rol del único superadmin del sistema.']);
            exit;
        }
    }
}

// Actualizar
if ($pass !== '') {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, email=?, rol=?, password=? WHERE id=?");
    $stmt->bind_param("ssssi", $nombre, $email, $rol, $hash, $id);
} else {
    $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, email=?, rol=? WHERE id=?");
    $stmt->bind_param("sssi", $nombre, $email, $rol, $id);
}

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['ok' => true, 'mensaje' => "Usuario «{$nombre}» actualizado correctamente."]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al actualizar. Intentá de nuevo.']);
}
