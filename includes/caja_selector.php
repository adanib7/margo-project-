<?php // Modal compartido por la caja y el mozo para cargar un pedido desde la carta. ?>
<div class="modal-fondo" id="modalCarta" role="dialog" aria-modal="true" aria-labelledby="selTitulo">
  <div class="modal-contenedor cj-modal-carta">
    <div class="modal-encabezado">
      <div class="modal-icono"><span class="material-symbols-outlined">restaurant_menu</span></div>
      <div>
        <h2 class="modal-titulo" id="selTitulo">Pedido</h2>
        <p class="modal-subtitulo">Elegí para quién es y tocá los productos.</p>
      </div>
      <button type="button" class="modal-cerrar" data-cerrar="modalCarta" aria-label="Cerrar">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <div class="cj-sel-para">
      <span class="cj-sel-etiqueta" id="selParaEtiqueta">Para</span>
      <div class="cj-sel-asientos" id="selAsientos" role="group" aria-labelledby="selParaEtiqueta"></div>
    </div>

    <div class="campo-input-wrapper">
      <span class="campo-icono material-symbols-outlined">search</span>
      <input class="campo-input" type="search" id="selBuscar" placeholder="Buscar en la carta…" aria-label="Buscar en la carta" autocomplete="off">
    </div>

    <div class="cj-tabs" id="selCategorias" role="group" aria-label="Categorías de la carta"></div>
    <div class="cj-productos" id="selProductos"></div>

    <div class="cj-sel-pedido">
      <div class="cj-sel-pedido-cab">
        <h3 class="cj-sel-etiqueta">Este pedido</h3>
        <span id="selResumen" aria-live="polite"></span>
      </div>
      <ul class="cj-pedido" id="selPedido"></ul>
    </div>

    <div class="modal-acciones">
      <button type="button" class="boton-secundario" data-cerrar="modalCarta">Cancelar</button>
      <button type="button" class="boton-accion boton-modal-submit" id="selEnviar" disabled>
        <span class="material-symbols-outlined">send</span>
        <span>Enviar pedido</span>
      </button>
    </div>
  </div>
</div>
