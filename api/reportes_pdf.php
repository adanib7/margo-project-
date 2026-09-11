<?php
/**
 * Informe de reportes en PDF (A4), con el mismo membrete que el comprobante.
 *
 * Si algo falla, abrir con ?debug=1 para ver el motivo en texto.
 */

if (isset($_GET['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "\nNo se pudo generar el informe.\nError: {$e['message']}\n  en {$e['file']} línea {$e['line']}\n";
    }
});

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

if (!is_file(__DIR__ . '/../includes/lib/fpdf/fpdf.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Falta la librería FPDF en includes/lib/fpdf/.';
    exit;
}
require_once __DIR__ . '/../includes/lib/fpdf/fpdf.php';

if ($conn === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sin conexión a la base de datos.';
    exit;
}

/** UTF-8 -> Windows-1252, que es lo que esperan las fuentes base de FPDF. */
function tr(string $s): string
{
    if (function_exists('iconv')) {
        $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        if ($r !== false) {
            return $r;
        }
    }
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }
    return $s;
}

class ReportePDF extends FPDF
{
    public $subtitulo = '';

    public function Footer()
    {
        $this->SetY(-16);
        $this->SetDrawColor(210, 205, 190);
        $this->SetLineWidth(0.2);
        $this->Line(18, $this->GetY(), 192, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Times', '', 8);
        $this->SetTextColor(110, 110, 105);
        $this->Cell(0, 4, tr(cfg('local.nombre') . ' · Informe generado el ' . date('d/m/Y') . ' a las ' . date('H:i')), 0, 0, 'L');
        $this->Cell(0, 4, tr('Página ' . $this->PageNo()), 0, 0, 'R');
    }
}

$preset = trim($_GET['preset'] ?? '30d');
if (!in_array($preset, REPORTE_PRESETS, true)) {
    $preset = '30d';
}

$r = reporteCompleto($conn, $preset, trim($_GET['desde'] ?? ''), trim($_GET['hasta'] ?? ''));

$VERDE  = [45, 95, 63];
$DORADO = [176, 127, 34];
$GRIS   = [110, 110, 105];
$TINTA  = [38, 38, 33];
$X0     = 18;
$X1     = 192;
$ANCHO  = $X1 - $X0;

try {

$pdf = new ReportePDF('P', 'mm', 'A4');
$pdf->SetTitle(tr('Informe de reservas · ' . $r['periodo']['etiqueta']));
$pdf->SetAuthor(tr((string) cfg('local.nombre')));
$pdf->SetMargins($X0, 16, 18);
$pdf->SetAutoPageBreak(true, 22);
$pdf->AddPage();

/* ── Membrete ── */
$logo = __DIR__ . '/../assets/img/logo-horizontal-verde.png';
if (is_file($logo)) {
    $pdf->Image($logo, $X0, 14, 48);
    $pdf->SetY(14 + 14);
} else {
    $pdf->SetTextColor(...$VERDE);
    $pdf->SetFont('Times', 'B', 18);
    $pdf->Cell(0, 9, tr((string) cfg('local.nombre')), 0, 1);
}

$pdf->SetX($X0);
$pdf->SetTextColor(...$VERDE);
$pdf->SetFont('Times', 'B', 15);
$pdf->Cell(0, 8, tr('Informe de reservas'), 0, 1);

$pdf->SetX($X0);
$pdf->SetTextColor(...$GRIS);
$pdf->SetFont('Times', '', 10);
$pdf->Cell(0, 5, tr($r['periodo']['etiqueta'] . '  ·  del ' . reporteFechaCorta($r['periodo']['desde'])
    . ' al ' . reporteFechaCorta($r['periodo']['hasta']) . '  ·  ' . $r['periodo']['dias'] . ' días'), 0, 1);

$pdf->Ln(2);
$pdf->SetDrawColor(...$DORADO);
$pdf->SetLineWidth(0.5);
$pdf->Line($X0, $pdf->GetY(), $X1, $pdf->GetY());
$pdf->Ln(7);

/* ── Helpers de dibujo ── */
$titulo = function (string $txt) use ($pdf, $VERDE, $X0) {
    $pdf->Ln(3);
    $pdf->SetX($X0);
    $pdf->SetTextColor(...$VERDE);
    $pdf->SetFont('Times', 'B', 12);
    $pdf->Cell(0, 7, tr($txt), 0, 1);
};

// Fila con barra proporcional: etiqueta | barra | valor
$fila = function (string $etiqueta, int|float $valor, float $max, string $textoDer, array $color) use ($pdf, $X0, $TINTA, $GRIS) {
    $pdf->SetX($X0);
    $pdf->SetFont('Times', '', 10);
    $pdf->SetTextColor(...$TINTA);
    $pdf->Cell(38, 6, tr($etiqueta), 0, 0);

    $anchoMax = 92;
    $w = $max > 0 ? max(0.6, $anchoMax * ($valor / $max)) : 0.6;
    $y = $pdf->GetY() + 1.5;

    $pdf->SetFillColor(238, 236, 228);
    $pdf->Rect($X0 + 38, $y, $anchoMax, 3.2, 'F');
    $pdf->SetFillColor(...$color);
    $pdf->Rect($X0 + 38, $y, $w, 3.2, 'F');

    $pdf->SetX($X0 + 38 + $anchoMax + 4);
    $pdf->SetTextColor(...$GRIS);
    $pdf->Cell(0, 6, tr($textoDer), 0, 1);
};

/* ── Resumen ── */
$s = $r['resumen'];
$pdf->SetX($X0);
$pdf->SetFont('Times', '', 10.5);
$pdf->SetTextColor(...$TINTA);

$kpis = [
    ['Reservas',          number_format($s['reservas'], 0, ',', '.')],
    ['Comensales',        number_format($s['comensales'], 0, ',', '.')],
    ['Media por reserva', number_format($s['media'], 1, ',', '') . ' pers.'],
    ['Canceladas',        $s['tasa_cancelacion'] . '%  (' . $s['canceladas'] . ' de ' . $s['total'] . ')'],
    ['Antelación media',  number_format($s['antelacion'], 1, ',', '') . ' días'],
];

$anchoKpi = $ANCHO / 5;
$yKpi = $pdf->GetY();
foreach ($kpis as $i => [$lab, $val]) {
    $x = $X0 + $i * $anchoKpi;
    $pdf->SetXY($x, $yKpi);
    $pdf->SetFont('Times', '', 8);
    $pdf->SetTextColor(...$GRIS);
    $pdf->Cell($anchoKpi, 4, tr($lab), 0, 2);
    $pdf->SetFont('Times', 'B', 13);
    $pdf->SetTextColor(...$VERDE);
    $pdf->Cell($anchoKpi, 7, tr($val), 0, 0);
}
$pdf->SetY($yKpi + 13);

if ($s['var_reservas'] !== null || $s['var_comensales'] !== null) {
    $pdf->SetX($X0);
    $pdf->SetFont('Times', 'I', 9);
    $pdf->SetTextColor(...$GRIS);
    $partes = [];
    if ($s['var_reservas'] !== null) {
        $partes[] = 'reservas ' . ($s['var_reservas'] >= 0 ? '+' : '') . $s['var_reservas'] . '%';
    }
    if ($s['var_comensales'] !== null) {
        $partes[] = 'comensales ' . ($s['var_comensales'] >= 0 ? '+' : '') . $s['var_comensales'] . '%';
    }
    $pdf->Cell(0, 5, tr('Respecto al período previo: ' . implode(' · ', $partes)), 0, 1);
}

/* ── Día de la semana ── */
$titulo('Reservas por día de la semana');
$maxD = max(1, ...array_map(fn($d) => $d['reservas'], $r['dias_semana']));
foreach ($r['dias_semana'] as $d) {
    $fila(
        $d['nombre'],
        $d['reservas'],
        $maxD,
        $d['reservas'] . ($d['cerrado'] ? '   (día de cierre)' : '   ·  ' . $d['comensales'] . ' comensales'),
        $d['cerrado'] ? [200, 198, 190] : $VERDE
    );
}

/* ── Franjas ── */
$titulo('Reservas por horario');
if ($r['franjas']) {
    $maxF = max(1, ...array_map(fn($f) => $f['reservas'], $r['franjas']));
    foreach ($r['franjas'] as $f) {
        $fila($f['hora'], $f['reservas'], $maxF, $f['reservas'] . '   ·  ' . $f['comensales'] . ' comensales', $DORADO);
    }
} else {
    $pdf->SetX($X0);
    $pdf->SetFont('Times', 'I', 10);
    $pdf->SetTextColor(...$GRIS);
    $pdf->Cell(0, 6, tr('Sin reservas en el período.'), 0, 1);
}

/* ── Mesas ── */
$titulo('Uso de las mesas');
if ($r['mesas']) {
    $maxM = max(1, ...array_map(fn($m) => $m['reservas'], $r['mesas']));
    foreach ($r['mesas'] as $m) {
        $der = $m['reservas'] === 0
            ? '0   (sin uso)'
            : $m['reservas'] . '   ·  ' . min(100, $m['llenado']) . '% llena';
        $fila('Mesa ' . $m['numero'] . ' (' . $m['capacidad'] . ' pers.)', $m['reservas'], $maxM, $der,
            $m['reservas'] === 0 ? [200, 198, 190] : $VERDE);
    }
} else {
    $pdf->SetX($X0);
    $pdf->SetFont('Times', 'I', 10);
    $pdf->SetTextColor(...$GRIS);
    $pdf->Cell(0, 6, tr('No hay un plano de mesas cargado.'), 0, 1);
}

/* ── Clientes ── */
if ($r['clientes']) {
    $titulo('Clientes que repiten');
    $maxC = max(1, ...array_map(fn($c) => $c['reservas'], $r['clientes']));
    foreach ($r['clientes'] as $c) {
        $fila($c['nombre'], $c['reservas'], $maxC, $c['reservas'] . ' reservas', $VERDE);
    }
}

/* ── Inventario ── */
if (!empty($r['inventario']['articulos'])) {
    $inv = $r['inventario'];
    $titulo('Inventario (estado de hoy)');
    $pdf->SetX($X0);
    $pdf->SetFont('Times', '', 10);
    $pdf->SetTextColor(...$TINTA);
    $pdf->Cell(0, 6, tr('Valor del stock: ' . number_format($inv['valor'], 2, ',', '.') . ' EUR'
        . '   ·  ' . $inv['articulos'] . ' artículos'
        . '   ·  ' . $inv['bajos'] . ' bajo mínimo'), 0, 1);
    $pdf->Ln(1);

    if ($inv['categorias']) {
        $maxV = max(1, ...array_map(fn($c) => $c['valor'], $inv['categorias']));
        foreach ($inv['categorias'] as $c) {
            $fila($c['categoria'], $c['valor'], $maxV, number_format($c['valor'], 2, ',', '.') . ' EUR', $DORADO);
        }
    }
}

$salida = $pdf->Output('S');

} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'No se pudo generar el informe: ' . $e->getMessage();
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="informe-' . $r['periodo']['desde'] . '_' . $r['periodo']['hasta'] . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $salida;
