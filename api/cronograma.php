<?php
// api/cronograma.php - API REST para el cronograma
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
        $method === 'GET' && $action === 'cronograma'         => getCronograma($db),
        $method === 'GET' && $action === 'stats'              => getStats($db),
        $method === 'GET' && $action === 'actividades'        => getActividades($db),
        $method === 'GET' && $action === 'subtareas'          => getSubtareas($db),
        $method === 'POST' && $action === 'toggle'            => toggleProgramacion($db),
        $method === 'POST' && $action === 'actividad'         => crearActividad($db),
        $method === 'PUT' && $action === 'actividad'          => actualizarActividad($db),
        $method === 'DELETE' && $action === 'actividad'       => eliminarActividad($db),
        $method === 'PUT' && $action === 'estado'             => actualizarEstado($db),
        $method === 'POST' && $action === 'subtarea'          => crearSubtarea($db),
        $method === 'PUT' && $action === 'subtarea'           => toggleSubtarea($db),
        $method === 'DELETE' && $action === 'subtarea'        => eliminarSubtarea($db),
        default => jsonResponse(['error' => 'Acción no válida'], 404)
    };
} catch (PDOException $e) {
    jsonResponse(['error' => 'Error de base de datos: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
}

// ─── FUNCIONES ────────────────────────────────────────────────────

function getCronograma(PDO $db): never {
    $anio = (int)($_GET['anio'] ?? date('Y'));
    $filtro_cat = $_GET['categoria'] ?? '';
    $filtro_resp = $_GET['responsable'] ?? '';

    $where = ['a.anio = :anio', 'a.activo = 1'];
    $params = [':anio' => $anio];

    if ($filtro_cat) {
        $where[] = 'a.categoria = :cat';
        $params[':cat'] = $filtro_cat;
    }
    if ($filtro_resp) {
        $where[] = 'a.responsable = :resp';
        $params[':resp'] = $filtro_resp;
    }

    $sql = "SELECT a.*, r.ruta as repositorio_ruta
            FROM actividades a
            LEFT JOIN repositorios r ON a.repositorio_id = r.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.categoria, a.codigo, a.id";

    $actividades = $db->prepare($sql);
    $actividades->execute($params);
    $rows = $actividades->fetchAll();

    // Cargar programaciones
    $prog_sql = "SELECT actividad_id, mes, tipo, estado, observaciones
                 FROM programacion
                 WHERE anio = :anio";
    $prog_stmt = $db->prepare($prog_sql);
    $prog_stmt->execute([':anio' => $anio]);
    $programaciones = [];
    foreach ($prog_stmt->fetchAll() as $p) {
        $programaciones[$p['actividad_id']][$p['mes']][$p['tipo']] = [
            'estado' => $p['estado'],
            'obs'    => $p['observaciones']
        ];
    }

    foreach ($rows as &$act) {
        $act['meses'] = $programaciones[$act['id']] ?? [];
    }

    jsonResponse([
        'anio'       => $anio,
        'actividades'=> $rows,
        'meses'      => MESES
    ]);
}

function getStats(PDO $db): never {
    $anio = (int)($_GET['anio'] ?? date('Y'));
    $mesActual = (int)date('n');

    // Total actividades
    $total = $db->prepare("SELECT COUNT(*) FROM actividades WHERE anio=:a AND activo=1");
    $total->execute([':a' => $anio]);

    // Programadas (P) hasta el mes actual
    $prog = $db->prepare("SELECT COUNT(DISTINCT actividad_id) FROM programacion
                          WHERE anio=:a AND tipo='P' AND mes <= :m");
    $prog->execute([':a' => $anio, ':m' => $mesActual]);

    // Ejecutadas (E) hasta el mes actual
    $ejec = $db->prepare("SELECT COUNT(*) FROM programacion
                          WHERE anio=:a AND tipo='E' AND estado='completado' AND mes <= :m");
    $ejec->execute([':a' => $anio, ':m' => $mesActual]);

    // Vencidas (P sin E correspondiente en meses pasados)
    $venc = $db->prepare("SELECT COUNT(*) FROM programacion p1
                          WHERE p1.anio=:a AND p1.tipo='P' AND p1.mes < :m
                          AND NOT EXISTS (
                              SELECT 1 FROM programacion p2
                              WHERE p2.actividad_id=p1.actividad_id
                              AND p2.anio=p1.anio AND p2.mes=p1.mes AND p2.tipo='E'
                          )");
    $venc->execute([':a' => $anio, ':m' => $mesActual]);

    // Por categoría
    $cats = $db->prepare("SELECT categoria, COUNT(*) as total
                          FROM actividades WHERE anio=:a AND activo=1
                          GROUP BY categoria ORDER BY categoria");
    $cats->execute([':a' => $anio]);

    // Cumplimiento por mes (porcentaje de E vs P por mes)
    $por_mes = $db->prepare("
        SELECT mes,
               SUM(tipo='P') as programadas,
               SUM(tipo='E' AND estado='completado') as ejecutadas
        FROM programacion WHERE anio=:a
        GROUP BY mes ORDER BY mes
    ");
    $por_mes->execute([':a' => $anio]);

    $total_acts = (int)$total->fetchColumn();
    $prog_acts  = (int)$prog->fetchColumn();
    $ejec_acts  = (int)$ejec->fetchColumn();
    $venc_acts  = (int)$venc->fetchColumn();

    $cumplimiento = $prog_acts > 0 ? round(($ejec_acts / $prog_acts) * 100, 1) : 0;

    jsonResponse([
        'total_actividades' => $total_acts,
        'programadas'       => $prog_acts,
        'ejecutadas'        => $ejec_acts,
        'vencidas'          => $venc_acts,
        'cumplimiento_pct'  => $cumplimiento,
        'mes_actual'        => $mesActual,
        'por_categoria'     => $cats->fetchAll(),
        'por_mes'           => $por_mes->fetchAll(),
    ]);
}

function getActividades(PDO $db): never {
    $anio = (int)($_GET['anio'] ?? date('Y'));
    $stmt = $db->prepare("SELECT * FROM actividades WHERE anio=:a AND activo=1 ORDER BY categoria, codigo");
    $stmt->execute([':a' => $anio]);
    jsonResponse($stmt->fetchAll());
}

function toggleProgramacion(PDO $db): never {
    $data = json_decode(file_get_contents('php://input'), true);
    $act_id = (int)($data['actividad_id'] ?? 0);
    $anio   = (int)($data['anio'] ?? date('Y'));
    $mes    = (int)($data['mes'] ?? 0);
    $tipo   = $data['tipo'] ?? 'P';

    if (!$act_id || !$mes || !in_array($tipo, ['P','E'])) {
        throw new Exception('Parámetros inválidos');
    }

    // Verificar si existe
    $check = $db->prepare("SELECT id FROM programacion WHERE actividad_id=:a AND anio=:y AND mes=:m AND tipo=:t");
    $check->execute([':a'=>$act_id, ':y'=>$anio, ':m'=>$mes, ':t'=>$tipo]);
    $exists = $check->fetch();

    if ($exists) {
        $db->prepare("DELETE FROM programacion WHERE actividad_id=:a AND anio=:y AND mes=:m AND tipo=:t")
           ->execute([':a'=>$act_id, ':y'=>$anio, ':m'=>$mes, ':t'=>$tipo]);
        jsonResponse(['action' => 'removed', 'actividad_id'=>$act_id, 'mes'=>$mes, 'tipo'=>$tipo]);
    } else {
        $estado = $tipo === 'E' ? 'completado' : 'pendiente';
        $db->prepare("INSERT INTO programacion (actividad_id, anio, mes, tipo, estado) VALUES (:a,:y,:m,:t,:e)")
           ->execute([':a'=>$act_id, ':y'=>$anio, ':m'=>$mes, ':t'=>$tipo, ':e'=>$estado]);
        jsonResponse(['action' => 'added', 'actividad_id'=>$act_id, 'mes'=>$mes, 'tipo'=>$tipo]);
    }
}

function crearActividad(PDO $db): never {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare("INSERT INTO actividades (codigo, nombre, responsable, anio, categoria, repositorio_id)
                          VALUES (:cod, :nom, :resp, :anio, :cat, :repo)");
    $stmt->execute([
        ':cod'  => trim($data['codigo'] ?? ''),
        ':nom'  => trim($data['nombre'] ?? ''),
        ':resp' => trim($data['responsable'] ?? ''),
        ':anio' => (int)($data['anio'] ?? date('Y')),
        ':cat'  => trim($data['categoria'] ?? 'F2'),
        ':repo' => $data['repositorio_id'] ?? null,
    ]);
    jsonResponse(['id' => $db->lastInsertId(), 'message' => 'Actividad creada correctamente']);
}

function actualizarActividad(PDO $db): never {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    if (!$id) throw new Exception('ID requerido');

    $stmt = $db->prepare("UPDATE actividades SET codigo=:cod, nombre=:nom, responsable=:resp,
                          categoria=:cat WHERE id=:id");
    $stmt->execute([
        ':cod'  => trim($data['codigo'] ?? ''),
        ':nom'  => trim($data['nombre'] ?? ''),
        ':resp' => trim($data['responsable'] ?? ''),
        ':cat'  => trim($data['categoria'] ?? 'F2'),
        ':id'   => $id,
    ]);
    jsonResponse(['message' => 'Actividad actualizada correctamente']);
}

function eliminarActividad(PDO $db): never {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) throw new Exception('ID requerido');
    $db->prepare("UPDATE actividades SET activo=0 WHERE id=:id")->execute([':id' => $id]);
    jsonResponse(['message' => 'Actividad eliminada correctamente']);
}

function getSubtareas(PDO $db): never {
    $actId = (int)($_GET['actividad_id'] ?? 0);
    $anio  = (int)($_GET['anio']         ?? 0);
    $mes   = (int)($_GET['mes']          ?? 0);
    if (!$actId) throw new Exception('actividad_id requerido');

    // Si piden una instancia concreta, devuelve sus subtareas
    if ($anio && $mes) {
        $stmt = $db->prepare("SELECT * FROM subtareas WHERE actividad_id=:id AND anio=:y AND mes=:m ORDER BY orden, id");
        $stmt->execute([':id' => $actId, ':y' => $anio, ':m' => $mes]);
        jsonResponse($stmt->fetchAll());
    }

    // Sin mes: devuelve resumen de todas las instancias programadas (P) con su conteo
    $stmt = $db->prepare("
        SELECT p.mes, p.anio,
               COUNT(s.id)                             AS total,
               SUM(COALESCE(s.completada, 0))          AS completadas
        FROM programacion p
        LEFT JOIN subtareas s ON s.actividad_id = p.actividad_id
                              AND s.anio = p.anio AND s.mes = p.mes
        WHERE p.actividad_id = :id AND p.tipo = 'P'
        GROUP BY p.anio, p.mes
        ORDER BY p.anio, p.mes
    ");
    $stmt->execute([':id' => $actId]);
    jsonResponse($stmt->fetchAll());
}

function crearSubtarea(PDO $db): never {
    $data   = json_decode(file_get_contents('php://input'), true);
    $actId  = (int)($data['actividad_id'] ?? 0);
    $anio   = (int)($data['anio']         ?? 0);
    $mes    = (int)($data['mes']          ?? 0);
    $nombre = trim($data['nombre']        ?? '');
    if (!$actId || !$anio || !$mes || $nombre === '') throw new Exception('Parámetros inválidos');

    $stmt = $db->prepare("INSERT INTO subtareas (actividad_id, anio, mes, nombre, orden)
                          SELECT :id, :y, :m, :nom, COALESCE(MAX(orden),0)+1
                          FROM subtareas WHERE actividad_id=:id2 AND anio=:y2 AND mes=:m2");
    $stmt->execute([':id'=>$actId,':y'=>$anio,':m'=>$mes,':nom'=>$nombre,':id2'=>$actId,':y2'=>$anio,':m2'=>$mes]);
    $row = $db->prepare("SELECT * FROM subtareas WHERE id=:id");
    $row->execute([':id' => $db->lastInsertId()]);
    jsonResponse($row->fetch());
}

function toggleSubtarea(PDO $db): never {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);
    if (!$id) throw new Exception('ID requerido');

    // Hacer el toggle
    $db->prepare("UPDATE subtareas SET completada = 1 - completada WHERE id=:id")->execute([':id' => $id]);

    // Leer la subtarea actualizada para saber actividad/anio/mes
    $sub = $db->prepare("SELECT * FROM subtareas WHERE id=:id");
    $sub->execute([':id' => $id]);
    $subtarea = $sub->fetch();

    $actId = (int)$subtarea['actividad_id'];
    $anio  = (int)$subtarea['anio'];
    $mes   = (int)$subtarea['mes'];

    // Verificar si TODAS las subtareas de esta instancia están completadas
    $stats = $db->prepare("
        SELECT COUNT(*) as total, SUM(completada) as completadas
        FROM subtareas WHERE actividad_id=:a AND anio=:y AND mes=:m
    ");
    $stats->execute([':a' => $actId, ':y' => $anio, ':m' => $mes]);
    $r = $stats->fetch();
    $todasCompletadas = $r['total'] > 0 && (int)$r['completadas'] === (int)$r['total'];

    // Verificar si ya existe un registro E para esta instancia
    $existeE = $db->prepare("SELECT id FROM programacion WHERE actividad_id=:a AND anio=:y AND mes=:m AND tipo='E'");
    $existeE->execute([':a' => $actId, ':y' => $anio, ':m' => $mes]);
    $tieneE = $existeE->fetch();

    $autoMarcado = false;
    if ($todasCompletadas && !$tieneE) {
        // Marcar automáticamente como Ejecutada
        $db->prepare("INSERT INTO programacion (actividad_id, anio, mes, tipo, estado) VALUES (:a,:y,:m,'E','completado')")
           ->execute([':a' => $actId, ':y' => $anio, ':m' => $mes]);
        $autoMarcado = true;

        // Disparar notificación por correo (misma lógica que notificar.php)
        try {
            require_once __DIR__ . '/../mail/Mailer.php';
            $cfgStmt = $db->prepare("SELECT valor FROM configuracion WHERE clave='notif_ejecucion'");
            $cfgStmt->execute();
            $notifActiva = $cfgStmt->fetchColumn();

            if ($notifActiva !== '0') {
                $actStmt = $db->prepare("SELECT * FROM actividades WHERE id=:id");
                $actStmt->execute([':id' => $actId]);
                $actividad = $actStmt->fetch();

                $mailer = new Mailer();
                $destinatarios = $mailer->getDestinatariosActividad($actId);
                if (!empty($destinatarios)) {
                    $mailer->enviarNotificacionEjecucion($actividad, $mes, $destinatarios);
                }
            }
        } catch (\Exception $e) {
            error_log('[CyberPlan] Error notificación auto-E: ' . $e->getMessage());
        }

    } elseif (!$todasCompletadas && $tieneE) {
        // Si se desmarcó una subtarea, quitar el E automático (solo si fue marcado con subtareas)
        // Solo eliminar si NO hay subtareas pendientes marcadas manualmente
        // (conservamos el E si el total de subtareas es 0, es decir fue marcado a mano)
        if ((int)$r['total'] > 0) {
            $db->prepare("DELETE FROM programacion WHERE actividad_id=:a AND anio=:y AND mes=:m AND tipo='E'")
               ->execute([':a' => $actId, ':y' => $anio, ':m' => $mes]);
        }
    }

    jsonResponse([
        'subtarea'      => $subtarea,
        'todas_done'    => $todasCompletadas,
        'auto_ejecutada'=> $autoMarcado,
        'total'         => (int)$r['total'],
        'completadas'   => (int)$r['completadas'],
    ]);
}

function eliminarSubtarea(PDO $db): never {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) throw new Exception('ID requerido');
    $db->prepare("DELETE FROM subtareas WHERE id=:id")->execute([':id' => $id]);
    jsonResponse(['message' => 'Subtarea eliminada']);
}

function actualizarEstado(PDO $db): never {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $db->prepare("UPDATE programacion SET estado=:e, observaciones=:o
                          WHERE actividad_id=:a AND anio=:y AND mes=:m AND tipo=:t");
    $stmt->execute([
        ':e' => $data['estado'],
        ':o' => $data['observaciones'] ?? null,
        ':a' => (int)$data['actividad_id'],
        ':y' => (int)$data['anio'],
        ':m' => (int)$data['mes'],
        ':t' => $data['tipo'],
    ]);
    jsonResponse(['message' => 'Estado actualizado']);
}
