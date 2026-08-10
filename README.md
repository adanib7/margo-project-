# CRUD – Prueba Semestral de Programación

Sistema de gestión para un restaurante, desarrollado en PHP + MySQL. Incluye un CRUD para armar y editar de forma visual el plano de mesas del local (agregar, mover, redimensionar, rotar y eliminar mesas).

## Tecnologías usadas en el crud

- PHP 
- MySQL
- HTML / CSS / JavaScript (sin frameworks ni librerías externas)

## Acceso

El proyecto está desplegado en hosting (InfinityFree) y también puede correrse en local con XAMPP; el mismo código detecta automáticamente en qué entorno está y elige la base de datos correspondiente (no hay que tocar nada a mano).

 **URL online:** `corralin.kesug.com`

### Opción A — Online (sin instalar nada)

1. Entrar a `corralin.kesug.com`. Desde la landing page, hacer clic en **Reservar** para iniciar sesión.
2. Ingresar con las credenciales de Super Admin:

   | Usuario  | Contraseña     |
   |----------|----------------|
   | PruebaSU | Margoproject1  |

3. Dentro del panel, entrar a la card **Plano de mesas** para acceder al CRUD.

### Opción B — Local con XAMPP

1. Descomprimir este zip dentro de `C:\xampp\htdocs\` (la carpeta puede llamarse `margo-project-`).
2. Abrir el Panel de Control de XAMPP e iniciar **Apache** y **MySQL**.
3. Ir al navegador a `http://localhost/margo-project-/dashboards/admin_plano.php` para ver el CRUD del plano de mesas directamente (no pide login).
4. La base de datos local (`margo-project-`) y la tabla de mesas se crean solas la primera vez que se accede al sitio, no hace falta importar nada para ver el CRUD del plano.
5. (Opcional) Para probar también el login y los dashboards, importar `base de datos/margo-project-.sql` desde phpMyAdmin (`http://localhost/phpmyadmin`) en la base `margo-project-`, y después entrar a `http://localhost/margo-project-/public/login.php` con las credenciales de la tabla de arriba.

## Archivo usado

- `dashboards/admin_plano.php` — CRUD del plano de mesas. Permite crear, editar, mover, redimensionar, rotar y eliminar mesas (número, capacidad, forma, posición y rotación), con guardado en base de datos.