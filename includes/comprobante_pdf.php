<?php
/**
 * Comprobante de reserva en PDF (hoja con membrete, A4).
 *
 * Se llega desde la pantalla de "reserva confirmada" y desde "Mis reservas".
 * Descarga un archivo  reserva-COR-XXXXXX.pdf.
 *
 * Si algo falla, abrir con  ?debug=1  para ver el motivo en texto.
 */

$DEBUG_PDF = isset($_GET['debug']);
if ($DEBUG_PDF) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Cazar errores fatales que ocurran fuera del try/catch de más abajo
// (así el hosting no devuelve un 500 en blanco).
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "\nNo se pudo generar el PDF.\nError: {$e['message']}\n  en {$e['file']} línea {$e['line']}\n";
    }
});

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/plano_db.php';
requireLogin();

if (!is_file(__DIR__ . '/lib/fpdf/fpdf.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Falta la librería FPDF en includes/lib/fpdf/. Subí esa carpeta al servidor.';
    exit;
}
require_once __DIR__ . '/lib/fpdf/fpdf.php';

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Madrid');
}

/**
 * UTF-8 -> Windows-1252 (lo que esperan las fuentes base de FPDF).
 * Sin depender de iconv/mbstring: algunos hostings compartidos (InfinityFree)
 * no traen iconv y el script moría con error 500.
 */
function t(string $s): string
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
    return utf8ToWin1252($s);
}

/**
 * Conversión manual UTF-8 -> Windows-1252 (fallback puro PHP).
 */
function utf8ToWin1252(string $s): string
{
    // Codepoints de 0x80-0x9F que Windows-1252 coloca distinto a Latin-1.
    static $especiales = [
        0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85,
        0x2020 => 0x86, 0x2021 => 0x87, 0x02C6 => 0x88, 0x2030 => 0x89, 0x0160 => 0x8A,
        0x2039 => 0x8B, 0x0152 => 0x8C, 0x017D => 0x8E, 0x2018 => 0x91, 0x2019 => 0x92,
        0x201C => 0x93, 0x201D => 0x94, 0x2022 => 0x95, 0x2013 => 0x96, 0x2014 => 0x97,
        0x02DC => 0x98, 0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B, 0x0153 => 0x9C,
        0x017E => 0x9E, 0x0178 => 0x9F,
    ];
    $out = '';
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = ord($s[$i]);
        if ($c < 0x80) {
            $out .= chr($c);
            continue;
        }
        // decodificar el codepoint UTF-8
        if ($c >= 0xF0 && $i + 3 < $len) {
            $cp = (($c & 0x07) << 18) | ((ord($s[$i + 1]) & 0x3F) << 12) | ((ord($s[$i + 2]) & 0x3F) << 6) | (ord($s[$i + 3]) & 0x3F);
            $i += 3;
        } elseif ($c >= 0xE0 && $i + 2 < $len) {
            $cp = (($c & 0x0F) << 12) | ((ord($s[$i + 1]) & 0x3F) << 6) | (ord($s[$i + 2]) & 0x3F);
            $i += 2;
        } elseif ($c >= 0xC0 && $i + 1 < $len) {
            $cp = (($c & 0x1F) << 6) | (ord($s[$i + 1]) & 0x3F);
            $i += 1;
        } else {
            $out .= '?';
            continue;
        }
        if ($cp <= 0xFF) {
            $out .= chr($cp);              // Latin-1 directo
        } elseif (isset($especiales[$cp])) {
            $out .= chr($especiales[$cp]); // €, comillas tipográficas, guiones…
        } else {
            $out .= '?';
        }
    }
    return $out;
}

/**
 * Membrete arriba y pie al fondo en cada página; el cuerpo va en el medio.
 */
class ComprobantePDF extends FPDF
{
    public $pieCodigo = '';

