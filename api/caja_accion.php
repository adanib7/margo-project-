<?php
/**
 * Acciones sobre las cuentas del salón (POST JSON con "accion").
 *
 *   abrir_mesa, agregar_items, anular_item, pedir_cuenta, reabrir,
 *   cambiar_comensales, mover_mesa                 → mozo, admin, superadmin
 *   cobrar, anular_cuenta                          → admin, superadmin (caja)
 */
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/facturacion_db.php';
require_once __DIR__ . '/../includes/cfe_proveedor.php';

$conn = facIniciarApi(FAC_ROLES_SALON);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    facJson(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$body = facCuerpo();
$accion = (string) ($body['accion'] ?? '');
$rol = $_SESSION['rol'];
$esCaja = in_array($rol, FAC_ROLES_CAJA, true);

if (in_array($accion, ['cobrar', 'anular_cuenta'], true) && !$esCaja) {
    facJson(['ok' => false, 'mensaje' => 'Solo la caja puede hacer esto.'], 403);
}

/** Bloquea la cuenta para la transacción y comprueba que siga abierta. */
function cuentaParaEditar(mysqli $conn, int $id): array
{
    $stmt = $conn->prepare("SELECT * FROM cuentas WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$c) {
        $conn->rollback();
        facJson(['ok' => false, 'mensaje' => 'La cuenta no existe.'], 404);
    }
    if (!in_array($c['estado'], ['abierta', 'pide_cuenta'], true)) {
        $conn->rollback();
        facJson(['ok' => false, 'mensaje' => 'Esa cuenta ya está cerrada.'], 409);
    }
    return $c;
}

function mesaLibre(mysqli $conn, int $mesaId): ?array
{
    $stmt = $conn->prepare("SELECT id, numero FROM mesas WHERE id = ? AND activa = 1");
    $stmt->bind_param('i', $mesaId);
    $stmt->execute();
    $mesa = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$mesa) return null;
    $stmt = $conn->prepare("SELECT 1 FROM cuentas WHERE mesa_id = ? AND estado IN ('abierta', 'pide_cuenta') LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $mesaId);
    $stmt->execute();
    $stmt->store_result();
    $ocupada = $stmt->num_rows > 0;
    $stmt->close();
    return $ocupada ? ['ocupada' => true] + $mesa : $mesa;
}

facEjecutar($conn, function () use ($conn, $body, $accion, $rol, $esCaja) {
    $usuario = facUsuarioNombre();
    $ahora = date('Y-m-d H:i:s');
    $cuentaId = (int) ($body['cuenta_id'] ?? 0);

    switch ($accion) {

    case 'abrir_mesa': {
        $mesaId = (int) ($body['mesa_id'] ?? 0);
        $comensales = max(1, min(40, (int) ($body['comensales'] ?? 1)));
        $reservaId = (int) ($body['reserva_id'] ?? 0);
        $forzar = !empty($body['forzar']);
        $conn->begin_transaction();
        $mesaNumero = 0;
        $reservas = facReservasPendientes($conn);
        $reserva = null;
        if ($reservaId > 0) {
            // Llegó una reserva: tiene que ser de hoy, activa y sin otra mesa abierta.
            foreach ($reservas as $r) if ($r['id'] === $reservaId) $reserva = $r;
            if (!$reserva) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'Esa reserva ya no está pendiente (se canceló, ya se sentó o terminó su horario).'], 409); }
            if (empty($body['comensales'])) $comensales = max(1, $reserva['personas']);
        }
        if ($mesaId > 0) {
            $mesa = mesaLibre($conn, $mesaId);
            if (!$mesa) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'La mesa no existe.'], 404); }
            if (!empty($mesa['ocupada'])) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'Esa mesa ya tiene una cuenta abierta.'], 409); }
            $mesaNumero = (int) $mesa['numero'];

            // ¿Sentar gente acá pisa una reserva? (salvo que sea esa misma reserva)
            $choque = facReservaQueBloquea($reservas, $mesaId);
            if ($choque && $choque['id'] !== $reservaId && !$forzar) {
                $conn->rollback();
                $alternativas = [];
                foreach (facEstadoSalon($conn)['mesas'] as $m) {
                    if (!$m['cuenta'] && !$m['reserva'] && $m['id'] !== $mesaId && $m['capacidad'] >= $comensales) {
                        $alternativas[] = ['id' => $m['id'], 'numero' => $m['numero'], 'capacidad' => $m['capacidad']];
                    }
                }
                usort($alternativas, fn($a, $b) => $a['capacidad'] <=> $b['capacidad'] ?: $a['numero'] <=> $b['numero']);
                $cuando = $choque['minutos'] > 0 ? "en {$choque['minutos']} min" : 'ya debería estar llegando';
                facJson([
                    'ok' => false,
                    'codigo' => 'mesa_reservada',
                    'mensaje' => "La mesa {$mesaNumero} está reservada a las {$choque['hora']} ({$cuando}) para {$choque['nombre']}.",
                    'reserva' => $choque,
                    'alternativas' => array_slice($alternativas, 0, 6),
                ], 409);
            }
        }
        $reservaParam = $reserva ? $reservaId : null;
        // El mozo se asigna solo; la caja puede elegir a quién le toca la mesa.
        $mozoId = null; $mozoNombre = null;
        if ($rol === 'mozo') {
            $mozoId = (int) $_SESSION['usuario_id'];
            $mozoNombre = $usuario;
        } elseif (!empty($body['mozo_id'])) {
            $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE id = ? AND rol = 'mozo'");
            $mid = (int) $body['mozo_id'];
            $stmt->bind_param('i', $mid);
            $stmt->execute();
            $m = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($m) { $mozoId = (int) $m['id']; $mozoNombre = $m['nombre']; }
        }
        $mesaParam = $mesaId > 0 ? $mesaId : null;
        $stmt = $conn->prepare("INSERT INTO cuentas (mesa_id, mesa_numero, reserva_id, estado, comensales, mozo_id, mozo_nombre, abierta_en) VALUES (?, ?, ?, 'abierta', ?, ?, ?, ?)");
        $stmt->bind_param('iiiiiss', $mesaParam, $mesaNumero, $reservaParam, $comensales, $mozoId, $mozoNombre, $ahora);
        $stmt->execute();
        $id = (int) $conn->insert_id;
        $stmt->close();
        $conn->commit();
        $mensaje = $mesaNumero ? "Mesa {$mesaNumero} abierta." : 'Venta de mostrador abierta.';
        if ($reserva) $mensaje = "Llegó la reserva de {$reserva['nombre']}: mesa {$mesaNumero} abierta.";
        elseif ($forzar && $mesaId > 0 && facReservaQueBloquea($reservas, $mesaId)) $mensaje .= ' Ojo: tiene una reserva próxima.';
        facJson(['ok' => true, 'mensaje' => $mensaje, 'cuenta' => facCuentaDetalle($conn, $id)]);
    }

    case 'agregar_items': {
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        if (!$items || count($items) > 60) {
            facJson(['ok' => false, 'mensaje' => 'No hay productos para agregar.'], 422);
        }
        $conn->begin_transaction();
        $cuenta = cuentaParaEditar($conn, $cuentaId);
        $max = max(1, (int) $cuenta['comensales']);
        $buscar = $conn->prepare("SELECT id, nombre, precio, iva, destino FROM carta_productos WHERE id = ? AND activo = 1");
        $insertar = $conn->prepare("INSERT INTO cuenta_items (cuenta_id, producto_id, nombre, precio, iva, destino, cantidad, asiento, nota, cargado_por, creado_en)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $n = 0;
        foreach ($items as $it) {
            $pid = (int) ($it['producto_id'] ?? 0);
            $cant = (int) ($it['cantidad'] ?? 1);
            if ($pid <= 0 || $cant < 1 || $cant > 99) continue;
            $buscar->bind_param('i', $pid);
            $buscar->execute();
            $p = $buscar->get_result()->fetch_assoc();
            if (!$p) continue;
            // Asiento: letra A.. según la cantidad de comensales, o compartido.
            $asiento = strtoupper(trim((string) ($it['asiento'] ?? '')));
            if (!preg_match('/^[A-Z]$/', $asiento) || ord($asiento) - 64 > $max) $asiento = null;
            $nota = mb_substr(trim((string) ($it['nota'] ?? '')), 0, 160);
            $nota = $nota !== '' ? $nota : null;
            $precio = (float) $p['precio'];
            $insertar->bind_param('iisdssissss', $cuentaId, $pid, $p['nombre'], $precio, $p['iva'], $p['destino'], $cant, $asiento, $nota, $usuario, $ahora);
            $insertar->execute();
            $n++;
        }
        $buscar->close();
        $insertar->close();
        if ($n === 0) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'Los productos elegidos ya no están en la carta.'], 422); }
        // Si había pedido la cuenta y pide algo más, vuelve a estar comiendo.
        if ($cuenta['estado'] === 'pide_cuenta') {
            $conn->query("UPDATE cuentas SET estado = 'abierta', pidio_cuenta_en = NULL WHERE id = " . (int) $cuentaId);
        }
        $conn->commit();
        facJson(['ok' => true, 'mensaje' => $n === 1 ? 'Pedido enviado.' : "Pedido enviado ({$n} productos).", 'cuenta' => facCuentaDetalle($conn, $cuentaId)]);
    }

    case 'anular_item': {
        $itemId = (int) ($body['item_id'] ?? 0);
        $motivo = mb_substr(trim((string) ($body['motivo'] ?? '')), 0, 160);
        $conn->begin_transaction();
        $stmt = $conn->prepare("SELECT cuenta_id, comanda_impresa, anulado FROM cuenta_items WHERE id = ?");
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item || $item['anulado']) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'El producto ya no está en la cuenta.'], 404); }
        $cuentaId = (int) $item['cuenta_id'];
        cuentaParaEditar($conn, $cuentaId);
        // Lo que ya salió para cocina solo lo anula la caja, y con motivo.
        if ($item['comanda_impresa'] && !$esCaja) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'Ya salió para cocina. Pedile a la caja que lo anule.'], 403); }
        if ($item['comanda_impresa'] && $motivo === '') { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'Indicá el motivo de la anulación.'], 422); }
        $detalle = facCuentaDetalle($conn, $cuentaId);
        if ($detalle['pagado'] > 0) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'La cuenta ya tiene pagos: no se pueden quitar productos.'], 409); }
        $motivoFinal = ($motivo !== '' ? $motivo : 'Quitado antes de imprimir') . ' · ' . $usuario;
        $stmt = $conn->prepare("UPDATE cuenta_items SET anulado = 1, motivo_anulacion = ? WHERE id = ?");
        $stmt->bind_param('si', $motivoFinal, $itemId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        facJson(['ok' => true, 'mensaje' => 'Producto anulado.', 'cuenta' => facCuentaDetalle($conn, $cuentaId)]);
    }

    case 'pedir_cuenta':
    case 'reabrir': {
        $conn->begin_transaction();
        cuentaParaEditar($conn, $cuentaId);
        if ($accion === 'pedir_cuenta') {
            $stmt = $conn->prepare("UPDATE cuentas SET estado = 'pide_cuenta', pidio_cuenta_en = ? WHERE id = ?");
            $stmt->bind_param('si', $ahora, $cuentaId);
        } else {
            $stmt = $conn->prepare("UPDATE cuentas SET estado = 'abierta', pidio_cuenta_en = NULL WHERE id = ?");
            $stmt->bind_param('i', $cuentaId);
        }
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        facJson(['ok' => true, 'mensaje' => $accion === 'pedir_cuenta' ? 'Avisado a la caja: la mesa pide la cuenta.' : 'La mesa sigue comiendo.', 'cuenta' => facCuentaDetalle($conn, $cuentaId)]);
    }

    case 'cambiar_comensales': {
        $comensales = max(1, min(40, (int) ($body['comensales'] ?? 1)));
        $conn->begin_transaction();
        cuentaParaEditar($conn, $cuentaId);
        // No se puede bajar por debajo del último asiento con pedidos.
        $stmt = $conn->prepare("SELECT MAX(asiento) a FROM cuenta_items WHERE cuenta_id = ? AND anulado = 0");
        $stmt->bind_param('i', $cuentaId);
        $stmt->execute();
        $ultimo = $stmt->get_result()->fetch_assoc()['a'];
        $stmt->close();
        if ($ultimo && ord($ultimo) - 64 > $comensales) {
            $conn->rollback();
            facJson(['ok' => false, 'mensaje' => "El asiento {$ultimo} tiene pedidos: no se puede bajar a {$comensales}."], 422);
        }
        $stmt = $conn->prepare("UPDATE cuentas SET comensales = ? WHERE id = ?");
        $stmt->bind_param('ii', $comensales, $cuentaId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        facJson(['ok' => true, 'mensaje' => 'Comensales actualizados.', 'cuenta' => facCuentaDetalle($conn, $cuentaId)]);
    }

    case 'mover_mesa': {
        $mesaId = (int) ($body['mesa_id'] ?? 0);
        $conn->begin_transaction();
        $actual = cuentaParaEditar($conn, $cuentaId);
        $mesa = mesaLibre($conn, $mesaId);
        if (!$mesa) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'La mesa no existe.'], 404); }
        if (!empty($mesa['ocupada'])) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'Esa mesa ya está ocupada.'], 409); }
        $choque = facReservaQueBloquea(facReservasPendientes($conn), $mesaId);
        if ($choque && $choque['id'] !== (int) ($actual['reserva_id'] ?? 0) && empty($body['forzar'])) {
            $conn->rollback();
            facJson(['ok' => false, 'codigo' => 'mesa_reservada', 'reserva' => $choque,
                'mensaje' => "La mesa {$mesa['numero']} está reservada a las {$choque['hora']} para {$choque['nombre']}. Elegí otra."], 409);
        }
        $num = (int) $mesa['numero'];
        $stmt = $conn->prepare("UPDATE cuentas SET mesa_id = ?, mesa_numero = ? WHERE id = ?");
        $stmt->bind_param('iii', $mesaId, $num, $cuentaId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        facJson(['ok' => true, 'mensaje' => "Cuenta movida a la mesa {$num}.", 'cuenta' => facCuentaDetalle($conn, $cuentaId)]);
    }

    case 'anular_cuenta': {
        $motivo = mb_substr(trim((string) ($body['motivo'] ?? '')), 0, 200);
        if ($motivo === '') facJson(['ok' => false, 'mensaje' => 'Indicá el motivo de la anulación.'], 422);
        $conn->begin_transaction();
        cuentaParaEditar($conn, $cuentaId);
        $detalle = facCuentaDetalle($conn, $cuentaId);
        if ($detalle['pagado'] > 0) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'La cuenta tiene pagos registrados: cobrá el saldo en lugar de anularla.'], 409); }
        $m = $motivo . ' · ' . $usuario;
        $stmt = $conn->prepare("UPDATE cuentas SET estado = 'anulada', cerrada_en = ?, motivo_anulacion = ? WHERE id = ?");
        $stmt->bind_param('ssi', $ahora, $m, $cuentaId);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        facJson(['ok' => true, 'mensaje' => 'Cuenta anulada. La mesa quedó libre.']);
    }

    case 'cobrar': {
        $medio = (string) ($body['medio'] ?? '');
        if (!isset(FAC_MEDIOS[$medio])) facJson(['ok' => false, 'mensaje' => 'Elegí el medio de pago.'], 422);
        $propina = round(max(0, (float) ($body['propina'] ?? 0)), 2);
        $asiento = isset($body['asiento']) && preg_match('/^[A-Z]$/', (string) $body['asiento']) ? (string) $body['asiento'] : null;

        $cfe = is_array($body['cfe'] ?? null) ? $body['cfe'] : [];
        $tipo = ($cfe['tipo'] ?? '') === 'efactura' ? 'efactura' : 'eticket';
        $rut = preg_replace('/\D/', '', (string) ($cfe['rut'] ?? ''));
        $razon = mb_substr(trim((string) ($cfe['razon_social'] ?? '')), 0, 150);
        $email = trim((string) ($cfe['email'] ?? ''));
        if ($tipo === 'efactura') {
            if (!facRutValido($rut)) facJson(['ok' => false, 'mensaje' => 'El RUT no es válido (12 dígitos con su dígito verificador).', 'campo' => 'rut'], 422);
            if ($razon === '') facJson(['ok' => false, 'mensaje' => 'La e-Factura necesita la razón social.', 'campo' => 'razon_social'], 422);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) facJson(['ok' => false, 'mensaje' => 'El correo no es válido.', 'campo' => 'email'], 422);

        $conn->begin_transaction();
        $turno = facTurnoAbierto($conn);
        if (!$turno) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'Primero abrí la caja del turno.', 'codigo' => 'sin_turno'], 409); }
        $c = cuentaParaEditar($conn, $cuentaId);
        $cuenta = facCuentaDetalle($conn, $cuentaId);
        if ($cuenta['saldo'] <= 0) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'La cuenta no tiene saldo para cobrar.'], 422); }

        // El monto de un asiento lo calcula el servidor; el último paga el saldo exacto.
        if ($asiento !== null) {
            $seat = null; $pendientes = 0;
            foreach ($cuenta['asientos'] as $a) {
                if (!$a['pagado']) $pendientes++;
                if ($a['letra'] === $asiento) $seat = $a;
            }
            if (!$seat) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'Ese asiento no tiene pedidos.'], 422); }
            if ($seat['pagado']) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => "El asiento {$asiento} ya pagó."], 409); }
            $monto = $pendientes === 1 ? $cuenta['saldo'] : min($seat['total'], $cuenta['saldo']);
        } else {
            $monto = round((float) ($body['monto'] ?? 0), 2);
            if ($monto <= 0) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'El monto a cobrar no es válido.'], 422); }
            if ($monto > $cuenta['saldo'] + 0.01) { $conn->rollback(); facJson(['ok' => false, 'mensaje' => 'El monto supera el saldo de la cuenta.'], 422); }
            $monto = min($monto, $cuenta['saldo']);
        }

        $lineas = facLineasPago($cuenta, $monto, $asiento);
        $d = facDesglose($lineas);

        // Numeración interna correlativa de la serie X (no fiscal).
        $res = $conn->query("SELECT COALESCE(MAX(numero), 0) + 1 n FROM comprobantes WHERE serie = 'X' FOR UPDATE");
        $numero = (int) $res->fetch_assoc()['n'];
        $turnoId = (int) $turno['id'];
        $detalleJson = json_encode($lineas, JSON_UNESCAPED_UNICODE);
        $rutParam = $tipo === 'efactura' ? $rut : null;
        $razonParam = $tipo === 'efactura' ? $razon : null;
        $emailParam = $email !== '' ? $email : null;
        $stmt = $conn->prepare("INSERT INTO comprobantes (tipo, serie, numero, cuenta_id, turno_id, receptor_rut, receptor_nombre, receptor_email,
                total, neto_basica, iva_basica, neto_minima, iva_minima, exento, propina, medio, detalle, creado_en)
            VALUES (?, 'X', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('siiisssdddddddsss', $tipo, $numero, $cuentaId, $turnoId, $rutParam, $razonParam, $emailParam,
            $monto, $d['neto_basica'], $d['iva_basica'], $d['neto_minima'], $d['iva_minima'], $d['exento'], $propina, $medio, $detalleJson, $ahora);
        $stmt->execute();
        $comprobanteId = (int) $conn->insert_id;
        $stmt->close();

        $mozo = $c['mozo_nombre'];
        $stmt = $conn->prepare("INSERT INTO pagos (cuenta_id, turno_id, monto, propina, medio, asiento, comprobante_id, mozo_nombre, cobrado_por, creado_en)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiddssisss', $cuentaId, $turnoId, $monto, $propina, $medio, $asiento, $comprobanteId, $mozo, $usuario, $ahora);
        $stmt->execute();
        $stmt->close();

        $saldo = round($cuenta['saldo'] - $monto, 2);
        $cerrada = $saldo <= 0.009;
        if ($cerrada) {
            $stmt = $conn->prepare("UPDATE cuentas SET estado = 'cobrada', cerrada_en = ?, turno_id = ? WHERE id = ?");
            $stmt->bind_param('sii', $ahora, $turnoId, $cuentaId);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();

        // Envío a DGI (hoy sin integrar). Va después del commit: el cobro no se
        // pierde aunque el proveedor no responda; se reintenta más tarde.
        $envio = cfeEmitir(['id' => $comprobanteId, 'tipo' => $tipo, 'lineas' => $lineas] + $d);
        $estadoDgi = in_array($envio['estado'] ?? '', ['aceptado', 'pendiente', 'rechazado'], true) ? $envio['estado'] : 'sin_integrar';
        if ($estadoDgi !== 'sin_integrar') {
            $stmt = $conn->prepare("UPDATE comprobantes SET estado_dgi = ? WHERE id = ?");
            $stmt->bind_param('si', $estadoDgi, $comprobanteId);
            $stmt->execute();
            $stmt->close();
        }

        $nombre = facNombreComprobante(['tipo' => $tipo, 'serie' => 'X', 'numero' => $numero]);
        facJson([
            'ok' => true,
            'mensaje' => $cerrada ? 'Mesa cobrada y liberada.' : 'Pago registrado. Saldo pendiente.',
            'comprobante' => ['id' => $comprobanteId, 'nombre' => $nombre, 'total' => $monto, 'propina' => $propina],
            'cerrada' => $cerrada,
            'cuenta' => facCuentaDetalle($conn, $cuentaId),
        ]);
    }

    default:
        facJson(['ok' => false, 'mensaje' => 'Acción desconocida.'], 400);
    }
});
