<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Debés iniciar sesión para ver horarios.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$fecha = trim($_GET['fecha'] ?? '');

if ($fecha === '' || DateTime::createFromFormat('Y-m-d', $fecha)->format('Y-m-d') !== $fecha) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Fecha inválida.']);
    exit;
}

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo conectar con la base de datos.']);
    exit;
}

$stmt = $conn->prepare(
    "SELECT hora FROM reservas WHERE fecha = ? AND estado != 'cancelada'"
);
$stmt->bind_param('s', $fecha);
$stmt->execute();
$result = $stmt->get_result();
$horarios = [];
while ($row = $result->fetch_assoc()) {
    $horarios[] = substr($row['hora'], 0, 5);
}
$stmt->close();

echo json_encode(['ok' => true, 'horarios' => array_values(array_unique($horarios))]);
