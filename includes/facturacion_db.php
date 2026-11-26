<?php
/**
 * Módulo de caja y facturación: esquema, cálculos y consultas compartidas.
 *
 * Igual que el plano y el inventario, las tablas se crean solas la primera vez.
 * Todavía NO hay conexión con DGI: los comprobantes se guardan con numeración
 * interna (serie "X") y estado_dgi = 'sin_integrar'. Cuando exista un proveedor
 * de facturación electrónica habilitado, el envío se agrega en cfe_proveedor.php
 * sin tocar este archivo ni las pantallas.
 *
 *   carta_productos  lo que se vende (precio con IVA incluido)
 *   cuentas          una mesa ocupada (o una venta de mostrador)
 *   cuenta_items     cada pedido cargado a la cuenta, con asiento opcional
 *   pagos            cada cobro (una cuenta puede pagarse en partes)
 *   comprobantes     e-Ticket / e-Factura emitidos por cada pago
 *   turnos_caja      apertura, cierre y arqueo de efectivo
 */

require_once __DIR__ . '/plano_db.php';

// Tasas de IVA de Uruguay. El precio de la carta ya las incluye.
const FAC_IVA = [
    'basica' => ['label' => 'Básica 22%', 'tasa' => 0.22],
    'minima' => ['label' => 'Mínima 10%', 'tasa' => 0.10],
    'exento' => ['label' => 'Exento',     'tasa' => 0.0],
];

const FAC_CATEGORIAS = [
    'entradas' => 'Entradas',
    'principales' => 'Principales',
    'guarniciones' => 'Guarniciones',
    'postres' => 'Postres',
    'bebidas' => 'Bebidas',
    'vinos' => 'Vinos y sidras',
    'cafeteria' => 'Cafetería',
    'otros' => 'Otros',
];

const FAC_DESTINOS = ['cocina' => 'Cocina', 'barra' => 'Barra'];

const FAC_MEDIOS = [
    'efectivo' => 'Efectivo',
    'debito' => 'Débito',
    'credito' => 'Crédito',
    'transferencia' => 'Transferencia / QR',
];

// Billetes y monedas uruguayos para el arqueo.
const FAC_DENOMINACIONES = [2000, 1000, 500, 200, 100, 50, 20, 10, 5, 2, 1];

const FAC_ROLES_CAJA = ['admin', 'superadmin'];
const FAC_ROLES_SALON = ['admin', 'superadmin', 'mozo'];

