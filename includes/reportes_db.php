<?php
/**
 * Cálculo de los datos de la sección Reportes.
 *
 * Vive aparte del endpoint porque lo usan tres salidas: la pantalla (JSON),
 * la descarga CSV y el PDF.
 */

require_once __DIR__ . '/plano_db.php';

const REPORTE_PRESETS = ['7d', '30d', '90d', 'mes', 'mes_anterior', 'anio'];

/**
 * Traduce un preset a un rango de fechas concreto.
 *
 * @return array{desde:string, hasta:string, etiqueta:string}
 */
function reportePeriodo(string $preset, string $desde = '', string $hasta = ''): array
{
    $hoy = new DateTime('today');

    $valida = static function (string $f): ?string {
        $d = DateTime::createFromFormat('Y-m-d', $f);
        return ($d && $d->format('Y-m-d') === $f) ? $f : null;
    };

    // Rango a medida: gana sobre el preset.
    $d = $valida($desde);
    $h = $valida($hasta);
    if ($d && $h) {
        if ($d > $h) {
            [$d, $h] = [$h, $d];
        }
        return ['desde' => $d, 'hasta' => $h, 'etiqueta' => 'Del ' . reporteFechaCorta($d) . ' al ' . reporteFechaCorta($h)];
    }

    switch ($preset) {
        case '7d':
            $ini = (clone $hoy)->modify('-6 days');
            return ['desde' => $ini->format('Y-m-d'), 'hasta' => $hoy->format('Y-m-d'), 'etiqueta' => 'Últimos 7 días'];
        case '90d':
            $ini = (clone $hoy)->modify('-89 days');
            return ['desde' => $ini->format('Y-m-d'), 'hasta' => $hoy->format('Y-m-d'), 'etiqueta' => 'Últimos 90 días'];
        case 'mes':
            return [
                'desde'    => $hoy->format('Y-m-01'),
                'hasta'    => $hoy->format('Y-m-t'),
                'etiqueta' => 'Este mes (' . reporteMes((int) $hoy->format('n')) . ')',
            ];
        case 'mes_anterior':
            $ant = (new DateTime('first day of last month'));
            return [
                'desde'    => $ant->format('Y-m-01'),
                'hasta'    => $ant->format('Y-m-t'),
                'etiqueta' => 'Mes anterior (' . reporteMes((int) $ant->format('n')) . ')',
            ];
        case 'anio':
            return ['desde' => $hoy->format('Y-01-01'), 'hasta' => $hoy->format('Y-12-31'), 'etiqueta' => 'Año ' . $hoy->format('Y')];
        case '30d':
        default:
            $ini = (clone $hoy)->modify('-29 days');
            return ['desde' => $ini->format('Y-m-d'), 'hasta' => $hoy->format('Y-m-d'), 'etiqueta' => 'Últimos 30 días'];
    }
}

function reporteMes(int $n): string
{
    $m = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
          'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $m[$n] ?? '';
}

function reporteFechaCorta(string $iso): string
{
    $d = new DateTime($iso);
    return (int) $d->format('j') . ' ' . substr(reporteMes((int) $d->format('n')), 0, 3) . ' ' . $d->format('Y');
}

/**
 * Totales de un rango: reservas, comensales, canceladas y antelación media.
 */
function reporteResumen(mysqli $conn, string $desde, string $hasta): array
{
    $vacio = ['reservas' => 0, 'comensales' => 0, 'canceladas' => 0, 'media' => 0.0, 'antelacion' => 0.0];

    $stmt = $conn->prepare(
        "SELECT
            SUM(estado <> 'cancelada')                                        AS activas,
            COALESCE(SUM(CASE WHEN estado <> 'cancelada' THEN personas END),0) AS comensales,
            SUM(estado =  'cancelada')                                        AS canceladas,
            COUNT(*)                                                          AS total,
            AVG(CASE WHEN estado <> 'cancelada' THEN DATEDIFF(fecha, DATE(fecha_creacion)) END) AS antelacion
         FROM reservas
         WHERE fecha BETWEEN ? AND ?"
    );
    if ($stmt === false) {
        return $vacio;
    }
    $stmt->bind_param('ss', $desde, $hasta);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $activas = (int) ($r['activas'] ?? 0);
    $comen   = (int) ($r['comensales'] ?? 0);

    return [
        'reservas'   => $activas,
        'comensales' => $comen,
        'canceladas' => (int) ($r['canceladas'] ?? 0),
        'total'      => (int) ($r['total'] ?? 0),
        'media'      => $activas > 0 ? round($comen / $activas, 1) : 0.0,
        'antelacion' => round((float) ($r['antelacion'] ?? 0), 1),
    ];
}

