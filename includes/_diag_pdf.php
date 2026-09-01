<?php
/**
 * Diagnóstico del comprobante PDF (borrar cuando esté resuelto).
 * Abrir en el navegador:  corralin.kesug.com/includes/_diag_pdf.php
 * Pegar TODO lo que muestre.
 */

header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "== Entorno ==\n";
echo 'PHP            : ' . PHP_VERSION . "\n";
echo 'OS             : ' . PHP_OS . "\n";
echo 'iconv          : ' . (function_exists('iconv') ? 'sí' : 'NO') . "\n";
echo 'mbstring       : ' . (function_exists('mb_convert_encoding') ? 'sí' : 'NO') . "\n";
echo 'zlib/gzcompress: ' . (function_exists('gzcompress') ? 'sí' : 'NO') . "\n";
echo 'gd             : ' . (extension_loaded('gd') ? 'sí' : 'no') . "\n";
echo 'session_start  : ' . (function_exists('session_start') ? 'sí' : 'NO') . "\n";
echo 'memory_limit   : ' . ini_get('memory_limit') . "\n";
echo 'output_buffering: ' . ini_get('output_buffering') . "\n";
echo "\n";

echo "== Archivos FPDF ==\n";
$base = __DIR__ . '/lib/fpdf';
foreach (['fpdf.php', 'font/times.php', 'font/timesb.php', 'font/timesi.php'] as $f) {
    $p = $base . '/' . $f;
    echo str_pad($f, 18) . ' : ' . (is_file($p) ? 'ok (' . filesize($p) . ' b)' : 'FALTA') . "\n";
}
echo "\n";

echo "== Cargar FPDF ==\n";
try {
    require_once $base . '/fpdf.php';
    echo class_exists('FPDF') ? "clase FPDF cargada ok\n" : "clase FPDF NO existe\n";
} catch (\Throwable $e) {
    echo 'ERROR al cargar fpdf.php: ' . $e->getMessage() . "\n";
    exit;
}
echo "\n";

echo "== Generar un PDF de prueba ==\n";
try {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Times', 'B', 16);
    $pdf->Cell(0, 10, 'Prueba - acentos: ' . iconv_test(), 0, 1);
    $s = $pdf->Output('S');
    echo 'PDF generado ok, ' . strlen($s) . " bytes\n";
    echo 'empieza con: ' . substr($s, 0, 8) . "\n";
} catch (\Throwable $e) {
    echo 'ERROR al generar: ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

function iconv_test(): string
{
    $txt = 'Código ñ · —';
    if (function_exists('iconv')) {
        $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $txt);
        if ($r !== false) {
            return $r;
        }
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($txt, 'Windows-1252', 'UTF-8');
    }
    return '(sin conversor)';
}
