-- CyberPlan - Sistema de Control de Cronogramas de Ciberseguridad
-- AUNOR / Aleatica

CREATE DATABASE IF NOT EXISTS cyberplan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cyberplan;

-- Repositorios (carpetas de documentos)
CREATE TABLE repositorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ruta VARCHAR(500) NOT NULL,
    descripcion VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Actividades de ciberseguridad
CREATE TABLE actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repositorio_id INT,
    codigo VARCHAR(20) NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    responsable VARCHAR(50),
    anio YEAR NOT NULL DEFAULT 2025,
    categoria VARCHAR(50) DEFAULT 'F2',
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repositorio_id) REFERENCES repositorios(id) ON DELETE SET NULL,
    INDEX idx_codigo (codigo),
    INDEX idx_anio (anio)
);

-- Programación mensual (P = Programado, E = Ejecutado)
CREATE TABLE programacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    anio YEAR NOT NULL,
    mes TINYINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    tipo ENUM('P','E') NOT NULL DEFAULT 'P',
    estado ENUM('pendiente','completado','en_proceso','vencido') DEFAULT 'pendiente',
    observaciones TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    UNIQUE KEY uk_actividad_mes_tipo (actividad_id, anio, mes, tipo),
    INDEX idx_anio_mes (anio, mes)
);

-- Usuarios del sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin','responsable','viewer') DEFAULT 'viewer',
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- DATOS INICIALES
-- =============================================

INSERT INTO repositorios (ruta, descripcion) VALUES
('Aleatica, S.A.B. de C.V\\01_TI_AUNOR - Documentos\\011.Ciberseguridad\\00.Documentos-RRHH', 'Documentos de RRHH y Ciberseguridad');

INSERT INTO actividades (repositorio_id, codigo, nombre, responsable, anio, categoria) VALUES
(1, 'F3-001', 'Solicitud a Personas y Cultura listado de usuarios activos o dados de baja en el último semestre', 'RSRR', 2025, 'F3'),
(1, 'F3-002', 'Control de acceso lógico', 'RSRR', 2025, 'F3'),
(1, 'F2-013', 'Capacitación en Ciberseguridad', 'RSRR', 2025, 'F2'),
(1, 'F3-030', 'Revisión de Línea Base', NULL, 2025, 'F3'),
(1, 'F2-031', 'Revisión Procedimientos Mejora Continua', NULL, 2025, 'F2'),
(1, 'F3-007', 'Revisión de Privilegios de Acceso', 'RSRR', 2025, 'F3'),
(1, 'F2-004', 'Análisis de Vulnerabilidades', 'RSRR', 2025, 'F2'),
(1, 'F2-008', 'Gestión de Red', NULL, 2025, 'F2'),
(1, 'F2-008', 'Gestión de la Capacidad', NULL, 2025, 'F2'),
(1, 'F2-021', 'Revisión de Software y Aplicaciones', NULL, 2025, 'F2'),
(1, 'F2-023', 'Hardening de Servidores - Revisión de Medidas de Seguridad', NULL, 2025, 'F2'),
(1, 'F2-031', 'Reuniones de Coordinación para la mejora continua', NULL, 2025, 'F2'),
(1, 'F3-021', 'Actualización del cronograma de revisión', NULL, 2025, 'F3'),
(1, 'F3-023', 'Implementación y revisión de medidas de seguridad en servidores', NULL, 2025, 'F3');

-- Programaciones (P=Programado, E=Ejecutado) basadas en la imagen
INSERT INTO programacion (actividad_id, anio, mes, tipo, estado) VALUES
-- F3-001
(1, 2025, 11, 'P', 'pendiente'), (1, 2025, 12, 'E', 'completado'),
-- F3-002
(2, 2025, 4, 'P', 'completado'), (2, 2025, 11, 'P', 'pendiente'), (2, 2025, 5, 'E', 'completado'), (2, 2025, 12, 'E', 'pendiente'),
-- F2-013
(3, 2025, 2, 'P', 'completado'), (3, 2025, 8, 'P', 'pendiente'),
-- F3-030
(4, 2025, 3, 'P', 'en_proceso'), (4, 2025, 9, 'P', 'pendiente'), (4, 2025, 3, 'E', 'en_proceso'),
-- F2-031 Procedimientos
(5, 2025, 3, 'P', 'pendiente'), (5, 2025, 5, 'P', 'pendiente'), (5, 2025, 9, 'P', 'pendiente'), (5, 2025, 12, 'P', 'pendiente'),
-- F3-007
(6, 2025, 2, 'P', 'completado'), (6, 2025, 6, 'P', 'pendiente'),
-- F2-004
(7, 2025, 3, 'P', 'pendiente'), (7, 2025, 7, 'P', 'pendiente'), (7, 2025, 11, 'P', 'pendiente'),
-- F2-008 Red
(8, 2025, 3, 'P', 'pendiente'), (8, 2025, 9, 'P', 'pendiente'),
-- F2-008 Capacidad
(9, 2025, 3, 'P', 'pendiente'), (9, 2025, 9, 'P', 'pendiente'),
-- F2-021
(10, 2025, 6, 'P', 'pendiente'),
-- F2-023
(11, 2025, 2, 'P', 'completado'), (11, 2025, 8, 'P', 'pendiente'),
-- F2-031 Reuniones
(12, 2025, 4, 'P', 'pendiente'), (12, 2025, 10, 'P', 'pendiente'),
-- F3-021
(13, 2025, 2, 'P', 'completado'),
-- F3-023
(14, 2025, 5, 'P', 'pendiente'), (14, 2025, 11, 'P', 'pendiente');

INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES
('Hugo Reyes', 'hugo@aunor.pe', '$2y$12$placeholder_hash_here', 'admin'),
('RSRR Usuario', 'rsrr@aunor.pe', '$2y$12$placeholder_hash_here', 'responsable');
