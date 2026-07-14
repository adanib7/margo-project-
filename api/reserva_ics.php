<?php
session_start();
require_once '../includes/config.php';

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
    "SELECT codigo, nombre, fecha, hora, personas FROM reservas WHERE codigo = ? AND usuario_id = ?"
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
$fin    = (clone $inicio)->modify('+2 hours');

function icsEscape(string $texto): string {
    return str_replace(["\\", ",", ";", "\n"], ["\\\\", "\\,", "\\;", "\\n"], $texto);
}

$resumen      = icsEscape('Reserva en El Corralín de Campanal');
$descripcion  = icsEscape("Reserva para {$reserva['personas']} personas a nombre de {$reserva['nombre']}. Código: {$reserva['codigo']}");
$ubicacion    = icsEscape('Plaza Manuel Uría, 4, 33520 Nava, Asturias');

$lineas = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//El Corralin de Campanal//Reservas//ES',
    'CALSCALE:GREGORIAN',
    'BEGIN:VEVENT',
    'UID:' . $reserva['codigo'] . '@elcorralindelcampanal.com',
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
