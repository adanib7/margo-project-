<?php
/**
 * Historial de ventas (caja).
 *   GET ?desde=YYYY-MM-DD&hasta=YYYY-MM-DD&medio=&tipo=&q=&pagina=1
 *       → comprobantes del período, resumen, serie para el gráfico y anulaciones
 *   GET ... &formato=csv → descarga de los comprobantes del período
 *   GET ?cuenta=N → detalle de una venta (ítems, pagos, comprobantes)
 */
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/facturacion_db.php';

$conn = facIniciarApi(FAC_ROLES_CAJA);

facEjecutar($conn, function () use ($conn) {
    if (isset($_GET['cuenta'])) {
        $cuenta = facCuentaDetalle($conn, (int) $_GET['cuenta']);
        if (!$cuenta) facJson(['ok' => false, 'mensaje' => 'La venta no existe.'], 404);
        $c = facCuentaBasica($conn, (int) $_GET['cuenta']);
        $cuenta['fecha'] = substr($c['abierta_en'], 0, 10);
        $cuenta['cerrada_en'] = $c['cerrada_en'];
        $cuenta['motivo_anulacion'] = $c['motivo_anulacion'];
        facJson(['ok' => true, 'cuenta' => $cuenta]);
    }

    $fecha = fn($v, $def) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : $def;
    $desde = $fecha($_GET['desde'] ?? null, date('Y-m-d'));
    $hasta = $fecha($_GET['hasta'] ?? null, $desde);
    if ($hasta < $desde) [$desde, $hasta] = [$hasta, $desde];
    $ini = $desde . ' 00:00:00';
    $fin = $hasta . ' 23:59:59';

    $where = ['co.creado_en BETWEEN ? AND ?'];
    $tipos = 'ss';
    $params = [$ini, $fin];
    $medio = (string) ($_GET['medio'] ?? '');
    if (isset(FAC_MEDIOS[$medio])) { $where[] = 'co.medio = ?'; $tipos .= 's'; $params[] = $medio; }
    $tipo = (string) ($_GET['tipo'] ?? '');
    if (in_array($tipo, ['eticket', 'efactura'], true)) { $where[] = 'co.tipo = ?'; $tipos .= 's'; $params[] = $tipo; }
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . $q . '%';
        $num = ctype_digit(ltrim($q, 'xX-')) ? (int) ltrim($q, 'xX-') : -1;
        $where[] = '(co.numero = ? OR cu.mesa_numero = ? OR cu.mozo_nombre LIKE ? OR co.receptor_rut LIKE ? OR co.receptor_nombre LIKE ? OR co.receptor_email LIKE ?)';
        $tipos .= 'iissss';
        array_push($params, $num, $num, $like, $like, $like, $like);
    }
    $sqlWhere = implode(' AND ', $where);
    $base = "FROM comprobantes co JOIN cuentas cu ON cu.id = co.cuenta_id LEFT JOIN pagos pa ON pa.comprobante_id = co.id WHERE $sqlWhere";

    $consultar = function (string $sql, string $t, array $p) use ($conn) {
        $stmt = $conn->prepare($sql);
        if ($p) $stmt->bind_param($t, ...$p);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $res;
    };

    $campos = "co.id, co.tipo, co.serie, co.numero, co.cuenta_id, co.total, co.propina, co.medio, co.receptor_rut, co.receptor_nombre,
        co.estado_dgi, co.creado_en, cu.mesa_numero, cu.mozo_nombre, pa.asiento, pa.cobrado_por";

    // ── Exportar CSV ──
    if (($_GET['formato'] ?? '') === 'csv') {
        $filas = $consultar("SELECT $campos, co.neto_basica, co.iva_basica, co.neto_minima, co.iva_minima, co.exento $base ORDER BY co.creado_en", $tipos, $params);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ventas_' . $desde . ($hasta !== $desde ? '_a_' . $hasta : '') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel abre bien los acentos
        fputcsv($out, ['Fecha', 'Hora', 'Comprobante', 'Tipo', 'Mesa', 'Mozo', 'Medio', 'Neto 22%', 'IVA 22%', 'Neto 10%', 'IVA 10%', 'Exento', 'Total', 'Propina', 'RUT receptor', 'Receptor', 'Cobró', 'Estado DGI'], ';');
        foreach ($filas as $f) {
            $n = fn($v) => number_format((float) $v, 2, ',', '');
            fputcsv($out, [
                substr($f['creado_en'], 0, 10), substr($f['creado_en'], 11, 5), facNombreComprobante($f),
                $f['tipo'] === 'efactura' ? 'e-Factura' : 'e-Ticket', $f['mesa_numero'] ? $f['mesa_numero'] : 'Mostrador',
                $f['mozo_nombre'], FAC_MEDIOS[$f['medio']] ?? $f['medio'], $n($f['neto_basica']), $n($f['iva_basica']),
                $n($f['neto_minima']), $n($f['iva_minima']), $n($f['exento']), $n($f['total']), $n($f['propina']),
                $f['receptor_rut'], $f['receptor_nombre'], $f['cobrado_por'], $f['estado_dgi'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ── Resumen del período (con los mismos filtros) ──
    $r = $consultar("SELECT COUNT(*) n, COALESCE(SUM(co.total),0) total, COALESCE(SUM(co.propina),0) propina,
            COALESCE(SUM(co.iva_basica + co.iva_minima),0) iva, COUNT(DISTINCT co.cuenta_id) cuentas $base", $tipos, $params)[0];
    $porMedio = [];
    foreach (FAC_MEDIOS as $k => $label) $porMedio[$k] = ['label' => $label, 'monto' => 0.0, 'cantidad' => 0];
    foreach ($consultar("SELECT co.medio, COUNT(*) n, SUM(co.total) m $base GROUP BY co.medio", $tipos, $params) as $f) {
        if (isset($porMedio[$f['medio']])) { $porMedio[$f['medio']]['monto'] = facRedondear((float) $f['m']); $porMedio[$f['medio']]['cantidad'] = (int) $f['n']; }
    }

    // Serie para el gráfico: por hora si es un solo día, por día si es un rango.
    $unDia = $desde === $hasta;
    $grupo = $unDia ? "DATE_FORMAT(co.creado_en, '%H:00')" : 'DATE(co.creado_en)';
    $serie = array_map(fn($f) => ['etiqueta' => $f['g'], 'monto' => facRedondear((float) $f['m'])],
        $consultar("SELECT $grupo g, SUM(co.total) m $base GROUP BY g ORDER BY g", $tipos, $params));

    // Productos más vendidos del período (por cuentas con algún comprobante en el rango).
    $top = $consultar("SELECT i.nombre, SUM(i.cantidad) cant, SUM(i.cantidad * i.precio) monto FROM cuenta_items i
        WHERE i.anulado = 0 AND i.cuenta_id IN (SELECT DISTINCT co.cuenta_id $base)
        GROUP BY i.nombre ORDER BY cant DESC, monto DESC LIMIT 8", $tipos, $params);

    // ── Listado paginado ──
    $porPagina = 50;
    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
    $offset = ($pagina - 1) * $porPagina;
    $filas = $consultar("SELECT $campos $base ORDER BY co.creado_en DESC, co.id DESC LIMIT $porPagina OFFSET $offset", $tipos, $params);
    $comprobantes = array_map(fn($f) => [
        'id' => (int) $f['id'], 'nombre' => facNombreComprobante($f), 'tipo' => $f['tipo'],
        'cuenta_id' => (int) $f['cuenta_id'], 'fecha' => substr($f['creado_en'], 0, 10), 'hora' => substr($f['creado_en'], 11, 5),
        'mesa' => (int) $f['mesa_numero'], 'mozo' => $f['mozo_nombre'], 'medio' => $f['medio'], 'asiento' => $f['asiento'],
        'total' => (float) $f['total'], 'propina' => (float) $f['propina'], 'receptor' => $f['receptor_nombre'],
        'rut' => $f['receptor_rut'], 'cobro' => $f['cobrado_por'], 'estado_dgi' => $f['estado_dgi'],
    ], $filas);

    // ── Anulaciones del período (control, sin filtros de medio/tipo) ──
    $anuladas = array_map(fn($f) => [
        'tipo' => 'cuenta', 'cuenta_id' => (int) $f['id'], 'fecha' => substr($f['cerrada_en'], 0, 10), 'hora' => substr($f['cerrada_en'], 11, 5),
        'mesa' => (int) $f['mesa_numero'], 'mozo' => $f['mozo_nombre'], 'detalle' => 'Cuenta completa', 'monto' => (float) $f['monto'], 'motivo' => $f['motivo_anulacion'],
    ], $consultar("SELECT c.id, c.cerrada_en, c.mesa_numero, c.mozo_nombre, c.motivo_anulacion,
            COALESCE((SELECT SUM(precio * cantidad) FROM cuenta_items i WHERE i.cuenta_id = c.id AND i.anulado = 0), 0) monto
        FROM cuentas c WHERE c.estado = 'anulada' AND c.cerrada_en BETWEEN ? AND ? ORDER BY c.cerrada_en DESC LIMIT 200", 'ss', [$ini, $fin]));
    foreach ($consultar("SELECT i.cuenta_id, i.creado_en, i.nombre, i.cantidad, i.precio, i.motivo_anulacion, c.mesa_numero, c.mozo_nombre
            FROM cuenta_items i JOIN cuentas c ON c.id = i.cuenta_id
            WHERE i.anulado = 1 AND i.creado_en BETWEEN ? AND ? ORDER BY i.creado_en DESC LIMIT 200", 'ss', [$ini, $fin]) as $f) {
        $anuladas[] = [
            'tipo' => 'item', 'cuenta_id' => (int) $f['cuenta_id'], 'fecha' => substr($f['creado_en'], 0, 10), 'hora' => substr($f['creado_en'], 11, 5),
            'mesa' => (int) $f['mesa_numero'], 'mozo' => $f['mozo_nombre'], 'detalle' => $f['cantidad'] . '× ' . $f['nombre'],
            'monto' => facRedondear((float) $f['precio'] * (int) $f['cantidad']), 'motivo' => $f['motivo_anulacion'],
        ];
    }
    usort($anuladas, fn($a, $b) => strcmp($b['fecha'] . $b['hora'], $a['fecha'] . $a['hora']));

    $cantidad = (int) $r['n'];
    facJson([
        'ok' => true,
        'desde' => $desde, 'hasta' => $hasta, 'un_dia' => $unDia,
        'resumen' => [
            'total' => facRedondear((float) $r['total']),
            'comprobantes' => $cantidad,
            'cuentas' => (int) $r['cuentas'],
            'promedio' => (int) $r['cuentas'] ? facRedondear((float) $r['total'] / (int) $r['cuentas']) : 0,
            'propina' => facRedondear((float) $r['propina']),
            'iva' => facRedondear((float) $r['iva']),
            'medios' => $porMedio,
        ],
        'serie' => $serie,
        'top' => array_map(fn($f) => ['nombre' => $f['nombre'], 'cantidad' => (int) $f['cant'], 'monto' => facRedondear((float) $f['monto'])], $top),
        'comprobantes' => $comprobantes,
        'pagina' => $pagina,
        'paginas' => max(1, (int) ceil($cantidad / $porPagina)),
        'anulaciones' => $anuladas,
    ]);
});
