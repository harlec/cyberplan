#!/usr/bin/env php
<?php
// cron/resumen_semanal.php
// Ejecutar via cron cada lunes a las 8am:
// 0 8 * * 1 /usr/bin/php /ruta/cyberplan/cron/resumen_semanal.php >> /var/log/cyberplan_cron.log 2>&1

define('CYBERPLAN_CRON', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mail/Mailer.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando resumen semanal...\n";

try {
    $db  = getDB();

    // Verificar que el resumen semanal esté activo
    $cfg = $db->query("SELECT clave, valor FROM configuracion WHERE clave IN ('notif_resumen','resumen_dia','resumen_hora')")->fetchAll(PDO::FETCH_KEY_PAIR);

    if (($cfg['notif_resumen'] ?? '1') === '0') {
        echo "[" . date('Y-m-d H:i:s') . "] Resumen semanal desactivado en configuración. Saliendo.\n";
        exit(0);
    }

    $mailer = new Mailer();
    $ok     = $mailer->enviarResumenSemanal();

    if ($ok) {
        echo "[" . date('Y-m-d H:i:s') . "] ✅ Resumen semanal enviado correctamente.\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] ❌ Error al enviar resumen semanal. Revisar email_log en BD.\n";
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ Excepción: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
