<?php
// Detectar BASE_URL de forma simple y confiable
$scriptPath = $_SERVER['SCRIPT_NAME'];
// str_replace normaliza el separador '\' que dirname() devuelve en Windows (dev local)
$scriptDir = str_replace('\\', '/', dirname($scriptPath));

// Eliminar caracteres de ruta no deseados y normalizar
$basePath = rtrim($scriptDir, '/');

// Si la ruta termina en /includes, /dashboards, /api o /public, subir un nivel
if (preg_match('/(\/includes|\/dashboards|\/api|\/public)$/', $basePath)) {
    $basePath = str_replace('\\', '/', dirname($basePath));
}

// Si está vacío o es la raíz ("/", ".") después de todo, es raíz
if (empty($basePath) || $basePath === '.' || $basePath === '/') {
    $basePath = '';
}

define('BASE_URL', $basePath);
define('GOOGLE_CLIENT_ID', '191789640459-kt1mfd30prke6f6i97ttvm4tg58b57ka.apps.googleusercontent.com');

// Función auxiliar para construir URLs seguras
function buildUrl(string $path, bool $absolute = false): string {
    $base = rtrim(BASE_URL, '/');
    // Asegurar que el path comience con / y no tenga barras duplicadas
    $path = '/' . ltrim($path, '/');

    $url = $base . $path;
    
    // Si se necesita URL absoluta (para redirects), agregar protocolo y host
    if ($absolute) {
        // Detectar protocolo (HTTPS o HTTP)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        
        // Obtener host de forma segura
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        
        // Validar que no sea un string vacío o inválido
        if (empty($host)) {
            $host = 'localhost';
        }
        
        // Construir URL absoluta completa
        $url = $protocol . rtrim($host, '/') . $url;
    }
    
    return $url;
}

$conn = null;
$dbErrorMessage = '';

// Evita que mysqli emita warnings nativos (rompen las respuestas JSON de la API);
// el connect_error se sigue chequeando manualmente abajo.
mysqli_report(MYSQLI_REPORT_OFF);

try {
    // @: la falla de red ya se detecta manualmente vía connect_error; sin esto
    // PHP emite un warning nativo que corrompe las respuestas JSON de la API.
    $conn = @new mysqli("sql213.infinityfree.com", "if0_41994986", "hrXr99gspmS", "if0_41994986_margoproject");
    if ($conn->connect_error) {
        throw new RuntimeException($conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    $conn = null;
    $dbErrorMessage = 'No se pudo conectar con la base de datos remota. Verifica la configuración de conexión.';
}
