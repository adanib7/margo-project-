<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}
echo json_encode([
    'ok' => true,
    'vence_en' => max(0, min(
        $_SESSION['sesion_actividad'] + SESION_INACTIVIDAD,
        $_SESSION['sesion_inicio'] + SESION_DURACION_MAXIMA
    ) - time()),
]);
