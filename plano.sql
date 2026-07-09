-- =====================================================
-- Corralín Online - Plano de mesas
-- Agrega columnas de posición a la tabla mesa existente.
-- Si tu tabla mesa ya tiene numero y capacidad, solo corré el ALTER.
-- =====================================================

ALTER TABLE mesa
  ADD COLUMN pos_x FLOAT NOT NULL DEFAULT 100,
  ADD COLUMN pos_y FLOAT NOT NULL DEFAULT 100,
  ADD COLUMN forma ENUM('cuadrada','redonda') NOT NULL DEFAULT 'cuadrada',
  ADD COLUMN ancho FLOAT NOT NULL DEFAULT 70,
  ADD COLUMN alto FLOAT NOT NULL DEFAULT 70,
  ADD COLUMN rotacion FLOAT NOT NULL DEFAULT 0;

-- Si todavía no tenés la tabla mesa, usá esta en su lugar:
/*
CREATE TABLE mesa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero INT NOT NULL,
  capacidad INT NOT NULL DEFAULT 4,
  pos_x FLOAT NOT NULL DEFAULT 100,
  pos_y FLOAT NOT NULL DEFAULT 100,
  forma ENUM('cuadrada','redonda') NOT NULL DEFAULT 'cuadrada',
  ancho FLOAT NOT NULL DEFAULT 70,
  alto FLOAT NOT NULL DEFAULT 70,
  rotacion FLOAT NOT NULL DEFAULT 0
);
*/
