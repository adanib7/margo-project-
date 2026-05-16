<?php
define('BASE_URL', '/margo-project-');

$conn = new mysqli("localhost", "root", "", "margo-project-");
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
