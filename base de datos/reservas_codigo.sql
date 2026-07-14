-- Agrega el código de reserva (para el comprobante y el archivo de calendario)
-- Ejecutar en el phpMyAdmin de InfinityFree, sobre la base que ya tiene la tabla `reservas`.

ALTER TABLE `reservas`
  ADD COLUMN `codigo` varchar(20) NOT NULL DEFAULT '' AFTER `id`;

ALTER TABLE `reservas`
  ADD UNIQUE KEY `codigo` (`codigo`);
