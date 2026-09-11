<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/reportes_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $dbErrorMessage ?: 'Sin conexión a la base de datos.']);
    exit;
}

$preset = trim($_GET['preset'] ?? '30d');
$desde  = trim($_GET['desde'] ?? '');
$hasta  = trim($_GET['hasta'] ?? '');

if (!in_array($preset, REPORTE_PRESETS, true)) {
    $preset = '30d';
}

echo json_encode(['ok' => true] + reporteCompleto($conn, $preset, $desde, $hasta), JSON_UNESCAPED_UNICODE);
