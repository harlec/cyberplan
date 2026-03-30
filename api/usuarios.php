<?php
// api/usuarios.php - CRUD de usuarios, config SMTP, asignaciones
require_once '../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = getDB();
    match(true) {
        $method==='GET'  && $action==='usuarios'          => getUsuarios($db),
        $method==='POST' && $action==='usuario'           => crearUsuario($db),
        $method==='PUT'  && $action==='usuario'           => actualizarUsuario($db),
        $method==='DELETE' && $action==='usuario'         => eliminarUsuario($db),
        $method==='GET'  && $action==='config'            => getConfig($db),
        $method==='POST' && $action==='config'            => saveConfig($db),
        $method==='GET'  && $action==='asignaciones'      => getAsignaciones($db),
        $method==='POST' && $action==='asignar'           => asignarUsuario($db),
        $method==='DELETE' && $action==='asignar'         => desasignarUsuario($db),
        $method==='POST' && $action==='test_email'        => testEmail($db),
        $method==='GET'  && $action==='email_log'         => getEmailLog($db),
        default => jsonResponse(['error' => 'Acción no válida'], 404)
    };
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

function getUsuarios(PDO $db): never {
    $stmt = $db->query("SELECT id, nombre, email, rol, activo, recibe_notificaciones, recibe_resumen_semanal, creado_en FROM usuarios ORDER BY rol, nombre");
    jsonResponse($stmt->fetchAll());
}

function crearUsuario(PDO $db): never {
    $d = json_decode(file_get_contents('php://input'), true);
    $db->prepare("INSERT INTO usuarios (nombre, email, rol, recibe_notificaciones, recibe_resumen_semanal) VALUES (?,?,?,?,?)")
       ->execute([trim($d['nombre']), trim($d['email']), $d['rol']??'responsable', (int)($d['recibe_notificaciones']??1), (int)($d['recibe_resumen_semanal']??1)]);
    jsonResponse(['id' => $db->lastInsertId(), 'message' => 'Usuario creado']);
}

function actualizarUsuario(PDO $db): never {
    $d = json_decode(file_get_contents('php://input'), true);
    $db->prepare("UPDATE usuarios SET nombre=?,email=?,rol=?,activo=?,recibe_notificaciones=?,recibe_resumen_semanal=? WHERE id=?")
       ->execute([trim($d['nombre']), trim($d['email']), $d['rol'], (int)$d['activo'], (int)$d['recibe_notificaciones'], (int)$d['recibe_resumen_semanal'], (int)$d['id']]);
    jsonResponse(['message' => 'Usuario actualizado']);
}

function eliminarUsuario(PDO $db): never {
    $id = (int)($_GET['id'] ?? 0);
    $db->prepare("UPDATE usuarios SET activo=0 WHERE id=?")->execute([$id]);
    jsonResponse(['message' => 'Usuario desactivado']);
}

function getConfig(PDO $db): never {
    $stmt = $db->query("SELECT clave, valor, descripcion FROM configuracion ORDER BY clave");
    $cfg = [];
    foreach ($stmt->fetchAll() as $r) $cfg[$r['clave']] = ['valor'=>$r['valor'], 'descripcion'=>$r['descripcion']];
    jsonResponse($cfg);
}

function saveConfig(PDO $db): never {
    $d = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)");
    foreach ($d as $k => $v) {
        if (!preg_match('/^[a-z_]{1,60}$/', $k)) continue;
        $stmt->execute([$k, $v]);
    }
    jsonResponse(['message' => 'Configuración guardada']);
}

function getAsignaciones(PDO $db): never {
    $actId = (int)($_GET['actividad_id'] ?? 0);
    if ($actId) {
        $stmt = $db->prepare("SELECT au.*, u.nombre, u.email, u.rol FROM actividad_usuarios au JOIN usuarios u ON au.usuario_id = u.id WHERE au.actividad_id = ?");
        $stmt->execute([$actId]);
    } else {
        $stmt = $db->query("SELECT au.*, u.nombre, u.email, a.codigo, a.nombre as act_nombre FROM actividad_usuarios au JOIN usuarios u ON au.usuario_id=u.id JOIN actividades a ON au.actividad_id=a.id ORDER BY a.codigo");
    }
    jsonResponse($stmt->fetchAll());
}

