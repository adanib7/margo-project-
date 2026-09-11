<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';

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

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Sin conexión a la base de datos.']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$id     = (int) ($body['id'] ?? 0);
$estado = trim((string) ($body['estado'] ?? ''));

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']);
    exit;
}

if (!in_array($estado, ['pendiente', 'confirmada', 'cancelada'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'mensaje' => 'Estado no válido.']);
    exit;
}

$stmt = $conn->prepare("SELECT codigo, nombre, estado FROM reservas WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reserva) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Reserva no encontrada.']);
    exit;
}

if ($reserva['estado'] === $estado) {
    echo json_encode(['ok' => true, 'mensaje' => 'La reserva ya estaba en ese estado.']);
    exit;
}

$stmt = $conn->prepare("UPDATE reservas SET estado = ? WHERE id = ?");
$stmt->bind_param('si', $estado, $id);

if ($stmt->execute()) {
    $stmt->close();
    $label = ['pendiente' => 'marcada como pendiente', 'confirmada' => 'confirmada', 'cancelada' => 'cancelada'][$estado];
    echo json_encode([
        'ok'      => true,
        'mensaje' => "Reserva {$reserva['codigo']} de «{$reserva['nombre']}» {$label}.",
        'estado'  => $estado,
    ]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al cambiar el estado.']);
}
