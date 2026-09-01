<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/plano_db.php';
require_once '../includes/mailer.php';

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

ensureMesaTable($conn);
ensureReservaMesaColumn($conn);

$body       = json_decode(file_get_contents('php://input'), true);
$nombre     = trim($body['nombre']     ?? '');
$fecha      = trim($body['fecha']      ?? '');
$hora       = trim($body['hora']       ?? '');
$personas   = (int) ($body['personas'] ?? 0);
$comentario = trim($body['comentario'] ?? '');
$telefono   = trim($body['telefono'] ?? '');
$mesaId     = (int) ($body['mesa_id'] ?? 0);
$usuarioId  = (int) $_SESSION['usuario_id'];

$errores = [];
$mesaNumero = null;

if ($nombre === '') {
    $errores['nombre'] = 'El nombre es obligatorio.';
}

if ($telefono === '') {
    $errores['telefono'] = 'El teléfono es obligatorio.';
} elseif (!preg_match('/^[0-9+()\s-]{6,25}$/', $telefono)) {
    $errores['telefono'] = 'Ingresá un teléfono válido.';
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

if ($mesaId <= 0) {
    $errores['mesa'] = 'Elegí una mesa del plano.';
} else {
    $stmt = $conn->prepare("SELECT numero, capacidad FROM mesas WHERE id = ?");
    $stmt->bind_param('i', $mesaId);
    $stmt->execute();
    $mesa = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$mesa) {
        $errores['mesa'] = 'La mesa elegida ya no está disponible. Actualizá el plano.';
    } else {
        $mesaNumero = (int) $mesa['numero'];
        if ($personas > (int) $mesa['capacidad']) {
            $errores['personas'] = "La mesa {$mesaNumero} admite hasta {$mesa['capacidad']} personas.";
        }
    }
}

if (!empty($errores)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM reservas WHERE mesa_id = ? AND fecha = ? AND hora = ? AND estado != 'cancelada' LIMIT 1");
$stmt->bind_param('iss', $mesaId, $fecha, $hora);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $errores['mesa'] = "La mesa {$mesaNumero} ya está reservada en esa franja. Elegí otra mesa u otro horario.";
}
$stmt->close();

if (!empty($errores)) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

$alfabetoCodigo = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$codigo = 'COR-';
for ($i = 0; $i < 6; $i++) {
    $codigo .= $alfabetoCodigo[random_int(0, strlen($alfabetoCodigo) - 1)];
}

$stmt = $conn->prepare(
    "INSERT INTO reservas (codigo, usuario_id, mesa_id, nombre, fecha, hora, personas, comentario, telefono, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmada')"
);

if ($stmt === false) {
    $errorMsg = $conn->error;
    if (stripos($errorMsg, 'unknown column') !== false) {
        // Base sin columna `telefono` (deploy viejo): insertamos sin ese campo.
        $stmt = $conn->prepare(
            "INSERT INTO reservas (codigo, usuario_id, mesa_id, nombre, fecha, hora, personas, comentario, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmada')"
        );
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo preparar la inserción de reserva.']);
            exit;
        }
        $stmt->bind_param('siisssis', $codigo, $usuarioId, $mesaId, $nombre, $fecha, $hora, $personas, $comentario);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => 'La tabla de reservas no existe todavía en la base de datos.']);
        exit;
    }
} else {
    $stmt->bind_param('siisssiss', $codigo, $usuarioId, $mesaId, $nombre, $fecha, $hora, $personas, $comentario, $telefono);
}

if ($stmt->execute()) {
    $stmt->close();

    // Correo de confirmación al cliente. Si falla, la reserva ya está hecha:
    // no se corta ni se devuelve error, solo se informa en 'email_enviado'.
    $emailEnviado = false;
    if (mailHabilitado()) {
        $u = $conn->prepare("SELECT email FROM usuarios WHERE id = ?");
        if ($u !== false) {
            $u->bind_param('i', $usuarioId);
            $u->execute();
            $emailUsuario = trim((string) ($u->get_result()->fetch_assoc()['email'] ?? ''));
            $u->close();

            if ($emailUsuario !== '') {
                [$asunto, $html] = correoConfirmacionReserva([
                    'codigo'      => $codigo,
                    'nombre'      => $nombre,
                    'fecha'       => $fecha,
                    'hora'        => $hora,
                    'personas'    => $personas,
                    'mesa_numero' => $mesaNumero,
                    'comentario'  => $comentario,
                ]);
                [$emailEnviado] = enviarCorreoBrevo($emailUsuario, $nombre, $asunto, $html);
            }
        }
    }

    echo json_encode([
        'ok'            => true,
        'mensaje'       => "¡Reserva confirmada para el {$fecha} a las {$hora}hs!",
        'codigo'        => $codigo,
        'nombre'        => $nombre,
        'fecha'         => $fecha,
        'hora'          => $hora,
        'personas'      => $personas,
        'telefono'      => $telefono,
        'mesa'          => $mesaNumero,
        'email_enviado' => $emailEnviado,
    ]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al crear la reserva. Intentá de nuevo.']);
}
