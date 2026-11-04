<?php
// El local está en Nava (Asturias): fijamos la zona horaria para que todas las
// páginas comparen fechas y horas contra el mismo reloj. Sin esto, el servidor
// (que suele estar en UTC) puede adelantar el día y rechazar reservas válidas.
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Madrid');
}

require_once __DIR__ . '/rutas.php';
// Funciona tanto en el dominio raíz como en /margo-project- de XAMPP.
define('BASE_URL', baseDesdeScript($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
define('GOOGLE_CLIENT_ID', '191789640459-kt1mfd30prke6f6i97ttvm4tg58b57ka.apps.googleusercontent.com');

// Función auxiliar para construir URLs seguras
function buildUrl(string $path, bool $absolute = false): string {
    $base = rtrim(BASE_URL, '/');
    // Asegurar que el path comience con / y no tenga barras duplicadas
    $path = rutaPublica($path);

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

// URL de un archivo de assets/js. El ?v= cambia cada vez que se edita el
// archivo, así el navegador no se queda con una versión vieja en caché.
function jsUrl(string $file): string {
    $path = __DIR__ . '/../assets/js/' . $file;
    $version = is_file($path) ? filemtime($path) : time();
    return buildUrl('/assets/js/' . $file) . '?v=' . $version;
}

require_once __DIR__ . '/sesion.php';
comprobarSesionRequest();

// El control de sesión no necesita conexión a MySQL.
if (str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', '/api/sesion.php')) { return; }

// Los enlaces guardados con .php siguen funcionando. No redirigir POST:
// se conserva el cuerpo de formularios y de las operaciones del plano.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)
    && is_string($requestPath) && str_starts_with($requestPath, BASE_URL . '/')) {
    $rutaAnterior = substr($requestPath, strlen(BASE_URL));
    if (isset(rutasPantallas()[$rutaAnterior])) {
        $consulta = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . buildUrl($rutaAnterior) . ($consulta !== '' ? '?' . $consulta : ''), true, 302);
        exit;
    }
}

$conn = null;
$dbErrorMessage = '';

// Evita que mysqli emita warnings nativos (rompen las respuestas JSON de la API);
// el connect_error se sigue chequeando manualmente abajo.
mysqli_report(MYSQLI_REPORT_OFF);

// Corriendo en XAMPP local (localhost/127.0.0.1) -> usar MySQL local en vez del
// hosting remoto, para poder probar el proyecto sin depender de internet.
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$esLocal = (bool) preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $host);

if ($esLocal) {
    $dbLocal = 'margo-project-';
    try {
        $conn = @new mysqli('localhost', 'root', '', '');
        if ($conn->connect_error) {
            throw new RuntimeException($conn->connect_error);
        }
        // Crea la base de datos local si todavía no existe (primera vez en XAMPP).
        $conn->query("CREATE DATABASE IF NOT EXISTS `{$dbLocal}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $conn->select_db($dbLocal);
        $conn->set_charset('utf8mb4');
    } catch (Throwable $e) {
        $conn = null;
        $dbErrorMessage = 'No se pudo conectar con MySQL local. Verifica que el servicio MySQL de XAMPP esté iniciado.';
    }
} else {
    try {
        // Los datos de acceso viven en includes/db_config.php, que NO se sube a
        // GitHub (.gitignore). En el hosting se sube a mano una sola vez.
        $dbFile = __DIR__ . '/db_config.php';
        if (!is_file($dbFile)) {
            throw new RuntimeException('Falta includes/db_config.php');
        }
        $db = require $dbFile;

        // @: la falla de red ya se detecta manualmente vía connect_error; sin esto
        // PHP emite un warning nativo que corrompe las respuestas JSON de la API.
        $conn = @new mysqli($db['host'], $db['usuario'], $db['password'], $db['base']);
        if ($conn->connect_error) {
            throw new RuntimeException($conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
    } catch (Throwable $e) {
        $conn = null;
        $dbErrorMessage = 'No se pudo conectar con la base de datos remota. Verifica la configuración de conexión.';
    }
}

// Configuración editable del local (nombre, dirección, horarios, reglas…).
// Va al final porque lee de $conn, y se carga siempre para que cfg() esté
// disponible en cualquier página, incluidos nav.php y footer.php.
require_once __DIR__ . '/config_app.php';
