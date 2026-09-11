<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/plano_db.php';

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

$body       = json_decode(file_get_contents('php://input'), true) ?: [];
$id         = (int) ($body['id'] ?? 0);
$nombre     = trim((string) ($body['nombre'] ?? ''));
$telefono   = trim((string) ($body['telefono'] ?? ''));
$fecha      = trim((string) ($body['fecha'] ?? ''));
$hora       = trim((string) ($body['hora'] ?? ''));
$personas   = (int) ($body['personas'] ?? 0);
$comentario = trim((string) ($body['comentario'] ?? ''));
$estado     = trim((string) ($body['estado'] ?? ''));
$mesaId     = (int) ($body['mesa_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'ID inválido.']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM reservas WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->store_result();
$existe = $stmt->num_rows > 0;
$stmt->close();

if (!$existe) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'mensaje' => 'Reserva no encontrada.']);
    exit;
}

$errores    = [];
$mesaNumero = null;
$conMesa    = planoLigadoAReservas($conn);

if ($nombre === '') {
    $errores['nombre'] = 'El nombre es obligatorio.';
}

if ($telefono !== '' && !preg_match('/^[0-9+()\s-]{6,25}$/', $telefono)) {
    $errores['telefono'] = 'Ingresá un teléfono válido.';
}

$fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    $errores['fecha'] = 'Ingresá una fecha válida.';
}

if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    $errores['hora'] = 'Seleccioná un horario válido.';
}

if ($personas < 1 || $personas > 20) {
    $errores['personas'] = 'Ingresá entre 1 y 20 personas.';
}

if (!in_array($estado, ['pendiente', 'confirmada', 'cancelada'], true)) {
    $errores['estado'] = 'Estado no válido.';
}

// Mesa: solo se valida si el plano existe. El admin puede dejarla sin asignar.
if ($conMesa && $mesaId > 0) {
    $q = $conn->prepare("SELECT numero, capacidad FROM mesas WHERE id = ?");
    $q->bind_param('i', $mesaId);
    $q->execute();
    $mesa = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$mesa) {
        $errores['mesa'] = 'La mesa elegida ya no existe.';
    } else {
        $mesaNumero = (int) $mesa['numero'];
        if ($personas > (int) $mesa['capacidad']) {
            $errores['personas'] = "La mesa {$mesaNumero} admite hasta {$mesa['capacidad']} personas.";
        }
    }
}

if ($errores) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

// Choque con otra reserva activa en la misma mesa / franja.
if ($conMesa && $mesaId > 0 && $estado !== 'cancelada') {
    $q = $conn->prepare(
        "SELECT codigo FROM reservas
         WHERE mesa_id = ? AND fecha = ? AND hora = ? AND estado != 'cancelada' AND id <> ? LIMIT 1"
    );
    $q->bind_param('issi', $mesaId, $fecha, $hora, $id);
    $q->execute();
    $choque = $q->get_result()->fetch_assoc();
    $q->close();

    if ($choque) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'errores' => [
            'mesa' => "La mesa {$mesaNumero} ya está ocupada en esa franja (reserva {$choque['codigo']}).",
        ]]);
        exit;
    }
}

if ($conMesa) {
    $mesaParam = $mesaId > 0 ? $mesaId : null;
    $stmt = $conn->prepare(
        "UPDATE reservas SET nombre = ?, telefono = ?, fecha = ?, hora = ?, personas = ?, comentario = ?, estado = ?, mesa_id = ?
         WHERE id = ?"
    );
    $stmt->bind_param('ssssissii', $nombre, $telefono, $fecha, $hora, $personas, $comentario, $estado, $mesaParam, $id);
} else {
    $stmt = $conn->prepare(
        "UPDATE reservas SET nombre = ?, telefono = ?, fecha = ?, hora = ?, personas = ?, comentario = ?, estado = ?
         WHERE id = ?"
    );
    $stmt->bind_param('ssssissi', $nombre, $telefono, $fecha, $hora, $personas, $comentario, $estado, $id);
}

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo preparar la actualización: ' . $conn->error]);
    exit;
}

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['ok' => true, 'mensaje' => "Reserva de «{$nombre}» actualizada."]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al guardar los cambios.']);
}
