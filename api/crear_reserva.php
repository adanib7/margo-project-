<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Debés iniciar sesión para reservar.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo conectar con la base de datos.']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$nombre     = trim($body['nombre']     ?? '');
$fecha      = trim($body['fecha']      ?? '');
$hora       = trim($body['hora']       ?? '');
$personas   = (int) ($body['personas'] ?? 0);
$comentario = trim($body['comentario'] ?? '');
$usuarioId  = (int) $_SESSION['usuario_id'];

$errores = [];

if ($nombre === '') {
    $errores['nombre'] = 'El nombre es obligatorio.';
}

$fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    $errores['fecha'] = 'Ingresá una fecha válida.';
} elseif ($fecha < date('Y-m-d')) {
    $errores['fecha'] = 'La fecha no puede ser anterior a hoy.';
}

if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    $errores['hora'] = 'Seleccioná un horario válido.';
}

if ($personas < 1 || $personas > 20) {
    $errores['personas'] = 'Ingresá entre 1 y 20 personas.';
}

if (!empty($errores)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

$alfabetoCodigo = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$codigo = 'COR-';
for ($i = 0; $i < 6; $i++) {
    $codigo .= $alfabetoCodigo[random_int(0, strlen($alfabetoCodigo) - 1)];
}

$stmt = $conn->prepare(
    "INSERT INTO reservas (codigo, usuario_id, nombre, fecha, hora, personas, comentario) VALUES (?, ?, ?, ?, ?, ?, ?)"
);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'La tabla de reservas no existe todavía en la base de datos.']);
    exit;
}

$stmt->bind_param('sisssis', $codigo, $usuarioId, $nombre, $fecha, $hora, $personas, $comentario);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode([
        'ok'      => true,
        'mensaje' => "¡Reserva confirmada para el {$fecha} a las {$hora}hs!",
        'codigo'  => $codigo,
        'nombre'  => $nombre,
        'fecha'   => $fecha,
        'hora'    => $hora,
        'personas' => $personas,
    ]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al crear la reserva. Intentá de nuevo.']);
}