/**
 * Reservas y comensales por día de la semana (1 = lunes … 7 = domingo).
 */
function reportePorDiaSemana(mysqli $conn, string $desde, string $hasta): array
{
    $datos = [];
    foreach (DIAS_SEMANA as $n => $nombre) {
        $datos[$n] = ['dia' => $n, 'nombre' => $nombre, 'reservas' => 0, 'comensales' => 0, 'cerrado' => false];
    }

    $cerrados = array_map('intval', cfgArray('horario.dias_cerrados'));
    foreach ($cerrados as $c) {
        if (isset($datos[$c])) {
            $datos[$c]['cerrado'] = true;
        }
    }

    $stmt = $conn->prepare(
        "SELECT WEEKDAY(fecha) + 1 AS dia, COUNT(*) AS n, COALESCE(SUM(personas),0) AS p
         FROM reservas
         WHERE fecha BETWEEN ? AND ? AND estado <> 'cancelada'
         GROUP BY dia"
    );
    if ($stmt !== false) {
        $stmt->bind_param('ss', $desde, $hasta);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $d = (int) $row['dia'];
            if (isset($datos[$d])) {
                $datos[$d]['reservas']   = (int) $row['n'];
                $datos[$d]['comensales'] = (int) $row['p'];
            }
        }
        $stmt->close();
    }

    return array_values($datos);
}

/**
 * Reservas y comensales por horario.
 */
function reportePorFranja(mysqli $conn, string $desde, string $hasta): array
{
    $stmt = $conn->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') AS h, COUNT(*) AS n, COALESCE(SUM(personas),0) AS p
         FROM reservas
         WHERE fecha BETWEEN ? AND ? AND estado <> 'cancelada'
         GROUP BY h
         ORDER BY h ASC"
    );
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param('ss', $desde, $hasta);
    $stmt->execute();
    $res = $stmt->get_result();

    $filas = [];
    while ($row = $res->fetch_assoc()) {
        $filas[] = ['hora' => $row['h'], 'reservas' => (int) $row['n'], 'comensales' => (int) $row['p']];
    }
    $stmt->close();

    return $filas;
}

/**
 * Ranking de mesas por uso. Vacío si el plano no está montado.
 */
function reportePorMesa(mysqli $conn, string $desde, string $hasta): array
{
    if (!planoLigadoAReservas($conn)) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT m.numero, m.capacidad,
                COUNT(r.id) AS n,
                COALESCE(SUM(r.personas),0) AS p
         FROM mesas m
         LEFT JOIN reservas r
                ON r.mesa_id = m.id AND r.fecha BETWEEN ? AND ? AND r.estado <> 'cancelada'
         GROUP BY m.id, m.numero, m.capacidad
         ORDER BY n DESC, m.numero ASC"
    );
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param('ss', $desde, $hasta);
    $stmt->execute();
    $res = $stmt->get_result();

    $filas = [];
    while ($row = $res->fetch_assoc()) {
        $n = (int) $row['n'];
        $p = (int) $row['p'];
        $filas[] = [
            'numero'     => (int) $row['numero'],
            'capacidad'  => (int) $row['capacidad'],
            'reservas'   => $n,
            'comensales' => $p,
            // Qué tan llena viene: comensales medios sobre la capacidad.
            'llenado'    => ($n > 0 && $row['capacidad'] > 0) ? round($p / $n / (int) $row['capacidad'] * 100) : 0,
        ];
    }
    $stmt->close();

    return $filas;
}

/**
 * Clientes que más reservaron en el período.
 */
