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
