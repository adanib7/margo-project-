-- Solo para instalaciones antiguas si el hosting no permite la actualizacion desde PHP.
-- Ejecutar una sola vez; si activa ya existe, no hace falta importar este archivo.
ALTER TABLE mesas ADD COLUMN activa TINYINT(1) NOT NULL DEFAULT 1;
