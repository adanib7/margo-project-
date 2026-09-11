<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/config_app.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Sin conexión a la base de datos.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];

$errores = [];
$pares   = [];

/* ── Horarios ── */
$horaOk = static fn($h) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $h);

foreach (['almuerzo', 'cena'] as $turno) {
    $activo = !empty($body["horario.{$turno}_activo"]);
    $pares["horario.{$turno}_activo"] = $activo ? '1' : '0';

    $ini = trim((string) ($body["horario.{$turno}_inicio"] ?? ''));
    $fin = trim((string) ($body["horario.{$turno}_fin"] ?? ''));

    if (!$horaOk($ini) || !$horaOk($fin)) {
        if ($activo) {
            $errores["horario.{$turno}_inicio"] = 'Horas inválidas (formato HH:MM).';
        }
        continue;
    }
    if ($fin <= $ini) {
        if ($activo) {
            $errores["horario.{$turno}_fin"] = 'El fin tiene que ser posterior al inicio.';
        }
        continue;
    }
    $pares["horario.{$turno}_inicio"] = $ini;
    $pares["horario.{$turno}_fin"]    = $fin;
}

if (empty($body['horario.almuerzo_activo']) && empty($body['horario.cena_activo'])) {
    $errores['horario.almuerzo_activo'] = 'Tiene que haber al menos un turno activo.';
}

$intervalo = (int) ($body['horario.intervalo_min'] ?? 30);
if (!in_array($intervalo, [15, 30, 60], true)) {
    $errores['horario.intervalo_min'] = 'Elegí 15, 30 o 60 minutos.';
} else {
    $pares['horario.intervalo_min'] = (string) $intervalo;
}

// Días cerrados: enteros 1..7, sin repetidos.
$dias = $body['horario.dias_cerrados'] ?? [];
$dias = is_array($dias) ? array_values(array_unique(array_filter(array_map('intval', $dias), fn($d) => $d >= 1 && $d <= 7))) : [];
if (count($dias) >= 7) {
    $errores['horario.dias_cerrados'] = 'No podés cerrar los siete días.';
} else {
    sort($dias);
    $pares['horario.dias_cerrados'] = json_encode($dias);
}

// Fechas cerradas: Y-m-d válidas, sin repetidos, ordenadas.
$fechas = $body['horario.fechas_cerradas'] ?? [];
$limpias = [];
if (is_array($fechas)) {
    foreach ($fechas as $f) {
        $f = trim((string) $f);
        $d = DateTime::createFromFormat('Y-m-d', $f);
        if ($d && $d->format('Y-m-d') === $f && !in_array($f, $limpias, true)) {
            $limpias[] = $f;
        }
    }
}
sort($limpias);
$pares['horario.fechas_cerradas'] = json_encode($limpias);

/* ── Reglas de reserva ── */
$reglas = [
    'reservas.max_personas'         => [1, 50,  'Ingresá entre 1 y 50.'],
    'reservas.antelacion_min_horas' => [0, 168, 'Ingresá entre 0 y 168 horas.'],
    'reservas.antelacion_max_dias'  => [1, 365, 'Ingresá entre 1 y 365 días.'],
    'reservas.duracion_horas'       => [1, 8,   'Ingresá entre 1 y 8 horas.'],
    'reservas.cortesia_min'         => [0, 120, 'Ingresá entre 0 y 120 minutos.'],
];

foreach ($reglas as $clave => [$min, $max, $msg]) {
    $v = $body[$clave] ?? null;
    if ($v === null || $v === '' || !is_numeric($v)) {
        $errores[$clave] = $msg;
        continue;
    }
    $v = (int) $v;
    if ($v < $min || $v > $max) {
        $errores[$clave] = $msg;
        continue;
    }
    $pares[$clave] = (string) $v;
}

$pares['reservas.auto_confirmar'] = !empty($body['reservas.auto_confirmar']) ? '1' : '0';

if ($errores) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errores' => $errores]);
    exit;
}

[$ok, $n] = cfgGuardar($conn, $pares);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo guardar la configuración: ' . $conn->error]);
    exit;
}

echo json_encode([
    'ok'       => true,
    'mensaje'  => 'Configuración guardada.',
    'guardadas' => $n,
    'franjas'  => horarioFranjas(),
]);
