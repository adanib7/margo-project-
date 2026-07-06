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
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
