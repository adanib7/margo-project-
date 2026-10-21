# Guia para defender la programacion del proyecto

Esta guia conecta el codigo real con los apuntes del curso. Sirve para estudiar ejecutando el sistema y siguiendo cada paso; no sustituye entender los archivos.

## 1. Como se organiza

- `index.php`: presentacion publica del restaurante.
- `public/login.php` e `includes/auth.php`: registro y acceso con contrasena.
- `dashboards/`: pantallas segun el rol del usuario.
- `assets/js/`: interaccion con las pantallas y solicitudes con fetch.
- `api/`: recibe solicitudes HTTP, valida permisos y datos, consulta MySQL y devuelve respuestas.
- `includes/config.php`: conexion MySQL, rutas y carga de configuracion.
- `includes/check_auth.php`: funciones para exigir inicio de sesion y roles.
- `includes/plano_db.php`: mesas y calculo de ocupacion.
- `includes/reportes_db.php`: consultas y calculos compartidos por pantalla, CSV y PDF.
- `assets/js/comun.js`: escape de HTML y avisos compartidos por las pantallas.

PHP se ejecuta en el servidor. JavaScript se ejecuta en el navegador. MySQL guarda los datos entre visitas. JSON es el formato de intercambio; no es la base de datos del proyecto.

El login acepta el nombre de usuario o el correo en un mismo campo. La consulta
preparada busca por `nombre = ? OR email = ?`. La sesion siempre conserva el nombre
real obtenido de la base, aunque se haya escrito el correo. Si la entrada coincide
con dos cuentas diferentes, se rechaza el acceso para no elegir una arbitrariamente.

Las contrasenas nuevas admiten simbolos opcionales: se mantienen el minimo de seis
caracteres, una mayuscula y un numero. `validarPassword()` se comparte entre registro,
perfil y recuperacion. Al iniciar sesion se verifica el hash guardado, sin volver a
exigir las reglas de creacion a contrasenas existentes.

## 2. Recorrido que deben poder explicar: crear una reserva

1. El usuario inicia sesion. PHP guarda su identificador y rol en `$_SESSION`.
2. `dashboards/user.php` muestra el formulario y publica las reglas que necesita JavaScript.
3. `assets/js/user.js` consulta horarios y mesas mediante fetch a `ocupaciones_horarios.php` y `plano_disponible.php`.
4. Al confirmar, envia un POST a `api/crear_reserva.php`, con `Content-Type: application/json` y `JSON.stringify(...)` en el body.
5. PHP lee `file_get_contents('php://input')` y usa `json_decode(..., true)` para obtener un arreglo asociativo.
6. Comprueba la sesion, los datos, las reglas, la capacidad de la mesa y el solapamiento con otras reservas.
7. Prepara un INSERT con `?`, asigna los valores con `bind_param()` y lo ejecuta.
8. Devuelve un arreglo convertido con `json_encode()`. Puede enviar un correo mediante Brevo si esta configurado.
9. JavaScript usa `await respuesta.json()` y muestra el resultado o los errores de campos.

La validacion del navegador ayuda al usuario; la del servidor es indispensable porque cualquiera puede enviar solicitudes sin usar el formulario.

## 3. HTTP, fetch, API y JSON

| Tema del curso | Ejemplo real | Explicacion que deben poder dar |
| --- | --- | --- |
| GET y parametros | `api/listar_usuarios.php?q=Ana&rol=usuario` | Consulta informacion; PHP lee los parametros con `$_GET`. |
| POST y body | `api/crear_usuario.php` | Recibe los datos del usuario y el rol en JSON. |
| fetch y await | `assets/js/superadmin.js` | Envia una solicitud y espera la respuesta sin recargar la pagina. |
| JSON y arreglos | `api/listar_inventario.php` | Un ciclo while recorre filas y arma un arreglo de items que se convierte a JSON. |
| Headers | `Content-Type: application/json` | Indica el formato del contenido enviado. |
| Codigos HTTP | APIs de reservas | 401: falta iniciar sesion; 403: faltan permisos; 405: metodo no admitido; 409: conflicto; 422: datos invalidos; 500: fallo del servidor. |

`response.ok` indica un estado HTTP exitoso; `data.ok` es un campo que nosotros incluimos dentro del JSON. Son cosas diferentes.