function ensureFacturacionTables(mysqli $conn): void
{
    static $hecho = false;
    if ($hecho) return;
    $hecho = true;

    ensureMesaTable($conn);
    $motor = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    $conn->query("CREATE TABLE IF NOT EXISTS carta_productos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(120) NOT NULL,
        categoria VARCHAR(30) NOT NULL DEFAULT 'principales',
        precio DECIMAL(10,2) NOT NULL DEFAULT 0,
        iva VARCHAR(10) NOT NULL DEFAULT 'basica',
        destino VARCHAR(10) NOT NULL DEFAULT 'cocina',
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $motor");

    $conn->query("CREATE TABLE IF NOT EXISTS turnos_caja (
        id INT AUTO_INCREMENT PRIMARY KEY,
        abierto_por VARCHAR(100) NOT NULL,
        abierto_en DATETIME NOT NULL,
        fondo_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
        cerrado_por VARCHAR(100) DEFAULT NULL,
        cerrado_en DATETIME DEFAULT NULL,
        efectivo_esperado DECIMAL(10,2) DEFAULT NULL,
        efectivo_contado DECIMAL(10,2) DEFAULT NULL,
        diferencia DECIMAL(10,2) DEFAULT NULL,
        arqueo TEXT DEFAULT NULL,
        nota VARCHAR(255) DEFAULT NULL,
        INDEX idx_turno_abierto (cerrado_en)
    ) $motor");

    $conn->query("CREATE TABLE IF NOT EXISTS cuentas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mesa_id INT DEFAULT NULL,
        mesa_numero INT NOT NULL DEFAULT 0,
        estado VARCHAR(15) NOT NULL DEFAULT 'abierta',
        comensales INT NOT NULL DEFAULT 1,
        mozo_id INT DEFAULT NULL,
        mozo_nombre VARCHAR(100) DEFAULT NULL,
        turno_id INT DEFAULT NULL,
        abierta_en DATETIME NOT NULL,
        pidio_cuenta_en DATETIME DEFAULT NULL,
        cerrada_en DATETIME DEFAULT NULL,
        motivo_anulacion VARCHAR(255) DEFAULT NULL,
        INDEX idx_cuenta_estado (estado),
        INDEX idx_cuenta_mesa (mesa_id, estado)
    ) $motor");

    // Vínculo con la reserva cuando el cliente que reservó llega y se sienta.
    $col = $conn->query("SHOW COLUMNS FROM cuentas LIKE 'reserva_id'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE cuentas ADD COLUMN reserva_id INT DEFAULT NULL AFTER mesa_numero, ADD INDEX idx_cuenta_reserva (reserva_id)");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS cuenta_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cuenta_id INT NOT NULL,
        producto_id INT DEFAULT NULL,
        nombre VARCHAR(120) NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        iva VARCHAR(10) NOT NULL DEFAULT 'basica',
        destino VARCHAR(10) NOT NULL DEFAULT 'cocina',
        cantidad INT NOT NULL DEFAULT 1,
        asiento VARCHAR(2) DEFAULT NULL,
        nota VARCHAR(160) DEFAULT NULL,
        anulado TINYINT(1) NOT NULL DEFAULT 0,
        motivo_anulacion VARCHAR(160) DEFAULT NULL,
        comanda_impresa TINYINT(1) NOT NULL DEFAULT 0,
        cargado_por VARCHAR(100) DEFAULT NULL,
        creado_en DATETIME NOT NULL,
        INDEX idx_item_cuenta (cuenta_id),
        INDEX idx_item_comanda (comanda_impresa, anulado)
    ) $motor");

    $conn->query("CREATE TABLE IF NOT EXISTS comprobantes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(10) NOT NULL DEFAULT 'eticket',
        serie VARCHAR(4) NOT NULL DEFAULT 'X',
        numero INT NOT NULL,
        cuenta_id INT NOT NULL,
        turno_id INT DEFAULT NULL,
        receptor_rut VARCHAR(12) DEFAULT NULL,
        receptor_nombre VARCHAR(150) DEFAULT NULL,
        receptor_email VARCHAR(255) DEFAULT NULL,
        total DECIMAL(10,2) NOT NULL,
        neto_basica DECIMAL(10,2) NOT NULL DEFAULT 0,
        iva_basica DECIMAL(10,2) NOT NULL DEFAULT 0,
        neto_minima DECIMAL(10,2) NOT NULL DEFAULT 0,
        iva_minima DECIMAL(10,2) NOT NULL DEFAULT 0,
        exento DECIMAL(10,2) NOT NULL DEFAULT 0,
        propina DECIMAL(10,2) NOT NULL DEFAULT 0,
        medio VARCHAR(15) NOT NULL,
        detalle TEXT NOT NULL,
        estado_dgi VARCHAR(15) NOT NULL DEFAULT 'sin_integrar',
        creado_en DATETIME NOT NULL,
        UNIQUE KEY uq_comprobante (serie, numero),
        INDEX idx_comp_turno (turno_id)
    ) $motor");

    $conn->query("CREATE TABLE IF NOT EXISTS pagos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cuenta_id INT NOT NULL,
        turno_id INT NOT NULL,
        monto DECIMAL(10,2) NOT NULL,
        propina DECIMAL(10,2) NOT NULL DEFAULT 0,
        medio VARCHAR(15) NOT NULL,
        asiento VARCHAR(2) DEFAULT NULL,
        comprobante_id INT DEFAULT NULL,
        mozo_nombre VARCHAR(100) DEFAULT NULL,
        cobrado_por VARCHAR(100) NOT NULL,
        creado_en DATETIME NOT NULL,
        INDEX idx_pago_cuenta (cuenta_id),
        INDEX idx_pago_turno (turno_id)
    ) $motor");

    $vacia = $conn->query("SELECT 1 FROM carta_productos LIMIT 1");
    if ($vacia && $vacia->num_rows === 0) {
        // Carta de ejemplo en pesos uruguayos, para que la caja no arranque vacía.
        $conn->query("INSERT INTO carta_productos (nombre, categoria, precio, iva, destino) VALUES
            ('Croquetas de jamón (6)', 'entradas', 480, 'basica', 'cocina'),
            ('Chorizo a la sidra', 'entradas', 420, 'basica', 'cocina'),
            ('Tabla de quesos', 'entradas', 690, 'basica', 'cocina'),
            ('Fabada asturiana', 'principales', 690, 'basica', 'cocina'),
            ('Cachopo al corte', 'principales', 1450, 'basica', 'cocina'),
            ('Merluza a la sidra', 'principales', 980, 'basica', 'cocina'),
            ('Papas fritas', 'guarniciones', 260, 'basica', 'cocina'),
            ('Ensalada mixta', 'guarniciones', 290, 'basica', 'cocina'),
            ('Arroz con leche', 'postres', 290, 'basica', 'cocina'),
            ('Flan casero', 'postres', 270, 'basica', 'cocina'),
            ('Agua mineral 500 ml', 'bebidas', 160, 'basica', 'barra'),
            ('Refresco', 'bebidas', 190, 'basica', 'barra'),
            ('Sidra natural (botella)', 'vinos', 390, 'basica', 'barra'),
            ('Vino tinto (botella)', 'vinos', 980, 'basica', 'barra'),
            ('Café', 'cafeteria', 140, 'basica', 'barra'),
            ('Cubierto', 'otros', 120, 'basica', 'cocina')");
    }
}

/* ═════════════════════════ Respuestas JSON ═════════════════════════ */

function facJson(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Cabecera común de las API del módulo: rol, conexión y tablas. */
function facIniciarApi(array $roles): mysqli
{
    header('Content-Type: application/json; charset=utf-8');
    if (!in_array($_SESSION['rol'] ?? '', $roles, true)) {
        facJson(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);
    }
    $conn = $GLOBALS['conn'] ?? null;
    if (!($conn instanceof mysqli)) {
        facJson(['ok' => false, 'mensaje' => $GLOBALS['dbErrorMessage'] ?: 'Sin conexión a la base de datos.'], 500);
    }
    ensureFacturacionTables($conn);
    return $conn;
}

/**
 * Ejecuta el cuerpo de una API con errores de MySQL como excepciones. Si algo
 * falla, revierte la transacción en curso y responde un error genérico.
 */
function facEjecutar(mysqli $conn, callable $fn): void
{
    $anterior = (new mysqli_driver())->report_mode;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $fn();
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignorar) {}
        error_log('Facturación: ' . $e->getMessage());
        mysqli_report($anterior);
        facJson(['ok' => false, 'mensaje' => 'Ocurrió un error interno. Intentá de nuevo.'], 500);
    }
    mysqli_report($anterior);
}

