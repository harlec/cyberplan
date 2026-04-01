-- ============================================
-- MIGRACIÓN v3: Subtareas por instancia programada
-- CyberPlan - AUNOR / Aleatica
-- ============================================

USE cyberplan;

CREATE TABLE IF NOT EXISTS subtareas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    anio         YEAR NOT NULL,
    mes          TINYINT NOT NULL CHECK (mes BETWEEN 1 AND 12),
    nombre       VARCHAR(255) NOT NULL,
    completada   TINYINT(1) DEFAULT 0,
    orden        SMALLINT DEFAULT 0,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    INDEX idx_instancia (actividad_id, anio, mes)
);
