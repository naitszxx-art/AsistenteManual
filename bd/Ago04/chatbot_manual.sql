-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-08-2026 a las 14:31:02
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `chatbot_manual`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conversaciones`
--

CREATE TABLE `conversaciones` (
  `id` int(11) NOT NULL,
  `pregunta` text NOT NULL,
  `respuesta` longtext NOT NULL,
  `ip_usuario` varchar(45) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `conversaciones`
--

INSERT INTO `conversaciones` (`id`, `pregunta`, `respuesta`, `ip_usuario`, `fecha`) VALUES
(1, 'hola', 'Esta es una respuesta de prueba del sistema.', '::1', '2026-07-30 08:16:43'),
(2, 'hola', 'Esta es una respuesta de prueba del sistema.', '::1', '2026-07-30 08:16:52'),
(3, 'hola', 'Esta es una respuesta de prueba del sistema.', '::1', '2026-07-30 08:16:57'),
(4, 'hola', 'No fue posible obtener una respuesta.', '::1', '2026-07-30 08:23:12'),
(5, '¿que es una computadora?', 'No fue posible obtener una respuesta.', '::1', '2026-07-30 08:23:47'),
(6, '¿que es una computadora?', 'No fue posible obtener una respuesta.', '::1', '2026-07-30 08:35:12'),
(7, '¿que es una computadora?', 'No fue posible obtener una respuesta.', '::1', '2026-07-30 08:35:19'),
(8, '¿que es una computadora?', 'No fue posible obtener una respuesta.', '::1', '2026-07-30 08:36:46'),
(9, '¿que es una computadora?', 'No fue posible obtener una respuesta.', '::1', '2026-07-30 08:36:51'),
(10, '¿que es una computadora?', 'Error 404: This model models/gemini-2.5-flash-lite is no longer available to new users. Please update your code to use a newer model for the latest features and improvements.', '::1', '2026-07-30 08:47:25'),
(11, '¿que es una computadora?', 'Error 404: This model models/gemini-2.5-flash-lite is no longer available to new users. Please update your code to use a newer model for the latest features and improvements.', '::1', '2026-07-30 08:47:31');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
