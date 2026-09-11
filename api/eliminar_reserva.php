<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';

header('Content-Type: application/json; charset=utf-8');

// Borrar del historial es más delicado que cancelar: solo superadmin.
if (($_SESSION['rol'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Solo un superadmin puede eliminar reservas. Cancelala en su lugar.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Sin conexión a la base de datos.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$id   = (int) ($body['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']);
    exit;
}

$stmt = $conn->prepare("SELECT codigo, nombre FROM reservas WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reserva) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Reserva no encontrada.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM reservas WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['ok' => true, 'mensaje' => "Reserva {$reserva['codigo']} de «{$reserva['nombre']}» eliminada."]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al eliminar.']);
}
