-- El Corralin: instalacion del esquema actual en una base vacia.
-- En phpMyAdmin, seleccionar primero la base del sitio y luego importar este archivo.
-- Crea las tablas; no recupera usuarios, reservas ni configuraciones anteriores.
-- Si hay una copia SQL con los datos anteriores, esa copia es la restauracion.
-- No modifica la estructura de tablas que ya existan: no es una migracion.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `google_id` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `foto` VARCHAR(500) DEFAULT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rol` VARCHAR(20) DEFAULT 'usuario',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `mesas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `numero` INT NOT NULL DEFAULT 1,
  `capacidad` INT NOT NULL DEFAULT 4,
  `forma` VARCHAR(20) NOT NULL DEFAULT 'cuadrada',
  `pos_x` DOUBLE NOT NULL DEFAULT 100,
  `pos_y` DOUBLE NOT NULL DEFAULT 100,
  `ancho` DOUBLE NOT NULL DEFAULT 70,
  `alto` DOUBLE NOT NULL DEFAULT 70,
  `rotacion` DOUBLE NOT NULL DEFAULT 0,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `reservas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL,
  `usuario_id` INT DEFAULT NULL,
  `mesa_id` INT DEFAULT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `fecha` DATE NOT NULL,
  `hora` TIME NOT NULL,
  `personas` INT NOT NULL,
  `comentario` VARCHAR(500) DEFAULT NULL,
  `telefono` VARCHAR(50) DEFAULT NULL,
  `estado` VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_mesa_franja` (`mesa_id`, `fecha`, `hora`),
  CONSTRAINT `reservas_usuario_fk` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `inventario` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `categoria` VARCHAR(30) NOT NULL DEFAULT 'general',
  `unidad` VARCHAR(20) NOT NULL DEFAULT 'unidad',
  `stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `proveedor` VARCHAR(120) DEFAULT NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `configuracion` (
  `clave` VARCHAR(60) NOT NULL,
  `valor` TEXT NOT NULL,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `registro_pendientes` (
  `email` VARCHAR(255) NOT NULL PRIMARY KEY,
  `nombre` VARCHAR(255) NOT NULL DEFAULT '',
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `google_id` VARCHAR(255) DEFAULT NULL,
  `foto` VARCHAR(500) DEFAULT NULL,
  `codigo_hash` VARCHAR(255) NOT NULL DEFAULT '',
  `solicitud_hash` CHAR(64) NOT NULL DEFAULT '',
  `expira` BIGINT NOT NULL DEFAULT 0,
  `intentos` INT NOT NULL DEFAULT 0,
  `envios` INT NOT NULL DEFAULT 0,
  `ventana` BIGINT NOT NULL DEFAULT 0,
  `enviado_en` BIGINT NOT NULL DEFAULT 0,
  `creado_en` BIGINT NOT NULL,
  INDEX `idx_registro_creado` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `registro_limites` (
  `clave` CHAR(64) NOT NULL PRIMARY KEY,
  `inicio` BIGINT NOT NULL,
  `cantidad` INT NOT NULL,
  INDEX `idx_registro_inicio` (`inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `usuario_id` INT NOT NULL,
  `codigo_hash` VARCHAR(255) NOT NULL,
  `expira` DATETIME NOT NULL,
  `intentos` INT NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL,
  PRIMARY KEY (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
