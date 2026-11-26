<?php
// Detalle de una cuenta: ítems, pagos, saldo y división por asiento.
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/facturacion_db.php';

$conn = facIniciarApi(FAC_ROLES_SALON);

facEjecutar($conn, function () use ($conn) {
    $id = (int) ($_GET['id'] ?? 0);
    $cuenta = $id > 0 ? facCuentaDetalle($conn, $id) : null;
    if (!$cuenta) {
        facJson(['ok' => false, 'mensaje' => 'La cuenta no existe.'], 404);
    }
    facJson(['ok' => true, 'cuenta' => $cuenta]);
});
