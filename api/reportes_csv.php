<?php
/**
 * Descarga las reservas del período como CSV (se abre en Excel).
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/reportes_db.php';
requireLogin();

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acceso denegado.';
    exit;
}

if ($conn === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sin conexión a la base de datos.';
    exit;
}

$preset = trim($_GET['preset'] ?? '30d');
if (!in_array($preset, REPORTE_PRESETS, true)) {
    $preset = '30d';
}
$p = reportePeriodo($preset, trim($_GET['desde'] ?? ''), trim($_GET['hasta'] ?? ''));

$conMesa = planoLigadoAReservas($conn);
$sql = $conMesa
    ? "SELECT r.codigo, r.fecha, r.hora, r.nombre, r.telefono, r.personas, r.estado, r.comentario, m.numero AS mesa
       FROM reservas r LEFT JOIN mesas m ON m.id = r.mesa_id
       WHERE r.fecha BETWEEN ? AND ? ORDER BY r.fecha ASC, r.hora ASC"
    : "SELECT r.codigo, r.fecha, r.hora, r.nombre, r.telefono, r.personas, r.estado, r.comentario, NULL AS mesa
       FROM reservas r
       WHERE r.fecha BETWEEN ? AND ? ORDER BY r.fecha ASC, r.hora ASC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo consultar: ' . $conn->error;
    exit;
}
$stmt->bind_param('ss', $p['desde'], $p['hasta']);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$archivo = 'reservas-' . $p['desde'] . '_' . $p['hasta'] . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Cache-Control: private, max-age=0, must-revalidate');

$out = fopen('php://output', 'w');

// BOM para que Excel reconozca el UTF-8 y no rompa los acentos.
fwrite($out, "\xEF\xBB\xBF");

// Excel en español espera punto y coma como separador.
fputcsv($out, ['Codigo', 'Fecha', 'Hora', 'Cliente', 'Telefono', 'Mesa', 'Personas', 'Estado', 'Nota'], ';');

foreach ($filas as $f) {
    fputcsv($out, [
        $f['codigo'],
        $f['fecha'],
        substr((string) $f['hora'], 0, 5),
        $f['nombre'],
        $f['telefono'] ?? '',
        $f['mesa'] !== null ? 'Mesa ' . (int) $f['mesa'] : '',
        (int) $f['personas'],
        $f['estado'],
        $f['comentario'] ?? '',
    ], ';');
}

fclose($out);