El proyecto tiene una API HTTP que intercambia JSON. Actualmente utiliza POST tambien para editar y eliminar y autentica mediante sesiones. No deben presentarlo como una API REST estricta. En el material REST, PUT se usa para reemplazar, PATCH para cambios parciales y DELETE para eliminar. No hace falta cambiar toda la arquitectura para explicar esta diferencia.

## 4. Sesiones y cookies

`session_start()` inicia o recupera una sesion. Normalmente PHP envia una cookie con un identificador, y el navegador lo devuelve en las siguientes solicitudes al mismo sitio. Los datos de `$_SESSION` se guardan en el servidor, no directamente en esa cookie.

El proyecto usa `usuario_id`, `usuario_logueado` y `rol`. El rol controla que pantallas y acciones se permiten. Ocultar un boton no protege una API: el endpoint debe comprobar los permisos.

No confundir autenticar (comprobar quien sos) con autorizar (comprobar que podes hacer). Los apuntes de cookies explican ademas Secure, HttpOnly y SameSite; no deben afirmar que todos estan configurados expresamente en el proyecto sin revisar la configuracion efectiva de PHP.

## 5. Seguridad que se conserva

- `password_hash()`: genera un hash para guardar la contrasena sin almacenarla en texto plano.
- `password_verify()`: comprueba una contrasena contra ese hash. No se desencripta una contrasena.
- Consultas preparadas: separan la estructura SQL de los valores. `bind_param('ssi', ...)` indica dos strings y un entero.
- `htmlspecialchars()` en PHP y `escaparHtml()` en JavaScript: hacen que un dato se muestre como texto al insertarlo en HTML.
- `textContent`: permite poner un mensaje de texto sin interpretarlo como HTML; lo usa el aviso compartido.
- Permisos en el servidor: impiden que un cliente gestione usuarios o inventario enviando una solicitud manual.

Estas medidas cumplen tareas distintas: escapar HTML no evita una inyeccion SQL y usar password_hash no protege una consulta mal construida.

## 6. SQL que ya existe y conviene estudiar

### GROUP BY y HAVING

En `includes/reportes_db.php`, `reporteTopClientes()` agrupa las reservas por nombre, cuenta las de cada grupo y muestra los grupos con mas de una reserva:

```sql
SELECT nombre, COUNT(*) AS n, COALESCE(SUM(personas), 0) AS p
FROM reservas
WHERE fecha BETWEEN ? AND ? AND estado <> 'cancelada'
GROUP BY nombre
HAVING n > 1
ORDER BY n DESC, p DESC
LIMIT ?
```

`WHERE` filtra filas antes de agrupar. `GROUP BY` forma grupos. `HAVING` filtra los grupos calculados. `ORDER BY` ordena el resultado. `COALESCE` reemplaza un NULL por el valor indicado.

Limitacion real: agrupa por nombre de la reserva, asi que dos personas con el mismo nombre pueden quedar juntas. No identifica de forma unica a un cliente.

### JOIN

En `api/listar_reservas.php` se relacionan reservas, mesas y usuarios:

```sql
FROM reservas r
LEFT JOIN mesas m ON m.id = r.mesa_id
LEFT JOIN usuarios u ON u.id = r.usuario_id
```

Se usa LEFT JOIN porque una reserva cargada por el administrador puede no tener usuario ni mesa asignados. INNER JOIN mostraria solamente las que tienen coincidencia. Los alias `r`, `m` y `u` abrevian nombres de tablas.

En `reportePorMesa()` tambien se usa LEFT JOIN para incluir mesas con cero reservas. Cambiarlo por INNER JOIN cambiaria lo que muestra el reporte.

## 7. Por que una mesa no puede reservarse en horarios superpuestos

`franjaReserva()` calcula inicio, fin y duracion. Con duracion de dos horas, una reserva a las 21:00 ocupa hasta las 23:00.

Hay solapamiento cuando el inicio de una reserva es anterior al fin de la otra y su fin es posterior al inicio de la otra. Por eso 21:00 y 21:30 chocan, pero 21:00 y 23:00 no.

MySQL TIME admite duraciones y horas mayores a 23:59. Por eso el codigo puede representar 25:00 para el fin de una reserva que empieza a las 23:00.

Limites actuales: comprobar y guardar son pasos separados, por lo que falta proteger la operacion contra solicitudes simultaneas. La consulta compara reservas con la misma fecha; no resuelve cruces entre reservas de fechas distintas pasada medianoche.

