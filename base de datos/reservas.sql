-- Tabla de reservas de mesa (dashboard de usuario)

CREATE TABLE `reservas` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mesa_id` int(11) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `personas` int(11) NOT NULL,
  `comentario` varchar(500) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `reservas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_mesa_franja` (`mesa_id`, `fecha`, `hora`);

-- Si la tabla `reservas` ya existía sin la columna de mesa, ejecutar:
-- ALTER TABLE `reservas` ADD COLUMN `mesa_id` int(11) DEFAULT NULL AFTER `usuario_id`;
-- ALTER TABLE `reservas` ADD INDEX `idx_mesa_franja` (`mesa_id`, `fecha`, `hora`);

ALTER TABLE `reservas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `reservas`
  ADD CONSTRAINT `reservas_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
