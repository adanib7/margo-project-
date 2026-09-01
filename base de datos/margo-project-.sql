-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 07, 2026 at 01:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `margo-project-`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventario`
--

CREATE TABLE `inventario` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `categoria` varchar(30) NOT NULL DEFAULT 'general',
  `unidad` varchar(20) NOT NULL DEFAULT 'unidad',
  `stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `proveedor` varchar(120) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventario`
--

INSERT INTO `inventario` (`id`, `nombre`, `categoria`, `unidad`, `stock`, `stock_minimo`, `precio_unitario`, `proveedor`) VALUES
(1, 'Faba de la Granja', 'seco', 'kg', 18.00, 8.00, 6.50, 'Legumbres del Nalón'),
(2, 'Ternera asturiana', 'carne', 'kg', 12.50, 6.00, 14.90, 'Carnicería Cué'),
(3, 'Queso Afuega\'l Pitu', 'lacteo', 'kg', 3.20, 2.00, 19.00, 'Quesería La Peral'),
(4, 'Sidra natural DOP', 'bebida', 'botella', 96.00, 48.00, 3.20, 'Llagar Trabanco'),
(5, 'Chorizo asturiano', 'carne', 'kg', 5.00, 4.00, 11.50, 'Embutidos Nava'),
(6, 'Cebolla', 'verdura', 'kg', 22.00, 10.00, 1.10, 'Huerta El Sueve'),
(7, 'Aceite de oliva virgen', 'seco', 'l', 14.00, 6.00, 7.80, 'Distribuciones Uría'),
(8, 'Detergente lavavajillas', 'limpieza', 'caja', 2.00, 3.00, 24.00, 'Higiene Pro');

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `foto` varchar(500) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `rol` varchar(20) DEFAULT 'usuario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `google_id`, `email`, `nombre`, `foto`, `password`, `fecha_registro`, `rol`) VALUES
(1, '108964263815946922697', 'gbraito18@gmail.com', 'gbraito18', 'https://lh3.googleusercontent.com/a/ACg8ocLolr8VfhhUPL_gwrMuyxf8qoo4baF7rJ6S5QjhgBCKp5DIrA=s96-c', NULL, '2026-05-19 15:21:18', 'superadmin'),
(2, NULL, 'test@usuario.com', 'usuariotest', NULL, '$2y$10$Bd2PTwMx3UnNfLJriUdaHurqrvljyOM.4JAZkILfW3pqgb69wKpxG', '2026-05-21 22:13:18', 'usuario'),
(3, '106636717361695858223', 'gonzalobrait@gmail.com', 'gonzalobrait', 'https://lh3.googleusercontent.com/a/ACg8ocJPbElaAea1E-cNz5OYk5ZwuY_LDpOMakzJmeXXTcy0iN7odg=s96-c', NULL, '2026-05-22 16:07:39', 'usuario'),
(4, NULL, 'prueba@admin.com', 'Prueba ADMIN', NULL, '$2y$10$DbnTvR7tTs8.ajN.PSnNLuy0JwKcJgBatXrPjTmOVpdYD5EBOBNFq', '2026-05-27 00:21:54', 'admin'),
(5, NULL, 'margaritavega@gmail.com', 'Margo', NULL, '$2y$10$Qn7fQDfWW8qW0ehLCj3tsOm9b5hh1WGQkSZZ/BcJxvuyiJrwReHHa', '2026-06-11 14:23:50', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
