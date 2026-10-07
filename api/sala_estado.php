<?php
/**
 * Plano en vivo de la sala (dashboards/sala.php + assets/js/sala.js).
 *
 * Devuelve en un solo pedido las mesas del plano y las reservas de un día.
 * El navegador calcula el estado de cada mesa para la hora elegida en la
 * barra de tiempo (así moverla es instantáneo, sin pedir nada al servidor).
 *
 *   GET ?fecha=2026-10-10  ->  { ok, hoy, ahora, duracion_min, desde, hasta, mesas, reservas }
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/plano_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}
if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Sin conexión a la base de datos.']);
    exit;
}

$fecha = trim($_GET['fecha'] ?? '');
$f = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$f || $f->format('Y-m-d') !== $fecha) {
    $fecha = date('Y-m-d');
}

ensureMesaTable($conn);
ensureReservaMesaColumn($conn);

// Mesas del plano (sin fecha/hora: solo posición, tamaño y capacidad).
$mesas = planoMesasConOcupacion($conn);

// Reservas del día que siguen en pie. Con r.* sirve aunque la base del hosting
// sea de un deploy viejo y le falte alguna columna (ej. telefono).
$stmt = $conn->prepare(
    "SELECT * FROM reservas
     WHERE fecha = ? AND estado <> 'cancelada'
     ORDER BY hora ASC"
);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudieron leer las reservas del día.']);
    exit;
}
$stmt->bind_param('s', $fecha);
$stmt->execute();
$reservas = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $reservas[] = [
        'id'         => (int) $r['id'],
        'codigo'     => $r['codigo'],
        'nombre'     => $r['nombre'],
        'hora'       => substr((string) $r['hora'], 0, 5),
        'personas'   => (int) $r['personas'],
        'estado'     => $r['estado'],
        'mesa_id'    => isset($r['mesa_id']) ? (int) $r['mesa_id'] : null,
        'telefono'   => $r['telefono'] ?? '',
        'comentario' => $r['comentario'] ?? '',
    ];
}
$stmt->close();

// Rango de la barra de tiempo: desde que abre el primer servicio hasta que
// termina la última reserva posible del último (fin + duración de la mesa).
$duracionMin = max(1, cfgInt('reservas.duracion_horas')) * 60;
$aMin = static fn(string $h) => (int) substr($h, 0, 2) * 60 + (int) substr($h, 3, 2);
$inicios = [];
$fines   = [];
foreach (['almuerzo', 'cena'] as $turno) {
    if (cfgBool("horario.{$turno}_activo")) {
        $inicios[] = $aMin((string) cfg("horario.{$turno}_inicio"));
        $fines[]   = $aMin((string) cfg("horario.{$turno}_fin"));
    }
}
$desde = $inicios ? min($inicios) : 12 * 60;
$hasta = $fines ? min(24 * 60 - 1, max($fines) + $duracionMin) : 24 * 60 - 1;

echo json_encode([
    'ok'           => true,
    'fecha'        => $fecha,
    'hoy'          => date('Y-m-d'), // hora del restaurante (Europe/Madrid), no la de la compu
    'ahora'        => date('H:i'),
    'duracion_min' => $duracionMin,
    'desde'        => $desde,        // minutos desde las 00:00
    'hasta'        => $hasta,
    'ancho'        => PLANO_ANCHO,
    'alto'         => PLANO_ALTO,
    'mesas'        => $mesas,
    'reservas'     => $reservas,
], JSON_UNESCAPED_UNICODE);
