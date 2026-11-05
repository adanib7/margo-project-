// Propone posiciones sin modificar las mesas. Los tamaños ya incluyen su giro.
function proponerDistribucion(mesas, tipo, ancho = 900, alto = 560) {
  if (!mesas.length) return null;
  const margen = 24, separacion = 24;
  const anchoMayor = Math.max(...mesas.map(m => m.ancho));
  const altoMayor = Math.max(...mesas.map(m => m.alto));
  let mejor = null;

  for (let columnas = 1; columnas <= mesas.length; columnas++) {
    const filasMin = tipo === 'centro' ? 2 : Math.ceil(mesas.length / columnas);
    const filasMax = tipo === 'centro' ? mesas.length + 2 : filasMin;
    for (let filas = filasMin; filas <= filasMax; filas++) {
      const celdaAncho = (ancho - margen * 2) / columnas;
      const celdaAlto = (alto - margen * 2) / filas;
      if (celdaAncho < anchoMayor + separacion || celdaAlto < altoMayor + separacion) continue;
      const lugares = [];
      for (let fila = 0; fila < filas; fila++) {
        for (let columna = 0; columna < columnas; columna++) {
          if (tipo === 'centro' && fila > 0 && fila < filas - 1 && columna > 0 && columna < columnas - 1) continue;
          lugares.push({ x: margen + (columna + 0.5) * celdaAncho, y: margen + (fila + 0.5) * celdaAlto });
        }
      }
      if (lugares.length < mesas.length) continue;
      const puntaje = (lugares.length - mesas.length) * 1000 + Math.abs(celdaAncho - celdaAlto);
      if (!mejor || puntaje < mejor.puntaje) mejor = { puntaje, lugares };
    }
  }
  if (!mejor) return null;
  // Centrar la caja ocupada por cada figura, incluso en triángulos girados.
  return mesas.map((mesa, i) => ({
    clave: mesa.clave,
    x: mejor.lugares[i].x - mesa.desfaseX,
    y: mejor.lugares[i].y - mesa.desfaseY,
  }));
}
