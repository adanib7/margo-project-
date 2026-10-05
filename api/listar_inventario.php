<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/inventario_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

if (!isset($conn) || $conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $dbErrorMessage ?: 'Sin conexión a la base de datos.']);
    exit;
}

ensureInventarioTable($conn);

$search    = trim($_GET['q'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$soloBajo  = ($_GET['bajo'] ?? '') === '1';

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(nombre LIKE ? OR proveedor LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if (array_key_exists($categoria, INV_CATEGORIAS)) {
    $where[]  = 'categoria = ?';
    $params[] = $categoria;
    $types   .= 's';
}

if ($soloBajo) {
    $where[] = 'stock <= stock_minimo';
}

$sql = 'SELECT id, nombre, categoria, unidad, stock, stock_minimo, precio_unitario, proveedor, actualizado_en FROM inventario';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY nombre ASC';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$items = array_map(static function (array $f): array {
    return [
        'id'              => (int) $f['id'],
        'nombre'          => $f['nombre'],
        'categoria'       => $f['categoria'],
        'unidad'          => $f['unidad'],
        'stock'           => (float) $f['stock'],
        'stock_minimo'    => (float) $f['stock_minimo'],
        'precio_unitario' => (float) $f['precio_unitario'],
        'proveedor'       => $f['proveedor'],
        'actualizado_en'  => $f['actualizado_en'],
        'bajo_stock'      => (float) $f['stock'] <= (float) $f['stock_minimo'],
    ];
}, $filas);

// Totales globales (sobre toda la tabla, sin filtros).
$totales = ['articulos' => 0, 'bajo_stock' => 0, 'valor_total' => 0.0];
$res = $conn->query("
    SELECT
        COUNT(*)                              AS articulos,
        SUM(stock <= stock_minimo)            AS bajo_stock,
        COALESCE(SUM(stock * precio_unitario), 0) AS valor_total
    FROM inventario
");
if ($res && ($row = $res->fetch_assoc())) {
    $totales['articulos']   = (int) $row['articulos'];
    $totales['bajo_stock']  = (int) $row['bajo_stock'];
    $totales['valor_total'] = (float) $row['valor_total'];
}

echo json_encode([
    'ok'         => true,
    'items'      => $items,
    'totales'    => $totales,
    'categorias' => INV_CATEGORIAS,
    'unidades'   => INV_UNIDADES,
]);
