CRUD PRUEBA SEMESTRAL DE PROGRAMACION


COMO USAR:

En XAMPP, crear o seleccionar la base `margo-project-` e importar `base de datos/margo-project-.sql` y luego `base de datos/reservas.sql` en una instalacion nueva.

El codigo actual usa la tabla `mesas` (plural) y la crea al abrir los modulos del plano o de reservas. `plano.sql` es un script antiguo para la tabla `mesa`; no corresponde al esquema actual. Las bases existentes pueden requerir los ajustes de esquema que comprueba el propio PHP.

UNA VEZ VISTA LA LANDING PAGE, HACER CLICK EN RESERVAR

Las credenciales del superadmin se entregan por privado (no se publican en el repositorio).

Dentro del panel: CRUD DE PLANO DE MESAS EN CARD DE PLANO DE MESAS

PUBLICAR EN EL HOSTING:

Cada push a main se sube solo por FTP (GitHub Actions). Hay dos archivos con
contraseñas que NO estan en el repositorio y se suben a mano una sola vez a
htdocs/includes/ en el hosting:

- includes/db_config.php   (copiar de includes/db_config.example.php)
- includes/mail_config.php (copiar de includes/mail_config.example.php)


Guia para estudiar la programacion y preparar la defensa: [documentacion/guia-defensa-programacion.md](documentacion/guia-defensa-programacion.md).

VERIFICACION DE NUEVOS REGISTROS:

El registro con usuario y contrasena envia un codigo de seis digitos mediante Brevo. La cuenta se
crea en `usuarios` solo al confirmar el codigo. Requiere el correo habilitado en
`includes/mail_config.php`; si falla, el registro no se habilita.

Las tablas `registro_pendientes` y `registro_limites` se preparan automaticamente.
En un hosting sin permisos de creacion, importar antes
`base de datos/registro_verificacion.sql` en la base existente. No modifica cuentas
actuales. El despliegue FTP no importa este SQL automaticamente.

El codigo vence en 15 minutos, admite cinco intentos y puede reenviarse tras
60 segundos, con hasta cinco envios por correo por hora. Hay un limite adicional
de diez solicitudes de envio y treinta confirmaciones por IP por hora. Los
pendientes de mas de 24 horas se limpian al procesar otro registro.

Google permite entrar y crear cuentas directamente, sin un codigo adicional del
sitio. Se conserva la validacion del token de Google. La vinculacion automatica a
una cuenta local solo se permite cuando Google administra el correo (Gmail o Workspace).
Las cuentas creadas por el superadministrador mantienen su flujo administrativo.

Prueba de integracion: `C:\xampp\php\php.exe tests/registro.php` con una instancia
MySQL local de pruebas en el puerto 3317 (o la variable `TEST_MYSQL_PORT`). Crea y
elimina una base temporal, simula el correo y no usa la base del restaurante.

URLS AMIGABLES:

Las pantallas usan `/ingresar`, `/panel`, `/admin`, `/superadmin`, `/usuarios`,
`/reservas`, `/plano`, `/inventario`, `/reportes`, `/configuracion`, `/mi-perfil`
y `/mis-reservas`. En XAMPP se agrega delante `/margo-project-`, por ejemplo:
`http://localhost/margo-project-/usuarios`.

Subir tambien `.htaccess` e `includes/rutas.php` al publicar estos cambios.
Apache debe tener `mod_rewrite` y permitir las reglas de `.htaccess`.
Las direcciones anteriores redirigen a las nuevas conservando los parametros en
GET/HEAD; los POST antiguos siguen procesandose sin perder los datos enviados.
Las API, los comprobantes y el callback de Google mantienen sus direcciones.

Prueba de correspondencia de rutas: `C:\xampp\php\php.exe tests/rutas.php`.
