-- ============================================
-- MIGRACIÓN v4: Subtareas como plantillas por actividad
--   + estado de completado por instancia (anio+mes)
-- CyberPlan — AUNOR / Aleatica
-- ============================================

USE cyberplan;

-- 1. Eliminar tabla vieja (tenía anio/mes embebidos)
DROP TABLE IF EXISTS subtareas;

-- 2. Subtareas como plantillas por actividad (sin instancia)
CREATE TABLE IF NOT EXISTS subtareas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    nombre       VARCHAR(255) NOT NULL,
    orden        SMALLINT DEFAULT 0,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    INDEX idx_actividad (actividad_id)
);

-- 3. Estado de completado por instancia planificada
CREATE TABLE IF NOT EXISTS subtarea_instancias (
    subtarea_id  INT NOT NULL,
    anio         YEAR NOT NULL,
    mes          TINYINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    completada   TINYINT(1) DEFAULT 0,
    PRIMARY KEY (subtarea_id, anio, mes),
    FOREIGN KEY (subtarea_id) REFERENCES subtareas(id) ON DELETE CASCADE
);
