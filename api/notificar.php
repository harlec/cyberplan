<?php
// api/notificar.php - Disparador de notificación al marcar E
require_once '../config.php';
require_once '../mail/Mailer.php';

header('Content-Type: application/json; charset=utf-8');

$d = json_decode(file_get_contents('php://input'), true);
$actividadId = (int)($d['actividad_id'] ?? 0);
$mes         = (int)($d['mes'] ?? 0);

if (!$actividadId || !$mes) {
    jsonResponse(['error' => 'Parámetros inválidos'], 400);
}

try {
    $db = getDB();

    // Verificar que notificaciones estén activas
    $cfgStmt = $db->prepare("SELECT valor FROM configuracion WHERE clave='notif_ejecucion'");
    $cfgStmt->execute();
    $activa = $cfgStmt->fetchColumn();
    if ($activa === '0') jsonResponse(['skipped' => true, 'reason' => 'Notificaciones desactivadas']);

    // Obtener datos de la actividad
    $stmt = $db->prepare("SELECT * FROM actividades WHERE id = :id");
    $stmt->execute([':id' => $actividadId]);
    $actividad = $stmt->fetch();
    if (!$actividad) jsonResponse(['error' => 'Actividad no encontrada'], 404);

    $mailer       = new Mailer();
    $destinatarios = $mailer->getDestinatariosActividad($actividadId);

    if (empty($destinatarios)) {
        jsonResponse(['skipped' => true, 'reason' => 'Sin destinatarios configurados para esta actividad']);
    }

    $ok = $mailer->enviarNotificacionEjecucion($actividad, $mes, $destinatarios);
    jsonResponse(['success' => $ok, 'destinatarios' => count($destinatarios)]);

} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
