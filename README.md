# Margo Project

Este proyecto es una aplicación web para gestionar un restaurante o local gastronómico, con funcionalidades de autenticación, reservas de mesas, administración de usuarios y edición del plano de mesas. Está pensado para que tanto clientes como administradores puedan interactuar con el sistema desde una interfaz sencilla.

## ¿Qué hace el proyecto?

En una frase: es una plataforma web que permite reservar mesas, gestionar usuarios y controlar el espacio físico del local desde un panel administrativo.

Incluye lo siguiente:

- Una landing page o página inicial con información del restaurante.
- Registro e inicio de sesión de usuarios.
- Diferentes tipos de usuarios: usuario normal, administrador y superadmin.
- Reservas de mesas con fecha y hora.
- Gestión del plano de mesas para ver qué mesas están ocupadas o disponibles.
- Paneles administrativos para controlar usuarios, reservas y distribución del local.
- Exportación de reservas en formato ICS para calendario.
- Integración con Google Sign-In.

## Tecnologías que usa

El proyecto está desarrollado principalmente con:

- PHP para la lógica del servidor y las páginas dinámicas.
- MySQL/MariaDB como base de datos.
- HTML, CSS y JavaScript para la interfaz.
- Tailwind CSS mediante CDN para estilos modernos.
- Konva.js para la edición visual del plano de mesas.
- Google API para el inicio de sesión con Google.

## Estructura del proyecto

La organización del proyecto es la siguiente:

- api/: archivos PHP que manejan operaciones como crear reservas, usuarios, admins, editar o eliminar datos.
- dashboards/: páginas del panel interno para cada tipo de usuario.
- public/: páginas públicas y accesibles sin entrar al panel administrativo.
- includes/: archivos compartidos como conexión a base de datos, autenticación, header, footer y navegación.
- assets/: archivos de estilos, imágenes y scripts usados por la interfaz.
- base de datos/: scripts SQL para crear y cargar la estructura de datos del proyecto.
- src/: estructura preparada para organizar controladores, modelos, rutas y vistas.
- tests/: carpeta para pruebas o futuras mejoras.

## Funcionalidades principales

### 1. Autenticación y roles

El sistema permite registrarse e iniciar sesión. Además, distingue entre distintos roles:

- Usuario: puede ver y gestionar sus propias reservas.
- Admin: puede administrar operaciones del negocio y del plano.
- Superadmin: tiene acceso completo a la gestión.

### 2. Página inicial

La página principal presenta la identidad del restaurante y permite acceder a la opción de reservar.

### 3. Reservas

El sistema permite elegir una fecha, una hora y una mesa disponible para hacer una reserva.

### 4. Plano de mesas

Se puede editar el plano del local para agregar, mover o eliminar mesas. Esto se hace de forma visual con una interfaz interactiva.

### 5. Gestión de usuarios

Se pueden crear, editar o eliminar usuarios desde el panel administrativo.

### 6. Exportación de reservas

El sistema incluye una opción para generar archivos ICS, útiles para agregar reservas al calendario.

## Requisitos para usarlo

Para correr este proyecto necesitas:

- XAMPP, WAMP o cualquier servidor local con Apache, MySQL y PHP.
- Un navegador actualizado.
- Una base de datos MySQL disponible.

## Instalación y uso

1. Coloca la carpeta del proyecto dentro de la carpeta htdocs de XAMPP.
2. Inicia Apache y MySQL desde XAMPP.
3. Crea la base de datos y carga los archivos SQL que están en la carpeta base de datos.
4. Ajusta las credenciales de conexión en los archivos de configuración de la carpeta includes, según tu entorno local.
5. Abre el proyecto en el navegador.

### Ruta principal

- Página inicial: index.php
- Login: public/login.php

## Credenciales de prueba

El proyecto incluye una cuenta de superadmin para pruebas:

- Usuario: PruebaSU
- Contraseña: Margoproject1

## Resumen fácil de explicar

Este proyecto es un sistema web de reservas para un restaurante. Permite que los clientes puedan reservar mesas, mientras que los administradores pueden gestionar usuarios, reservas y la distribución de mesas en el local. Todo está conectado a una base de datos y organizado con PHP, HTML, CSS, JavaScript y MySQL.

## Nota importante

Algunas partes del proyecto usan configuraciones específicas de base de datos y servicios externos, por lo que si lo vas a ejecutar localmente puede ser necesario ajustar los datos de conexión según tu computadora o servidor.

