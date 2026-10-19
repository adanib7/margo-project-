-- No modifica usuarios existentes. Los registros se convierten en cuentas al verificar.
CREATE TABLE IF NOT EXISTS registro_pendientes (
    email VARCHAR(255) NOT NULL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) DEFAULT NULL,
    google_id VARCHAR(255) DEFAULT NULL,
    foto VARCHAR(500) DEFAULT NULL,
    codigo_hash VARCHAR(255) NOT NULL DEFAULT '',
    solicitud_hash CHAR(64) NOT NULL DEFAULT '',
    expira BIGINT NOT NULL DEFAULT 0,
    intentos INT NOT NULL DEFAULT 0,
    envios INT NOT NULL DEFAULT 0,
    ventana BIGINT NOT NULL DEFAULT 0,
    enviado_en BIGINT NOT NULL DEFAULT 0,
    creado_en BIGINT NOT NULL,
    INDEX idx_registro_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS registro_limites (
    clave CHAR(64) NOT NULL PRIMARY KEY,
    inicio BIGINT NOT NULL,
    cantidad INT NOT NULL,
    INDEX idx_registro_inicio (inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
