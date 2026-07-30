# CRUD – Prueba Semestral de Programación

Sistema de gestión para un restaurante, desarrollado en PHP + MySQL. Incluye un CRUD para armar y editar de forma visual el plano de mesas del local (agregar, mover, redimensionar, rotar y eliminar mesas).

## Tecnologías usadas en el crud

- PHP 
- MySQL
- HTML / CSS / JavaScript (sin frameworks ni librerías externas)

## Acceso

El proyecto ya está desplegado en hosting (InfinityFree), no requiere instalación local.

 **URL:** `[corralin.kesug.com]`

## Cómo usar

1. Entrar al link de arriba. Desde la landing page, hacer clic en **Reservar** para iniciar sesión.
2. Ingresar con las credenciales de Super Admin:

   | Usuario  | Contraseña     |
   |----------|----------------|
   | PruebaSU | Margoproject1  |

3. Dentro del panel, entrar a la card **Plano de mesas** para acceder al CRUD.

## Archivo usado

- `dashboards/admin_plano.php` — CRUD del plano de mesas. Permite crear, editar, mover, redimensionar, rotar y eliminar mesas (número, capacidad, forma, posición y rotación), con guardado en base de datos.