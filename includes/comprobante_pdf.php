<?php
/**
 * Comprobante de reserva en PDF (hoja con membrete, A4).
 *
 * Se llega desde la pantalla de "reserva confirmada" y desde "Mis reservas".
 * Descarga un archivo  reserva-COR-XXXXXX.pdf.
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/plano_db.php';
require_once __DIR__ . '/lib/fpdf/fpdf.php';
requireLogin();

date_default_timezone_set('Europe/Madrid');

/* FPDF (fuentes base) trabaja en Latin-1; convertimos desde UTF-8. CP1252
   mantiene los acentos, la ñ, el · y el € que usa el texto. */
function t(string $s): string
{
    return iconv('UTF-8', 'CP1252//TRANSLIT', $s);
}

/**
 * Membrete arriba y pie al fondo en cada página; el cuerpo va en el medio.
 */
class ComprobantePDF extends FPDF
{
    public string $pieCodigo = '';

    public function Footer(): void
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
    ensureMesaTable($conn);
    ensureReservaMesaColumn($conn);

    $stmt = $conn->prepare(
        "SELECT r.codigo, r.nombre, r.fecha, r.hora, r.personas, r.comentario, r.telefono, r.estado,
                m.numero AS mesa_numero
         FROM reservas r
         LEFT JOIN mesas m ON m.id = r.mesa_id
         WHERE r.codigo = ? AND r.usuario_id = ?"
    );
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

$pdf = new ComprobantePDF('P', 'mm', 'A4');
$pdf->pieCodigo = $reserva['codigo'];
$pdf->SetTitle(t('Comprobante de reserva ' . $reserva['codigo']));
$pdf->SetAuthor(t('El Corralín de Campanal'));
$pdf->SetMargins(25, 22, 25);
$pdf->SetAutoPageBreak(true, 26);
$pdf->AddPage();

/* ───────── Membrete ───────── */
$pdf->SetTextColor(...$VERDE);
$pdf->SetFont('Times', 'B', 21);
$pdf->Cell(0, 10, t('El Corralín de Campanal'), 0, 1);

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

$pdf->Output('D', 'reserva-' . $reserva['codigo'] . '.pdf');
