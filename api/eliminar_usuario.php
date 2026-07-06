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

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']);
    exit;
}

// No podés eliminarte a vos mismo
if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === $id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'No podés eliminar tu propia cuenta.']);
    exit;
}

// Obtener datos del usuario a eliminar
$stmt = $conn->prepare("SELECT nombre, rol FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Usuario no encontrado.']);
    exit;
}

// No eliminar el último superadmin
if ($user['rol'] === 'superadmin') {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'superadmin'");
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($count <= 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'mensaje' => 'No podés eliminar el único superadmin del sistema.']);
        exit;
    }
}

$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['ok' => true, 'mensaje' => "Usuario «{$user['nombre']}» eliminado correctamente."]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al eliminar. Intentá de nuevo.']);
}
