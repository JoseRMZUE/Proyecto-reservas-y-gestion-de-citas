-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-09-2026 a las 23:29:55
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
-- Base de datos: `stay_beauty`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id_auditoria` int(11) NOT NULL,
  `tabla_afectada` varchar(50) NOT NULL,
  `id_registro` int(11) NOT NULL,
  `operacion` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `detalle` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id_auditoria`, `tabla_afectada`, `id_registro`, `operacion`, `id_usuario`, `fecha`, `detalle`) VALUES
(1, 'reserva', 1, 'INSERT', NULL, '2026-09-22 23:21:27', 'estado= | especialista=3 | fecha=2026-09-24 14:00:00'),
(2, 'reserva', 2, 'INSERT', NULL, '2026-09-22 23:38:28', 'estado= | especialista=4 | fecha=2026-09-25 18:00:00'),
(3, 'reserva', 1, 'UPDATE', NULL, '2026-09-22 23:54:20', 'estado: ->CONFIRMADA | fecha: 2026-09-24 14:00:00->2026-09-24 14:00:00'),
(4, 'reserva', 2, 'UPDATE', NULL, '2026-09-22 23:54:33', 'estado: ->CONFIRMADA | fecha: 2026-09-25 18:00:00->2026-09-25 18:00:00'),
(5, 'reserva', 1, 'UPDATE', NULL, '2026-09-22 23:55:28', 'estado: CONFIRMADA->ATENDIDA | fecha: 2026-09-24 14:00:00->2026-09-24 14:00:00'),
(6, 'reserva', 1, 'UPDATE', NULL, '2026-09-22 23:56:34', 'estado: ATENDIDA->ATENDIDA | fecha: 2026-09-24 14:00:00->2026-08-24 14:00:00'),
(7, 'reserva', 4, 'INSERT', NULL, '2026-09-23 00:00:10', 'estado= | especialista=4 | fecha=2026-09-25 17:30:00'),
(8, 'reserva', 4, 'DELETE', NULL, '2026-09-23 00:12:27', 'registro eliminado'),
(9, 'reserva', 7, 'INSERT', NULL, '2026-09-23 00:14:59', 'estado=CONFIRMADA | especialista=4 | fecha=2026-09-25 09:00:00'),
(10, 'reserva', 8, 'INSERT', NULL, '2026-09-23 01:42:49', 'estado=CONFIRMADA | especialista=3 | fecha=2026-09-24 11:00:00'),
(11, 'reserva', 8, 'UPDATE', NULL, '2026-09-23 01:47:46', 'estado: CONFIRMADA->CANCELADA | fecha: 2026-09-24 11:00:00->2026-09-24 11:00:00'),
(12, 'reserva', 9, 'INSERT', NULL, '2026-09-23 01:48:53', 'estado=CONFIRMADA | especialista=1 | fecha=2026-09-24 08:30:00'),
(13, 'reserva', 10, 'INSERT', NULL, '2026-09-23 11:37:52', 'estado=CONFIRMADA | especialista=6 | fecha=2026-09-24 13:30:00'),
(14, 'reserva', 2, 'UPDATE', NULL, '2026-09-23 13:46:07', 'estado: CONFIRMADA->CANCELADA | fecha: 2026-09-25 18:00:00->2026-09-25 18:00:00'),
(15, 'reserva', 2, 'UPDATE', 3, '2026-09-23 13:46:07', 'estado: CONFIRMADA->CANCELADA | fecha: 2026-09-25 18:00:00->2026-09-25 18:00:00'),
(16, 'reserva', 7, 'UPDATE', NULL, '2026-09-23 13:46:10', 'estado: CONFIRMADA->EN_PROCESO | fecha: 2026-09-25 09:00:00->2026-09-25 09:00:00'),
(17, 'reserva', 7, 'UPDATE', 3, '2026-09-23 13:46:11', 'estado: CONFIRMADA->EN_PROCESO | fecha: 2026-09-25 09:00:00->2026-09-25 09:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificacion`
--

CREATE TABLE `calificacion` (
  `id_calificacion` int(11) NOT NULL,
  `id_reserva` int(11) NOT NULL,
  `puntuacion` int(11) NOT NULL,
  `comentario` varchar(500) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_salon`
--

CREATE TABLE `configuracion_salon` (
  `id_configuracion` int(11) NOT NULL,
  `horas_minimas_cancelacion` int(11) NOT NULL DEFAULT 24
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion_salon`
--

INSERT INTO `configuracion_salon` (`id_configuracion`, `horas_minimas_cancelacion`) VALUES
(1, 24);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialista`
--

CREATE TABLE `especialista` (
  `id_especialista` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `especialista`
--

INSERT INTO `especialista` (`id_especialista`, `nombre`, `especialidad`, `activo`) VALUES
(1, 'Camila Morales', 'Cosmiatría y Limpieza Facial', 1),
(2, 'Valeria Gómez', 'Masajes Corporales y Masoterapia', 1),
(3, 'Andrea Ríos', 'Tratamientos Faciales y Corporales', 1),
(4, 'Carlos Mendoza', 'Dermatología Estética', 1),
(5, 'Esteban Gómez', 'Estilista Profesional, Cortes y Colorimetría', 1),
(6, 'Carolina Parra', 'Peluquería Femenina, Peinados y Balayage', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_estado`
--

CREATE TABLE `historial_estado` (
  `id_historial` int(11) NOT NULL,
  `id_reserva` int(11) NOT NULL,
  `tipo_cambio` enum('CAMBIO_ESTADO','REPROGRAMACION') NOT NULL,
  `estado_anterior` enum('CONFIRMADA','EN_PROCESO','ATENDIDA','CANCELADA') DEFAULT NULL,
  `estado_nuevo` enum('CONFIRMADA','EN_PROCESO','ATENDIDA','CANCELADA') DEFAULT NULL,
  `fecha_anterior` datetime DEFAULT NULL,
  `fecha_nueva` datetime DEFAULT NULL,
  `fecha_cambio` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario_cambio` int(11) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_estado`
--

INSERT INTO `historial_estado` (`id_historial`, `id_reserva`, `tipo_cambio`, `estado_anterior`, `estado_nuevo`, `fecha_anterior`, `fecha_nueva`, `fecha_cambio`, `id_usuario_cambio`, `motivo`) VALUES
(1, 8, 'CAMBIO_ESTADO', 'CONFIRMADA', 'CANCELADA', NULL, NULL, '2026-09-23 01:47:46', 6, 'Cancelada por el cliente'),
(2, 2, 'CAMBIO_ESTADO', 'CONFIRMADA', 'CANCELADA', NULL, NULL, '2026-09-23 13:46:07', 3, 'Cambiado por el administrador'),
(3, 7, 'CAMBIO_ESTADO', 'CONFIRMADA', 'EN_PROCESO', NULL, NULL, '2026-09-23 13:46:11', 3, 'Cambiado por el administrador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horario_atencion`
--

CREATE TABLE `horario_atencion` (
  `id_horario` int(11) NOT NULL,
  `id_configuracion` int(11) NOT NULL,
  `dia_semana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') NOT NULL,
  `hora_apertura` time DEFAULT NULL,
  `hora_cierre` time DEFAULT NULL,
  `cerrado` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `horario_atencion`
--

INSERT INTO `horario_atencion` (`id_horario`, `id_configuracion`, `dia_semana`, `hora_apertura`, `hora_cierre`, `cerrado`) VALUES
(1, 1, 'LUNES', '08:00:00', '19:00:00', 0),
(2, 1, 'MARTES', '08:00:00', '19:00:00', 0),
(3, 1, 'MIERCOLES', '08:00:00', '19:00:00', 0),
(4, 1, 'JUEVES', '08:00:00', '19:00:00', 0),
(5, 1, 'VIERNES', '08:00:00', '19:00:00', 0),
(6, 1, 'SABADO', '09:00:00', '18:00:00', 0),
(7, 1, 'DOMINGO', '10:00:00', '15:00:00', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reserva`
--

CREATE TABLE `reserva` (
  `id_reserva` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_servicio` int(11) NOT NULL,
  `id_especialista` int(11) NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `estado_actual` enum('CONFIRMADA','EN_PROCESO','ATENDIDA','CANCELADA') NOT NULL DEFAULT 'CONFIRMADA',
  `precio_reservado` decimal(10,2) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `slot_activo` tinyint(4) GENERATED ALWAYS AS (if(`estado_actual` <> 'CANCELADA',1,NULL)) STORED,
  `recordatorio_enviado` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reserva`
--

INSERT INTO `reserva` (`id_reserva`, `id_cliente`, `id_servicio`, `id_especialista`, `fecha_hora`, `estado_actual`, `precio_reservado`, `fecha_creacion`, `fecha_actualizacion`, `recordatorio_enviado`) VALUES
(1, 2, 1, 3, '2026-08-24 14:00:00', 'ATENDIDA', 85000.00, '2026-08-22 23:21:27', '2026-09-22 23:56:34', 0),
(2, 2, 2, 4, '2026-09-25 18:00:00', 'CANCELADA', 120000.00, '2026-09-22 23:38:28', '2026-09-23 13:46:07', 0),
(7, 4, 2, 4, '2026-09-25 09:00:00', 'EN_PROCESO', 120000.00, '2026-09-23 00:14:59', '2026-09-23 13:46:10', 0),
(8, 6, 9, 3, '2026-09-24 11:00:00', 'CANCELADA', 140000.00, '2026-09-23 01:42:49', '2026-09-23 01:47:46', 0),
(9, 6, 1, 1, '2026-09-24 08:30:00', 'CONFIRMADA', 85000.00, '2026-09-23 01:48:53', '2026-09-23 01:48:53', 0),
(10, 6, 2, 6, '2026-09-24 13:30:00', 'CONFIRMADA', 120000.00, '2026-09-23 11:37:52', '2026-09-23 11:37:52', 0);

--
-- Disparadores `reserva`
--
DELIMITER $$
CREATE TRIGGER `tr_aud_reserva_delete` AFTER DELETE ON `reserva` FOR EACH ROW BEGIN
  INSERT INTO auditoria (tabla_afectada, id_registro, operacion, id_usuario, detalle)
  VALUES ('reserva', OLD.id_reserva, 'DELETE', @usuario_actual, 'registro eliminado');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_aud_reserva_insert` AFTER INSERT ON `reserva` FOR EACH ROW BEGIN
  INSERT INTO auditoria (tabla_afectada, id_registro, operacion, id_usuario, detalle)
  VALUES ('reserva', NEW.id_reserva, 'INSERT', @usuario_actual,
          CONCAT('estado=', NEW.estado_actual,
                 ' | especialista=', NEW.id_especialista,
                 ' | fecha=', NEW.fecha_hora));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_aud_reserva_update` AFTER UPDATE ON `reserva` FOR EACH ROW BEGIN
  INSERT INTO auditoria (tabla_afectada, id_registro, operacion, id_usuario, detalle)
  VALUES ('reserva', NEW.id_reserva, 'UPDATE', @usuario_actual,
          CONCAT('estado: ', OLD.estado_actual, '->', NEW.estado_actual,
                 ' | fecha: ', OLD.fecha_hora, '->', NEW.fecha_hora));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id_rol`, `nombre_rol`, `descripcion`) VALUES
(1, 'Cliente', 'Rol con acceso al panel de clientes'),
(2, 'Administrador', 'Rol con acceso total al panel de administración');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicio`
--

CREATE TABLE `servicio` (
  `id_servicio` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `categoria` enum('FACIAL','CORPORAL','CAPILAR','MULTIPLE') NOT NULL,
  `tipo_servicio` enum('SIMPLE','PAQUETE') NOT NULL DEFAULT 'SIMPLE',
  `precio_actual` decimal(10,2) NOT NULL,
  `duracion_minutos` int(11) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `servicio`
--

INSERT INTO `servicio` (`id_servicio`, `nombre`, `descripcion`, `categoria`, `tipo_servicio`, `precio_actual`, `duracion_minutos`, `activo`) VALUES
(1, 'Limpieza Facial Profunda', 'Higienización, exfoliación, extracción e hidratación profunda con alta frecuencia.', 'FACIAL', 'SIMPLE', 85000.00, 60, 1),
(2, 'Peeling Químico', 'Exfoliación química para renovar capas superficiales de la piel.', 'FACIAL', 'SIMPLE', 120000.00, 45, 1),
(3, 'Masaje Reductivo Localizado', 'Masaje moldeador e hidrolipoclasia no invasiva.', 'CORPORAL', 'SIMPLE', 95000.00, 60, 1),
(4, 'Masaje Relajante con Piedras Volcánicas', 'Terapia de relajación muscular con aceites esenciales y piedras calientes.', 'CORPORAL', 'SIMPLE', 110000.00, 75, 1),
(5, 'Paquete VIP Renovación Total', 'Limpieza facial profunda + Masaje relajante + Velo de colágeno.', 'MULTIPLE', 'PAQUETE', 220000.00, 120, 1),
(6, 'Corte Dama y Cepillado', 'Corte personalizado según preferencias del cliente, incluye lavado con shampoo nutritivo y cepillado final.', 'CAPILAR', 'SIMPLE', 55000.00, 45, 1),
(7, 'Corte Caballero y Perfilado', 'Corte masculino moderno o clásico, incluye perfilado de cejas/patillas y lavado posterior.', 'CAPILAR', 'SIMPLE', 35000.00, 30, 1),
(8, 'Corte de Puntas y Bordenterapia', 'Eliminación de puntas horquilladas y dañadas sin perder el largo del cabello.', 'CAPILAR', 'SIMPLE', 40000.00, 30, 1),
(9, 'Tinte Completo y Baño de Color', 'Coloración uniforme con productos de alta gama bajos en amoníaco e hidratación ligera.', 'CAPILAR', 'SIMPLE', 140000.00, 90, 1),
(10, 'Balayage / Iluminación Capilar', 'Técnica de decoloración degradada personalizada, incluye matizante y tratamiento protector de fibra.', 'CAPILAR', 'SIMPLE', 260000.00, 180, 1),
(11, 'Peinado Social y Ondas', 'Peinado profesional para eventos especiales (ondas al agua, recogidos o peinado estructurado).', 'CAPILAR', 'SIMPLE', 75000.00, 60, 1),
(12, 'Combo Cambio de Look Total', 'Incluye asesoría de imagen + Corte + Tinte o Balayage + Cepillado + Mascarilla hidratante.', 'CAPILAR', 'PAQUETE', 290000.00, 180, 1);

--
-- Disparadores `servicio`
--
DELIMITER $$
CREATE TRIGGER `tr_aud_servicio_update` AFTER UPDATE ON `servicio` FOR EACH ROW BEGIN
  INSERT INTO auditoria (tabla_afectada, id_registro, operacion, id_usuario, detalle)
  VALUES ('servicio', NEW.id_servicio, 'UPDATE', @usuario_actual,
          CONCAT('precio: ', OLD.precio_actual, '->', NEW.precio_actual,
                 ' | activo: ', OLD.activo, '->', NEW.activo));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `token_recuperacion`
--

CREATE TABLE `token_recuperacion` (
  `id_token` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_expiracion` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `id_rol`, `nombre`, `correo`, `contrasena_hash`, `telefono`, `fecha_registro`, `activo`) VALUES
(2, 1, 'Johan Puerto', 'jojanpuerto2007@gmail.com', '$2y$10$skgN04t1zvq0HpymKMJODuSQbL9pYSLTfAUOAlR4LxgUanMosY/bK', '3023793560', '2026-09-21 19:46:03', 1),
(3, 2, 'Johan', 'jpuerto@staybeauty.com', '$2y$10$1KsnkC2OrR7litFskPklPeXYhXvm63iABEXhz3aqQmnhgF.c1/PH2', '3023793560', '2026-09-21 20:28:04', 1),
(4, 1, 'brayan', 'brayan@gmail.com', '$2y$10$wPJGAWvObP4BUagReZXQqOIQql19mEqTymGZnJ1Fgt1PmzcO3Etwq', '3000000000', '2026-09-21 20:29:40', 1),
(5, 1, 'jose', 'devid300@hotmail.com', '$2y$10$Dt9D2rbQqnPpQPMSNPBPkOCfkIQWLMO.zZXDuZP.j.yMBguyRFQDm', '30000000', '2026-09-21 21:35:35', 1),
(6, 1, 'jose', 'josemen1997@gmail.com', '$2y$10$7hxQNf/VhNANjyDeel2OGu/BDNe7W7dk/e20PtHYFVtbi03DJwoKe', '3203923090', '2026-09-23 01:41:37', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id_auditoria`),
  ADD KEY `idx_auditoria_tabla_fecha` (`tabla_afectada`,`fecha`),
  ADD KEY `fk_aud_usuario` (`id_usuario`);

--
-- Indices de la tabla `calificacion`
--
ALTER TABLE `calificacion`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD UNIQUE KEY `uq_calif_reserva` (`id_reserva`);

--
-- Indices de la tabla `configuracion_salon`
--
ALTER TABLE `configuracion_salon`
  ADD PRIMARY KEY (`id_configuracion`);

--
-- Indices de la tabla `especialista`
--
ALTER TABLE `especialista`
  ADD PRIMARY KEY (`id_especialista`);

--
-- Indices de la tabla `historial_estado`
--
ALTER TABLE `historial_estado`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `fk_hist_reserva` (`id_reserva`),
  ADD KEY `fk_hist_usuario` (`id_usuario_cambio`);

--
-- Indices de la tabla `horario_atencion`
--
ALTER TABLE `horario_atencion`
  ADD PRIMARY KEY (`id_horario`),
  ADD UNIQUE KEY `uq_horario_dia` (`id_configuracion`,`dia_semana`);

--
-- Indices de la tabla `reserva`
--
ALTER TABLE `reserva`
  ADD PRIMARY KEY (`id_reserva`),
  ADD UNIQUE KEY `uq_reserva_horario` (`id_especialista`,`fecha_hora`,`slot_activo`),
  ADD KEY `idx_reserva_estado_fecha` (`estado_actual`,`fecha_hora`),
  ADD KEY `idx_reserva_cliente_fecha` (`id_cliente`,`fecha_hora`),
  ADD KEY `fk_reserva_servicio` (`id_servicio`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indices de la tabla `servicio`
--
ALTER TABLE `servicio`
  ADD PRIMARY KEY (`id_servicio`);

--
-- Indices de la tabla `token_recuperacion`
--
ALTER TABLE `token_recuperacion`
  ADD PRIMARY KEY (`id_token`),
  ADD UNIQUE KEY `uq_token` (`token`),
  ADD KEY `fk_token_usuario` (`id_usuario`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuario_correo` (`correo`),
  ADD KEY `fk_usuario_rol` (`id_rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id_auditoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `calificacion`
--
ALTER TABLE `calificacion`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion_salon`
--
ALTER TABLE `configuracion_salon`
  MODIFY `id_configuracion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `especialista`
--
ALTER TABLE `especialista`
  MODIFY `id_especialista` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `historial_estado`
--
ALTER TABLE `historial_estado`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `horario_atencion`
--
ALTER TABLE `horario_atencion`
  MODIFY `id_horario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `reserva`
--
ALTER TABLE `reserva`
  MODIFY `id_reserva` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `servicio`
--
ALTER TABLE `servicio`
  MODIFY `id_servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `token_recuperacion`
--
ALTER TABLE `token_recuperacion`
  MODIFY `id_token` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `fk_aud_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `calificacion`
--
ALTER TABLE `calificacion`
  ADD CONSTRAINT `fk_calif_reserva` FOREIGN KEY (`id_reserva`) REFERENCES `reserva` (`id_reserva`);

--
-- Filtros para la tabla `historial_estado`
--
ALTER TABLE `historial_estado`
  ADD CONSTRAINT `fk_hist_reserva` FOREIGN KEY (`id_reserva`) REFERENCES `reserva` (`id_reserva`),
  ADD CONSTRAINT `fk_hist_usuario` FOREIGN KEY (`id_usuario_cambio`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `horario_atencion`
--
ALTER TABLE `horario_atencion`
  ADD CONSTRAINT `fk_horario_config` FOREIGN KEY (`id_configuracion`) REFERENCES `configuracion_salon` (`id_configuracion`);

--
-- Filtros para la tabla `reserva`
--
ALTER TABLE `reserva`
  ADD CONSTRAINT `fk_reserva_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `fk_reserva_especialista` FOREIGN KEY (`id_especialista`) REFERENCES `especialista` (`id_especialista`),
  ADD CONSTRAINT `fk_reserva_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`);

--
-- Filtros para la tabla `token_recuperacion`
--
ALTER TABLE `token_recuperacion`
  ADD CONSTRAINT `fk_token_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
