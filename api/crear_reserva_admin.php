<?php
/**
 * Alta rápida de reserva desde el panel (teléfono o mostrador).
 *
 * A diferencia de la reserva del cliente, acá el admin puede saltarse los días
 * de cierre y la antelación mínima: son justamente los casos de excepción que
 * se resuelven por teléfono. Lo que sí se respeta es lo físico: la capacidad de
 * la mesa y que no se pise con otra reserva.
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/plano_db.php';
require_once '../includes/mailer.php';

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

ensureMesaTable($conn);
ensureReservaMesaColumn($conn);
ensureReservaUsuarioOpcional($conn);

$body       = json_decode(file_get_contents('php://input'), true) ?: [];
$nombre     = trim((string) ($body['nombre'] ?? ''));
$telefono   = trim((string) ($body['telefono'] ?? ''));
$email      = trim((string) ($body['email'] ?? ''));
$fecha      = trim((string) ($body['fecha'] ?? ''));
$hora       = trim((string) ($body['hora'] ?? ''));
$personas   = (int) ($body['personas'] ?? 0);
$comentario = trim((string) ($body['comentario'] ?? ''));
$mesaId     = (int) ($body['mesa_id'] ?? 0);
$avisar     = !empty($body['avisar']);

$errores    = [];
$mesaNumero = null;
$conMesa    = planoLigadoAReservas($conn);

if ($nombre === '') {
    $errores['nombre'] = 'El nombre es obligatorio.';
}

if ($telefono === '') {
    $errores['telefono'] = 'El teléfono es obligatorio.';
} elseif (!preg_match('/^[0-9+()\s-]{6,25}$/', $telefono)) {
    $errores['telefono'] = 'Ingresá un teléfono válido.';
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores['email'] = 'Ingresá un correo válido.';
}

$fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
    $errores['fecha'] = 'Ingresá una fecha válida.';
}

if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    $errores['hora'] = 'Seleccioná un horario válido.';
}

$maxPersonas = max(1, cfgInt('reservas.max_personas'));
if ($personas < 1 || $personas > $maxPersonas) {
    $errores['personas'] = "Ingresá entre 1 y {$maxPersonas} personas.";
}

// La mesa es opcional: el admin puede dejarla sin asignar.
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

// Choque por solapamiento (lo físico sí se respeta).
if ($conMesa && $mesaId > 0) {
    $choque = reservaSolapada($conn, $mesaId, $fecha, $hora);
    if ($choque) {
        $desde = substr((string) $choque['hora'], 0, 5);
        http_response_code(409);
        echo json_encode(['ok' => false, 'errores' => [
            'mesa' => "La mesa {$mesaNumero} ya está ocupada desde las {$desde} (reserva {$choque['codigo']}).",
        ]]);
        exit;
    }
}

// Si el email coincide con una cuenta, la reserva se le vincula: así la ve en
// "Mis reservas" y puede descargar su comprobante.
$usuarioId = null;
if ($email !== '') {
    $q = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    if ($q !== false) {
        $q->bind_param('s', $email);
        $q->execute();
        $u = $q->get_result()->fetch_assoc();
        $q->close();
        if ($u) {
            $usuarioId = (int) $u['id'];
        }
    }
}

$alfabetoCodigo = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$codigo = 'COR-';
for ($i = 0; $i < 6; $i++) {
    $codigo .= $alfabetoCodigo[random_int(0, strlen($alfabetoCodigo) - 1)];
}

$mesaParam = ($conMesa && $mesaId > 0) ? $mesaId : null;

$stmt = $conn->prepare(
    "INSERT INTO reservas (codigo, usuario_id, mesa_id, nombre, fecha, hora, personas, comentario, telefono, estado)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmada')"
);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo preparar la reserva: ' . $conn->error]);
    exit;
}

$stmt->bind_param('siisssiss', $codigo, $usuarioId, $mesaParam, $nombre, $fecha, $hora, $personas, $comentario, $telefono);

if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al crear la reserva.']);
    exit;
}
$stmt->close();

// Confirmación por correo, si el admin la pidió y hay a dónde mandarla.
$emailEnviado = false;
$emailDetalle = '';

if ($avisar && $email !== '') {
    if (!mailHabilitado()) {
        $emailDetalle = 'el envío de correos no está configurado';
    } else {
        [$asunto, $html] = correoConfirmacionReserva([
            'codigo'      => $codigo,
            'nombre'      => $nombre,
            'fecha'       => $fecha,
            'hora'        => $hora,
            'personas'    => $personas,
            'mesa_numero' => $mesaNumero,
            'comentario'  => $comentario,
        ]);
        [$emailEnviado, $emailDetalle] = enviarCorreoBrevo($email, $nombre, $asunto, $html);
    }
}

$mensaje = "Reserva {$codigo} creada para «{$nombre}».";
if ($avisar && $email !== '') {
    $mensaje .= $emailEnviado
        ? ' Se envió la confirmación por correo.'
        : ' No se pudo enviar el correo (' . ($emailDetalle !== '' ? $emailDetalle : 'error de envío') . ').';
}

echo json_encode([
    'ok'            => true,
    'mensaje'       => $mensaje,
    'codigo'        => $codigo,
    'mesa'          => $mesaNumero,
    'vinculada'     => $usuarioId !== null,
    'email_enviado' => $emailEnviado,
]);
