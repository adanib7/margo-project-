<?php
/**
 * Tickets de 80 mm para la impresora térmica de la caja (o cualquier impresora).
 *
 *   ?tipo=comprobante&id=N   e-Ticket / e-Factura de un pago
 *   ?tipo=precuenta&cuenta=N cuenta para llevar a la mesa (no es comprobante)
 *   ?tipo=comandas           pedidos nuevos para cocina y barra (los marca impresos)
 *   ?tipo=comandas&cuenta=N  reimprime todos los pedidos de una cuenta
 *   ?tipo=z&turno=N          reporte Z del turno
 *
 * Se abre en una ventana aparte y lanza el diálogo de impresión solo.
 */
require_once __DIR__ . '/sesion.php';
iniciarSesion();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/facturacion_db.php';
require_once __DIR__ . '/cfe_proveedor.php';
requireRole(...FAC_ROLES_CAJA);

if ($conn === null) {
    http_response_code(500);
    exit('Sin conexión a la base de datos.');
}
ensureFacturacionTables($conn);

$tipo = (string) ($_GET['tipo'] ?? '');
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$plata = fn($n) => ((float) $n < 0 ? '− ' : '') . '$ ' . number_format(abs((float) $n), 2, ',', '.');
$local = cfg('local.nombre');
$razon = trim((string) cfg('facturacion.razon_social'));
$rutLocal = trim((string) cfg('facturacion.rut'));
$titulo = 'Ticket';
$cuerpo = '';
$sinImprimir = isset($_GET['ver']);

ob_start();

