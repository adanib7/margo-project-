<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/rutas.php';
$comprobaciones = 0;
function comprobarRuta(bool $ok, string $mensaje): void
{
    if (!$ok) { throw new RuntimeException($mensaje); }
    $GLOBALS['comprobaciones']++;
}
$reglas = file_get_contents(__DIR__ . '/../.htaccess');
foreach (rutasPantallas() as $archivo => $amigable) {
    comprobarRuta(is_file(__DIR__ . '/..' . $archivo), 'La pantalla debe existir: ' . $archivo);
    comprobarRuta(rutaPublica($archivo) === $amigable, 'Convertir ruta: ' . $archivo);
    comprobarRuta(rutaPublica($archivo . '?modo=registro&x=1#formulario') === $amigable . '?modo=registro&x=1#formulario', 'Conservar parámetros y anclas.');
    foreach (['', '/margo-project-', '/otro/proyecto'] as $prefijo) {
        comprobarRuta(baseDesdeScript($prefijo . $archivo) === $prefijo, 'Detectar base desde archivo: ' . $prefijo . $archivo);
        if ($amigable !== '/') {
            comprobarRuta(baseDesdeScript($prefijo . $amigable) === $prefijo, 'Detectar base desde URL amigable.');
            comprobarRuta(baseDesdeScript($prefijo . $amigable . '/') === $prefijo, 'Detectar base con barra final.');
        }
    }
    if ($amigable !== '/') {
        $regla = 'RewriteRule ^' . ltrim($amigable, '/') . '/?$ ' . ltrim($archivo, '/') . ' [L]';
        comprobarRuta(str_contains($reglas, $regla), 'La tabla PHP debe coincidir con .htaccess: ' . $amigable);
    }
}
foreach (['/api/recuperar_password.php', '/api/reportes.php', '/includes/google_auth.php', '/includes/comprobante.php'] as $ruta) {
    comprobarRuta(rutaPublica($ruta . '?codigo=PRUEBA') === $ruta . '?codigo=PRUEBA', 'Conservar endpoints y descargas.');
    comprobarRuta(baseDesdeScript('/margo-project-' . $ruta) === '/margo-project-', 'Base de endpoints en subcarpeta.');
}
echo "OK: {$comprobaciones} comprobaciones de rutas, archivos y reglas Apache.\n";
