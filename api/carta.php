<?php
/**
 * Carta (productos que se venden) y datos fiscales del local.
 *   GET                       → productos activos (mozo y caja); ?todos=1 incluye pausados (caja)
 *   POST {accion:"guardar", id?, nombre, categoria, precio, iva, destino, activo}
 *   POST {accion:"eliminar", id}   → si ya se vendió, se pausa en lugar de borrarse
 *   POST {accion:"ajustes", razon_social, rut, propina_sugerida, fondo_sugerido}
 */
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/facturacion_db.php';

$conn = facIniciarApi(FAC_ROLES_SALON);
$esCaja = in_array($_SESSION['rol'], FAC_ROLES_CAJA, true);

facEjecutar($conn, function () use ($conn, $esCaja) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $todos = $esCaja && ($_GET['todos'] ?? '') === '1';
        $sql = "SELECT id, nombre, categoria, precio, iva, destino, activo FROM carta_productos"
            . ($todos ? '' : ' WHERE activo = 1') . " ORDER BY FIELD(categoria, '" . implode("','", array_keys(FAC_CATEGORIAS)) . "'), nombre";
        $res = $conn->query($sql);
        $productos = [];
        while ($r = $res->fetch_assoc()) {
            $productos[] = [
                'id' => (int) $r['id'], 'nombre' => $r['nombre'], 'categoria' => $r['categoria'],
                'precio' => (float) $r['precio'], 'iva' => $r['iva'], 'destino' => $r['destino'], 'activo' => (bool) $r['activo'],
            ];
        }
        $respuesta = ['ok' => true, 'productos' => $productos, 'categorias' => FAC_CATEGORIAS, 'iva' => array_map(fn($t) => $t['label'], FAC_IVA), 'destinos' => FAC_DESTINOS];
        if ($esCaja) {
            $respuesta['ajustes'] = [
                'razon_social' => cfg('facturacion.razon_social'),
                'rut' => cfg('facturacion.rut'),
                'propina_sugerida' => cfg('facturacion.propina_sugerida'),
                'fondo_sugerido' => cfg('facturacion.fondo_sugerido'),
            ];
        }
        facJson($respuesta);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') facJson(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
    if (!$esCaja) facJson(['ok' => false, 'mensaje' => 'Acceso denegado.'], 403);

    $body = facCuerpo();
    $accion = (string) ($body['accion'] ?? '');

    if ($accion === 'guardar') {
        $id = (int) ($body['id'] ?? 0);
        $nombre = trim((string) ($body['nombre'] ?? ''));
        $categoria = (string) ($body['categoria'] ?? '');
        $iva = (string) ($body['iva'] ?? 'basica');
        $destino = (string) ($body['destino'] ?? 'cocina');
        $precio = $body['precio'] ?? null;
        $activo = !empty($body['activo']) ? 1 : 0;
        $errores = [];
        if ($nombre === '') $errores['nombre'] = 'El nombre es obligatorio.';
        elseif (mb_strlen($nombre) > 120) $errores['nombre'] = 'Máximo 120 caracteres.';
        if (!isset(FAC_CATEGORIAS[$categoria])) $errores['categoria'] = 'Elegí una categoría.';
        if (!isset(FAC_IVA[$iva])) $errores['iva'] = 'Elegí la tasa de IVA.';
        if (!isset(FAC_DESTINOS[$destino])) $errores['destino'] = 'Elegí a dónde va el pedido.';
        if ($precio === null || $precio === '' || !is_numeric($precio) || (float) $precio < 0 || (float) $precio > 1000000) $errores['precio'] = 'Ingresá un precio válido.';
        if ($errores) facJson(['ok' => false, 'mensaje' => 'Revisá los datos.', 'errores' => $errores], 422);
        $precio = round((float) $precio, 2);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE carta_productos SET nombre = ?, categoria = ?, precio = ?, iva = ?, destino = ?, activo = ? WHERE id = ?");
            $stmt->bind_param('ssdssii', $nombre, $categoria, $precio, $iva, $destino, $activo, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO carta_productos (nombre, categoria, precio, iva, destino, activo) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssdssi', $nombre, $categoria, $precio, $iva, $destino, $activo);
        }
        $stmt->execute();
        $stmt->close();
        // Los pedidos ya cargados conservan el precio con el que se pidieron.
        facJson(['ok' => true, 'mensaje' => $id > 0 ? 'Producto actualizado.' : 'Producto agregado a la carta.']);
    }

    if ($accion === 'eliminar') {
        $id = (int) ($body['id'] ?? 0);
        $stmt = $conn->prepare("SELECT 1 FROM cuenta_items WHERE producto_id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->store_result();
        $vendido = $stmt->num_rows > 0;
        $stmt->close();
        $stmt = $conn->prepare($vendido ? "UPDATE carta_productos SET activo = 0 WHERE id = ?" : "DELETE FROM carta_productos WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        facJson(['ok' => true, 'mensaje' => $vendido ? 'Ya tiene ventas: quedó pausado en lugar de borrarse.' : 'Producto eliminado.']);
    }

    if ($accion === 'ajustes') {
        $rut = preg_replace('/\D/', '', (string) ($body['rut'] ?? ''));
        $razon = mb_substr(trim((string) ($body['razon_social'] ?? '')), 0, 150);
        $propina = (int) ($body['propina_sugerida'] ?? 10);
        $fondo = (float) ($body['fondo_sugerido'] ?? 0);
        $errores = [];
        if ($rut !== '' && !facRutValido($rut)) $errores['rut'] = 'El RUT no es válido (12 dígitos con su dígito verificador).';
        if ($propina < 0 || $propina > 30) $errores['propina_sugerida'] = 'Entre 0 y 30 %.';
        if ($fondo < 0) $errores['fondo_sugerido'] = 'No puede ser negativo.';
        if ($errores) facJson(['ok' => false, 'mensaje' => 'Revisá los datos.', 'errores' => $errores], 422);
        [$ok] = cfgGuardar($conn, [
            'facturacion.rut' => $rut,
            'facturacion.razon_social' => $razon,
            'facturacion.propina_sugerida' => (string) $propina,
            'facturacion.fondo_sugerido' => (string) round($fondo, 2),
        ]);
        facJson(['ok' => $ok, 'mensaje' => $ok ? 'Datos de facturación guardados.' : 'No se pudieron guardar.'], $ok ? 200 : 500);
    }

    facJson(['ok' => false, 'mensaje' => 'Acción desconocida.'], 400);
});
