<?php
require_once __DIR__ . '/../includes/sesion.php';
iniciarSesion();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/plano_db.php';
requireRole('admin', 'superadmin');

function sendJson(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function cargarMesasPlano(mysqli $conn): array
{
    $result = $conn->query("SELECT id, numero, capacidad, forma, pos_x, pos_y, ancho, alto, rotacion FROM mesas ORDER BY id ASC");
    if (!$result) {
        throw new RuntimeException('No se pudieron cargar las mesas.');
    }
    $mesas = [];
    while ($row = $result->fetch_assoc()) {
        $mesas[] = $row;
    }
    return $mesas;
}

if (!isset($conn) || $conn === null) {
    http_response_code(500);
    sendJson(['error' => 'No se pudo conectar a la base de datos del servidor.']);
}

ensureMesaTable($conn);
ensureReservaMesaColumn($conn);

$action = $_GET['action'] ?? '';

if ($action === 'cargar') {
    try {
        sendJson(cargarMesasPlano($conn));
    } catch (Throwable $error) {
        http_response_code(500);
        sendJson(['error' => 'Error al cargar las mesas.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?: [];
    $action = $payload['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'guardar') {
        $mesas = $payload['mesas'] ?? [];
        $eliminadas = $payload['eliminadas'] ?? [];
        if (!is_array($mesas) || !is_array($eliminadas)) {
            http_response_code(422);
            sendJson(['error' => 'Los datos del plano no son válidos.']);
        }

        try {
            $conn->begin_transaction();
            foreach ($mesas as $m) {
                $id = isset($m['id']) && $m['id'] !== '' ? (int) $m['id'] : null;
                $numero = (int) ($m['numero'] ?? 1);
                $capacidad = (int) ($m['capacidad'] ?? 4);
                $forma = in_array($m['forma'] ?? '', PLANO_FORMAS, true) ? $m['forma'] : 'cuadrada';
                $posX = (float) ($m['pos_x'] ?? 100);
                $posY = (float) ($m['pos_y'] ?? 100);
                $ancho = (float) ($m['ancho'] ?? 70);
                $alto = (float) ($m['alto'] ?? 70);
                $rotacion = (float) ($m['rotacion'] ?? 0);

                if ($id) {
                    $stmt = $conn->prepare("
                        UPDATE mesas
                        SET numero = ?, capacidad = ?, forma = ?, pos_x = ?, pos_y = ?, ancho = ?, alto = ?, rotacion = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param('iisdddddi', $numero, $capacidad, $forma, $posX, $posY, $ancho, $alto, $rotacion, $id);
                    if (!$stmt->execute()) throw new RuntimeException('No se pudo actualizar la mesa.');
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO mesas (numero, capacidad, forma, pos_x, pos_y, ancho, alto, rotacion)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param('iisddddd', $numero, $capacidad, $forma, $posX, $posY, $ancho, $alto, $rotacion);
                    if (!$stmt->execute()) throw new RuntimeException('No se pudo crear la mesa.');
                    $stmt->close();
                }
            }

            $noEliminadas = [];
            foreach ($eliminadas as $mesaId) {
                $id = (int) $mesaId;
                if ($id > 0 && mesaTieneReservas($conn, $id)) {
                    $noEliminadas[] = $id;
                } else {
                    $stmt = $conn->prepare("DELETE FROM mesas WHERE id = ?");
                    $stmt->bind_param('i', $id);
                    if (!$stmt->execute()) throw new RuntimeException('No se pudo eliminar la mesa.');
                    $stmt->close();
                }
            }

            // Devolver los ID reales evita volver a crear mesas al seguir editando.
            $mesasGuardadas = cargarMesasPlano($conn);
            $conn->commit();
            sendJson([
                'ok' => true,
                'no_eliminadas' => $noEliminadas,
                'mesas' => $mesasGuardadas,
            ]);
        } catch (Throwable $error) {
            $conn->rollback();
            http_response_code(500);
            sendJson(['error' => 'No se pudo guardar el plano. No se aplicaron los cambios.']);
        }
    }
}
$pageTitle = 'Plano de mesas';
$pageCSS = '../assets/css/dashboard.css';
$volverUrl = ($_SESSION['rol'] ?? '') === 'superadmin'
    ? buildUrl('/dashboards/superadmin.php')
    : buildUrl('/dashboards/admin.php');
require_once '../includes/header.php';
?>
<?php require_once '../includes/nav.php'; ?>
<main class="contenido-principal corralin-plano" id="editorPlano">
  <header class="seccion-encabezado">
    <div>
      <a href="<?= $volverUrl ?>" class="enlace-volver"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span> Panel principal</a>
      <h1 class="titulo-pagina" style="margin-top:.5rem">Plano de mesas</h1>
      <p class="subtitulo-pagina">Cada mesa en su lugar. Organizá el salón a tu manera.</p>
    </div>
  </header>

  <section class="salon-editor" aria-label="Organizar el salón">
    <div class="salon-barra">
      <div class="salon-barra-acciones">
        <button type="button" class="boton-secundario" id="btnAgregarMesa"><span class="material-symbols-outlined" aria-hidden="true">add</span> Mesa</button>
        <button type="button" class="boton-secundario" id="btnDistribuir"><span class="material-symbols-outlined" aria-hidden="true">grid_view</span> Distribuir</button>
        <button type="button" class="boton-accion" id="btnGuardarPlano">Guardar plano</button>
      </div>
      <div class="salon-guardado">
        <span id="estadoGuardado">Cargando salón…</span>
        <button type="button" class="salon-enlace" id="btnDeshacer" disabled><span class="material-symbols-outlined" aria-hidden="true">undo</span> Deshacer</button>
      </div>
    </div>

    <div class="salon-cuerpo">
      <div class="plano-area">
        <div class="salon-lienzo-cabecera"><span class="texto-etiqueta">El salón</span><span id="resumenSalon"></span></div>
        <p id="planoInstruccion" class="salon-instruccion" role="status">Elegí una mesa para moverla, girarla o editarla.</p>
        <div class="salon-contexto" id="accionPlano" hidden>
          <span id="accionTexto"></span>
          <div>
            <button type="button" class="boton-accion" id="btnAplicarAccion" disabled>Colocar aquí</button>
            <button type="button" class="boton-secundario" id="btnCancelarAccion">Cancelar</button>
          </div>
        </div>
        <div id="canvas-wrap"><div id="plano" tabindex="0" aria-label="Plano editable del salón" aria-describedby="planoInstruccion"></div></div>
        <div class="salon-pie-plano">
          <span class="salon-leyenda"><i></i> Mesas del restaurante</span>
          <div class="plano-zoom" role="group" aria-label="Zoom del plano">
            <button type="button" class="salon-icono" id="zoomMenos" aria-label="Alejar plano">−</button>
            <output id="zoomValor" aria-label="Escala del plano">100%</output>
            <button type="button" class="salon-icono" id="zoomMas" aria-label="Acercar plano">+</button>
            <button type="button" class="salon-enlace" id="zoomAjustar">Ver todo</button>
          </div>
        </div>
        <p id="aviso" role="status"></p>
        <button type="button" class="salon-enlace" id="btnReintentar" hidden>Volver a cargar el plano</button>
      </div>

      <aside class="salon-inspector">
        <div class="salon-bienvenida" id="salonBienvenida">
          <span class="salon-sello material-symbols-outlined" aria-hidden="true">table_restaurant</span>
          <h2>Un lugar para cada encuentro</h2>
          <p>Tocá una mesa y elegí qué querés hacer con ella.</p>
          <p class="salon-nota">¿Estás empezando? Agregá tus mesas y probá una distribución para el salón.</p>
        </div>
        <section class="salon-ficha" id="panel-mesa" aria-label="Mesa seleccionada" hidden>
          <div class="salon-ficha-titulo">
            <div><p class="texto-etiqueta">Mesa seleccionada</p><h2 id="mesaActiva">Mesa</h2><p id="mesaDetalle"></p></div>
            <button type="button" class="salon-icono" id="cerrarFicha" aria-label="Cerrar selección"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
          </div>
          <div class="salon-acciones-mesa">
            <button type="button" class="boton-secundario" id="btnMoverMesa"><span class="material-symbols-outlined" aria-hidden="true">open_with</span> Mover</button>
            <button type="button" class="boton-secundario" id="btnGirarMesa"><span class="material-symbols-outlined" aria-hidden="true">rotate_right</span> Girar</button>
            <button type="button" class="boton-secundario" id="btnEditarMesa"><span class="material-symbols-outlined" aria-hidden="true">edit</span> Editar</button>
          </div>
          <details class="salon-ajuste-fino" id="ajusteFino">
            <summary>Ajuste fino</summary>
            <div class="plano-flechas" role="group" aria-label="Ajustar posición de la mesa">
              <button type="button" class="salon-icono" data-mover="-1,0" aria-label="Mover mesa a la izquierda">←</button>
              <button type="button" class="salon-icono" data-mover="0,-1" aria-label="Mover mesa hacia arriba">↑</button>
              <button type="button" class="salon-icono" data-mover="0,1" aria-label="Mover mesa hacia abajo">↓</button>
              <button type="button" class="salon-icono" data-mover="1,0" aria-label="Mover mesa a la derecha">→</button>
              <select id="pasoMesa" aria-label="Distancia del ajuste"><option value="10">10</option><option value="1">1</option></select>
            </div>
            <button type="button" class="salon-enlace" id="btnArrastrar">Arrastrar esta mesa</button>
            <button type="button" class="salon-enlace" id="btnManijas">Usar manijas de tamaño y giro</button>
          </details>
        </section>
        <details class="salon-buscar" id="buscarMesa">
          <summary>Elegir una mesa por número</summary>
          <label class="campo-etiqueta" for="selector-mesa">Mesa</label>
          <select id="selector-mesa" class="campo-input"><option value="">Elegí una mesa</option></select>
        </details>
      </aside>
    </div>
  </section>
</main>

<dialog class="salon-dialogo" id="dialogoAgregar" aria-labelledby="tituloAgregar">
  <form id="formAgregarMesa">
    <div class="salon-dialogo-titulo"><div><p class="texto-etiqueta">Un lugar más</p><h2 id="tituloAgregar">Agregar mesa</h2></div><button type="button" class="salon-icono" data-cerrar="dialogoAgregar" aria-label="Cerrar"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></div>
    <fieldset class="salon-formas"><legend class="campo-etiqueta">Elegí la forma</legend>
      <label><input type="radio" name="nuevaForma" value="cuadrada" checked><span class="salon-forma cuadrada"></span><span>Cuadrada</span></label>
      <label><input type="radio" name="nuevaForma" value="redonda"><span class="salon-forma redonda"></span><span>Redonda</span></label>
      <label><input type="radio" name="nuevaForma" value="triangular"><span class="salon-forma triangular"></span><span>Triangular</span></label>
    </fieldset>
    <label class="campo-etiqueta" for="nuevaCapacidad">¿Para cuántas personas?</label>
    <select class="campo-input" id="nuevaCapacidad"><option value="2">2 personas</option><option value="4" selected>4 personas</option><option value="6">6 personas</option><option value="8">8 personas</option></select>
    <p class="campo-ayuda">Buscaremos un espacio libre. Después podés ajustar sus medidas en Editar.</p>
    <p class="salon-error" id="errorAgregar" role="alert"></p>
    <div class="salon-dialogo-pie"><button type="button" class="boton-secundario" data-cerrar="dialogoAgregar">Cancelar</button><button type="submit" class="boton-accion">Agregar al salón</button></div>
  </form>
</dialog>

<dialog class="salon-dialogo" id="dialogoDistribuir" aria-labelledby="tituloDistribuir">
  <form id="formDistribuir">
    <div class="salon-dialogo-titulo"><div><p class="texto-etiqueta">Una nueva disposición</p><h2 id="tituloDistribuir">Distribuir el salón</h2></div><button type="button" class="salon-icono" data-cerrar="dialogoDistribuir" aria-label="Cerrar"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></div>
    <p>Probá una propuesta con tus mesas actuales. Podés verla antes de decidir.</p>
    <fieldset class="salon-distribuciones"><legend class="campo-etiqueta">¿Cómo las acomodamos?</legend>
      <label><input type="radio" name="distribucion" value="filas" checked><span class="salon-miniatura filas" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span><strong>En filas</strong><span>Una disposición pareja y ordenada.</span></label>
      <label><input type="radio" name="distribucion" value="centro"><span class="salon-miniatura centro" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span><strong>Centro libre</strong><span>Las mesas alrededor del espacio central.</span></label>
    </fieldset>
    <p class="campo-ayuda">Se conservan números, capacidades, formas, tamaños y giros. Ajustá la propuesta a la distribución real del local.</p>
    <p class="salon-error" id="errorDistribuir" role="alert"></p>
    <div class="salon-dialogo-pie"><button type="button" class="boton-secundario" data-cerrar="dialogoDistribuir">Cancelar</button><button type="submit" class="boton-accion">Ver propuesta</button></div>
  </form>
</dialog>

<dialog class="salon-dialogo" id="dialogoEditar" aria-labelledby="tituloEditar">
  <form id="formEditarMesa">
    <div class="salon-dialogo-titulo"><div><p class="texto-etiqueta">Los detalles</p><h2 id="tituloEditar">Editar mesa</h2></div><button type="button" class="salon-icono" data-cerrar="dialogoEditar" aria-label="Cerrar"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></div>
    <div class="plano-medidas">
      <div><label class="campo-etiqueta" for="in-numero">Número</label><input class="campo-input" type="number" id="in-numero" min="1" max="9999" required inputmode="numeric"></div>
      <div><label class="campo-etiqueta" for="in-capacidad">Personas</label><input class="campo-input" type="number" id="in-capacidad" min="1" max="20" required inputmode="numeric"></div>
    </div>
    <details class="salon-edicion-medidas"><summary>Medidas y giro</summary><div class="plano-medidas">
      <div><label class="campo-etiqueta" for="in-ancho">Ancho</label><input class="campo-input" type="number" id="in-ancho" min="30" max="400" required step="any" inputmode="decimal"></div>
      <div><label class="campo-etiqueta" for="in-alto">Alto</label><input class="campo-input" type="number" id="in-alto" min="30" max="400" required step="any" inputmode="decimal"></div>
    </div><label class="campo-etiqueta" for="in-rotacion">Giro en grados</label><input class="campo-input" type="number" id="in-rotacion" min="0" max="359" required step="any" inputmode="decimal"></details>
    <p class="salon-error" id="errorEditar" role="alert"></p>
    <button type="button" class="salon-quitar" id="btnEliminarMesa">Quitar esta mesa del plano</button>
    <div class="salon-dialogo-pie"><button type="button" class="boton-secundario" data-cerrar="dialogoEditar">Cancelar</button><button type="submit" class="boton-accion">Aplicar cambios</button></div>
  </form>
</dialog>

<script src="<?= jsUrl('konva.min.js') ?>"></script>
<script src="<?= jsUrl('plano_distribucion.js') ?>"></script>
<script src="<?= jsUrl('admin_plano.js') ?>"></script>
<?php require_once '../includes/footer.php'; ?>