    public function Footer()
    {
        $this->SetY(-20);
        $this->SetDrawColor(210, 205, 190);
        $this->SetLineWidth(0.2);
        $this->Line(25, $this->GetY(), 185, $this->GetY());
        $this->Ln(2.5);
        $this->SetFont('Times', '', 8);
        $this->SetTextColor(110, 110, 105);
        $this->Cell(0, 4, t('Comprobante emitido el ' . date('d/m/Y') . ' a las ' . date('H:i')
            . ' · Código de reserva ' . $this->pieCodigo), 0, 1, 'C');
        $this->Cell(0, 4, t('El Corralín de Campanal · Plaza Manuel Uría, 4 · 33520 Nava (Asturias)'), 0, 0, 'C');
    }
}

$codigo  = trim($_GET['codigo'] ?? '');
$reserva = null;

if ($codigo !== '' && $conn !== null) {
    // SELECT * : así no depende de qué columnas tenga `reservas` en cada
    // servidor (telefono / mesa_id pueden no existir todavía).
    $stmt = $conn->prepare("SELECT * FROM reservas WHERE codigo = ? AND usuario_id = ? LIMIT 1");
    if ($stmt === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No se pudo consultar la reserva: ' . $conn->error;
        exit;
    }
    $stmt->bind_param('si', $codigo, $_SESSION['usuario_id']);
    $stmt->execute();
    $reserva = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$reserva) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No existe una reserva con ese código a tu nombre.';
    exit;
}

/* La mesa se busca aparte y solo si corresponde, para que el comprobante
   funcione aunque el plano no esté montado en este servidor. */
$reserva['mesa_numero'] = null;
if (!empty($reserva['mesa_id']) && planoLigadoAReservas($conn)) {
    $qm = $conn->prepare("SELECT numero FROM mesas WHERE id = ?");
    if ($qm !== false) {
        $qm->bind_param('i', $reserva['mesa_id']);
        $qm->execute();
        $rowm = $qm->get_result()->fetch_assoc();
        $qm->close();
        if ($rowm) {
            $reserva['mesa_numero'] = (int) $rowm['numero'];
        }
    }
}
$reserva['telefono']   = $reserva['telefono']   ?? '';
$reserva['comentario'] = $reserva['comentario'] ?? '';

/* ---- fechas en castellano, sin depender de intl/strftime ---- */
$DIAS = [
    'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves',
    'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo',
];
$MESES = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
    7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
];

$fReserva   = new DateTime($reserva['fecha']);
$fechaLarga = $DIAS[$fReserva->format('l')] . ', ' . (int) $fReserva->format('j')
            . ' de ' . $MESES[(int) $fReserva->format('n')] . ' de ' . $fReserva->format('Y');

$hoy     = new DateTime('now');
$emision = 'Nava, a ' . (int) $hoy->format('j') . ' de ' . $MESES[(int) $hoy->format('n')] . ' de ' . $hoy->format('Y');

$hora     = substr((string) $reserva['hora'], 0, 5);
$personas = (int) $reserva['personas'];
$mesaTxt  = $reserva['mesa_numero'] !== null
    ? 'Mesa ' . (int) $reserva['mesa_numero']
    : 'Se asignará a la llegada';

/* ---- Paleta El Corralín ---- */
$VERDE   = [45, 95, 63];
$DORADO  = [176, 127, 34];
$GRIS    = [110, 110, 105];
$TINTA   = [38, 38, 33];
$BURDEOS = [140, 47, 57];
$X_FIN   = 185;

