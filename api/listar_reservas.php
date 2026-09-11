<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/check_auth.php';
require_once '../includes/plano_db.php';

header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
    exit;
}

if ($conn === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => $dbErrorMessage ?: 'Sin conexión a la base de datos.']);
    exit;
}

$search = trim($_GET['q'] ?? '');
$estado = trim($_GET['estado'] ?? '');
$rango  = trim($_GET['rango'] ?? '');

$conMesa = planoLigadoAReservas($conn);
$hoy     = date('Y-m-d');

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(r.nombre LIKE ? OR r.codigo LIKE ? OR r.telefono LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}

if (in_array($estado, ['pendiente', 'confirmada', 'cancelada'], true)) {
    $where[]  = 'r.estado = ?';
    $params[] = $estado;
    $types   .= 's';
}

switch ($rango) {
    case 'hoy':
        $where[] = 'r.fecha = ?';      $params[] = $hoy;                                  $types .= 's'; break;
    case 'manana':
        $where[] = 'r.fecha = ?';      $params[] = date('Y-m-d', strtotime('+1 day'));    $types .= 's'; break;
    case 'semana':
        $where[] = 'r.fecha BETWEEN ? AND ?';
        $params[] = $hoy; $params[] = date('Y-m-d', strtotime('+7 days'));                $types .= 'ss'; break;
    case 'proximas':
        $where[] = 'r.fecha >= ?';     $params[] = $hoy;                                  $types .= 's'; break;
    case 'pasadas':
        $where[] = 'r.fecha < ?';      $params[] = $hoy;                                  $types .= 's'; break;
}

// El JOIN con `mesas` solo si el plano está montado en este servidor.
$select = $conMesa
    ? "SELECT r.*, m.numero AS mesa_numero, m.capacidad AS mesa_capacidad, u.email AS usuario_email
       FROM reservas r
       LEFT JOIN mesas m ON m.id = r.mesa_id
       LEFT JOIN usuarios u ON u.id = r.usuario_id"
    : "SELECT r.*, NULL AS mesa_numero, NULL AS mesa_capacidad, u.email AS usuario_email
       FROM reservas r
       LEFT JOIN usuarios u ON u.id = r.usuario_id";

$sql = $select;
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
// Las pasadas de la más reciente hacia atrás; el resto, de la más próxima en adelante.
$orden = ($rango === 'pasadas' || $rango === '') ? 'DESC' : 'ASC';
$sql .= " ORDER BY r.fecha {$orden}, r.hora ASC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo consultar reservas: ' . $conn->error]);
    exit;
}
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$reservas = array_map(static function (array $f): array {
    return [
        'id'         => (int) $f['id'],
        'codigo'     => $f['codigo'],
        'nombre'     => $f['nombre'],
        'email'      => $f['usuario_email'] ?? '',
        'telefono'   => $f['telefono'] ?? '',
        'fecha'      => $f['fecha'],
        'hora'       => substr((string) $f['hora'], 0, 5),
        'personas'   => (int) $f['personas'],
        'comentario' => $f['comentario'] ?? '',
        'estado'     => $f['estado'],
        'mesa_id'    => isset($f['mesa_id']) ? (int) $f['mesa_id'] : 0,
        'mesa'       => $f['mesa_numero'] !== null ? (int) $f['mesa_numero'] : null,
        'mesa_cap'   => $f['mesa_capacidad'] !== null ? (int) $f['mesa_capacidad'] : null,
        'creada'     => $f['fecha_creacion'] ?? null,
    ];
}, $filas);

// Totales globales (sin filtros), para las tarjetas de arriba.
$totales = ['hoy' => 0, 'pendientes' => 0, 'confirmadas' => 0, 'canceladas' => 0, 'comensales_hoy' => 0];

$q = $conn->prepare("SELECT estado, COUNT(*) AS n FROM reservas GROUP BY estado");
if ($q) {
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) {
        $k = $row['estado'] . 's';
        if (isset($totales[$k])) {
            $totales[$k] = (int) $row['n'];
        }
    }
    $q->close();
}

$q = $conn->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(personas),0) AS p FROM reservas WHERE fecha = ? AND estado != 'cancelada'");
if ($q) {
    $q->bind_param('s', $hoy);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    $totales['hoy']            = (int) ($row['n'] ?? 0);
    $totales['comensales_hoy'] = (int) ($row['p'] ?? 0);
}

// Mesas disponibles para el selector del modal de edición.
$mesas = [];
if ($conMesa) {
    $res = $conn->query("SELECT id, numero, capacidad FROM mesas ORDER BY numero ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $mesas[] = [
                'id'        => (int) $row['id'],
                'numero'    => (int) $row['numero'],
                'capacidad' => (int) $row['capacidad'],
            ];
        }
    }
}

echo json_encode([
    'ok'       => true,
    'reservas' => $reservas,
    'totales'  => $totales,
    'mesas'    => $mesas,
    'hay_plano' => $conMesa,
], JSON_UNESCAPED_UNICODE);
