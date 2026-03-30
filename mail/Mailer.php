<?php
// mail/Mailer.php - SMTP con SSL directo puerto 465 (mismo método que cámaras Dahua)
require_once __DIR__ . '/../config.php';

class Mailer {

    private PDO $db;
    private array $cfg = [];

    public function __construct() {
        $this->db  = getDB();
        $this->cfg = $this->loadConfig();
    }

    private function loadConfig(): array {
        $stmt = $this->db->query("SELECT clave, valor FROM configuracion");
        $cfg  = [];
        foreach ($stmt->fetchAll() as $row) {
            $cfg[$row['clave']] = $row['valor'];
        }
        return $cfg;
    }

    // ═══════════════════════════════════════════
    // ENVÍO SMTP — SSL directo puerto 465
    // Mismo método que usan las cámaras Dahua/Hikvision
    // ═══════════════════════════════════════════
    public function send(array $to, string $subject, string $htmlBody): bool {
        $host   = $this->cfg['smtp_host']     ?? 'smtp.office365.com';
        $port   = (int)($this->cfg['smtp_port'] ?? 465);
        $user   = $this->cfg['smtp_usuario']  ?? '';
        $pass   = $this->cfg['smtp_password'] ?? '';
        $from   = $user;
        $nombre = $this->cfg['smtp_nombre']   ?? 'CyberPlan';

        if (empty($user) || empty($pass)) {
            $this->logEmail('prueba', $to, $subject, 'error', 'SMTP no configurado');
            return false;
        }

        $boundary = '----=_Part_' . md5(uniqid('', true));
        $toStr    = implode(', ', $to);
        $date     = date('r');

        // Cabeceras del mensaje
        $headers  = "Date: {$date}\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($nombre) . "?= <{$from}>\r\n";
        $headers .= "To: {$toStr}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: CyberPlan-AUNOR/2.0\r\n";

        // Cuerpo multipart (texto plano + HTML)
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode(strip_tags($htmlBody))) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        try {
            // ── Conexión SSL directa (igual que Dahua) ──────────────
            // Las cámaras Dahua usan ssl:// en lugar de tcp:// + STARTTLS
            $ctx = stream_context_create(['ssl' => [
                'verify_peer'       => false,   // igual que cámaras en redes internas
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]]);

            // ssl:// abre SSL desde el inicio, sin negociación STARTTLS
            $sock = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errno, $errstr, 30,
                STREAM_CLIENT_CONNECT, $ctx
            );

            if (!$sock) {
                // Fallback: intentar con STARTTLS en 587 si falla 465
                $sock = $this->conectarStartTLS($host, $user, $pass, $errno, $errstr, $ctx);
                if (!$sock) throw new Exception("No se pudo conectar: {$errstr} ({$errno})");
            }

            stream_set_timeout($sock, 10);

            $this->smtpRead($sock);                             // 220 banner
            $this->smtpCmd($sock, "EHLO " . gethostname());    // EHLO
            $this->smtpRead($sock);

            // AUTH LOGIN
            $this->smtpCmd($sock, "AUTH LOGIN");
            $this->smtpRead($sock);
            $this->smtpCmd($sock, base64_encode($user));
            $this->smtpRead($sock);
            $this->smtpCmd($sock, base64_encode($pass));
            $auth = $this->smtpRead($sock);

            if (!str_starts_with(trim($auth), '235')) {
                throw new Exception("Autenticación fallida ({$auth}). Verifica usuario/contraseña.");
            }

            // MAIL FROM
            $this->smtpCmd($sock, "MAIL FROM:<{$from}>");
            $this->smtpRead($sock);

            // RCPT TO por cada destinatario
            foreach ($to as $recipient) {
                $email = trim(preg_replace('/.*<(.+)>.*/', '$1', $recipient));
                $this->smtpCmd($sock, "RCPT TO:<{$email}>");
                $this->smtpRead($sock);
            }

            // DATA
            $this->smtpCmd($sock, "DATA");
            $this->smtpRead($sock);
            fwrite($sock, $headers . "\r\n" . $body . "\r\n.\r\n");
            $resp = $this->smtpRead($sock);

            $this->smtpCmd($sock, "QUIT");
            fclose($sock);

            if (!str_starts_with(trim($resp), '250')) {
                throw new Exception("Error DATA: {$resp}");
            }