function facUsuarioNombre(): string
{
    return (string) ($_SESSION['usuario_logueado'] ?? 'Sistema');
}

function facCuerpo(): array
{
    $body = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($body) ? $body : [];
}

/* ═════════════════════════ Dinero e IVA ═════════════════════════ */

function facRedondear(float $n): float
{
    return round($n + 0.0, 2);
}

/**
 * Desglosa líneas con precio IVA incluido en neto e IVA por tasa.
 * Cada línea: ['total' => float, 'iva' => 'basica'|'minima'|'exento'].
 */
function facDesglose(array $lineas): array
{
    $tot = ['basica' => 0.0, 'minima' => 0.0, 'exento' => 0.0];
    foreach ($lineas as $l) {
        $tasa = isset(FAC_IVA[$l['iva']]) ? $l['iva'] : 'basica';
        $tot[$tasa] += (float) $l['total'];
    }
    $netoB = $tot['basica'] / 1.22;
    $netoM = $tot['minima'] / 1.10;
    return [
        'total' => facRedondear($tot['basica'] + $tot['minima'] + $tot['exento']),
        'neto_basica' => facRedondear($netoB),
        'iva_basica' => facRedondear($tot['basica'] - $netoB),
        'neto_minima' => facRedondear($netoM),
        'iva_minima' => facRedondear($tot['minima'] - $netoM),
        'exento' => facRedondear($tot['exento']),
    ];
}

