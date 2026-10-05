<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/config_app.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo 'Debés iniciar sesión para descargar el evento.';
    exit;
}

$codigo = trim($_GET['codigo'] ?? '');

if ($codigo === '' || $conn === null) {
    http_response_code(404);
    echo 'Reserva no encontrada.';
    exit;
}

$stmt = $conn->prepare(
    "SELECT codigo, nombre, fecha, hora, personas, telefono FROM reservas WHERE codigo = ? AND usuario_id = ?"
);
$stmt->bind_param('si', $codigo, $_SESSION['usuario_id']);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reserva) {
    http_response_code(404);
    echo 'Reserva no encontrada.';
    exit;
}

$inicio = new DateTime($reserva['fecha'] . ' ' . $reserva['hora']);
$duracion = max(1, cfgInt('reservas.duracion_horas'));
$fin      = (clone $inicio)->modify("+{$duracion} hours");

function icsEscape(string $texto): string {
    return str_replace(["\\", ",", ";", "\n"], ["\\\\", "\\,", "\\;", "\\n"], $texto);
}

$resumen      = icsEscape('Reserva en ' . cfg('local.nombre'));
$descripcion  = icsEscape("Reserva para {$reserva['personas']} personas a nombre de {$reserva['nombre']}. Código: {$reserva['codigo']}");
if (!empty($reserva['telefono'])) {
    $descripcion .= icsEscape(" Teléfono: {$reserva['telefono']}");
}
$ubicacion    = icsEscape(localDireccion(', ', false));

$lineas = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//' . preg_replace('/[^A-Za-z0-9 ]/', '', (string) cfg('local.nombre')) . '//Reservas//ES',
    'CALSCALE:GREGORIAN',
    'BEGIN:VEVENT',
    'UID:' . $reserva['codigo'] . '@' . localDominio(),
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . $inicio->format('Ymd\THis'),
    'DTEND:' . $fin->format('Ymd\THis'),
    'SUMMARY:' . $resumen,
    'DESCRIPTION:' . $descripcion,
    'LOCATION:' . $ubicacion,
    'END:VEVENT',
    'END:VCALENDAR',
];

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="reserva-' . $reserva['codigo'] . '.ics"');
echo implode("\r\n", $lineas);
