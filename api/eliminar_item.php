<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/inventario_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if (!isset($conn) || $conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $dbErrorMessage ?: 'Sin conexión a la base de datos.']);
    exit;
}

ensureInventarioTable($conn);

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$id   = (int) ($body['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']);
    exit;
}

$stmt = $conn->prepare("SELECT nombre FROM inventario WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Artículo no encontrado.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM inventario WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['ok' => true, 'mensaje' => "Artículo «{$item['nombre']}» eliminado del inventario."]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al eliminar. Intentá de nuevo.']);
}
