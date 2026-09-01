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
[$errores, $d] = validarItemInventario($body);

if ($errores) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

// Evitar duplicados por nombre (case-insensitive según collation).
$stmt = $conn->prepare("SELECT id FROM inventario WHERE nombre = ?");
$stmt->bind_param('s', $d['nombre']);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    http_response_code(409);
    echo json_encode(['ok' => false, 'errores' => ['nombre' => 'Ya existe un artículo con ese nombre.']]);
    exit;
}
$stmt->close();

$stmt = $conn->prepare("
    INSERT INTO inventario (nombre, categoria, unidad, stock, stock_minimo, precio_unitario, proveedor)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    'sssddds',
    $d['nombre'],
    $d['categoria'],
    $d['unidad'],
    $d['stock'],
    $d['stock_minimo'],
    $d['precio_unitario'],
    $d['proveedor']
);

if ($stmt->execute()) {
    $id = $stmt->insert_id;
    $stmt->close();
    echo json_encode(['ok' => true, 'mensaje' => "Artículo «{$d['nombre']}» agregado al inventario.", 'id' => $id]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al crear el artículo. Intentá de nuevo.']);
}
