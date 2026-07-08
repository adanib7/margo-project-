<?php
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($basePath === '') {
    $basePath = '';
}
define('BASE_URL', $basePath);
define('GOOGLE_CLIENT_ID', '191789640459-kt1mfd30prke6f6i97ttvm4tg58b57ka.apps.googleusercontent.com');

$conn = null;
$dbErrorMessage = '';

try {
    $conn = new mysqli("ftpupload.net", "if0_41994986", "hrXr99gspmS", "if0_41994986_margoproject");
    if ($conn->connect_error) {
        throw new RuntimeException($conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    $conn = null;
    $dbErrorMessage = 'No se pudo conectar con la base de datos remota. Verifica la configuración de conexión.';
}
