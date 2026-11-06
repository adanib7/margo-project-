CRUD PRUEBA SEMESTRAL DE PROGRAMACION


COMO USAR:

Para una instalacion nueva o una base sin tablas, seleccionar la base del sitio
en phpMyAdmin e importar `base de datos/instalacion_vacia.sql`. En XAMPP, crear o
seleccionar primero la base `margo-project-`.

Ese archivo crea las ocho tablas actuales, con telefono en reservas y soporte
para Google, verificaciones y recuperacion de contrasena. No incluye cuentas ni
reservas anteriores: reconstruir la estructura y restaurar una copia con datos
son operaciones diferentes. Si se encuentra una copia SQL con los datos del sitio,
usarla para la restauracion.

Despues de una instalacion vacia, cerrar la sesion anterior y registrar una cuenta propia (Google o correo
con verificacion habilitada). Para recuperar la administracion del proyecto,
editar en phpMyAdmin esa fila de `usuarios` y poner `superadmin` en su campo `rol`;
despues cerrar sesion y volver a entrar. Las mesas y la configuracion del local se
cargan desde sus pantallas. El modulo inventario agrega ejemplos si esta vacio.

El codigo actual usa la tabla `mesas` (plural) y la crea al abrir los modulos del plano o de reservas. `plano.sql` es un script antiguo para la tabla `mesa`; no corresponde al esquema actual. Las bases existentes pueden requerir los ajustes de esquema que comprueba el propio PHP.

Si una base antigua permite listar reservas pero falla al editarlas, comprobar
`SHOW COLUMNS FROM reservas;`. Si falta `telefono`, ejecutar una vez
`base de datos/reservas_telefono.sql` en esa base. El campo es opcional y las
reservas existentes quedan con telefono NULL. Las instalaciones nuevas ya lo
incluyen en `reservas.sql`.

UNA VEZ VISTA LA LANDING PAGE, HACER CLICK EN RESERVAR

Las credenciales del superadmin se entregan por privado (no se publican en el repositorio).

Dentro del panel: CRUD DE PLANO DE MESAS EN CARD DE PLANO DE MESAS

PUBLICAR EN EL HOSTING:

Cada push a main se sube solo por FTP (GitHub Actions). Hay dos archivos con
contraseñas que NO estan en el repositorio y se suben a mano una sola vez a
htdocs/includes/ en el hosting:

- includes/db_config.php   (copiar de includes/db_config.example.php)
- includes/mail_config.php (copiar de includes/mail_config.example.php)

Si se borraron archivos del hosting y hace falta subir de nuevo todo el sitio:

1. Guardar los cambios del proyecto y subirlos a la rama main de GitHub.
2. Abrir Actions -> Deploy a InfinityFree -> Run workflow.
3. Elegir main, dejar marcada la opcion "Volver a subir todos los archivos del sitio"
   y ejecutar Run workflow. Esperar a que termine correctamente.
4. Si tambien se borraron, restaurar a mano `htdocs/includes/db_config.php` y
   `htdocs/includes/mail_config.php` con la configuracion real del hosting.

La subida completa elimina solamente `.ftp-deploy-sync-state.json`, que registra
lo ya subido, para que la accion vuelva a transferir todos los archivos publicables.
Al terminar se crea un registro nuevo para los siguientes despliegues normales.
No se importa ni se restaura la base de datos mediante este workflow. Si tambien
se perdieron sus datos, hace falta recuperar una copia de seguridad de MySQL.

SITIO DE PRUEBAS:

Crear otra cuenta de alojamiento en InfinityFree, dentro del mismo perfil, con
su propio subdominio y base MySQL. Cargar la estructura actual y datos ficticios
en esa base. El sitio de pruebas usa sus propios archivos en /htdocs/.

El workflow `.github/workflows/deploy-pruebas.yml` publica los pushes de la rama
`pruebas`. Tambien admite ejecucion manual para publicar la rama seleccionada
cuando el archivo del workflow este disponible en la rama predeterminada de GitHub.
En GitHub -> Settings -> Secrets and variables -> Actions, agregar las credenciales
FTP de la nueva cuenta con estos nombres:

- PRUEBAS_FTP_SERVER
- PRUEBAS_FTP_USERNAME
- PRUEBAS_FTP_PASSWORD

En el hosting de pruebas, subir a mano `includes/db_config.php` con los datos de
su base de pruebas. En Configuracion -> Direccion web, guardar el dominio de pruebas.
Para las pruebas sin correo, usar `includes/mail_config.php` basado en el ejemplo,
con `enabled` en false. En ese modo no se puede completar el registro por codigo;
para probarlo, configurar el correo y usar cuentas de prueba del equipo.

Para probar el acceso con Google, autorizar el origen HTTPS del nuevo sitio y su
URL `/includes/google_auth.php` en el cliente OAuth de Google.

Crear la rama local con `git switch -c pruebas` y publicarla con
`git push -u origin pruebas`. Trabajar y hacer push en esa rama publica en el sitio
de pruebas. Los pushes a `main` siguen publicando en el sitio principal.


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

SESIONES:

Las sesiones vencen tras 30 minutos sin actividad o a las 8 horas del acceso.
Los tiempos se cambian en `includes/sesion.php`. Los avisos automaticos de reservas
y las consultas de estado no renuevan la actividad. Las pantallas protegidas
comprueban el estado y notifican actividad real; las API rechazan sesiones vencidas
con HTTP 401. El logout elimina los datos del servidor y la cookie de acceso.
Al publicar este cambio, las sesiones anteriores sin marcas de tiempo requieren
volver a iniciar sesion. Cerrar una pestana no equivale a cerrar la sesion de todas
las pestanas; el vencimiento del servidor funciona aunque el navegador restaure cookies.

Prueba sin MySQL: `C:\xampp\php\php.exe tests/sesion.php`.

EDITOR DE MESAS:

El editor administrativo usa Konva 9.3.22, incluido en `assets/js/konva.min.js`
con su licencia en `assets/js/konva.LICENSE.txt`. Subir ambos archivos junto al
editor al publicar. No requiere instalar paquetes ni cargar Konva desde un CDN.
El asistente muestra Mesa, Distribuir y Guardar plano. Seleccionar una mesa abre
Mover, Girar y Editar; en celular la ficha aparece abajo. Movimiento y distribucion
tienen vista previa y se pueden cancelar. Deshacer conserva hasta 30 cambios antes
de guardar. Ajuste fino mantiene flechas, arrastre y manijas sin ocupar la vista inicial.
Se admiten mesas cuadradas, redondas y triangulares y se conserva el plano del cliente.
`assets/js/plano_distribucion.js` propone filas o centro libre; es codigo propio.
Al publicar esta version, subir juntos `dashboards/admin_plano.php`,
`assets/js/admin_plano.js`, `assets/js/plano_distribucion.js` y `assets/css/dashboard.css`.
El POST devuelve las mesas con sus ID reales y guarda el lote en una transaccion.
