<?php
// Estado en vivo del salón: mesas, cuentas abiertas, turno y comandas por imprimir.
// Lo consultan la caja y la vista del mozo cada pocos segundos.
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/facturacion_db.php';

$conn = facIniciarApi(FAC_ROLES_SALON);

facEjecutar($conn, function () use ($conn) {
    $rol = $_SESSION['rol'];
    $esCaja = in_array($rol, FAC_ROLES_CAJA, true);
    $turno = facTurnoAbierto($conn);

    $respuesta = [
        'ok' => true,
        'rol' => $rol,
        'usuario_id' => (int) $_SESSION['usuario_id'],
        'plano' => ['ancho' => PLANO_ANCHO, 'alto' => PLANO_ALTO],
        'propina_sugerida' => max(0, min(30, cfgInt('facturacion.propina_sugerida'))),
        'turno' => $turno ? [
            'id' => (int) $turno['id'],
            'abierto_en' => substr($turno['abierto_en'], 11, 5),
            'abierto_por' => $turno['abierto_por'],
            'fondo' => (float) $turno['fondo_inicial'],
        ] : null,
    ] + facEstadoSalon($conn);

    if ($esCaja) {
        if ($turno) {
            $r = facResumenTurno($conn, (int) $turno['id']);
            $respuesta['resumen'] = [
                'total' => $r['total'],
                'cobros' => $r['cobros'],
                'comprobantes' => $r['comprobantes']['eticket'] + $r['comprobantes']['efactura'],
            ];
        }
        $mozos = [];
        $res = $conn->query("SELECT id, nombre FROM usuarios WHERE rol = 'mozo' ORDER BY nombre");
        while ($res && ($m = $res->fetch_assoc())) $mozos[] = ['id' => (int) $m['id'], 'nombre' => $m['nombre']];
        $respuesta['mozos'] = $mozos;
    }

    facJson($respuesta);
});