            $this->logEmail('ejecucion', $to, $subject, 'enviado');
            return true;

        } catch (Exception $e) {
            $this->logEmail('ejecucion', $to, $subject, 'error', $e->getMessage());
            error_log("[CyberPlan Mailer] " . $e->getMessage());
            return false;
        }
    }

    // ── Fallback STARTTLS 587 ───────────────────────────────────────
    private function conectarStartTLS(string $host, string $user, string $pass, int &$errno, string &$errstr, $ctx) {
        $sock = @stream_socket_client("tcp://{$host}:587", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) return null;
        $this->smtpRead($sock);
        $this->smtpCmd($sock, "EHLO " . gethostname());
        $this->smtpRead($sock);
        $this->smtpCmd($sock, "STARTTLS");
        $this->smtpRead($sock);
        stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        return $sock;
    }

    private function smtpCmd($sock, string $cmd): void {
        fwrite($sock, $cmd . "\r\n");
    }

    private function smtpRead($sock): string {
        $resp = '';
        while ($line = fgets($sock, 512)) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $resp;
    }

    private function logEmail(string $tipo, array $to, string $asunto, string $estado, string $err = ''): void {
        try {
            $this->db->prepare("INSERT INTO email_log (tipo, destinatarios, asunto, estado, error_msg) VALUES (?,?,?,?,?)")
                ->execute([$tipo, implode(',', $to), $asunto, $estado, $err]);
        } catch (\Exception $e) { /* silencioso */ }
    }

    // ── Destinatarios de una actividad ─────────────────────────────
    public function getDestinatariosActividad(int $actividadId): array {
        $stmt = $this->db->prepare("
            SELECT u.nombre, u.email
            FROM usuarios u
            JOIN actividad_usuarios au ON u.id = au.usuario_id
            WHERE au.actividad_id = :id AND u.activo = 1 AND u.recibe_notificaciones = 1
        ");
        $stmt->execute([':id' => $actividadId]);
        $dest = $stmt->fetchAll();

        $lideres = $this->db->query("
            SELECT nombre, email FROM usuarios
            WHERE rol = 'lider_ti' AND activo = 1 AND recibe_notificaciones = 1
        ")->fetchAll();

        $emails = [];
        foreach (array_merge($dest, $lideres) as $u) {
            if (!in_array($u['email'], array_column($emails, 'email'))) {
                $emails[] = $u;
            }
        }
        return $emails;
    }

    // ── Notificación al marcar E ────────────────────────────────────
    public function enviarNotificacionEjecucion(array $actividad, int $mes, array $destinatarios): bool {
        $MESES = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                  'Julio','Agosto','Setiembre','Octubre','Noviembre','Diciembre'];
        $mesNombre = $MESES[$mes] ?? "Mes {$mes}";
        $subject   = "✅ Ejecutado: {$actividad['codigo']} — {$mesNombre}";
        $to        = array_map(fn($u) => "{$u['nombre']} <{$u['email']}>", $destinatarios);
        return $this->send($to, $subject, $this->templateEjecucion($actividad, $mesNombre));
    }

    // ── Resumen semanal ─────────────────────────────────────────────
    // Devuelve array: ['success'=>bool, 'destinatarios'=>int, 'emails'=>[], 'error'=>string]
    public function enviarResumenSemanal(): array {
        $anio = (int)date('Y');
        $mes  = (int)date('n');
        $a    = $anio;
        $m    = $mes;

        // Stats generales — interpolación directa (valores enteros seguros)
        $s = $this->db->query("
            SELECT
                (SELECT COUNT(*) FROM actividades WHERE anio={$a} AND activo=1) as total,
                (SELECT COUNT(*) FROM programacion WHERE anio={$a} AND tipo='P' AND mes<={$m}) as programadas,
                (SELECT COUNT(*) FROM programacion WHERE anio={$a} AND tipo='E' AND estado='completado' AND mes<={$m}) as ejecutadas,
                (SELECT COUNT(*) FROM programacion p1
                 WHERE p1.anio={$a} AND p1.tipo='P' AND p1.mes < {$m}
                 AND NOT EXISTS (
                     SELECT 1 FROM programacion p2
                     WHERE p2.actividad_id=p1.actividad_id
                     AND p2.anio={$a} AND p2.mes=p1.mes AND p2.tipo='E'
                 )) as vencidas
        ")->fetch() ?: ['total'=>0,'programadas'=>0,'ejecutadas'=>0,'vencidas'=>0];

        // Pendientes del mes actual (P sin E)
        $pends = $this->db->query("
            SELECT a.codigo, a.nombre, a.categoria, a.responsable
            FROM actividades a
            JOIN programacion p ON a.id = p.actividad_id
            WHERE p.anio={$a} AND p.mes={$m} AND p.tipo='P' AND a.activo=1
            AND NOT EXISTS (
                SELECT 1 FROM programacion e
                WHERE e.actividad_id=a.id AND e.anio={$a} AND e.mes={$m} AND e.tipo='E'
            )
            ORDER BY a.categoria, a.codigo
        ")->fetchAll();

        // Vencidas (meses pasados sin E)
        $venc = $this->db->query("
            SELECT a.codigo, a.nombre, p.mes
            FROM actividades a
            JOIN programacion p ON a.id = p.actividad_id
            WHERE p.anio={$a} AND p.tipo='P' AND p.mes < {$m} AND a.activo=1
            AND NOT EXISTS (
                SELECT 1 FROM programacion e
                WHERE e.actividad_id=a.id AND e.anio={$a} AND e.mes=p.mes AND e.tipo='E'
            )
            ORDER BY p.mes, a.codigo LIMIT 10
        ")->fetchAll();

        $cumpl = $s['programadas'] > 0
            ? round(($s['ejecutadas'] / $s['programadas']) * 100, 1)
            : 0;

        // Destinatarios
        $dest = $this->db->query("
            SELECT nombre, email FROM usuarios
            WHERE activo=1 AND recibe_resumen_semanal=1
        ")->fetchAll();

        if (empty($dest)) {
            return ['success'=>false, 'destinatarios'=>0, 'emails'=>[], 'error'=>'Sin destinatarios'];
        }

        $to      = array_map(fn($u) => "{$u['nombre']} <{$u['email']}>", $dest);
        $emails  = array_column($dest, 'email');
        $subject = "📊 Resumen Semanal CyberPlan — " . date('d/m/Y');
        $html    = $this->templateResumen($s, $cumpl, $pends, $venc, $anio);

        $ok = $this->send($to, $subject, $html);

        // Registrar en log
        try {
            $this->db->prepare("INSERT INTO email_log (tipo, destinatarios, asunto, estado) VALUES (?,?,?,?)")
                ->execute(['resumen_semanal', implode(', ', $emails), $subject, $ok ? 'enviado' : 'error']);
        } catch (\Exception $e) {}

        return [
            'success'       => $ok,
            'destinatarios' => count($dest),
            'emails'        => $emails,
            'error'         => $ok ? '' : 'Fallo SMTP — revisa credenciales',
        ];
    }

    // ═══════════════════════════════════════════
    // TEMPLATES HTML — Tema claro moderno
    // ═══════════════════════════════════════════
    private function templateEjecucion(array $act, string $mes): string {
        $appUrl  = $this->cfg['app_url'] ?? '#';
        $cat     = htmlspecialchars($act['categoria'] ?? '');
        $codigo  = htmlspecialchars($act['codigo'] ?? '');
        $nombre  = htmlspecialchars($act['nombre'] ?? '');
        $resp    = htmlspecialchars($act['responsable'] ?? 'Sin asignar');
        $fecha   = date('d/m/Y H:i');

        // Colores por categoría (tema claro)
        $catClr  = match($cat) { 'F3' => '#2ecc71', 'F2' => '#f7b731', default => '#00c8d4' };
        $catBg   = match($cat) { 'F3' => '#f0fdf4', 'F2' => '#fffbeb', default => '#f0fdff' };
        $catTxt  = match($cat) { 'F3' => '#15803d', 'F2' => '#b45309', default => '#0e7490' };

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Actividad Ejecutada — CyberPlan</title>
</head>
<body style="margin:0;padding:0;background:#eef2ff;font-family:'Segoe UI',Helvetica,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#dce8ff 0%,#ece8ff 60%,#e0f0ff 100%);padding:40px 0;min-height:100vh">
<tr><td align="center" style="padding:0 16px">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%">

  <!-- HEADER -->
  <tr><td bgcolor="#7c5cbf" style="background-color:#7c5cbf;border-radius:18px 18px 0 0;padding:32px 36px 28px">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td>
        <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#c4b5e8;margin-bottom:8px">AUNOR &middot; Aleatica &middot; CyberPlan</div>
        <div style="font-size:24px;font-weight:800;color:#ffffff;line-height:1.2">Actividad Ejecutada</div>
        <div style="font-size:12px;color:#c4b5e8;margin-top:6px">{$fecha} &nbsp;&middot;&nbsp; {$mes}</div>
      </td>
      <td align="right" valign="top">
        <table cellpadding="0" cellspacing="0"><tr><td bgcolor="#9b7fd4" style="background-color:#9b7fd4;border-radius:99px;padding:7px 16px;text-align:center">
          <span style="color:#ffffff;font-size:13px;font-weight:800;letter-spacing:1px;font-family:monospace">{$cat}</span>
        </td></tr></table>
      </td>
    </tr></table>
  </td></tr>

  <!-- CUERPO -->
  <tr><td style="background:#ffffff;padding:28px 36px">

    <!-- Card de actividad -->
    <div style="background:#f7f8ff;border:1px solid #e5e7eb;border-left:4px solid {$catClr};border-radius:12px;padding:20px 22px;margin-bottom:20px">
      <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#9ca3af;margin-bottom:6px">Actividad completada</div>
      <div style="font-size:17px;font-weight:700;color:#1e1e2d;line-height:1.4;margin-bottom:6px">{$nombre}</div>
      <div style="display:inline-block;background:{$catBg};color:{$catTxt};font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px;font-family:monospace">{$codigo}</div>
    </div>

    <!-- Dos columnas: Responsable / Período -->
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
    <tr>
      <td width="50%" style="padding-right:8px">
        <div style="background:#f7f8ff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;text-align:center">
          <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#9ca3af;margin-bottom:6px">Responsable</div>
          <div style="font-size:14px;font-weight:700;color:#7c5cbf">{$resp}</div>
        </div>
      </td>
      <td width="50%" style="padding-left:8px">
        <div style="background:#f7f8ff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;text-align:center">
          <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#9ca3af;margin-bottom:6px">Período</div>
          <div style="font-size:14px;font-weight:700;color:#2ecc71">{$mes}</div>
        </div>
      </td>
    </tr></table>

    <!-- CTA -->
    <div style="text-align:center">
      <a href="{$appUrl}" style="display:inline-block;background:#7c5cbf;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:13px 32px;border-radius:10px;letter-spacing:.3px">Ver en CyberPlan &rarr;</a>
    </div>

  </td></tr>

  <!-- FOOTER -->
  <tr><td style="background:#f0f2ff;border-radius:0 0 18px 18px;padding:18px 36px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="font-size:11px;color:#9ca3af;margin:0">Notificación automática &nbsp;·&nbsp; <strong style="color:#7c5cbf">CyberPlan &nbsp;·&nbsp; AUNOR &nbsp;·&nbsp; Red Vial 4</strong></p>
  </td></tr>

</table>
</td></tr></table>
</body>
</html>
HTML;
    }

    private function templateResumen(array $s, float $cumpl, array $pendientes, array $vencidas, int $anio): string {
        $appUrl  = $this->cfg['app_url'] ?? '#';
        $fecha   = date('d/m/Y');
        $semana  = date('W');
        $MESES   = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'];
        $barClr  = $cumpl >= 80 ? '#7c5cbf' : ($cumpl >= 50 ? '#f7b731' : '#e55353');
        $barW    = max(4, (int)$cumpl);

        // Filas de pendientes
        $pendHtml = '';
        if (empty($pendientes)) {
            $pendHtml = '<tr><td colspan="3" style="padding:16px;text-align:center;color:#9ca3af;font-size:12px">Sin pendientes para este mes &#x1F389;</td></tr>';
        } else {
            foreach ($pendientes as $i => $p) {
                $cc  = match($p['categoria']) { 'F3' => '#2ecc71', 'F2' => '#f7b731', default => '#00c8d4' };
                $ccB = match($p['categoria']) { 'F3' => '#f0fdf4', 'F2' => '#fffbeb', default => '#f0fdff' };
                $ccT = match($p['categoria']) { 'F3' => '#15803d', 'F2' => '#b45309', default => '#0e7490' };
                $bg  = $i % 2 === 0 ? '#ffffff' : '#f9fafb';
                $pendHtml .= "<tr style='background:{$bg};border-bottom:1px solid #f3f4f6'>
                  <td style='padding:10px 14px'>
                    <span style='background:{$ccB};color:{$ccT};font-size:10px;font-weight:700;padding:3px 9px;border-radius:99px;font-family:monospace'>{$p['codigo']}</span>
                  </td>
                  <td style='padding:10px 14px;color:#374151;font-size:12px;font-weight:500'>" . htmlspecialchars($p['nombre']) . "</td>
                  <td style='padding:10px 14px;text-align:center'>
                    <span style='background:#ede9fe;color:#7c5cbf;font-size:10px;font-weight:700;padding:3px 9px;border-radius:99px'>" . htmlspecialchars($p['responsable'] ?? '—') . "</span>
                  </td>
                </tr>";
            }
        }

        // Filas de vencidas
        $vencHtml = '';
        foreach ($vencidas as $i => $v) {
            $bg = $i % 2 === 0 ? '#fff5f5' : '#ffffff';
            $vencHtml .= "<tr style='background:{$bg};border-bottom:1px solid #fee2e2'>
              <td style='padding:10px 14px;font-family:monospace;font-size:11px;color:#e55353;font-weight:700'>{$v['codigo']}</td>
              <td style='padding:10px 14px;color:#374151;font-size:12px'>" . htmlspecialchars($v['nombre']) . "</td>
              <td style='padding:10px 14px;text-align:center;background:#fee2e2;color:#e55353;font-size:11px;font-weight:700'>{$MESES[$v['mes']]}</td>
            </tr>";
        }

        $vencBlock = !empty($vencidas) ? "
  <!-- VENCIDAS -->
  <tr><td style='background:#ffffff;padding:0 36px 24px'>
    <div style='border:1px solid #fecaca;border-radius:12px;overflow:hidden'>
      <div style='background:#fef2f2;padding:12px 16px;border-bottom:1px solid #fecaca'>
        <span style='font-size:12px;font-weight:700;color:#e55353'>&#x1F534; Actividades vencidas sin ejecución</span>
        <span style='background:#fee2e2;color:#e55353;font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;margin-left:8px'>{$s['vencidas']}</span>
      </div>
      <table width='100%' cellpadding='0' cellspacing='0'>
        <tr style='background:#fff5f5'>
          <th style='padding:8px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #fee2e2'>Código</th>
          <th style='padding:8px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #fee2e2'>Actividad</th>
          <th style='padding:8px 14px;text-align:center;font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #fee2e2'>Mes</th>
        </tr>{$vencHtml}
      </table>
    </div>
  </td></tr>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Resumen Semanal — CyberPlan</title>
</head>
<body style="margin:0;padding:0;background:#eef2ff;font-family:'Segoe UI',Helvetica,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#dce8ff 0%,#ece8ff 60%,#e0f0ff 100%);padding:40px 0;min-height:100vh">
<tr><td align="center" style="padding:0 16px">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">

  <!-- HEADER -->
  <tr><td bgcolor="#7c5cbf" style="background-color:#7c5cbf;border-radius:18px 18px 0 0;padding:32px 36px 28px">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td valign="middle">
        <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#c4b5e8;margin-bottom:8px">AUNOR &middot; Aleatica &middot; CyberPlan</div>
        <div style="font-size:26px;font-weight:800;color:#ffffff;line-height:1.2">Resumen Semanal</div>
        <div style="font-size:12px;color:#c4b5e8;margin-top:6px">Semana #{$semana} &nbsp;&middot;&nbsp; {$fecha} &nbsp;&middot;&nbsp; A&ntilde;o {$anio}</div>
      </td>
      <td align="right" valign="middle" style="padding-left:16px">
        <table cellpadding="0" cellspacing="0"><tr><td bgcolor="#9b7fd4" style="background-color:#9b7fd4;border-radius:12px;padding:14px 20px;text-align:center">
          <div style="font-size:28px;font-weight:900;color:#ffffff;line-height:1;white-space:nowrap">{$cumpl}%</div>
          <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#ddd6fe;margin-top:4px">Cumplimiento</div>
        </td></tr></table>
      </td>
    </tr></table>
  </td></tr>

  <!-- BARRA DE PROGRESO -->
  <tr><td bgcolor="#ffffff" style="background-color:#ffffff;padding:20px 36px 16px">
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px"><tr>
      <td style="font-size:13px;font-weight:600;color:#374151">Progreso del a&ntilde;o</td>
      <td align="right" style="font-size:13px;font-weight:700;color:{$barClr}">{$s['ejecutadas']} / {$s['programadas']} ejecutadas</td>
    </tr></table>
    <!-- Progress bar usando tabla (compatible con todos los clientes de correo) -->
    <table width="100%" cellpadding="0" cellspacing="0" style="border-radius:8px;overflow:hidden">
      <tr>
        <td width="{$barW}%" bgcolor="{$barClr}" height="10" style="background-color:{$barClr};border-radius:8px;font-size:1px;line-height:1px">&nbsp;</td>
        <td bgcolor="#f3f4f6" height="10" style="background-color:#f3f4f6;font-size:1px;line-height:1px">&nbsp;</td>
      </tr>
    </table>
  </td></tr>

  <!-- TARJETAS DE STATS -->
  <tr><td style="background:#ffffff;padding:0 36px 24px">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td width="25%" style="padding-right:5px">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;text-align:center">
          <div style="font-size:28px;font-weight:900;color:#15803d;line-height:1">{$s['total']}</div>
          <div style="font-size:9px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-top:4px">Total</div>
        </div>
      </td>
      <td width="25%" style="padding:0 5px">
        <div style="background:#f0fdff;border:1px solid #a5f3fc;border-radius:12px;padding:16px;text-align:center">
          <div style="font-size:28px;font-weight:900;color:#0e7490;line-height:1">{$s['programadas']}</div>
          <div style="font-size:9px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-top:4px">Program.</div>
        </div>
      </td>
      <td width="25%" style="padding:0 5px">
        <div style="background:#faf5ff;border:1px solid #ddd6fe;border-radius:12px;padding:16px;text-align:center">
          <div style="font-size:28px;font-weight:900;color:#7c5cbf;line-height:1">{$s['ejecutadas']}</div>
          <div style="font-size:9px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-top:4px">Ejecut.</div>
        </div>
      </td>
      <td width="25%" style="padding-left:5px">
        <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:12px;padding:16px;text-align:center">
          <div style="font-size:28px;font-weight:900;color:#e55353;line-height:1">{$s['vencidas']}</div>
          <div style="font-size:9px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;margin-top:4px">Vencidas</div>
        </div>
      </td>
    </tr></table>
  </td></tr>

  <!-- PENDIENTES DEL MES -->
  <tr><td style="background:#ffffff;padding:0 36px 24px">
    <div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">
      <div style="background:#f9fafb;padding:12px 16px;border-bottom:1px solid #e5e7eb">
        <span style="font-size:12px;font-weight:700;color:#374151">&#x23F3; Pendientes del mes actual</span>
        <span style="background:#fffbeb;color:#b45309;font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;margin-left:8px;border:1px solid #fde68a">{$s['programadas']}</span>
      </div>
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr style="background:#f9fafb">
          <th style="padding:8px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #f3f4f6">Código</th>
          <th style="padding:8px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #f3f4f6">Actividad</th>
          <th style="padding:8px 14px;text-align:center;font-size:10px;font-weight:700;text-transform:uppercase;color:#9ca3af;border-bottom:1px solid #f3f4f6">Resp.</th>
        </tr>
        {$pendHtml}
      </table>
    </div>
  </td></tr>

  {$vencBlock}

  <!-- CTA -->
  <tr><td style="background:#ffffff;padding:4px 36px 28px;text-align:center">
    <a href="{$appUrl}" style="display:inline-block;background:#7c5cbf;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:14px 36px;border-radius:10px;letter-spacing:.3px">Abrir CyberPlan &rarr;</a>
  </td></tr>

  <!-- FOOTER -->
  <tr><td style="background:#f0f2ff;border-radius:0 0 18px 18px;padding:18px 36px;border-top:1px solid #e5e7eb;text-align:center">
    <p style="font-size:11px;color:#9ca3af;margin:0">Resumen automático semanal &nbsp;·&nbsp; <strong style="color:#7c5cbf">CyberPlan &nbsp;·&nbsp; AUNOR &nbsp;·&nbsp; Red Vial 4 &nbsp;·&nbsp; {$anio}</strong></p>
  </td></tr>

</table>
</td></tr></table>
</body>
</html>
HTML;
    }
}
