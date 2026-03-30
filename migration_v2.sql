-- ============================================
-- MIGRACIÓN: Sistema de Usuarios y Notificaciones
-- CyberPlan - AUNOR / Aleatica
-- Ejecutar DESPUÉS del schema.sql inicial
-- ============================================

USE cyberplan;

-- Tabla de usuarios del sistema (reemplaza la anterior)
DROP TABLE IF EXISTS usuarios;
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    rol ENUM('admin','lider_ti','responsable','viewer') DEFAULT 'responsable',
    activo TINYINT(1) DEFAULT 1,
    recibe_notificaciones TINYINT(1) DEFAULT 1,
    recibe_resumen_semanal TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Configuración SMTP y del sistema
CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(60) PRIMARY KEY,
    valor TEXT,
    descripcion VARCHAR(255),
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Asignación de usuarios a actividades (muchos a muchos)
CREATE TABLE IF NOT EXISTS actividad_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    usuario_id INT NOT NULL,
    rol_asignacion ENUM('responsable','notificado') DEFAULT 'responsable',
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uk_act_usr (actividad_id, usuario_id)
);

-- Log de correos enviados
CREATE TABLE IF NOT EXISTS email_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('ejecucion','resumen_semanal','prueba') NOT NULL,
    destinatarios TEXT,
    asunto VARCHAR(255),
    estado ENUM('enviado','error') DEFAULT 'enviado',
    error_msg TEXT,
    enviado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── DATOS INICIALES ───────────────────────────

INSERT INTO usuarios (nombre, email, rol, recibe_notificaciones, recibe_resumen_semanal) VALUES
('Hugo Reyes',   'hugo.reyes@aunor.pe',   'lider_ti',    1, 1),
('RSRR Usuario', 'rsrr@aunor.pe',         'responsable', 1, 1);

-- Configuración SMTP Office 365
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('smtp_host',        'smtp.office365.com',      'Servidor SMTP de Office 365'),
('smtp_port',        '587',                      'Puerto SMTP (587 para TLS)'),
('smtp_usuario',     'tu.correo@empresa.pe',    'Correo corporativo remitente'),
('smtp_password',    '',                         'Contraseña del correo corporativo'),
('smtp_nombre',      'CyberPlan - AUNOR',        'Nombre que aparece como remitente'),
('resumen_dia',      '1',                        'Día del resumen semanal (1=Lunes)'),
('resumen_hora',     '08:00',                    'Hora del resumen semanal (HH:MM)'),
('app_url',          'http://localhost:8000',    'URL base de la aplicación'),
('notif_ejecucion',  '1',                        'Activar notificación al marcar E (1/0)'),
('notif_resumen',    '1',                        'Activar resumen semanal (1/0)');