/** RUT uruguayo: 12 dígitos y dígito verificador (módulo 11). */
function facRutValido(string $rut): bool
{
    if (!preg_match('/^\d{12}$/', $rut)) return false;
    $factores = [4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 11; $i++) {
        $suma += (int) $rut[$i] * $factores[$i];
    }
    $dv = 11 - ($suma % 11);
    if ($dv === 11) $dv = 0;
    if ($dv === 10) return false;
    return $dv === (int) $rut[11];
}

/* ═════════════════════════ Turnos ═════════════════════════ */

function facTurnoAbierto(mysqli $conn): ?array
{
    $res = $conn->query("SELECT * FROM turnos_caja WHERE cerrado_en IS NULL ORDER BY id DESC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
}

/** Totales del turno: cobros por medio, propinas por mozo, comprobantes e IVA. */
function facResumenTurno(mysqli $conn, int $turnoId): array
{
    $medios = [];
    foreach (FAC_MEDIOS as $k => $label) {
        $medios[$k] = ['label' => $label, 'cantidad' => 0, 'monto' => 0.0, 'propina' => 0.0];
    }
    $stmt = $conn->prepare("SELECT medio, COUNT(*) n, SUM(monto) m, SUM(propina) p FROM pagos WHERE turno_id = ? GROUP BY medio");
    $stmt->bind_param('i', $turnoId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $k = isset($medios[$r['medio']]) ? $r['medio'] : 'efectivo';
        $medios[$k]['cantidad'] += (int) $r['n'];
        $medios[$k]['monto'] += (float) $r['m'];
        $medios[$k]['propina'] += (float) $r['p'];
    }
    $stmt->close();

    $propinas = [];
    $stmt = $conn->prepare("SELECT COALESCE(mozo_nombre, 'Sin mozo') mozo, SUM(propina) p FROM pagos WHERE turno_id = ? AND propina > 0 GROUP BY mozo ORDER BY p DESC");
    $stmt->bind_param('i', $turnoId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $propinas[] = ['mozo' => $r['mozo'], 'monto' => facRedondear((float) $r['p'])];
    }
    $stmt->close();

    $cfe = ['eticket' => 0, 'efactura' => 0, 'neto_basica' => 0.0, 'iva_basica' => 0.0, 'neto_minima' => 0.0, 'iva_minima' => 0.0, 'exento' => 0.0];
    $stmt = $conn->prepare("SELECT tipo, COUNT(*) n, SUM(neto_basica) nb, SUM(iva_basica) ib, SUM(neto_minima) nm, SUM(iva_minima) im, SUM(exento) ex
        FROM comprobantes WHERE turno_id = ? GROUP BY tipo");
    $stmt->bind_param('i', $turnoId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $cfe[$r['tipo'] === 'efactura' ? 'efactura' : 'eticket'] += (int) $r['n'];
        foreach (['neto_basica' => 'nb', 'iva_basica' => 'ib', 'neto_minima' => 'nm', 'iva_minima' => 'im', 'exento' => 'ex'] as $k => $c) {
            $cfe[$k] += (float) $r[$c];
        }
    }
    $stmt->close();

    $horas = [];
    $stmt = $conn->prepare("SELECT DATE_FORMAT(creado_en, '%H') h, SUM(monto) m FROM pagos WHERE turno_id = ? GROUP BY h ORDER BY MIN(creado_en)");
    $stmt->bind_param('i', $turnoId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $horas[] = ['hora' => $r['h'] . ':00', 'monto' => facRedondear((float) $r['m'])];
    }
    $stmt->close();

    $total = 0.0; $cobros = 0; $propinaTotal = 0.0;
    foreach ($medios as &$m) {
        $m['monto'] = facRedondear($m['monto']);
        $m['propina'] = facRedondear($m['propina']);
        $total += $m['monto']; $cobros += $m['cantidad']; $propinaTotal += $m['propina'];
    }
    unset($m);
    foreach ($cfe as $k => $v) if (is_float($v)) $cfe[$k] = facRedondear($v);

    return [
        'medios' => $medios,
        'total' => facRedondear($total),
        'cobros' => $cobros,
        'propina_total' => facRedondear($propinaTotal),
        'propinas' => $propinas,
        'comprobantes' => $cfe,
        'horas' => $horas,
        // El efectivo esperado incluye las propinas cobradas en efectivo.
        'efectivo_cobrado' => facRedondear($medios['efectivo']['monto'] + $medios['efectivo']['propina']),
    ];
}

/* ═════════════════════════ Cuentas ═════════════════════════ */

function facCuentaBasica(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM cuentas WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Cuenta completa: ítems, pagos, saldo y división por asiento.
 * Los ítems sin asiento son "compartidos" y se reparten en partes iguales
 * entre los asientos que tienen pedidos propios.
 */
function facCuentaDetalle(mysqli $conn, int $id): ?array
{
    $c = facCuentaBasica($conn, $id);
    if (!$c) return null;

    $items = [];
    $stmt = $conn->prepare("SELECT id, producto_id, nombre, precio, iva, destino, cantidad, asiento, nota, anulado, motivo_anulacion, comanda_impresa, cargado_por, creado_en
        FROM cuenta_items WHERE cuenta_id = ? ORDER BY id");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $items[] = [
            'id' => (int) $r['id'], 'nombre' => $r['nombre'], 'precio' => (float) $r['precio'],
            'iva' => $r['iva'], 'destino' => $r['destino'], 'cantidad' => (int) $r['cantidad'],
            'total' => facRedondear((float) $r['precio'] * (int) $r['cantidad']),
            'asiento' => $r['asiento'], 'nota' => $r['nota'], 'anulado' => (bool) $r['anulado'],
            'motivo_anulacion' => $r['motivo_anulacion'], 'impreso' => (bool) $r['comanda_impresa'],
            'cargado_por' => $r['cargado_por'], 'hora' => substr($r['creado_en'], 11, 5),
        ];
    }
    $stmt->close();

    $pagos = [];
    $stmt = $conn->prepare("SELECT p.id, p.monto, p.propina, p.medio, p.asiento, p.comprobante_id, p.creado_en, c.tipo, c.serie, c.numero
        FROM pagos p LEFT JOIN comprobantes c ON c.id = p.comprobante_id WHERE p.cuenta_id = ? ORDER BY p.id");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $pagos[] = [
            'id' => (int) $r['id'], 'monto' => (float) $r['monto'], 'propina' => (float) $r['propina'],
            'medio' => $r['medio'], 'asiento' => $r['asiento'], 'hora' => substr($r['creado_en'], 11, 5),
            'comprobante_id' => $r['comprobante_id'] ? (int) $r['comprobante_id'] : null,
            'comprobante' => $r['numero'] ? facNombreComprobante($r) : null,
        ];
    }
    $stmt->close();

    $activos = array_values(array_filter($items, fn($i) => !$i['anulado']));
    $total = facRedondear(array_sum(array_column($activos, 'total')));
    $pagado = facRedondear(array_sum(array_column($pagos, 'monto')));

    // División por asiento.
    $porAsiento = [];
    $compartido = 0.0;
    foreach ($activos as $i) {
        if ($i['asiento']) {
            $porAsiento[$i['asiento']] ??= ['propio' => 0.0, 'items' => []];
            $porAsiento[$i['asiento']]['propio'] += $i['total'];
            $porAsiento[$i['asiento']]['items'][] = $i['id'];
        } else {
            $compartido += $i['total'];
        }
    }
    ksort($porAsiento);
    $pagados = [];
    foreach ($pagos as $p) if ($p['asiento']) $pagados[$p['asiento']] = true;
    $n = count($porAsiento);
    $asientos = [];
    foreach ($porAsiento as $letra => $a) {
        $asientos[] = [
            'letra' => $letra,
            'propio' => facRedondear($a['propio']),
            'compartido' => $n ? facRedondear($compartido / $n) : 0.0,
            'total' => facRedondear($a['propio'] + ($n ? $compartido / $n : 0)),
            'items' => $a['items'],
            'pagado' => isset($pagados[$letra]),
        ];
    }

    $reserva = null;
    if (!empty($c['reserva_id'])) {
        $stmt = $conn->prepare("SELECT id, codigo, nombre, hora, personas FROM reservas WHERE id = ?");
        $rid = (int) $c['reserva_id'];
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) $reserva = ['id' => (int) $r['id'], 'codigo' => $r['codigo'], 'nombre' => $r['nombre'], 'hora' => substr($r['hora'], 0, 5), 'personas' => (int) $r['personas']];
    }

    $abierta = strtotime($c['abierta_en']);
    $fin = $c['cerrada_en'] ? strtotime($c['cerrada_en']) : time();

    return [
        'id' => (int) $c['id'],
        'mesa_id' => $c['mesa_id'] !== null ? (int) $c['mesa_id'] : null,
        'mesa_numero' => (int) $c['mesa_numero'],
        'reserva' => $reserva,
        'estado' => $c['estado'],
        'comensales' => (int) $c['comensales'],
        'mozo_id' => $c['mozo_id'] !== null ? (int) $c['mozo_id'] : null,
        'mozo' => $c['mozo_nombre'],
        'abierta_en' => substr($c['abierta_en'], 11, 5),
        'minutos' => max(0, (int) floor(($fin - $abierta) / 60)),
        'items' => $items,
        'pagos' => $pagos,
        'total' => $total,
        'pagado' => $pagado,
        'saldo' => facRedondear(max(0, $total - $pagado)),
        'compartido' => facRedondear($compartido),
        'asientos' => $asientos,
        'desglose' => facDesglose($activos),
    ];
}