if ($tipo === 'comprobante') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT c.*, cu.mesa_numero, cu.mozo_nombre FROM comprobantes c JOIN cuentas cu ON cu.id = c.cuenta_id WHERE c.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$c) { http_response_code(404); exit('Comprobante no encontrado.'); }
    $lineas = json_decode($c['detalle'], true) ?: [];
    $titulo = facNombreComprobante($c);
    ?>
    <header class="cab">
      <strong><?= $e($razon !== '' ? $razon : $local) ?></strong>
      <?php if ($razon !== '' && $razon !== $local): ?><span><?= $e($local) ?></span><?php endif; ?>
      <span>RUT <?= $e($rutLocal !== '' ? $rutLocal : '[pendiente]') ?></span>
      <span><?= $e(localDireccion(' · ', false)) ?></span>
    </header>
    <div class="sep"></div>
    <p class="centro fuerte"><?= $c['tipo'] === 'efactura' ? 'e-FACTURA' : 'e-TICKET' ?> · Serie <?= $e($c['serie']) ?> Nº <?= str_pad((string) $c['numero'], 7, '0', STR_PAD_LEFT) ?></p>
    <p class="centro"><?= $e(date('d/m/Y H:i', strtotime($c['creado_en']))) ?> · <?= $c['mesa_numero'] ? 'Mesa ' . (int) $c['mesa_numero'] : 'Mostrador' ?></p>
    <?php if ($c['tipo'] === 'efactura'): ?>
      <div class="sep"></div>
      <p>RUT receptor: <?= $e($c['receptor_rut']) ?></p>
      <p><?= $e($c['receptor_nombre']) ?></p>
    <?php else: ?>
      <p class="centro">Consumo final</p>
    <?php endif; ?>
    <div class="sep"></div>
    <?php foreach ($lineas as $l): ?>
      <div class="fila"><span><?= (int) $l['cant'] ?> × <?= $e($l['desc']) ?></span><span><?= $plata($l['total']) ?></span></div>
    <?php endforeach; ?>
    <div class="sep"></div>
    <?php if ($c['neto_basica'] > 0): ?>
      <div class="fila"><span>Neto IVA 22%</span><span><?= $plata($c['neto_basica']) ?></span></div>
      <div class="fila"><span>IVA 22%</span><span><?= $plata($c['iva_basica']) ?></span></div>
    <?php endif; ?>
    <?php if ($c['neto_minima'] > 0): ?>
      <div class="fila"><span>Neto IVA 10%</span><span><?= $plata($c['neto_minima']) ?></span></div>
      <div class="fila"><span>IVA 10%</span><span><?= $plata($c['iva_minima']) ?></span></div>
    <?php endif; ?>
    <?php if ($c['exento'] > 0): ?>
      <div class="fila"><span>Exento</span><span><?= $plata($c['exento']) ?></span></div>
    <?php endif; ?>
    <div class="fila total"><span>TOTAL</span><span><?= $plata($c['total']) ?></span></div>
    <?php if ($c['propina'] > 0): ?>
      <div class="fila"><span>Propina (no integra el comprobante)</span><span><?= $plata($c['propina']) ?></span></div>
      <div class="fila fuerte"><span>Total abonado</span><span><?= $plata($c['total'] + $c['propina']) ?></span></div>
    <?php endif; ?>
    <p>Pago: <?= $e(FAC_MEDIOS[$c['medio']] ?? $c['medio']) ?><?= $c['mozo_nombre'] ? ' · Atendió: ' . $e($c['mozo_nombre']) : '' ?></p>
    <div class="sep"></div>
    <p class="centro leyenda"><?= $e(cfeLeyenda($c['estado_dgi'])) ?></p>
    <p class="centro">¡Gracias por su visita!</p>
    <?php
} elseif ($tipo === 'precuenta') {
    $cuenta = facCuentaDetalle($conn, (int) ($_GET['cuenta'] ?? 0));
    if (!$cuenta) { http_response_code(404); exit('Cuenta no encontrada.'); }
    $titulo = 'Cuenta mesa ' . $cuenta['mesa_numero'];
    $agrupado = [];
    foreach ($cuenta['items'] as $i) {
        if ($i['anulado']) continue;
        $k = $i['nombre'] . '|' . $i['precio'];
        $agrupado[$k] ??= ['nombre' => $i['nombre'], 'cant' => 0, 'total' => 0];
        $agrupado[$k]['cant'] += $i['cantidad'];
        $agrupado[$k]['total'] += $i['total'];
    }
    ?>
    <header class="cab"><strong><?= $e($local) ?></strong><span><?= $e(localDireccion(' · ', false)) ?></span></header>
    <div class="sep"></div>
    <p class="centro fuerte">CUENTA · <?= $cuenta['mesa_numero'] ? 'MESA ' . $cuenta['mesa_numero'] : 'MOSTRADOR' ?></p>
    <p class="centro"><?= date('d/m/Y H:i') ?> · <?= (int) $cuenta['comensales'] ?> pers.<?= $cuenta['mozo'] ? ' · ' . $e($cuenta['mozo']) : '' ?></p>
    <div class="sep"></div>
    <?php foreach ($agrupado as $a): ?>
      <div class="fila"><span><?= (int) $a['cant'] ?> × <?= $e($a['nombre']) ?></span><span><?= $plata($a['total']) ?></span></div>
    <?php endforeach; ?>
    <div class="sep"></div>
    <div class="fila total"><span>TOTAL</span><span><?= $plata($cuenta['total']) ?></span></div>
    <?php if ($cuenta['pagado'] > 0): ?>
      <div class="fila"><span>Ya pagado</span><span>− <?= $plata($cuenta['pagado']) ?></span></div>
      <div class="fila fuerte"><span>Saldo</span><span><?= $plata($cuenta['saldo']) ?></span></div>
    <?php endif; ?>
    <?php if (count($cuenta['asientos']) > 1): ?>
      <div class="sep"></div>
      <p class="fuerte">Por persona</p>
      <?php foreach ($cuenta['asientos'] as $a): ?>
        <div class="fila"><span>Asiento <?= $e($a['letra']) ?><?= $a['pagado'] ? ' (pagó)' : '' ?></span><span><?= $plata($a['total']) ?></span></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php $sug = (int) cfg('facturacion.propina_sugerida'); if ($sug > 0): ?>
      <p>Propina sugerida <?= $sug ?>%: <?= $plata($cuenta['saldo'] * $sug / 100) ?></p>
    <?php endif; ?>
    <div class="sep"></div>
    <p class="centro leyenda">ESTE DOCUMENTO NO ES UN COMPROBANTE. Solicite su e-Ticket al pagar.</p>
    <?php
} elseif ($tipo === 'comandas') {
    $titulo = 'Comandas';
    $cuentaId = (int) ($_GET['cuenta'] ?? 0);
    $sql = "SELECT i.id, i.nombre, i.cantidad, i.asiento, i.nota, i.destino, i.creado_en, i.cargado_por, c.mesa_numero, c.id cuenta_id, c.mozo_nombre
        FROM cuenta_items i JOIN cuentas c ON c.id = i.cuenta_id WHERE i.anulado = 0 AND ";
    if ($cuentaId > 0) {
        $stmt = $conn->prepare($sql . "c.id = ? ORDER BY i.destino, i.id");
        $stmt->bind_param('i', $cuentaId);
    } else {
        $stmt = $conn->prepare($sql . "i.comanda_impresa = 0 AND c.estado IN ('abierta', 'pide_cuenta') ORDER BY i.destino, c.mesa_numero, i.id");
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $grupos = [];
    $ids = [];
    while ($r = $res->fetch_assoc()) {
        $grupos[$r['destino'] . '|' . $r['cuenta_id']][] = $r;
        $ids[] = (int) $r['id'];
    }
    $stmt->close();
    if (!$grupos) {
        echo '<p class="centro">No hay comandas nuevas para imprimir.</p>';
        $sinImprimir = true;
    }
    $primero = true;
    foreach ($grupos as $filas) {
        $f0 = $filas[0];
        ?>
        <section class="comanda<?= $primero ? '' : ' corte' ?>">
          <p class="centro fuerte grande"><?= $e(strtoupper(FAC_DESTINOS[$f0['destino']] ?? $f0['destino'])) ?></p>
          <p class="centro fuerte grande"><?= $f0['mesa_numero'] ? 'MESA ' . (int) $f0['mesa_numero'] : 'MOSTRADOR' ?></p>
          <p class="centro"><?= date('H:i') ?><?= $f0['mozo_nombre'] ? ' · ' . $e($f0['mozo_nombre']) : '' ?><?= $cuentaId ? ' · REIMPRESIÓN' : '' ?></p>
          <div class="sep"></div>
          <?php foreach ($filas as $f): ?>
            <p class="item"><strong><?= (int) $f['cantidad'] ?> ×</strong> <?= $e($f['nombre']) ?><?= $f['asiento'] ? ' <span class="asiento">[' . $e($f['asiento']) . ']</span>' : '' ?></p>
            <?php if ($f['nota']): ?><p class="nota">→ <?= $e($f['nota']) ?></p><?php endif; ?>
          <?php endforeach; ?>
        </section>
        <?php
        $primero = false;
    }
    if ($ids && $cuentaId === 0) {
        $conn->query("UPDATE cuenta_items SET comanda_impresa = 1 WHERE id IN (" . implode(',', $ids) . ")");
    }
} elseif ($tipo === 'z') {
    $id = (int) ($_GET['turno'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM turnos_caja WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$t) { http_response_code(404); exit('Turno no encontrado.'); }
    $r = facResumenTurno($conn, $id);
    $titulo = 'Reporte Z #' . $id;
    $esperado = (float) $t['fondo_inicial'] + $r['efectivo_cobrado'];
    ?>
    <header class="cab">
      <strong><?= $e($razon !== '' ? $razon : $local) ?></strong>
      <span>RUT <?= $e($rutLocal !== '' ? $rutLocal : '[pendiente]') ?></span>
    </header>
    <div class="sep"></div>
    <p class="centro fuerte"><?= $t['cerrado_en'] ? 'REPORTE Z' : 'REPORTE X (PARCIAL)' ?> · TURNO #<?= $id ?></p>
    <p class="centro"><?= $e(date('d/m H:i', strtotime($t['abierto_en']))) ?> → <?= $t['cerrado_en'] ? $e(date('d/m H:i', strtotime($t['cerrado_en']))) : 'abierto' ?></p>
    <div class="sep"></div>
    <p class="fuerte">COBROS POR MEDIO</p>
    <?php foreach ($r['medios'] as $m): if (!$m['cantidad']) continue; ?>
      <div class="fila"><span><?= $e($m['label']) ?> (<?= $m['cantidad'] ?>)</span><span><?= $plata($m['monto']) ?></span></div>
    <?php endforeach; ?>
    <div class="fila total"><span>TOTAL VENTAS</span><span><?= $plata($r['total']) ?></span></div>
    <div class="sep"></div>
    <p class="fuerte">COMPROBANTES</p>
    <div class="fila"><span>e-Ticket</span><span><?= $r['comprobantes']['eticket'] ?></span></div>
    <div class="fila"><span>e-Factura</span><span><?= $r['comprobantes']['efactura'] ?></span></div>
    <div class="sep"></div>
    <p class="fuerte">IVA (incluido)</p>
    <div class="fila"><span>Neto 22%</span><span><?= $plata($r['comprobantes']['neto_basica']) ?></span></div>
    <div class="fila"><span>IVA 22%</span><span><?= $plata($r['comprobantes']['iva_basica']) ?></span></div>
    <?php if ($r['comprobantes']['neto_minima'] > 0): ?>
      <div class="fila"><span>Neto 10%</span><span><?= $plata($r['comprobantes']['neto_minima']) ?></span></div>
      <div class="fila"><span>IVA 10%</span><span><?= $plata($r['comprobantes']['iva_minima']) ?></span></div>
    <?php endif; ?>
    <?php if ($r['comprobantes']['exento'] > 0): ?>
      <div class="fila"><span>Exento</span><span><?= $plata($r['comprobantes']['exento']) ?></span></div>
    <?php endif; ?>
    <div class="sep"></div>
    <p class="fuerte">PROPINAS</p>
    <div class="fila"><span>Total</span><span><?= $plata($r['propina_total']) ?></span></div>
    <?php foreach ($r['propinas'] as $p): ?>
      <div class="fila"><span><?= $e($p['mozo']) ?></span><span><?= $plata($p['monto']) ?></span></div>
    <?php endforeach; ?>
    <div class="sep"></div>
    <p class="fuerte">ARQUEO DE EFECTIVO</p>
    <div class="fila"><span>Fondo inicial</span><span><?= $plata($t['fondo_inicial']) ?></span></div>
    <div class="fila"><span>Efectivo cobrado (+ propinas)</span><span><?= $plata($r['efectivo_cobrado']) ?></span></div>
    <div class="fila"><span>Esperado en caja</span><span><?= $plata($esperado) ?></span></div>
    <?php if ($t['cerrado_en']): ?>
      <div class="fila"><span>Contado</span><span><?= $plata($t['efectivo_contado']) ?></span></div>
      <div class="fila total"><span>Diferencia</span><span><?= ($t['diferencia'] > 0 ? '+ ' : '') . $plata($t['diferencia']) ?></span></div>
      <p>Cerró: <?= $e($t['cerrado_por']) ?></p>
      <?php if ($t['nota']): ?><p>Nota: <?= $e($t['nota']) ?></p><?php endif; ?>
    <?php endif; ?>
    <div class="sep"></div>
    <p class="centro leyenda">Reporte interno. Los comprobantes todavía no se envían a DGI.</p>
    <?php
} else {
    http_response_code(400);
    exit('Tipo de ticket desconocido.');
}

$cuerpo = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($titulo) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  @page { size: 80mm auto; margin: 4mm; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #eeeee8; font-family: 'Roboto Mono', monospace; font-size: 12px; color: #000; }
  .ticket { width: 72mm; margin: 16px auto; background: #fff; padding: 10px 8px 16px; box-shadow: 0 6px 18px rgba(0,0,0,.12); }
  p { margin: 2px 0; }
  .cab { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1px; }
  .cab strong { font-size: 14px; }
  .centro { text-align: center; }
  .fuerte { font-weight: 700; }
  .grande { font-size: 18px; }
  .sep { border-top: 1px dashed #000; margin: 6px 0; }
  .fila { display: flex; justify-content: space-between; gap: 8px; padding: 1px 0; }
  .fila span:last-child { white-space: nowrap; }
  .total { font-weight: 700; font-size: 15px; border-top: 1px solid #000; margin-top: 4px; padding-top: 4px; }
  .leyenda { font-size: 10px; font-weight: 700; }
  .item { font-size: 15px; margin: 4px 0; }
  .asiento { font-weight: 700; }
  .nota { font-size: 13px; padding-left: 14px; font-style: italic; }
  .corte { border-top: 2px dashed #000; margin-top: 14px; padding-top: 10px; }
  .acciones { text-align: center; margin: 12px; font-family: sans-serif; }
  .acciones button { padding: 10px 18px; border-radius: 8px; border: 0; background: #264220; color: #fff; font-weight: 700; cursor: pointer; }
  @media print {
    body { background: #fff; }
    .ticket { width: auto; margin: 0; box-shadow: none; padding: 0; }
    .acciones { display: none; }
    .corte { break-before: page; border-top: 0; margin-top: 0; }
  }
</style>
</head>
<body>
<div class="acciones"><button type="button" onclick="window.print()">Imprimir</button></div>
<main class="ticket"><?= $cuerpo ?></main>
<?php if (!$sinImprimir): ?>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
<?php endif; ?>
</body>
</html>
