<?php
/**
 * Esquema y catálogos compartidos del módulo de Inventario.
 *
 * Igual que el plano de mesas, la tabla se crea sola la primera vez que se
 * accede (útil en XAMPP local, donde la base se genera vacía). En el hosting
 * remoto la tabla ya existe pero el CREATE IF NOT EXISTS no molesta.
 */

const INV_CATEGORIAS = [
    'bebida'    => 'Bebida',
    'carne'     => 'Carne',
    'pescado'   => 'Pescado',
    'verdura'   => 'Verdura',
    'fruta'     => 'Fruta',
    'lacteo'    => 'Lácteo',
    'seco'      => 'Despensa',
    'congelado' => 'Congelado',
    'limpieza'  => 'Limpieza',
    'general'   => 'General',
];

const INV_UNIDADES = [
    'kg'      => 'kg',
    'g'       => 'g',
    'l'       => 'L',
    'ml'      => 'ml',
    'unidad'  => 'unidad',
    'botella' => 'botella',
    'caja'    => 'caja',
    'docena'  => 'docena',
];

/**
 * Valida y normaliza el cuerpo de un alta/edición de artículo.
 *
 * @return array{0: array<string,string>, 1: array<string,mixed>}  [errores, datos]
 */
function validarItemInventario(array $body): array
{
    $errores = [];

    $nombre    = trim((string) ($body['nombre'] ?? ''));
    $categoria = trim((string) ($body['categoria'] ?? 'general'));
    $unidad    = trim((string) ($body['unidad'] ?? 'unidad'));
    $proveedor = trim((string) ($body['proveedor'] ?? ''));

    $stock       = $body['stock'] ?? null;
    $stockMin    = $body['stock_minimo'] ?? null;
    $precio      = $body['precio_unitario'] ?? null;

    if ($nombre === '') {
        $errores['nombre'] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($nombre) > 120) {
        $errores['nombre'] = 'Máximo 120 caracteres.';
    }

    if (!array_key_exists($categoria, INV_CATEGORIAS)) {
        $errores['categoria'] = 'Seleccioná una categoría válida.';
    }

    if (!array_key_exists($unidad, INV_UNIDADES)) {
        $errores['unidad'] = 'Seleccioná una unidad válida.';
    }

    if (mb_strlen($proveedor) > 120) {
        $errores['proveedor'] = 'Máximo 120 caracteres.';
    }

    foreach (['stock' => $stock, 'stock_minimo' => $stockMin, 'precio_unitario' => $precio] as $campo => $valor) {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            $errores[$campo] = 'Ingresá un número válido.';
        } elseif ((float) $valor < 0) {
            $errores[$campo] = 'No puede ser negativo.';
        }
    }

    $datos = [
        'nombre'          => $nombre,
        'categoria'       => $categoria,
        'unidad'          => $unidad,
        'proveedor'       => $proveedor !== '' ? $proveedor : null,
        'stock'           => round((float) $stock, 2),
        'stock_minimo'    => round((float) $stockMin, 2),
        'precio_unitario' => round((float) $precio, 2),
    ];

    return [$errores, $datos];
}

/**
 * Crea la tabla `inventario` si no existe y agrega columnas que falten.
 */
function ensureInventarioTable(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS inventario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            categoria VARCHAR(30) NOT NULL DEFAULT 'general',
            unidad VARCHAR(20) NOT NULL DEFAULT 'unidad',
            stock DECIMAL(10,2) NOT NULL DEFAULT 0,
            stock_minimo DECIMAL(10,2) NOT NULL DEFAULT 0,
            precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
            proveedor VARCHAR(120) DEFAULT NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $columnas = [
        ['nombre',          'VARCHAR(120) NOT NULL'],
        ['categoria',       "VARCHAR(30) NOT NULL DEFAULT 'general'"],
        ['unidad',          "VARCHAR(20) NOT NULL DEFAULT 'unidad'"],
        ['stock',           'DECIMAL(10,2) NOT NULL DEFAULT 0'],
        ['stock_minimo',    'DECIMAL(10,2) NOT NULL DEFAULT 0'],
        ['precio_unitario', 'DECIMAL(10,2) NOT NULL DEFAULT 0'],
        ['proveedor',       'VARCHAR(120) DEFAULT NULL'],
        ['creado_en',       'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['actualizado_en',  'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
    ];

    foreach ($columnas as [$col, $definicion]) {
        $stmt = $conn->prepare("SHOW COLUMNS FROM inventario LIKE ?");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $col);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE inventario ADD COLUMN {$col} {$definicion}");
        }
        $stmt->close();
    }

    // Reparar la clave primaria si `id` quedó sin AUTO_INCREMENT (pasa cuando se
    // importa el .sql a medias): sin esto cada alta intenta insertar id = 0 y la
    // segunda falla con "Duplicate entry '0' for key 'PRIMARY'".
    $idInfo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'id'");
    $idCol  = $idInfo ? $idInfo->fetch_assoc() : null;
    if ($idCol && stripos((string) ($idCol['Extra'] ?? ''), 'auto_increment') === false) {
        $pk = $conn->query("SHOW KEYS FROM inventario WHERE Key_name = 'PRIMARY'");
        if ($pk && $pk->num_rows === 0) {
            $conn->query("ALTER TABLE inventario ADD PRIMARY KEY (id)");
        }
        // Mover cualquier fila que ya haya quedado con id = 0 al final de la secuencia.
        $conn->query("UPDATE inventario SET id = (SELECT n FROM (SELECT COALESCE(MAX(id),0)+1 AS n FROM inventario) t) WHERE id = 0");
        $conn->query("ALTER TABLE inventario MODIFY `id` INT NOT NULL AUTO_INCREMENT");
    }

    // Semilla: solo si la tabla está vacía, para que el CRUD no arranque en blanco.
    $vacia = $conn->query("SELECT 1 FROM inventario LIMIT 1");
    if ($vacia && $vacia->num_rows === 0) {
        $conn->query("
            INSERT INTO inventario (nombre, categoria, unidad, stock, stock_minimo, precio_unitario, proveedor) VALUES
            ('Faba de la Granja',        'seco',     'kg',      18.00, 8.00,  6.50,  'Legumbres del Nalón'),
            ('Ternera asturiana',        'carne',    'kg',      12.50, 6.00,  14.90, 'Carnicería Cué'),
            ('Queso Afuega''l Pitu',     'lacteo',   'kg',      3.20,  2.00,  19.00, 'Quesería La Peral'),
            ('Sidra natural DOP',        'bebida',   'botella', 96.00, 48.00, 3.20,  'Llagar Trabanco'),
            ('Chorizo asturiano',        'carne',    'kg',      5.00,  4.00,  11.50, 'Embutidos Nava'),
            ('Cebolla',                  'verdura',  'kg',      22.00, 10.00, 1.10,  'Huerta El Sueve'),
            ('Aceite de oliva virgen',   'seco',     'l',       14.00, 6.00,  7.80,  'Distribuciones Uría'),
            ('Detergente lavavajillas',  'limpieza', 'caja',    2.00,  3.00,  24.00, 'Higiene Pro')
        ");
    }
}