/**
 * Reservas de hoy que todavía no llegaron (ninguna cuenta vinculada) y cuyo
 * horario no terminó. Cada una dice:
 *   minutos  → cuánto falta (negativo: ya debería haber llegado)
 *   vencida  → pasó el tiempo de cortesía: ya no bloquea la mesa
 *   bloquea  → si alguien se sienta ahora en su mesa, se superpone con ella
 */
function facReservasPendientes(mysqli $conn): array
{
    $t = $conn->query("SHOW TABLES LIKE 'reservas'");
    if (!$t || $t->num_rows === 0) return [];
    ensureReservaMesaColumn($conn);

    $duracion = max(1, cfgInt('reservas.duracion_horas')) * 60;
    $cortesia = max(0, cfgInt('reservas.cortesia_min'));
    $ahora = time();
    $hoy = date('Y-m-d');
    $stmt = $conn->prepare("SELECT r.*, m.numero mesa_numero, m.capacidad mesa_capacidad
        FROM reservas r LEFT JOIN mesas m ON m.id = r.mesa_id
        WHERE r.fecha = ? AND r.estado <> 'cancelada'
          AND DATE_ADD(TIMESTAMP(r.fecha, r.hora), INTERVAL ? MINUTE) > ?
          AND NOT EXISTS (SELECT 1 FROM cuentas c WHERE c.reserva_id = r.id AND c.estado <> 'anulada')
        ORDER BY r.hora, r.id");
    $ahoraTxt = date('Y-m-d H:i:s', $ahora);
    $stmt->bind_param('sis', $hoy, $duracion, $ahoraTxt);
    $stmt->execute();
    $res = $stmt->get_result();
    $lista = [];
    while ($r = $res->fetch_assoc()) {
        $inicio = strtotime($r['fecha'] . ' ' . $r['hora']);
        $minutos = (int) floor(($inicio - $ahora) / 60);
        $vencida = $ahora > $inicio + $cortesia * 60;
        $lista[] = [
            'id' => (int) $r['id'],
            'codigo' => $r['codigo'],
            'nombre' => $r['nombre'],
            'hora' => substr($r['hora'], 0, 5),
            'personas' => (int) $r['personas'],
            'telefono' => $r['telefono'] ?? null,
            'comentario' => $r['comentario'] ?? null,
            'estado' => $r['estado'],
            'mesa_id' => $r['mesa_id'] !== null ? (int) $r['mesa_id'] : null,
            'mesa_numero' => $r['mesa_numero'] !== null ? (int) $r['mesa_numero'] : null,
            'minutos' => $minutos,
            'vencida' => $vencida,
            // Una mesa que se ocupa ahora queda tomada durante la duración de una reserva.
            'bloquea' => !$vencida && $inicio < $ahora + $duracion * 60,
        ];
    }
    $stmt->close();
    return $lista;
}

/** La reserva (no vencida) que choca con sentar a alguien ahora en esa mesa. */
function facReservaQueBloquea(array $reservas, int $mesaId): ?array
{
    foreach ($reservas as $r) {
        if ($r['mesa_id'] === $mesaId && $r['bloquea']) return $r;
    }
    return null;
}

/**
 * Mesas del plano con la cuenta abierta de cada una (para la caja y el mozo).
 */
function facEstadoSalon(mysqli $conn): array
{
    $cuentas = [];
    $res = $conn->query("SELECT c.id, c.mesa_id, c.mesa_numero, c.estado, c.comensales, c.mozo_id, c.mozo_nombre, c.abierta_en,
            COALESCE((SELECT SUM(precio * cantidad) FROM cuenta_items i WHERE i.cuenta_id = c.id AND i.anulado = 0), 0) total,
            COALESCE((SELECT SUM(monto) FROM pagos p WHERE p.cuenta_id = c.id), 0) pagado
        FROM cuentas c WHERE c.estado IN ('abierta', 'pide_cuenta') ORDER BY c.abierta_en");
    $ahora = time();
    $mostrador = [];
    while ($res && ($r = $res->fetch_assoc())) {
        $cuenta = [
            'id' => (int) $r['id'], 'estado' => $r['estado'], 'comensales' => (int) $r['comensales'],
            'mozo_id' => $r['mozo_id'] !== null ? (int) $r['mozo_id'] : null, 'mozo' => $r['mozo_nombre'],
            'total' => facRedondear((float) $r['total']), 'pagado' => facRedondear((float) $r['pagado']),
            'minutos' => max(0, (int) floor(($ahora - strtotime($r['abierta_en'])) / 60)),
            'mesa_numero' => (int) $r['mesa_numero'],
        ];
        if ($r['mesa_id'] === null) {
            $mostrador[] = $cuenta;
        } else {
            $cuentas[(int) $r['mesa_id']] = $cuenta;
        }
    }

    $reservas = facReservasPendientes($conn);
    $mesas = [];
    foreach (planoMesasConOcupacion($conn) as $m) {
        $m['cuenta'] = $cuentas[$m['id']] ?? null;
        // Próxima reserva de la mesa que importa ahora (la que bloquea, o la siguiente del día).
        $m['reserva'] = facReservaQueBloquea($reservas, $m['id']);
        $mesas[] = $m;
    }

    $comandas = 0;
    $r = $conn->query("SELECT COUNT(*) n FROM cuenta_items i JOIN cuentas c ON c.id = i.cuenta_id
        WHERE i.comanda_impresa = 0 AND i.anulado = 0 AND c.estado IN ('abierta', 'pide_cuenta')");
    if ($r) $comandas = (int) $r->fetch_assoc()['n'];

    return ['mesas' => $mesas, 'mostrador' => $mostrador, 'comandas_pendientes' => $comandas, 'reservas' => $reservas];
}

/* ═════════════════════════ Comprobantes ═════════════════════════ */

/**
 * Líneas del comprobante para un pago. Si se paga la cuenta entera salen los
 * ítems; si se paga un asiento, sus ítems y su parte de lo compartido; si es
 * una parte igual, una línea prorrateada por tasa de IVA.
 */
function facLineasPago(array $cuenta, float $monto, ?string $asiento): array
{
    $activos = array_values(array_filter($cuenta['items'], fn($i) => !$i['anulado']));
    $sinPagosPrevios = $cuenta['pagado'] <= 0.001;

    if ($asiento !== null) {
        $seat = null;
        foreach ($cuenta['asientos'] as $a) if ($a['letra'] === $asiento) $seat = $a;
        if ($seat) {
            $lineas = [];
            foreach ($activos as $i) {
                if ($i['asiento'] === $asiento) {
                    $lineas[] = ['desc' => $i['nombre'], 'cant' => $i['cantidad'], 'precio' => $i['precio'], 'total' => $i['total'], 'iva' => $i['iva']];
                }
            }
            $n = max(1, count($cuenta['asientos']));
            foreach ($activos as $i) {
                if (!$i['asiento']) {
                    $parte = facRedondear($i['total'] / $n);
                    $lineas[] = ['desc' => $i['nombre'] . ' (compartido 1/' . $n . ')', 'cant' => 1, 'precio' => $parte, 'total' => $parte, 'iva' => $i['iva']];
                }
            }
            return facAjustarLineas($lineas, $monto);
        }
    }

    if ($sinPagosPrevios && abs($monto - $cuenta['total']) < 0.01) {
        return array_map(fn($i) => ['desc' => $i['nombre'], 'cant' => $i['cantidad'], 'precio' => $i['precio'], 'total' => $i['total'], 'iva' => $i['iva']], $activos);
    }

    // Parte de la cuenta: se prorratea el monto según el peso de cada tasa.
    $porTasa = ['basica' => 0.0, 'minima' => 0.0, 'exento' => 0.0];
    foreach ($activos as $i) $porTasa[isset($porTasa[$i['iva']]) ? $i['iva'] : 'basica'] += $i['total'];
    $total = array_sum($porTasa) ?: 1;
    $lineas = [];
    $etiqueta = $cuenta['mesa_numero'] ? 'Consumo mesa ' . $cuenta['mesa_numero'] : 'Consumo mostrador';
    foreach ($porTasa as $tasa => $v) {
        if ($v <= 0) continue;
        $parte = facRedondear($monto * $v / $total);
        $lineas[] = ['desc' => $etiqueta . ' · pago parcial' . ($tasa !== 'basica' ? ' (' . FAC_IVA[$tasa]['label'] . ')' : ''), 'cant' => 1, 'precio' => $parte, 'total' => $parte, 'iva' => $tasa];
    }
    return facAjustarLineas($lineas, $monto);
}

/** Corrige el redondeo para que la suma de líneas sea exactamente el monto. */
function facAjustarLineas(array $lineas, float $monto): array
{
    if (!$lineas) return $lineas;
    $dif = facRedondear($monto - array_sum(array_column($lineas, 'total')));
    if (abs($dif) >= 0.01) {
        $k = count($lineas) - 1;
        $lineas[$k]['total'] = facRedondear($lineas[$k]['total'] + $dif);
        $lineas[$k]['precio'] = facRedondear($lineas[$k]['total'] / max(1, $lineas[$k]['cant']));
    }
    return $lineas;
}

function facNombreComprobante(array $c): string
{
    return ($c['tipo'] === 'efactura' ? 'e-Factura' : 'e-Ticket') . ' ' . $c['serie'] . '-' . str_pad((string) $c['numero'], 7, '0', STR_PAD_LEFT);
}