## 8. Librerias e integraciones: explicar su funcion y el punto de uso

- FPDF (`includes/lib/fpdf/`): genera archivos PDF. Revisar como lo usan los comprobantes y reportes; es codigo de una libreria externa.
- Lucide y Material Symbols: iconos de la interfaz.
- Tailwind: utilidades y estilos base de las pantallas; tambien se usa CSS propio.
- Google: acceso con una cuenta externa. `includes/google_auth.php` valida la credencial y busca una cuenta vinculada por su identificador `sub`. Permite crear la cuenta e iniciar sesion directamente, sin un codigo adicional del sitio.
- Brevo: API externa de correo. `includes/mailer.php` envia JSON mediante una solicitud HTTP con cURL, o un stream si cURL no esta disponible.

Conviene saber que problema resuelve cada herramienta, que datos recibe, que devuelve y que ocurre si falla. No tienen que atribuirse la implementacion interna de las librerias.

## 9. Simplificaciones aplicadas

- Una sola implementacion de escape de HTML y avisos, antes repetidos en siete archivos cada uno.
- El boton Crear Admin usa `crear_usuario.php` con rol `admin`; se elimino el endpoint duplicado. La API sigue exigiendo superadmin y validando el rol.
- Se quito configuracion de Tailwind sin usos en las pantallas, conservando la utilidad de fuente usada.
- Los listados de reservas e inventario recorren el resultado SQL con while, sin un arreglo intermedio ni un callback de array_map.
- El calculo de la franja de reserva usa directamente las horas, sin una funcion anonima de formato.
- Una fecha mal formada en la consulta de horarios devuelve un error controlado en vez de intentar usar un objeto inexistente.

Se conservan funcionalidades, integraciones, consultas preparadas y reglas de reservas. Las comprobaciones de tablas y columnas siguen presentes: permiten usar instalaciones con distintos esquemas. Quitarlas requiere unificar primero la base de datos de cada entorno.

## 10. Practica para la defensa

1. Abrir la pestana Red del navegador, listar usuarios y localizar metodo, URL, parametros, codigo HTTP y JSON de respuesta.
2. Crear una reserva y seguir su recorrido por JavaScript, API y SQL. Cambiar un dato a un valor invalido y explicar la respuesta.
3. Explicar por que un usuario comun no puede crear un administrador aunque modifique el JavaScript.
4. Mostrar GROUP BY, HAVING y LEFT JOIN en los reportes, y predecir que cambia si se quitan.
5. Dibujar dos reservas que se pisan y dos que no. Relacionarlas con las condiciones de `reservaSolapada()`.
6. Explicar que permanece en el navegador, que queda en la sesion del servidor y que se guarda en MySQL.
7. Distinguir el codigo propio de las librerias y contar con claridad en que se uso ayuda de IA y como se comprobo el resultado.

## 11. Registro con verificacion de correo

El formulario ya no inserta directamente en `usuarios`. `includes/auth.php` llama a
`includes/registro.php`, que guarda los datos provisionales en `registro_pendientes`
y envia un codigo mediante Brevo. La pantalla `modo=verificar` permite ingresarlo
o pedir otro. Son formularios POST procesados por PHP, sin un endpoint JSON nuevo.

La contrasena y el codigo se guardan como hashes con `password_hash()`. El codigo
tiene seis digitos, vence en 15 minutos y se bloquea despues de cinco errores.
Al reenviar hay que esperar 60 segundos; el nuevo codigo reemplaza al anterior.
Se admiten hasta cinco envios por email por hora. `registro_limites` conserva limites
por IP entre sesiones: diez solicitudes de envio y treinta confirmaciones por hora.
Los limites por IP pueden afectar a personas que comparten conexion y se renuevan
automaticamente. No son un bloqueo permanente ni garantizan una cuenta por persona.

Ademas del codigo, el servidor vincula la solicitud con un identificador aleatorio
guardado en la sesion. En la tabla se guarda su hash. Esto impide que una solicitud
hecha desde otro navegador cambie las credenciales que confirma el usuario original.
Hay que terminar el registro en el navegador donde se inicio; si se pierde la sesion,
se puede volver a completar el formulario de registro respetando los limites.

