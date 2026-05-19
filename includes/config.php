<?php
define('BASE_URL', '/margo-project-');
define('GOOGLE_CLIENT_ID', '191789640459-kt1mfd30prke6f6i97ttvm4tg58b57ka.apps.googleusercontent.com');

$conn = new mysqli("localhost", "root", "", "margo-project-");
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
