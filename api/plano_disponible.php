<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/plano_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Debés iniciar sesión para ver el plano.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $dbErrorMessage ?: 'No se pudo conectar con la base de datos.']);
    exit;
}

$fecha = trim($_GET['fecha'] ?? '');
$hora  = trim($_GET['hora'] ?? '');

if ($fecha !== '') {
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
        $fecha = '';
    }
}
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    $hora = '';
}

ensureMesaTable($conn);
ensureReservaMesaColumn($conn);

$mesas = planoMesasConOcupacion($conn, $fecha, $hora);

echo json_encode([
    'ok'    => true,
    'ancho' => PLANO_ANCHO,
    'alto'  => PLANO_ALTO,
    'mesas' => $mesas,
], JSON_UNESCAPED_UNICODE);
