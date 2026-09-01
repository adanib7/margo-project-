-- ══════════════════════════════════════════════════════════════════════
--  El Corralín — Inventario + Plano de mesas + reservas ligadas a mesa
--
--  Ejecutar UNA vez en el phpMyAdmin de InfinityFree:
--    phpMyAdmin  ->  base  if0_XXXXXXXX_margoproject  ->  pestaña "SQL"
--    pegar todo esto  ->  Continuar / Go
--
--  Es idempotente: si algo ya existía (porque la app lo creó sola), no
--  rompe nada y se puede volver a ejecutar sin problema.
-- ══════════════════════════════════════════════════════════════════════


-- ── 1) Tabla de inventario + datos de ejemplo ─────────────────────────
CREATE TABLE IF NOT EXISTS `inventario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `categoria` varchar(30) NOT NULL DEFAULT 'general',
  `unidad` varchar(20) NOT NULL DEFAULT 'unidad',
  `stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `proveedor` varchar(120) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `inventario`
  (`nombre`, `categoria`, `unidad`, `stock`, `stock_minimo`, `precio_unitario`, `proveedor`) VALUES
('Faba de la Granja',        'seco',     'kg',      18.00, 8.00,  6.50,  'Legumbres del Nalón'),
('Ternera asturiana',        'carne',    'kg',      12.50, 6.00,  14.90, 'Carnicería Cué'),
('Queso Afuega\'l Pitu',     'lacteo',   'kg',      3.20,  2.00,  19.00, 'Quesería La Peral'),
('Sidra natural DOP',        'bebida',   'botella', 96.00, 48.00, 3.20,  'Llagar Trabanco'),
('Chorizo asturiano',        'carne',    'kg',      5.00,  4.00,  11.50, 'Embutidos Nava'),
('Cebolla',                  'verdura',  'kg',      22.00, 10.00, 1.10,  'Huerta El Sueve'),
('Aceite de oliva virgen',   'seco',     'l',       14.00, 6.00,  7.80,  'Distribuciones Uría'),
('Detergente lavavajillas',  'limpieza', 'caja',    2.00,  3.00,  24.00, 'Higiene Pro');


-- ── 2) Tabla del plano de mesas (arranca vacía) ──────────────────────
--  Después entrá a  dashboards/admin_plano.php  y dibujá el salón; al
--  guardar se llena esta tabla y el cliente ya ve el plano al reservar.
CREATE TABLE IF NOT EXISTS `mesas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` int(11) NOT NULL DEFAULT 1,
  `capacidad` int(11) NOT NULL DEFAULT 4,
  `forma` varchar(20) NOT NULL DEFAULT 'cuadrada',
  `pos_x` double NOT NULL DEFAULT 100,
  `pos_y` double NOT NULL DEFAULT 100,
  `ancho` double NOT NULL DEFAULT 70,
  `alto` double NOT NULL DEFAULT 70,
  `rotacion` double NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ── 3) Ligar cada reserva a una mesa del plano ───────────────────────
ALTER TABLE `reservas`
  ADD COLUMN IF NOT EXISTS `mesa_id` int(11) DEFAULT NULL AFTER `usuario_id`;

ALTER TABLE `reservas`
  ADD INDEX IF NOT EXISTS `idx_mesa_franja` (`mesa_id`, `fecha`, `hora`);
