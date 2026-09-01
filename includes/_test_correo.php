<?php
/**
 * Prueba rápida del envío por Brevo (borrar cuando ya funcione).
 *
 *   corralin.kesug.com/includes/_test_correo.php?to=tuemail@ejemplo.com
 *
 * Requiere sesión de superadmin.
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/mailer.php';
requireLogin();

header('Content-Type: text/plain; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo 'Solo superadmin.';
    exit;
}

$cfg = mailConfig();
echo "Config:\n";
echo '  archivo mail_config.php : ' . (is_file(__DIR__ . '/mail_config.php') ? 'existe' : 'FALTA') . "\n";
echo '  enabled                 : ' . ($cfg['enabled'] ? 'sí' : 'no') . "\n";
echo '  api key                 : ' . ($cfg['brevo_api_key'] !== '' ? 'cargada (' . substr($cfg['brevo_api_key'], 0, 10) . '…)' : 'VACÍA') . "\n";
echo '  from                    : ' . $cfg['from_name'] . ' <' . $cfg['from_email'] . ">\n";
echo '  mailHabilitado()        : ' . (mailHabilitado() ? 'sí' : 'no') . "\n";
echo '  cURL                    : ' . (function_exists('curl_init') ? 'sí' : 'no') . "\n\n";

$to = trim($_GET['to'] ?? '');
if ($to === '') {
    echo 'Agregá ?to=tuemail@ejemplo.com para enviar una prueba.';
    exit;
}

[$asunto, $html] = correoConfirmacionReserva([
    'codigo' => 'COR-PRUEBA', 'nombre' => 'Prueba', 'fecha' => date('Y-m-d', strtotime('+3 days')),
    'hora' => '21:00', 'personas' => 2, 'mesa_numero' => 4, 'comentario' => 'Correo de prueba',
]);

[$ok, $detalle] = enviarCorreoBrevo($to, 'Prueba', '[PRUEBA] ' . $asunto, $html);

echo 'Envío a ' . $to . " : " . ($ok ? 'OK' : 'FALLÓ') . "\n";
echo 'Detalle: ' . $detalle . "\n";
