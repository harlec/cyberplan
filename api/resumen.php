<?php
// api/resumen.php
require_once '../config.php';
require_once '../mail/Mailer.php';

header('Content-Type: application/json; charset=utf-8');

// Evitar timeout — el resumen tiene queries + conexión SMTP
set_time_limit(120);
ini_set('max_execution_time', 120);
ignore_user_abort(true); // continuar aunque el navegador cierre

set_error_handler(function($errno, $errstr) {
    // Solo capturar errores fatales, no warnings de sockets
    if ($errno === E_ERROR || $errno === E_PARSE) {
        echo json_encode(['success' => false, 'message' => "Error PHP: {$errstr}"]);
        exit;
    }
    return false; // dejar que PHP maneje el resto normalmente
});

try {
    $db = getDB();

    // 1. ¿Está activo?
    $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave='notif_resumen'");
    $stmt->execute();
    $activo = $stmt->fetchColumn();
    if ($activo === '0') {
        echo json_encode(['success' => false, 'message' => 'Resumen desactivado en Configuración. Actívalo y vuelve a intentar.']);
        exit;
    }

    // DEBUG — mostrar todos los usuarios y sus flags
    $debug = $db->query("SELECT id, nombre, email, rol, activo, recibe_resumen_semanal, recibe_notificaciones FROM usuarios")->fetchAll();

    // Destinatarios
    $destStmt = $db->query("SELECT nombre, email FROM usuarios WHERE activo=1 AND recibe_resumen_semanal=1");
    $dest = $destStmt->fetchAll();
    if (empty($dest)) {
        echo json_encode([
            'success' => false,
            'message' => 'No hay usuarios con recibe_resumen_semanal=1. Revisa los datos.',
            'debug_usuarios' => $dest,
            'todos_usuarios' => $debug,
        ]);
        exit;
    }

    // 3. ¿SMTP configurado?
    $smtpStmt = $db->prepare("SELECT valor FROM configuracion WHERE clave='smtp_usuario'");
    $smtpStmt->execute();
    $smtpUser = $smtpStmt->fetchColumn();
    if (empty($smtpUser)) {
        echo json_encode(['success' => false, 'message' => 'SMTP no configurado. Ve a Configuración e ingresa tus credenciales.']);
        exit;
    }

    // 4. Enviar
    $mailer = new Mailer();
    $result = $mailer->enviarResumenSemanal();

    if ($result['success']) {
        echo json_encode([
            'success'       => true,
            'message'       => "✅ Resumen enviado a {$result['destinatarios']} destinatario(s): " . implode(', ', $result['emails']),
            'destinatarios' => $result['destinatarios'],
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '❌ Falló el envío SMTP. Detalle: ' . $result['error'] . '. Revisa el Log de Correos.',
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