try {

$pdf = new ComprobantePDF('P', 'mm', 'A4');
$pdf->pieCodigo = $reserva['codigo'];
$pdf->SetTitle(t('Comprobante de reserva ' . $reserva['codigo']));
$pdf->SetAuthor(t('El Corralín de Campanal'));
$pdf->SetMargins(25, 22, 25);
$pdf->SetAutoPageBreak(true, 26);
$pdf->AddPage();

/* ───────── Membrete ───────── */
$logo = __DIR__ . '/../assets/img/logo-horizontal-verde.png';
if (is_file($logo)) {
    // 1040 x 276 px  ->  a 56 mm de ancho ≈ 14,9 mm de alto
    $pdf->Image($logo, 25, 19, 56);
    $pdf->SetY(19 + 16);
} else {
    // Sin el archivo: título en texto como respaldo.
    $pdf->SetTextColor(...$VERDE);
    $pdf->SetFont('Times', 'B', 21);
    $pdf->Cell(0, 10, t('El Corralín de Campanal'), 0, 1);
}

$pdf->SetX(25);
$pdf->SetTextColor(...$GRIS);
$pdf->SetFont('Times', 'I', 10.5);
$pdf->Cell(0, 5, t('Cocina asturiana y sidra de llagar'), 0, 1);

$pdf->SetFont('Times', '', 9);
$pdf->Cell(0, 5, t('Plaza Manuel Uría, 4 · 33520 Nava (Asturias) · Tel. 985 71 60 42'), 0, 1);

$pdf->Ln(3.5);
$pdf->SetDrawColor(...$DORADO);
$pdf->SetLineWidth(0.5);
$pdf->Line(25, $pdf->GetY(), $X_FIN, $pdf->GetY());
$pdf->Ln(11);

/* ───────── Fecha de emisión ───────── */
$pdf->SetTextColor(...$TINTA);
$pdf->SetFont('Times', '', 10.5);
$pdf->Cell(0, 6, t($emision), 0, 1, 'R');
$pdf->Ln(4);

/* ───────── Saludo y cuerpo ───────── */
$pdf->SetFont('Times', '', 11.5);
$pdf->Cell(0, 7, t('Estimado/a ' . $reserva['nombre'] . ':'), 0, 1);
$pdf->Ln(1);

$pdf->SetFont('Times', '', 11);
$pdf->MultiCell(0, 6, t(
    'Le confirmamos su reserva en El Corralín de Campanal. A continuación figuran los datos; '
    . 'le rogamos que los revise y conserve este comprobante para el día de su visita.'
), 0, 'J');
$pdf->Ln(5);

/* ───────── Bloque de datos ───────── */
$fila = function (string $etiqueta, string $valor) use ($pdf, $GRIS, $TINTA) {
    $pdf->SetX(33);
    $pdf->SetFont('Times', 'B', 9.5);
    $pdf->SetTextColor(...$GRIS);
    $pdf->Cell(36, 6.6, t($etiqueta), 0, 0);
    $pdf->SetFont('Times', '', 11.5);
    $pdf->SetTextColor(...$TINTA);
    $pdf->MultiCell(0, 6.6, t($valor), 0, 'L');
};

$fila('Código',      $reserva['codigo']);
$fila('Fecha',       $fechaLarga);
$fila('Hora',        $hora . ' h');
$fila('Mesa',        $mesaTxt);
$fila('Comensales',  $personas === 1 ? '1 persona' : $personas . ' personas');
$fila('A nombre de', $reserva['nombre']);
if (trim((string) $reserva['telefono']) !== '') {
    $fila('Teléfono', $reserva['telefono']);
}
if (trim((string) $reserva['comentario']) !== '') {
    $fila('Nota', $reserva['comentario']);
}
$pdf->Ln(6);

/* ───────── Estado anulado ───────── */
if ($reserva['estado'] === 'cancelada') {
    $pdf->SetFont('Times', 'B', 11);
    $pdf->SetTextColor(...$BURDEOS);
    $pdf->MultiCell(0, 6, t('Aviso: esta reserva figura actualmente como ANULADA.'), 0, 'L');
    $pdf->Ln(3);
}

/* ───────── Cierre ───────── */
$pdf->SetFont('Times', '', 11);
$pdf->SetTextColor(...$TINTA);
$pdf->MultiCell(0, 6, t(
    'La mesa se mantiene reservada durante 15 minutos a partir de la hora indicada. '
    . 'Para cualquier cambio o anulación puede llamarnos al 985 71 60 42.'
), 0, 'J');
$pdf->Ln(7);

$pdf->Cell(0, 6, t('Un cordial saludo,'), 0, 1);
$pdf->Ln(2);
$pdf->SetFont('Times', 'B', 11);
$pdf->SetTextColor(...$VERDE);
$pdf->Cell(0, 6, t('El Corralín de Campanal'), 0, 1);

$salida = $pdf->Output('S');

} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'No se pudo generar el PDF: ' . $e->getMessage();
    exit;
}

/* Descartar cualquier salida accidental previa (algunos hostings compartidos
   inyectan un script, o un warning se cuela) para no corromper el binario. */
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="reserva-' . $reserva['codigo'] . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $salida;
