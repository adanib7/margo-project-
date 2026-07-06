<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

$search = trim($_GET['q']   ?? '');
$rol    = trim($_GET['rol'] ?? '');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(nombre LIKE ? OR email LIKE ?)';
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if (in_array($rol, ['usuario', 'admin', 'superadmin'], true)) {
    $where[]  = 'rol = ?';
    $params[] = $rol;
    $types   .= 's';
}

$sql = 'SELECT id, nombre, email, rol, fecha_registro FROM usuarios';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY fecha_registro DESC';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Totales por rol (siempre sobre toda la tabla, sin filtro)
$totales = ['usuario' => 0, 'admin' => 0, 'superadmin' => 0];
$res = $conn->query("SELECT rol, COUNT(*) AS total FROM usuarios GROUP BY rol");
while ($row = $res->fetch_assoc()) {
    if (isset($totales[$row['rol']])) {
        $totales[$row['rol']] = (int)$row['total'];
    }
}

echo json_encode(['ok' => true, 'usuarios' => $usuarios, 'totales' => $totales]);
