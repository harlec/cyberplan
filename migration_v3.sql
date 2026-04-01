-- ============================================
-- MIGRACIÓN v3: Subtareas por actividad
-- CyberPlan - AUNOR / Aleatica
-- Ejecutar DESPUÉS de migration_v2.sql
-- ============================================

USE cyberplan;

CREATE TABLE IF NOT EXISTS subtareas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    nombre       VARCHAR(255) NOT NULL,
    completada   TINYINT(1) DEFAULT 0,
    orden        SMALLINT DEFAULT 0,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    INDEX idx_actividad (actividad_id)
);