function asignarUsuario(PDO $db): never {
    $d = json_decode(file_get_contents('php://input'), true);
    $db->prepare("INSERT IGNORE INTO actividad_usuarios (actividad_id, usuario_id, rol_asignacion) VALUES (?,?,?)")
       ->execute([(int)$d['actividad_id'], (int)$d['usuario_id'], $d['rol_asignacion']??'responsable']);
    jsonResponse(['message' => 'Usuario asignado']);
}

function desasignarUsuario(PDO $db): never {
    $actId = (int)($_GET['actividad_id'] ?? 0);
    $usrId = (int)($_GET['usuario_id']   ?? 0);
    $db->prepare("DELETE FROM actividad_usuarios WHERE actividad_id=? AND usuario_id=?")->execute([$actId, $usrId]);
    jsonResponse(['message' => 'Asignación removida']);
}

function testEmail(PDO $db): never {
    require_once '../mail/Mailer.php';
    $d    = json_decode(file_get_contents('php://input'), true);
    $dest = [$d['email'] ?? ''];
    if (empty($dest[0])) throw new Exception('Email requerido');

    $fecha  = date('d/m/Y H:i');
    $html   = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:linear-gradient(135deg,#dce8ff 0%,#ece8ff 60%,#e0f0ff 100%);font-family:\'Segoe UI\',Helvetica,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0">
<tr><td align="center" style="padding:0 16px">
<table width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%">
  <tr><td style="background:#7c5cbf;border-radius:18px 18px 0 0;padding:28px 32px">
    <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.65);margin-bottom:8px">AUNOR &middot; Aleatica &middot; CyberPlan</div>
    <div style="font-size:22px;font-weight:800;color:#ffffff">Prueba de conexi&oacute;n SMTP</div>
    <div style="font-size:12px;color:rgba(255,255,255,.7);margin-top:5px">' . $fecha . '</div>
  </td></tr>
  <tr><td style="background:#ffffff;padding:28px 32px">
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-left:4px solid #2ecc71;border-radius:12px;padding:18px 20px;margin-bottom:18px">
      <div style="font-size:15px;font-weight:700;color:#15803d;margin-bottom:4px">Conexi&oacute;n exitosa</div>
      <div style="font-size:13px;color:#374151">La configuraci&oacute;n SMTP de CyberPlan funciona correctamente.</div>
    </div>
    <div style="background:#f7f8ff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px">
      <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#9ca3af;margin-bottom:6px">Informaci&oacute;n</div>
      <div style="font-size:12px;color:#6b7280">Si recibes este mensaje, el servidor SMTP est&aacute; correctamente configurado y CyberPlan puede enviar notificaciones y res&uacute;menes semanales.</div>
    </div>
  </td></tr>
  <tr><td style="background:#f0f2ff;border-radius:0 0 18px 18px;padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="font-size:11px;color:#9ca3af;margin:0">Correo de prueba &nbsp;&middot;&nbsp; <strong style="color:#7c5cbf">CyberPlan &nbsp;&middot;&nbsp; AUNOR &nbsp;&middot;&nbsp; Red Vial 4</strong></p>
  </td></tr>
</table></td></tr></table>
</body></html>';
    $mailer = new Mailer();
    $ok = $mailer->send($dest, '🔧 Prueba de conexión SMTP — CyberPlan', $html);
    jsonResponse(['success' => $ok, 'message' => $ok ? 'Correo enviado correctamente' : 'Error al enviar']);
}

function getEmailLog(PDO $db): never {
    $stmt = $db->query("SELECT * FROM email_log ORDER BY enviado_en DESC LIMIT 50");
    jsonResponse($stmt->fetchAll());
}
