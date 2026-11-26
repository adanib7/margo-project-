<?php
/**
 * Punto único de conexión con la facturación electrónica de DGI.
 *
 * HOY: no hay proveedor configurado. El comprobante queda guardado con
 * numeración interna (serie X) y estado 'sin_integrar', y el ticket impreso
 * dice "Documento no fiscal".
 *
 * MAÑANA: se elige un proveedor habilitado por DGI (o se certifica uno propio),
 * y solo se completa esta función: enviar el CFE, recibir serie/número/CAE y
 * el código de seguridad, y devolverlos. Nada más del módulo cambia.
 *
 * @param array $comprobante fila de `comprobantes` con 'lineas' decodificadas
 * @return array{estado:string, serie?:string, numero?:int, cae?:string, mensaje?:string}
 */
function cfeEmitir(array $comprobante): array
{
    return [
        'estado' => 'sin_integrar',
        'mensaje' => 'Facturación electrónica todavía no conectada con DGI.',
    ];
}

/** Texto que se imprime en el pie del ticket según el estado del envío. */
function cfeLeyenda(string $estadoDgi): string
{
    return match ($estadoDgi) {
        'aceptado' => 'Comprobante fiscal electrónico. Verificable en www.dgi.gub.uy',
        'pendiente' => 'CFE pendiente de envío a DGI.',
        default => 'DOCUMENTO NO FISCAL · Comprobante interno hasta habilitar la facturación electrónica.',
    };
}
