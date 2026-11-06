-- Ejecutar una vez en la base del proyecto si reservas no tiene telefono.
-- Las reservas existentes quedan con telefono NULL (campo opcional).
ALTER TABLE `reservas`
  ADD COLUMN `telefono` VARCHAR(50) DEFAULT NULL AFTER `comentario`;
