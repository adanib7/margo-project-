<?php
// Detectar BASE_URL de forma simple y confiable
$scriptPath = $_SERVER['SCRIPT_NAME'];
$scriptDir = dirname($scriptPath);

// Eliminar caracteres de ruta no deseados y normalizar
$basePath = rtrim($scriptDir, '/');

// Si la ruta termina en /includes o /dashboards, subir un nivel
if (preg_match('/(\/includes|\/dashboards)$/', $basePath)) {
    $basePath = dirname($basePath);
}

// Si está vacío después de todo, es raíz
if (empty($basePath) || $basePath === '.') {
    $basePath = '';
}

define('BASE_URL', $basePath);
define('GOOGLE_CLIENT_ID', '191789640459-kt1mfd30prke6f6i97ttvm4tg58b57ka.apps.googleusercontent.com');

// Función auxiliar para construir URLs seguras
function buildUrl(string $path): string {
    $base = BASE_URL;
    // Asegurar que el path comience con /
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    return $base . $path;
}

$conn = null;
$dbErrorMessage = '';

try {
    $conn = new mysqli("sql213.infinityfree.com", "if0_41994986", "hrXr99gspmS", "if0_41994986_margoproject");
    if ($conn->connect_error) {
        throw new RuntimeException($conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    $conn = null;
    $dbErrorMessage = 'No se pudo conectar con la base de datos remota. Verifica la configuración de conexión.';
}
