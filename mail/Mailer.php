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
    // TEMPLATES HTML
    // ═══════════════════════════════════════════
    private function templateEjecucion(array $act, string $mes): string {
        $appUrl  = $this->cfg['app_url'] ?? '#';
        $cat     = htmlspecialchars($act['categoria'] ?? '');
        $codigo  = htmlspecialchars($act['codigo'] ?? '');
        $nombre  = htmlspecialchars($act['nombre'] ?? '');
        $resp    = htmlspecialchars($act['responsable'] ?? 'Sin asignar');
        $fecha   = date('d/m/Y H:i');
        $catClr  = match($cat) { 'F3'=>'#72BF44','F2'=>'#F99B1C', default=>'#00BBE7' };

        return <<<HTML
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#0d1117;font-family:'Segoe UI',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1117;padding:32px 0">
<tr><td align="center">
<table width="580" cellpadding="0" cellspacing="0" style="max-width:580px;width:100%">

  <tr><td style="background:#161b22;border-radius:16px 16px 0 0;padding:28px 32px;border-bottom:3px solid {$catClr}">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td>
        <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#8b949e;margin-bottom:6px">AUNOR · Aleatica · CyberPlan</div>
        <div style="font-size:22px;font-weight:900;color:#e6edf3">✅ Actividad Ejecutada</div>
        <div style="font-size:12px;color:#8b949e;margin-top:4px">{$fecha} · {$mes}</div>
      </td>
      <td align="right"><div style="background:{$catClr};color:#fff;font-size:13px;font-weight:800;padding:7px 14px;border-radius:7px;font-family:monospace">{$cat}</div></td>
    </tr></table>
  </td></tr>

  <tr><td style="background:#161b22;padding:24px 32px">
    <div style="background:#1c2333;border:1px solid #30363d;border-left:4px solid {$catClr};border-radius:10px;padding:20px">
      <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8b949e;margin-bottom:6px">Actividad</div>
      <div style="font-size:17px;font-weight:800;color:#e6edf3;margin-bottom:4px">{$nombre}</div>
      <div style="font-size:12px;color:#8b949e;font-family:monospace">{$codigo}</div>
    </div>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px">
    <tr>
      <td width="50%" style="padding-right:8px">
        <div style="background:#1c2333;border:1px solid #30363d;border-radius:10px;padding:14px;text-align:center">
          <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8b949e;margin-bottom:5px">Responsable</div>
          <div style="font-size:14px;font-weight:700;color:#00BBE7">{$resp}</div>
        </div>
      </td>
      <td width="50%" style="padding-left:8px">
        <div style="background:#1c2333;border:1px solid #30363d;border-radius:10px;padding:14px;text-align:center">
          <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#8b949e;margin-bottom:5px">Período</div>
          <div style="font-size:14px;font-weight:700;color:#72BF44">{$mes}</div>
        </div>
      </td>
    </tr></table>

    <div style="text-align:center;margin-top:22px">
      <a href="{$appUrl}" style="display:inline-block;background:#72BF44;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px">Ver en CyberPlan →</a>
    </div>
  </td></tr>

  <tr><td style="background:#0d1117;border-radius:0 0 16px 16px;padding:16px 32px;border-top:1px solid #21262d;text-align:center">
    <p style="font-size:11px;color:#484f58;margin:0">Generado automáticamente por <strong style="color:#8b949e">CyberPlan · AUNOR · Red Vial 4</strong></p>
  </td></tr>

</table></td></tr></table>
</body></html>
HTML;
    }

    private function templateResumen(array $s, float $cumpl, array $pendientes, array $vencidas, int $anio): string {
        $appUrl  = $this->cfg['app_url'] ?? '#';
        $fecha   = date('d/m/Y');
        $semana  = date('W');
        $MESES   = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'];
        $barClr  = $cumpl >= 80 ? '#72BF44' : ($cumpl >= 50 ? '#F99B1C' : '#f85149');
        $barW    = max(4, (int)$cumpl);

        $pendHtml = '';
        if (empty($pendientes)) {
            $pendHtml = '<tr><td colspan="3" style="padding:14px;text-align:center;color:#8b949e;font-size:12px">🎉 Sin pendientes para este mes</td></tr>';
        } else {
            foreach ($pendientes as $p) {
                $cc = match($p['categoria']) { 'F3'=>'#72BF44','F2'=>'#F99B1C', default=>'#00BBE7' };
                $pendHtml .= "<tr style='border-bottom:1px solid #21262d'>
                  <td style='padding:10px 12px'><span style='background:{$cc}22;color:{$cc};font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;font-family:monospace'>{$p['codigo']}</span></td>
                  <td style='padding:10px 12px;color:#c9d1d9;font-size:12px'>" . htmlspecialchars($p['nombre']) . "</td>
                  <td style='padding:10px 12px;text-align:center'><span style='background:#00BBE722;color:#00BBE7;font-size:10px;font-weight:700;padding:2px 7px;border-radius:99px'>" . htmlspecialchars($p['responsable'] ?? '—') . "</span></td>
                </tr>";
            }
        }

        $vencHtml = '';
        foreach ($vencidas as $v) {
            $vencHtml .= "<tr style='border-bottom:1px solid #21262d'>
              <td style='padding:9px 12px;font-family:monospace;font-size:11px;color:#f85149;font-weight:700'>{$v['codigo']}</td>
              <td style='padding:9px 12px;color:#c9d1d9;font-size:12px'>" . htmlspecialchars($v['nombre']) . "</td>
              <td style='padding:9px 12px;text-align:center;color:#f85149;font-size:11px;font-weight:700'>{$MESES[$v['mes']]}</td>
            </tr>";
        }

        $vencBlock = !empty($vencidas) ? "
  <tr><td style='background:#161b22;padding:16px 32px 0'>
    <div style='font-size:13px;font-weight:700;color:#f85149;margin-bottom:10px'>🔴 Vencidas sin ejecución <span style='background:#f8514922;color:#f85149;font-size:10px;padding:2px 8px;border-radius:99px;margin-left:6px'>{$s['vencidas']}</span></div>
    <div style='background:#1c2333;border:1px solid #f8514930;border-radius:10px;overflow:hidden'>
      <table width='100%' cellpadding='0' cellspacing='0'>
        <tr style='background:#21262d'>
          <th style='padding:8px 12px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;color:#8b949e'>Código</th>
          <th style='padding:8px 12px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;color:#8b949e'>Actividad</th>
          <th style='padding:8px 12px;text-align:center;font-size:10px;font-weight:700;text-transform:uppercase;color:#8b949e'>Mes</th>
        </tr>{$vencHtml}
      </table>
    </div>
  </td></tr>" : '';

        return <<<HTML
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#0d1117;font-family:'Segoe UI',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0d1117;padding:32px 0">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">

  <tr><td style="background:#161b22;border-radius:16px 16px 0 0;padding:28px 32px;border-bottom:3px solid #72BF44">
    <div style="font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#8b949e;margin-bottom:8px">AUNOR · Aleatica · CyberPlan</div>
    <div style="font-size:22px;font-weight:900;color:#e6edf3">📊 Resumen Semanal</div>
    <div style="font-size:12px;color:#8b949e;margin-top:4px">Semana #{$semana} · {$fecha} · Año {$anio}</div>
  </td></tr>

  <tr><td style="background:#161b22;padding:22px 32px 0">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td width="25%" style="padding-right:6px"><div style="background:#1c2333;border:1px solid #30363d;border-radius:10px;padding:14px;text-align:center;border-top:3px solid #72BF44">
        <div style="font-size:26px;font-weight:900;color:#e6edf3">{$s['total']}</div>
        <div style="font-size:9px;font-weight:700;color:#8b949e;text-transform:uppercase;margin-top:3px">Actividades</div>
      </div></td>
      <td width="25%" style="padding:0 6px"><div style="background:#1c2333;border:1px solid #30363d;border-radius:10px;padding:14px;text-align:center;border-top:3px solid #00BBE7">
        <div style="font-size:26px;font-weight:900;color:#e6edf3">{$s['programadas']}</div>
        <div style="font-size:9px;font-weight:700;color:#8b949e;text-transform:uppercase;margin-top:3px">Programadas</div>
      </div></td>
      <td width="25%" style="padding:0 6px"><div style="background:#1c2333;border:1px solid #30363d;border-radius:10px;padding:14px;text-align:center;border-top:3px solid #72BF44">
        <div style="font-size:26px;font-weight:900;color:#72BF44">{$s['ejecutadas']}</div>
        <div style="font-size:9px;font-weight:700;color:#8b949e;text-transform:uppercase;margin-top:3px">Ejecutadas</div>
      </div></td>
      <td width="25%" style="padding-left:6px"><div style="background:#1c2333;border:1px solid #30363d;border-radius:10px;padding:14px;text-align:center;border-top:3px solid #f85149">
        <div style="font-size:26px;font-weight:900;color:#f85149">{$s['vencidas']}</div>
        <div style="font-size:9px;font-weight:700;color:#8b949e;text-transform:uppercase;margin-top:3px">Vencidas</div>
      </div></td>
    </tr></table>
  </td></tr>

  <tr><td style="background:#161b22;padding:16px 32px 0">
    <div style="background:#1c2333;border:1px solid #30363d;border-radius:10px;padding:18px">
      <table width="100%" cellpadding="0" cellspacing="0"><tr>
        <td><div style="font-size:13px;font-weight:700;color:#c9d1d9">Cumplimiento General</div></td>
        <td align="right"><div style="font-size:20px;font-weight:900;color:{$barClr}">{$cumpl}%</div></td>
      </tr></table>
      <div style="background:#21262d;border-radius:99px;height:7px;margin-top:10px;overflow:hidden">
        <div style="background:{$barClr};height:7px;border-radius:99px;width:{$barW}%"></div>
      </div>
      <div style="font-size:11px;color:#8b949e;margin-top:6px">{$s['ejecutadas']} de {$s['programadas']} ejecutadas al mes actual</div>
    </div>
  </td></tr>

  <tr><td style="background:#161b22;padding:16px 32px 0">
    <div style="font-size:13px;font-weight:700;color:#c9d1d9;margin-bottom:10px">⏳ Pendientes del mes <span style="background:#F99B1C22;color:#F99B1C;font-size:10px;padding:2px 8px;border-radius:99px;margin-left:6px">{$s['programadas']}</span></div>
    <div style="background:#1c2333;border:1px solid #30363d;border-radius:10px;overflow:hidden">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr style="background:#21262d">
          <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;color:#8b949e">Código</th>
          <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;color:#8b949e">Actividad</th>
          <th style="padding:8px 12px;text-align:center;font-size:10px;font-weight:700;text-transform:uppercase;color:#8b949e">Resp.</th>
        </tr>
        {$pendHtml}
      </table>
    </div>
  </td></tr>

  {$vencBlock}

  <tr><td style="background:#161b22;padding:22px 32px">
    <div style="text-align:center">
      <a href="{$appUrl}" style="display:inline-block;background:#72BF44;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 30px;border-radius:8px">Abrir CyberPlan →</a>
    </div>
  </td></tr>

  <tr><td style="background:#0d1117;border-radius:0 0 16px 16px;padding:16px 32px;border-top:1px solid #21262d;text-align:center">
    <p style="font-size:11px;color:#484f58;margin:0">Resumen automático · <strong style="color:#8b949e">CyberPlan · AUNOR · Red Vial 4 · {$anio}</strong></p>
  </td></tr>

</table></td></tr></table>
</body></html>
HTML;
    }
}