`registroConfirmar()` usa una transaccion y `SELECT ... FOR UPDATE`: bloquea el
pendiente mientras comprueba el codigo, crea la cuenta y elimina ese
pendiente. `commit()` confirma esos cambios juntos; `rollback()` los deshace si
algo falla. Dos confirmaciones simultaneas no pueden consumir el mismo codigo.
Ese bloqueo es para verificar registros; no soluciona la concurrencia de reservas.

Solo despues de confirmar se crea la cuenta con rol `usuario`. No se inicia sesion
automaticamente: se vuelve al login. Las cuentas existentes y las altas realizadas
por el superadministrador conservan su funcionamiento. El registro con Google no
usa este codigo: el servidor valida la credencial de Google y permite entrar
directamente. La vinculacion automatica a una cuenta local con el mismo email solo
se hace si Google administra ese correo (Gmail o Workspace); conserva nombre,
contrasena y rol. Si es un correo externo y existe una cuenta local sin vincular,
se solicita usar el acceso local para evitar una vinculacion insegura.

Los formularios de registro y verificacion incluyen un token CSRF: PHP comprueba que
coincida con el de la sesion antes de procesarlos. En Google se comparan la cookie
y el campo `g_csrf_token`, ademas de validar el token de identidad con Google.

Si Brevo no esta configurado o el envio falla, la cuenta no se habilita. Los correos
con codigos de registro no se copian al destinatario BCC de las reservas. Los
pendientes abandonados se eliminan tras 24 horas cuando se procesa otro registro.
En el hosting las tablas pueden importarse con `base de datos/registro_verificacion.sql`.

Limites del alcance: un correo temporal puede recibir el codigo; estas medidas no
demuestran una identidad real. No se exige verificar retroactivamente cuentas
existentes ni se agrega aqui una verificacion de los cambios de email en Mi perfil.

Para practicar: registrar una cuenta de prueba, mostrar que todavia no aparece en
`usuarios`, provocar un codigo incorrecto, confirmar el correcto e intentar reutilizarlo.
Verificar tambien vencimiento, espera de reenvio y acceso con una cuenta existente.

## 12. URLs amigables

Una direccion como `/usuarios` ejecuta internamente
`dashboards/gestion-usuarios.php`. Apache aplica las reglas de `.htaccess`: no se
mueven los archivos ni se cambian los controles de permisos de las pantallas.
`includes/rutas.php` relaciona los archivos con sus nombres publicos y detecta la
carpeta base; `buildUrl()` genera los enlaces limpios conservando consultas y anclas.
En el hosting la base es vacia; en XAMPP puede ser `/margo-project-`.

Cuando se abre un enlace antiguo por GET o HEAD, `config.php` responde con una
redireccion 302 hacia la direccion nueva. Los POST no se redirigen: deben conservar
su cuerpo y seguir procesando formularios y cambios del plano. Las API, descargas
y el callback de Google conservan sus direcciones. Las imagenes, CSS y solicitudes
JavaScript usan rutas desde la base para funcionar tambien desde las URLs amigables.

## 13. Vencimiento y cierre de sesiones

`includes/sesion.php` centraliza el inicio, cierre y validacion. Al acceder con
contrasena o Google se genera otro ID y se guardan la fecha de inicio y la ultima
actividad. El servidor exige volver a entrar tras 30 minutos de inactividad o
al llegar a ocho horas, incluso si se sigue usando la aplicacion.

`config.php` comprueba la sesion antes de ejecutar operaciones o conectar a MySQL.
Las API protegidas responden 401 con JSON al perder el acceso; las pantallas
redirigen al login. `api/sesion.php` permite consultar el vencimiento con GET y
notificar actividad con POST. Los sondeos automaticos de nuevas reservas no
actualizan el reloj, para que dejar una pestana abierta no mantenga el acceso.

`assets/js/sesion.js` detecta interaccion real y sincroniza la pantalla con el
servidor. No es quien decide los permisos: aunque se desactive JavaScript,
las solicitudes posteriores siguen comprobando el vencimiento en PHP.
Cerrar sesion elimina todos los datos, destruye el registro del servidor y borra
la cookie; las paginas protegidas usan `Cache-Control: no-store`.

La cookie no tiene una duracion persistente y usa HttpOnly, SameSite=Lax y Secure
cuando hay HTTPS. Cerrar una pestana no cierra las otras. Un navegador puede
restaurar cookies al reabrirse; por eso la proteccion principal es el vencimiento
controlado por el servidor, no detectar el cierre de una ventana.

Referencia: [OWASP, gestion de sesiones](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).
