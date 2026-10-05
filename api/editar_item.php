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

$stmt = $conn->prepare("SELECT id FROM inventario WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Artículo no encontrado.']);
    exit;
}
$stmt->close();

[$errores, $d] = validarItemInventario($body);

if ($errores) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

// Otro artículo con el mismo nombre.
$stmt = $conn->prepare("SELECT id FROM inventario WHERE nombre = ? AND id <> ?");
$stmt->bind_param('si', $d['nombre'], $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    http_response_code(409);
    echo json_encode(['ok' => false, 'errores' => ['nombre' => 'Ya existe otro artículo con ese nombre.']]);
    exit;
}
$stmt->close();

$stmt = $conn->prepare("
    UPDATE inventario
    SET nombre = ?, categoria = ?, unidad = ?, stock = ?, stock_minimo = ?, precio_unitario = ?, proveedor = ?
    WHERE id = ?
");
$stmt->bind_param(
    'sssdddsi',
    $d['nombre'],
    $d['categoria'],
    $d['unidad'],
    $d['stock'],
    $d['stock_minimo'],
    $d['precio_unitario'],
    $d['proveedor'],
    $id
);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['ok' => true, 'mensaje' => "Artículo «{$d['nombre']}» actualizado."]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al guardar. Intentá de nuevo.']);
}
