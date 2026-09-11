<?php
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

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$id     = (int) ($body['id'] ?? 0);
$estado = trim((string) ($body['estado'] ?? ''));
$avisar = !empty($body['avisar']);
$motivo = trim((string) ($body['motivo'] ?? ''));

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

$stmt = $conn->prepare("SELECT * FROM reservas WHERE id = ?");
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

    // Aviso al cliente por correo (opcional, solo al anular).
    $emailEnviado = false;
    $emailDetalle = '';

    if ($avisar && $estado === 'cancelada' && !mailHabilitado()) {
        $emailDetalle = 'el envío de correos no está configurado';
    }

    if ($avisar && $estado === 'cancelada' && mailHabilitado()) {
        // Email del titular de la reserva.
        $u = $conn->prepare("SELECT email FROM usuarios WHERE id = ?");
        $emailCliente = '';
        if ($u !== false) {
            $u->bind_param('i', $reserva['usuario_id']);
            $u->execute();
            $emailCliente = trim((string) ($u->get_result()->fetch_assoc()['email'] ?? ''));
            $u->close();
        }

        // Número de mesa, solo si el plano está montado.
        $mesaNumero = null;
        if (!empty($reserva['mesa_id']) && planoLigadoAReservas($conn)) {
            $qm = $conn->prepare("SELECT numero FROM mesas WHERE id = ?");
            if ($qm !== false) {
                $qm->bind_param('i', $reserva['mesa_id']);
                $qm->execute();
                $rowm = $qm->get_result()->fetch_assoc();
                $qm->close();
                if ($rowm) { $mesaNumero = (int) $rowm['numero']; }
            }
        }

        if ($emailCliente !== '') {
            [$asunto, $html] = correoCancelacionReserva([
                'codigo'      => $reserva['codigo'],
                'nombre'      => $reserva['nombre'],
                'fecha'       => $reserva['fecha'],
                'hora'        => $reserva['hora'],
                'personas'    => $reserva['personas'],
                'mesa_numero' => $mesaNumero,
                'motivo'      => $motivo,
            ]);
            [$emailEnviado, $emailDetalle] = enviarCorreoBrevo($emailCliente, $reserva['nombre'], $asunto, $html);
        } else {
            $emailDetalle = 'el cliente no tiene email registrado';
        }
    }

    $label = ['pendiente' => 'marcada como pendiente', 'confirmada' => 'confirmada', 'cancelada' => 'cancelada'][$estado];
    $mensaje = "Reserva {$reserva['codigo']} de «{$reserva['nombre']}» {$label}.";
    if ($avisar && $estado === 'cancelada') {
        $mensaje .= $emailEnviado
            ? ' Se avisó al cliente por correo.'
            : ' No se avisó por correo (' . ($emailDetalle !== '' ? $emailDetalle : 'error de envío') . ').';
    }

    echo json_encode([
        'ok'            => true,
        'mensaje'       => $mensaje,
        'estado'        => $estado,
        'email_enviado' => $emailEnviado,
        'email_detalle' => $emailDetalle,
    ]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error interno al cambiar el estado.']);
}