function reporteTopClientes(mysqli $conn, string $desde, string $hasta, int $limite = 5): array
{
    $stmt = $conn->prepare(
        "SELECT nombre, COUNT(*) AS n, COALESCE(SUM(personas),0) AS p
         FROM reservas
         WHERE fecha BETWEEN ? AND ? AND estado <> 'cancelada'
         GROUP BY nombre
         HAVING n > 1
         ORDER BY n DESC, p DESC
         LIMIT ?"
    );
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param('ssi', $desde, $hasta, $limite);
    $stmt->execute();
    $res = $stmt->get_result();

    $filas = [];
    while ($row = $res->fetch_assoc()) {
        $filas[] = ['nombre' => $row['nombre'], 'reservas' => (int) $row['n'], 'comensales' => (int) $row['p']];
    }
    $stmt->close();

    return $filas;
}

/**
 * Estado actual del inventario (no depende del período: es una foto de hoy).
 */
function reporteInventario(mysqli $conn): array
{
    $base = ['articulos' => 0, 'valor' => 0.0, 'bajos' => 0, 'categorias' => []];

    $t = $conn->query("SHOW TABLES LIKE 'inventario'");
    if (!$t || $t->num_rows === 0) {
        return $base;
    }

    $row = $conn->query(
        "SELECT COUNT(*) AS n, COALESCE(SUM(stock * precio_unitario),0) AS valor,
                SUM(stock <= stock_minimo) AS bajos
         FROM inventario"
    );
    if ($row && ($r = $row->fetch_assoc())) {
        $base['articulos'] = (int) $r['n'];
        $base['valor']     = round((float) $r['valor'], 2);
        $base['bajos']     = (int) $r['bajos'];
    }

    $res = $conn->query(
        "SELECT categoria, COALESCE(SUM(stock * precio_unitario),0) AS valor
         FROM inventario GROUP BY categoria ORDER BY valor DESC"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $base['categorias'][] = [
                'categoria' => INV_CATEGORIAS[$r['categoria']] ?? $r['categoria'],
                'valor'     => round((float) $r['valor'], 2),
            ];
        }
    }

    return $base;
}

/**
 * Arma el informe completo, con comparación contra el período anterior.
 */
function reporteCompleto(mysqli $conn, string $preset, string $desde = '', string $hasta = ''): array
{
    require_once __DIR__ . '/inventario_db.php';

    $p = reportePeriodo($preset, $desde, $hasta);

    // Período previo de la misma duración, justo antes.
    $ini  = new DateTime($p['desde']);
    $fin  = new DateTime($p['hasta']);
    $dias = (int) $ini->diff($fin)->days + 1;

    $prevFin = (clone $ini)->modify('-1 day');
    $prevIni = (clone $prevFin)->modify('-' . ($dias - 1) . ' days');

    $actual   = reporteResumen($conn, $p['desde'], $p['hasta']);
    $anterior = reporteResumen($conn, $prevIni->format('Y-m-d'), $prevFin->format('Y-m-d'));

    $variacion = static function (int $hoy, int $antes): ?int {
        if ($antes === 0) {
            return $hoy > 0 ? null : 0;   // null = "sin base de comparación"
        }
        return (int) round(($hoy - $antes) / $antes * 100);
    };

    return [
        'periodo' => $p + ['dias' => $dias, 'previo_desde' => $prevIni->format('Y-m-d'), 'previo_hasta' => $prevFin->format('Y-m-d')],
        'resumen' => $actual + [
            'var_reservas'   => $variacion($actual['reservas'], $anterior['reservas']),
            'var_comensales' => $variacion($actual['comensales'], $anterior['comensales']),
            'tasa_cancelacion' => $actual['total'] > 0 ? round($actual['canceladas'] / $actual['total'] * 100) : 0,
        ],
        'dias_semana' => reportePorDiaSemana($conn, $p['desde'], $p['hasta']),
        'franjas'     => reportePorFranja($conn, $p['desde'], $p['hasta']),
        'mesas'       => reportePorMesa($conn, $p['desde'], $p['hasta']),
        'clientes'    => reporteTopClientes($conn, $p['desde'], $p['hasta']),
        'inventario'  => reporteInventario($conn),
    ];
}
