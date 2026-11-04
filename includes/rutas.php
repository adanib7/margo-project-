<?php
// Los archivos conservan su ubicación; estos son sus nombres públicos.
function rutasPantallas(): array
{
    return [
        '/index.php' => '/',
        '/public/login.php' => '/ingresar',
        '/dashboards/user.php' => '/panel',
        '/dashboards/admin.php' => '/admin',
        '/dashboards/superadmin.php' => '/superadmin',
        '/dashboards/gestion-usuarios.php' => '/usuarios',
        '/dashboards/reservas.php' => '/reservas',
        '/dashboards/admin_plano.php' => '/plano',
        '/dashboards/inventario.php' => '/inventario',
        '/dashboards/reportes.php' => '/reportes',
        '/dashboards/configuracion.php' => '/configuracion',
        '/dashboards/mi_perfil.php' => '/mi-perfil',
        '/dashboards/mis-reservas.php' => '/mis-reservas',
    ];
}

function baseDesdeScript(string $script): string
{
    $script = rtrim(str_replace('\\', '/', $script), '/');
    foreach (rutasPantallas() as $archivo => $ruta) {
        foreach ([$archivo, $ruta] as $final) {
            if ($final !== '/' && str_ends_with($script, $final)) {
                return substr($script, 0, -strlen($final));
            }
        }
    }
    // Las API y los callbacks conservan sus direcciones y no son pantallas.
    $base = str_replace('\\', '/', dirname($script));
    if (preg_match('~/(includes|dashboards|api|public)$~', $base)) {
        $base = str_replace('\\', '/', dirname($base));
    }
    return in_array($base, ['/', '.', ''], true) ? '' : rtrim($base, '/');
}

function rutaPublica(string $path): string
{
    $path = '/' . ltrim($path, '/');
    // Conservar consultas y anclas: ?modo=registro, ?logout=1, etc.
    $posicion = strcspn($path, '?#');
    $archivo = substr($path, 0, $posicion);
    $extra = substr($path, $posicion);
    return (rutasPantallas()[$archivo] ?? $archivo) . $extra;
}
