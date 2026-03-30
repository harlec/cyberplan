<?php
require_once 'config.php';
$anio_actual = (int)($_GET['anio'] ?? date('Y'));
$anios_disponibles = range(2024, 2027);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CyberPlan — <?= APP_COMPANY ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700;900&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root {
  --primary:   #7c5cbf; --primary-d: #6347a8; --primary-l: rgba(124,92,191,.1);
  --teal:      #00c8d4; --teal-l: rgba(0,200,212,.12);
  --orange:    #f7b731; --orange-l: rgba(247,183,49,.12);
  --green:     #2ecc71; --green-l: rgba(46,204,113,.12);
  --red:       #e55353; --red-l: rgba(229,83,83,.1);
  --bg:        #eef2ff;
  --surface:   #ffffff; --surface2: #f7f8ff; --surface3: #f0f2ff;
  --border:    rgba(0,0,0,.07); --border2: rgba(0,0,0,.12);
  --text:      #1e1e2d; --text2: #6b7280; --text3: #9ca3af;
  --radius:    16px; --radius-sm: 10px;
  --shadow:    0 2px 16px rgba(124,92,191,.08);
  --shadow-lg: 0 12px 48px rgba(124,92,191,.16);
  --shadow-card: 0 2px 12px rgba(0,0,0,.05);
  --font: 'Titillium Web', sans-serif; --mono: 'JetBrains Mono', monospace;
  --sidebar-w: 240px; --header-h: 68px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--font);background:linear-gradient(135deg,#dce8ff 0%,#ece8ff 55%,#e0f0ff 100%) fixed;background-attachment:fixed;color:var(--text);min-height:100vh;display:flex;overflow-x:hidden}

/* SIDEBAR */
.sidebar{width:var(--sidebar-w);background:var(--surface);border-right:1px solid var(--border);box-shadow:4px 0 24px rgba(0,0,0,.04);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100}
.sidebar-logo{padding:22px 20px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.logo-icon{width:38px;height:38px;background:linear-gradient(135deg,var(--primary),var(--teal));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;box-shadow:0 4px 12px rgba(124,92,191,.3)}
.logo-text strong{font-size:16px;font-weight:700;color:var(--text);display:block;line-height:1.1}
.logo-text span{font-size:10px;color:var(--text2);letter-spacing:.5px;text-transform:uppercase}
.sidebar-section{padding:16px 16px 4px;font-size:10px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--text3)}
.sidebar-nav{flex:1;overflow-y:auto;padding:8px}
.sidebar-nav a{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--radius-sm);color:var(--text2);text-decoration:none;font-size:13.5px;font-weight:600;transition:all .2s;cursor:pointer;margin-bottom:2px}
.sidebar-nav a:hover{background:var(--surface2);color:var(--text)}
.sidebar-nav a.active{background:var(--primary-l);color:var(--primary);font-weight:700}
.sidebar-nav a.active i{color:var(--primary)}
.sidebar-nav a i{width:16px;text-align:center;font-size:14px;color:var(--text3);transition:color .2s}
.sidebar-footer{padding:16px;border-top:1px solid var(--border)}
.user-card{display:flex;align-items:center;gap:10px;padding:10px;background:var(--surface2);border-radius:var(--radius-sm);border:1px solid var(--border)}
.user-avatar{width:34px;height:34px;background:linear-gradient(135deg,var(--orange),var(--primary));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(124,92,191,.25)}
.user-info strong{font-size:12px;display:block;color:var(--text)}
.user-info span{font-size:10px;color:var(--text2)}

/* MAIN */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.header{height:var(--header-h);background:rgba(255,255,255,.85);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 32px;position:sticky;top:0;z-index:50}
.header-left{display:flex;align-items:center;gap:16px}
.page-title{font-size:19px;font-weight:700;color:var(--text)}
.page-sub{font-size:12px;color:var(--text2)}
.header-right{display:flex;align-items:center;gap:12px}
.year-selector{display:flex;align-items:center;gap:6px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:7px 14px;box-shadow:var(--shadow-card)}
.year-selector select{background:transparent;border:none;color:var(--text);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer;outline:none}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--radius-sm);border:none;font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;white-space:nowrap}
.btn-primary{background:var(--primary);color:#fff;box-shadow:0 4px 14px rgba(124,92,191,.35)}
.btn-primary:hover{background:var(--primary-d);transform:translateY(-1px);box-shadow:0 6px 20px rgba(124,92,191,.45)}
.btn-ghost{background:var(--surface);color:var(--text2);border:1px solid var(--border);box-shadow:var(--shadow-card)}
.btn-ghost:hover{color:var(--text);border-color:var(--border2);background:var(--surface2)}
.btn-danger{background:var(--red-l);color:var(--red);border:1px solid rgba(229,83,83,.2)}
.btn-danger:hover{background:rgba(229,83,83,.18)}
.btn-orange{background:var(--orange-l);color:#c48a00;border:1px solid rgba(247,183,49,.3)}
.btn-blue{background:var(--teal-l);color:#008a94;border:1px solid rgba(0,200,212,.3)}
.btn-blue:hover{background:rgba(0,200,212,.2)}
.btn-sm{padding:6px 12px;font-size:12px}

/* CONTENT */
.content{padding:28px 32px;flex:1}
.view{display:none}
.view.active{display:block;animation:fadeIn .25s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

/* STAT CARDS */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:22px;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s;box-shadow:var(--shadow-card)}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 32px rgba(124,92,191,.12)}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:3px 3px 0 0}
.stat-card.green::before{background:linear-gradient(90deg,var(--green),var(--teal))}
.stat-card.orange::before{background:linear-gradient(90deg,var(--orange),#f7971e)}
.stat-card.blue::before{background:linear-gradient(90deg,var(--teal),var(--primary))}
.stat-card.red::before{background:linear-gradient(90deg,var(--red),var(--orange))}
.stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:16px}
.stat-icon.green{background:var(--green-l);color:var(--green)}
.stat-icon.orange{background:var(--orange-l);color:#c48a00}
.stat-icon.blue{background:var(--teal-l);color:#008a94}
.stat-icon.red{background:var(--red-l);color:var(--red)}
.stat-value{font-size:34px;font-weight:900;line-height:1;margin-bottom:4px;color:var(--text)}
.stat-label{font-size:11.5px;color:var(--text2);font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.stat-badge{position:absolute;top:18px;right:18px;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px}
.badge-green{background:var(--green-l);color:#1a9e50}
.badge-orange{background:var(--orange-l);color:#c48a00}

/* CHARTS */
.charts-row{display:grid;grid-template-columns:1fr 320px;gap:16px;margin-bottom:24px}
.chart-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow-card)}
.chart-title{font-size:14px;font-weight:700;margin-bottom:3px;color:var(--text)}
.chart-sub{font-size:11px;color:var(--text2);margin-bottom:20px}
.chart-wrap{position:relative;height:200px}
.gauge-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0;height:100%}
.gauge-arc{position:relative;width:200px;height:110px}
.gauge-arc canvas{width:200px!important;height:110px!important}
.gauge-center{text-align:center;margin-top:-8px}
.gauge-pct{font-size:40px;font-weight:900;line-height:1;background:linear-gradient(135deg,var(--primary),var(--teal));-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.gauge-label{font-size:11px;color:var(--text2);font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.legend-list{margin-top:16px;width:100%}
.legend-item{display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:12px}
.legend-item:last-child{border:none}
.legend-dot{width:8px;height:8px;border-radius:50%;margin-right:8px;flex-shrink:0}
.legend-left{display:flex;align-items:center;color:var(--text2)}
.legend-val{font-weight:700;font-size:13px;color:var(--text)}

/* SECTION HEADER */
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:12px;flex-wrap:wrap}
.section-title{font-size:15px;font-weight:700;color:var(--text)}
.filters-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.search-box{display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:7px 14px;transition:border-color .2s,box-shadow .2s;box-shadow:var(--shadow-card)}
.search-box:focus-within{border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-l)}
.search-box i{color:var(--text3);font-size:12px}
.search-box input{background:transparent;border:none;color:var(--text);font-family:var(--font);font-size:12px;outline:none;width:150px}
.search-box input::placeholder{color:var(--text3)}
.filter-select{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:var(--font);font-size:12px;padding:7px 12px;cursor:pointer;outline:none;font-weight:600;box-shadow:var(--shadow-card)}
.filter-select:focus{border-color:var(--primary)}

/* TABLE */
.table-container{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:auto;box-shadow:var(--shadow-card)}
.cron-table{width:100%;border-collapse:collapse;font-size:12.5px;min-width:1000px}
.cron-table thead th{background:var(--surface2);padding:11px 14px;text-align:center;font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border);white-space:nowrap;position:sticky;top:0}
.cron-table thead th.left{text-align:left}
.cron-table tbody td{padding:10px 14px;border-bottom:1px solid rgba(0,0,0,.04);vertical-align:middle;color:var(--text)}
.cron-table tbody tr:last-child td{border-bottom:none}
.cron-table tbody tr:hover td{background:var(--surface2)}
.td-name{max-width:260px;line-height:1.4;color:var(--text)}
.td-code{font-family:var(--mono);font-size:11px;font-weight:600;white-space:nowrap;color:var(--text)}

.cat-badge{display:inline-block;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700;font-family:var(--mono)}
.cat-F2{background:var(--orange-l);color:#c48a00}
.cat-F3{background:var(--green-l);color:#1a9e50}
.cat-F1{background:var(--teal-l);color:#008a94}
.badge-resp{display:inline-block;padding:3px 10px;background:var(--primary-l);color:var(--primary);border-radius:20px;font-size:10px;font-weight:700}

/* PILLS */
.month-head{min-width:56px}
.current-month{color:var(--primary)!important;position:relative}
.current-month::after{content:'';position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:20px;height:2px;background:var(--primary);border-radius:2px}
.month-cell{text-align:center;min-width:56px;padding:6px 4px!important}
.cell-grid{display:flex;flex-direction:column;align-items:center;gap:3px}
.pill{display:inline-flex;align-items:center;justify-content:center;width:26px;height:20px;border-radius:6px;font-size:10px;font-weight:700;cursor:pointer;border:1.5px solid transparent;transition:all .15s;user-select:none;font-family:var(--mono)}
.pill-P{background:var(--teal-l);color:#008a94;border-color:rgba(0,200,212,.3)}
.pill-P:hover{background:rgba(0,200,212,.22)}
.pill-E{background:var(--green-l);color:#1a9e50;border-color:rgba(46,204,113,.35)}
.pill-E:hover{background:rgba(46,204,113,.24)}
.pill-empty{background:transparent;color:var(--text3);border:1.5px dashed var(--border2);font-size:9px}
.pill-empty:hover{background:var(--surface2);border-color:var(--primary);color:var(--primary);border-style:solid}
.pill-vencido{background:var(--red-l);border-color:rgba(229,83,83,.25);color:var(--red)}

.legend-bar{display:flex;gap:20px;align-items:center;padding:12px 16px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:14px;flex-wrap:wrap;box-shadow:var(--shadow-card)}
.legend-bar-item{display:flex;align-items:center;gap:6px;font-size:11.5px;color:var(--text2)}

/* CARDS DE CONFIGURACION */
.config-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.config-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow-card)}
.config-card-title{font-size:14px;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:8px;color:var(--text)}
.config-card-sub{font-size:11.5px;color:var(--text2);margin-bottom:18px}
.form-group{margin-bottom:14px}
.form-label{display:block;font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.form-input,.form-select{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:var(--font);font-size:13px;padding:10px 14px;outline:none;transition:border-color .2s,box-shadow .2s}
.form-input:focus,.form-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-l)}
.form-input[type=password]{font-family:var(--mono);letter-spacing:2px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-hint{font-size:10.5px;color:var(--text3);margin-top:4px}
.toggle-wrap{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:10px}
.toggle-label{font-size:13px;font-weight:600;color:var(--text)}
.toggle-sub{font-size:11px;color:var(--text2)}
.toggle{position:relative;width:40px;height:22px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:var(--border2);border-radius:22px;cursor:pointer;transition:.3s}
.toggle-slider::before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.toggle input:checked+.toggle-slider{background:var(--primary)}
.toggle input:checked+.toggle-slider::before{transform:translateX(18px)}

/* USUARIOS */
.user-rol-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700}
.rol-lider{background:var(--orange-l);color:#c48a00}
.rol-responsable{background:var(--teal-l);color:#008a94}
.rol-admin{background:var(--primary-l);color:var(--primary)}
.rol-viewer{background:rgba(107,114,128,.1);color:var(--text2)}
.check-icon{color:var(--green);font-size:13px}
.cross-icon{color:var(--text3);font-size:13px}

/* LOG */
.log-estado-enviado{color:#1a9e50;font-weight:700;font-size:11px}
.log-estado-error{color:var(--red);font-weight:700;font-size:11px}
.log-tipo{display:inline-block;padding:3px 8px;border-radius:6px;font-size:10px;font-weight:700}
.tipo-ejecucion{background:var(--green-l);color:#1a9e50}
.tipo-resumen{background:var(--teal-l);color:#008a94}
.tipo-prueba{background:var(--orange-l);color:#c48a00}

/* ASIGNACIONES */
.assign-row{display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-bottom:1px solid rgba(0,0,0,.04);font-size:12.5px}
.assign-row:last-child{border:none}
.assign-row:hover{background:var(--surface2)}

/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(30,30,45,.35);backdrop-filter:blur(10px);z-index:200;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:30px;width:100%;max-width:520px;box-shadow:0 24px 64px rgba(0,0,0,.14);transform:translateY(20px) scale(.98);transition:transform .25s}
.modal-overlay.open .modal{transform:translateY(0) scale(1)}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px}
.modal-title{font-size:16px;font-weight:700;color:var(--text)}
.modal-close{background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text2);cursor:pointer;font-size:14px;padding:6px 9px;transition:all .2s}
.modal-close:hover{color:var(--text);background:var(--surface3)}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:24px}

/* TOAST */
.toast-container{position:fixed;bottom:24px;right:24px;z-index:300;display:flex;flex-direction:column;gap:8px}
.toast{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:13px 18px;font-size:13px;display:flex;align-items:center;gap:10px;box-shadow:0 8px 32px rgba(0,0,0,.12);animation:slideIn .3s ease;min-width:260px;font-weight:600;color:var(--text)}
.toast.success{border-left:3px solid var(--green)}
.toast.success i{color:var(--green)}
.toast.error{border-left:3px solid var(--red)}
.toast.error i{color:var(--red)}
@keyframes slideIn{from{transform:translateX(40px);opacity:0}to{transform:translateX(0);opacity:1}}
.loading{display:flex;align-items:center;justify-content:center;padding:40px;gap:12px;color:var(--text2)}
.spinner{width:24px;height:24px;border:2px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.empty-state{text-align:center;padding:48px 20px;color:var(--text2)}
.empty-state i{font-size:40px;color:var(--text3);margin-bottom:12px;display:block}
.empty-state p{font-size:14px}

/* NOTIF BADGE */
.notif-sending{animation:pulse 1s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🛡️</div>
    <div class="logo-text">
      <strong>CyberPlan</strong>
      <span>AUNOR · Aleatica</span>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section">Principal</div>
    <a class="active" onclick="showView('dashboard')" id="nav-dashboard"><i class="fas fa-gauge-high"></i> Dashboard</a>
    <a onclick="showView('cronograma')" id="nav-cronograma"><i class="fas fa-calendar-check"></i> Cronograma</a>
    <div class="sidebar-section" style="margin-top:6px">Gestión</div>
    <a onclick="showView('actividades')" id="nav-actividades"><i class="fas fa-list-check"></i> Actividades</a>
    <a onclick="showView('usuarios')" id="nav-usuarios"><i class="fas fa-users"></i> Usuarios</a>
    <div class="sidebar-section" style="margin-top:6px">Sistema</div>
    <a onclick="showView('configuracion')" id="nav-configuracion"><i class="fas fa-gear"></i> Configuración</a>
    <a onclick="showView('email_log')" id="nav-email_log"><i class="fas fa-envelope-circle-check"></i> Log de Correos</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">HR</div>
      <div class="user-info"><strong>Hugo Reyes</strong><span>Líder TI · AUNOR</span></div>
    </div>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <header class="header">
    <div class="header-left">
      <div>
        <div class="page-title" id="page-title">Dashboard</div>
        <div class="page-sub" id="page-sub">Control de Cronogramas de Ciberseguridad</div>
      </div>
    </div>
    <div class="header-right">
      <div class="year-selector">
        <i class="fas fa-calendar" style="color:var(--green);font-size:12px"></i>
        <select id="yearSelect" onchange="changeYear(this.value)">
          <?php foreach($anios_disponibles as $a): ?>
          <option value="<?= $a ?>" <?= $a===$anio_actual?'selected':'' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-ghost btn-sm" onclick="location.reload()"><i class="fas fa-rotate-right"></i></button>
      <button class="btn btn-primary btn-sm" onclick="openModalNueva()"><i class="fas fa-plus"></i> Nueva</button>
    </div>
  </header>

  <div class="content">

    <!-- ══ DASHBOARD ══ -->
    <div class="view active" id="view-dashboard">
      <div class="stats-grid" id="statsGrid"><div class="loading" style="grid-column:1/-1"><div class="spinner"></div> Cargando...</div></div>
      <div class="charts-row">
        <div class="chart-card">
          <div class="chart-title">Cumplimiento Mensual</div>
          <div class="chart-sub">Programadas (P) vs Ejecutadas (E) · <?= $anio_actual ?></div>
          <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
        </div>
        <div class="chart-card">
          <div class="chart-title">Cumplimiento General</div>
          <div class="chart-sub">% ejecutado acumulado</div>
          <div class="gauge-wrap">
            <div class="gauge-arc"><canvas id="gaugeChart"></canvas></div>
            <div class="gauge-center">
              <div class="gauge-pct" id="gaugePct">—</div>
              <div class="gauge-label">Cumplimiento</div>
            </div>
            <div class="legend-list" id="legendList"></div>
          </div>
        </div>
      </div>
      <div class="section-header">
        <div class="section-title"><i class="fas fa-clock" style="color:var(--orange);margin-right:8px"></i>Actividades del Mes Actual</div>
      </div>
      <div class="table-container" id="actividadesMes"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- ══ CRONOGRAMA ══ -->
    <div class="view" id="view-cronograma">
      <div class="section-header">
        <div class="section-title"><i class="fas fa-calendar-check" style="color:var(--green);margin-right:8px"></i>Cronograma Anual <?= $anio_actual ?></div>
        <div class="filters-row">
          <div class="search-box"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Buscar..." oninput="filterTable()"></div>
          <select class="filter-select" id="catFilter" onchange="filterTable()">
            <option value="">Todas las categorías</option>
            <option value="F1">F1</option><option value="F2">F2</option><option value="F3">F3</option>
          </select>
          <select class="filter-select" id="respFilter" onchange="filterTable()">
            <option value="">Todos los responsables</option>
            <option value="RSRR">RSRR</option>
          </select>
          <button class="btn btn-ghost btn-sm" onclick="exportCSV()"><i class="fas fa-download"></i> CSV</button>
        </div>
      </div>
      <div class="legend-bar">
        <div class="legend-bar-item"><div class="pill pill-P" style="cursor:default">P</div> Programado</div>
        <div class="legend-bar-item"><div class="pill pill-E" style="cursor:default">E</div> Ejecutado</div>
        <div class="legend-bar-item"><div class="pill pill-vencido" style="cursor:default">P</div> Vencido</div>
        <div style="margin-left:auto;font-size:11px;color:var(--text3)"><i class="fas fa-envelope" style="color:var(--green)"></i> Al marcar E se envía notificación</div>
      </div>
      <div class="table-container" id="cronogramaTable"><div class="loading"><div class="spinner"></div> Cargando...</div></div>
    </div>

    <!-- ══ ACTIVIDADES ══ -->
    <div class="view" id="view-actividades">
      <div class="section-header">
        <div class="section-title"><i class="fas fa-list-check" style="color:var(--blue);margin-right:8px"></i>Gestión de Actividades</div>
        <button class="btn btn-primary btn-sm" onclick="openModalNueva()"><i class="fas fa-plus"></i> Nueva Actividad</button>
      </div>
      <div class="table-container" id="actividadesTable"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- ══ USUARIOS ══ -->
    <div class="view" id="view-usuarios">
      <div class="section-header">
        <div class="section-title"><i class="fas fa-users" style="color:var(--orange);margin-right:8px"></i>Usuarios y Destinatarios</div>
        <button class="btn btn-primary btn-sm" onclick="openModalUsuario()"><i class="fas fa-plus"></i> Nuevo Usuario</button>
      </div>
      <div class="table-container" id="usuariosTable"><div class="loading"><div class="spinner"></div></div></div>

      <div style="margin-top:20px">
        <div class="section-header">
          <div class="section-title"><i class="fas fa-link" style="color:var(--blue);margin-right:8px"></i>Asignaciones Actividad → Usuario</div>
          <button class="btn btn-blue btn-sm" onclick="openModalAsignar()"><i class="fas fa-plus"></i> Asignar</button>
        </div>
        <div class="table-container" id="asignacionesTable"><div class="loading"><div class="spinner"></div></div></div>
      </div>
    </div>

    <!-- ══ CONFIGURACIÓN ══ -->
    <div class="view" id="view-configuracion">
      <div class="config-grid">
        <!-- SMTP -->
        <div class="config-card">
          <div class="config-card-title"><i class="fas fa-server" style="color:var(--blue)"></i> Servidor SMTP — Office 365</div>
          <div class="config-card-sub">Credenciales para envío de correos corporativos</div>
          <div class="form-group">
            <label class="form-label">Servidor SMTP</label>
            <input type="text" class="form-input" id="cfg_smtp_host" placeholder="smtp.office365.com">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Puerto</label>
              <input type="number" class="form-input" id="cfg_smtp_port" placeholder="587">
            </div>
            <div class="form-group">
              <label class="form-label">Nombre remitente</label>
              <input type="text" class="form-input" id="cfg_smtp_nombre" placeholder="CyberPlan - AUNOR">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Correo corporativo</label>
            <input type="email" class="form-input" id="cfg_smtp_usuario" placeholder="tu.correo@empresa.pe">
          </div>
          <div class="form-group">
            <label class="form-label">Contraseña</label>
            <input type="password" class="form-input" id="cfg_smtp_password" placeholder="••••••••">
            <div class="form-hint">Se almacena en la BD. Usar contraseña de aplicación si tienes 2FA activo.</div>
          </div>
          <div class="form-group">
            <label class="form-label">URL de la aplicación</label>
            <input type="text" class="form-input" id="cfg_app_url" placeholder="https://cyberplan.aunor.pe">
          </div>
          <!-- Test -->
          <div style="display:flex;gap:10px;align-items:flex-end;margin-top:4px">
            <div class="form-group" style="flex:1;margin-bottom:0">
              <label class="form-label">Correo de prueba</label>
              <input type="email" class="form-input" id="testEmail" placeholder="hugo@aunor.pe">
            </div>
            <button class="btn btn-orange btn-sm" onclick="enviarPrueba()" style="margin-bottom:0"><i class="fas fa-paper-plane"></i> Probar</button>
          </div>
        </div>

        <!-- NOTIFICACIONES -->
        <div class="config-card">
          <div class="config-card-title"><i class="fas fa-bell" style="color:var(--green)"></i> Notificaciones</div>
          <div class="config-card-sub">Configurar qué correos se envían y cuándo</div>

          <div class="toggle-wrap">
            <div>
              <div class="toggle-label">✅ Notificación al marcar E</div>
              <div class="toggle-sub">Envía correo al responsable y líder TI cuando se ejecuta una actividad</div>
            </div>
            <label class="toggle"><input type="checkbox" id="cfg_notif_ejecucion" checked><span class="toggle-slider"></span></label>
          </div>

          <div class="toggle-wrap">
            <div>
              <div class="toggle-label">📊 Resumen semanal automático</div>
              <div class="toggle-sub">Envía resumen cada lunes con estado de actividades y % de cumplimiento</div>
            </div>
            <label class="toggle"><input type="checkbox" id="cfg_notif_resumen" checked><span class="toggle-slider"></span></label>
          </div>

          <div style="margin-top:16px">
            <div class="config-card-title" style="font-size:13px;margin-bottom:12px"><i class="fas fa-clock" style="color:var(--orange)"></i> Cron Job — Resumen Semanal</div>
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px">
              <div style="font-size:11px;color:var(--text2);margin-bottom:8px">Agrega esta línea al crontab del servidor:</div>
              <code style="font-family:var(--mono);font-size:11px;color:var(--green);background:var(--bg);padding:8px 12px;border-radius:6px;display:block;word-break:break-all">0 8 * * 1 /usr/bin/php /ruta/cyberplan/cron/resumen_semanal.php >> /var/log/cyberplan.log 2>&1</code>
              <div style="font-size:10.5px;color:var(--text3);margin-top:8px">Se ejecuta cada lunes a las 08:00 AM</div>
            </div>
          </div>

          <div style="margin-top:16px">
            <button class="btn btn-primary btn-sm" onclick="enviarResumenAhora()"><i class="fas fa-paper-plane"></i> Enviar resumen ahora</button>
            <div style="font-size:10.5px;color:var(--text3);margin-top:6px">Envía el resumen semanal inmediatamente a todos los usuarios configurados</div>
          </div>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:4px">
        <button class="btn btn-ghost" onclick="loadConfig()"><i class="fas fa-rotate-right"></i> Recargar</button>
        <button class="btn btn-primary" onclick="saveConfig()"><i class="fas fa-save"></i> Guardar Configuración</button>
      </div>
    </div>

    <!-- ══ EMAIL LOG ══ -->
    <div class="view" id="view-email_log">
      <div class="section-header">
        <div class="section-title"><i class="fas fa-envelope-circle-check" style="color:var(--blue);margin-right:8px"></i>Log de Correos Enviados</div>
        <button class="btn btn-ghost btn-sm" onclick="loadEmailLog()"><i class="fas fa-rotate-right"></i> Actualizar</button>
      </div>
      <div class="table-container" id="logTable"><div class="loading"><div class="spinner"></div></div></div>
    </div>

  </div>
</main>

<!-- MODAL ACTIVIDAD -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modalTitle">Nueva Actividad</div>
      <button class="modal-close" onclick="closeModalDirect()"><i class="fas fa-times"></i></button>
    </div>
    <form id="actividadForm" onsubmit="guardarActividad(event)">
      <input type="hidden" id="editId">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Código</label><input type="text" class="form-input" id="fCodigo" placeholder="F2-004" required></div>
        <div class="form-group"><label class="form-label">Categoría</label>
          <select class="form-select" id="fCategoria"><option value="F1">F1</option><option value="F2" selected>F2</option><option value="F3">F3</option></select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Nombre</label><input type="text" class="form-input" id="fNombre" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Responsable</label><input type="text" class="form-input" id="fResponsable" placeholder="RSRR"></div>
        <div class="form-group"><label class="form-label">Año</label><input type="number" class="form-input" id="fAnio" value="<?= $anio_actual ?>" min="2024" max="2030"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModalDirect()">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL USUARIO -->
<div class="modal-overlay" id="modalUsuarioOverlay" onclick="closeModalUsuario(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modalUsuarioTitle">Nuevo Usuario</div>
      <button class="modal-close" onclick="closeModalUsuarioDirect()"><i class="fas fa-times"></i></button>
    </div>
    <form id="usuarioForm" onsubmit="guardarUsuario(event)">
      <input type="hidden" id="uEditId">
      <div class="form-group"><label class="form-label">Nombre completo</label><input type="text" class="form-input" id="uNombre" required></div>
      <div class="form-group"><label class="form-label">Correo electrónico</label><input type="email" class="form-input" id="uEmail" required placeholder="nombre@empresa.pe"></div>
      <div class="form-group"><label class="form-label">Rol</label>
        <select class="form-select" id="uRol">
          <option value="lider_ti">Líder TI</option>
          <option value="responsable" selected>Responsable</option>
          <option value="viewer">Solo lectura</option>
        </select>
      </div>
      <div style="display:flex;gap:16px;margin-top:4px">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" id="uNotif" checked> Recibe notificaciones de ejecución
        </label>
      </div>
      <div style="display:flex;gap:16px;margin-top:10px">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" id="uResumen" checked> Recibe resumen semanal
        </label>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeModalUsuarioDirect()">Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL ASIGNAR -->
<div class="modal-overlay" id="modalAsignarOverlay" onclick="closeModalAsignar(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Asignar Usuario a Actividad</div>
      <button class="modal-close" onclick="closeModalAsignarDirect()"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group"><label class="form-label">Actividad</label>
      <select class="form-select" id="aActividad"></select>
    </div>
    <div class="form-group"><label class="form-label">Usuario</label>
      <select class="form-select" id="aUsuario"></select>
    </div>
    <div class="form-group"><label class="form-label">Rol en la asignación</label>
      <select class="form-select" id="aRolAsig">
        <option value="responsable">Responsable (recibe notificación al marcar E)</option>
        <option value="notificado">Solo notificado</option>
      </select>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModalAsignarDirect()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarAsignacion()"><i class="fas fa-link"></i> Asignar</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const STATE = { anio: <?= $anio_actual ?>, mesActual: <?= date('n') ?>, datos: null, stats: null, charts: {}, usuarios: [], actividades: [] };
const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'];
const MESES_F = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Setiembre','Octubre','Noviembre','Diciembre'];

// ── NAVEGACIÓN ──────────────────────────────
function showView(n) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
  document.getElementById('view-' + n).classList.add('active');
  document.getElementById('nav-' + n)?.classList.add('active');
  const T = { dashboard:'Dashboard', cronograma:'Cronograma Anual', actividades:'Gestión de Actividades', usuarios:'Usuarios y Destinatarios', configuracion:'Configuración SMTP', email_log:'Log de Correos' };
  document.getElementById('page-title').textContent = T[n] || n;
  if (n === 'cronograma')   loadCronograma();
  if (n === 'actividades')  loadActividadesTable();
  if (n === 'usuarios')     { loadUsuarios(); loadAsignaciones(); }
  if (n === 'configuracion') loadConfig();
  if (n === 'email_log')    loadEmailLog();
}
function changeYear(y) {
  STATE.anio = parseInt(y); STATE.datos = null; STATE.stats = null; loadDashboard();
  if (document.getElementById('view-cronograma').classList.contains('active')) loadCronograma();
}

// ── API ─────────────────────────────────────
async function api(url, method = 'GET', body = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json' } };
  if (body) opts.body = JSON.stringify(body);
  const r = await fetch(url, opts);
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return r.json();
}
const cronAPI = p => 'api/cronograma.php?' + new URLSearchParams(p);
const usrAPI  = p => p.action === 'resumen' ? 'api/resumen.php' : 'api/usuarios.php?' + new URLSearchParams(p);

// ── DASHBOARD ───────────────────────────────
async function loadDashboard() {
  try {
    if (!STATE.stats) STATE.stats = await api(cronAPI({ action: 'stats', anio: STATE.anio }));
    renderStats(STATE.stats); renderCharts(STATE.stats);
    if (!STATE.datos) STATE.datos = await api(cronAPI({ action: 'cronograma', anio: STATE.anio }));
    renderActMes();
  } catch(e) { toast('Error: ' + e.message, 'error'); }
}

function renderStats(s) {
  document.getElementById('statsGrid').innerHTML = `
    <div class="stat-card green"><div class="stat-icon green"><i class="fas fa-list-check"></i></div><div class="stat-value">${s.total_actividades}</div><div class="stat-label">Total Actividades</div><div class="stat-badge badge-green">${STATE.anio}</div></div>
    <div class="stat-card blue"><div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div><div class="stat-value">${s.programadas}</div><div class="stat-label">Programadas</div><div class="stat-badge badge-green">hasta ${MESES[s.mes_actual-1]}</div></div>
    <div class="stat-card green"><div class="stat-icon green"><i class="fas fa-circle-check"></i></div><div class="stat-value">${s.ejecutadas}</div><div class="stat-label">Ejecutadas</div><div class="stat-badge badge-green">${s.cumplimiento_pct}%</div></div>
    <div class="stat-card ${s.vencidas>0?'red':'green'}"><div class="stat-icon ${s.vencidas>0?'red':'green'}"><i class="fas fa-triangle-exclamation"></i></div><div class="stat-value">${s.vencidas}</div><div class="stat-label">Vencidas</div>${s.vencidas>0?'<div class="stat-badge" style="background:rgba(229,83,83,.1);color:#e55353">Atención</div>':''}</div>`;
}

function renderCharts(s) {
  const lbl = s.por_mes.map(m => MESES[m.mes-1]);
  const ctx = document.getElementById('monthlyChart').getContext('2d');
  if (STATE.charts.bar) STATE.charts.bar.destroy();
  STATE.charts.bar = new Chart(ctx, { type: 'bar', data: { labels: lbl, datasets: [
    { label:'Programadas', data: s.por_mes.map(m=>+m.programadas||0), backgroundColor:'rgba(0,200,212,.18)', borderColor:'#00c8d4', borderWidth:1.5, borderRadius:6 },
    { label:'Ejecutadas',  data: s.por_mes.map(m=>+m.ejecutadas||0),  backgroundColor:'rgba(124,92,191,.22)', borderColor:'#7c5cbf', borderWidth:1.5, borderRadius:6 },
  ]}, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ labels:{ color:'#6b7280', font:{family:'Titillium Web',size:11} } }, tooltip:{backgroundColor:'#fff',borderColor:'rgba(0,0,0,.08)',borderWidth:1,titleColor:'#1e1e2d',bodyColor:'#6b7280',boxShadow:'0 4px 20px rgba(0,0,0,.1)'} }, scales:{ x:{ticks:{color:'#6b7280',font:{size:10}},grid:{color:'rgba(0,0,0,.04)'}}, y:{ticks:{color:'#6b7280',stepSize:1},grid:{color:'rgba(0,0,0,.04)'},beginAtZero:true} } }});

  const pct = s.cumplimiento_pct;
  const gCtx = document.getElementById('gaugeChart').getContext('2d');
  if (STATE.charts.gauge) STATE.charts.gauge.destroy();
  STATE.charts.gauge = new Chart(gCtx, { type:'doughnut', data:{ datasets:[{ data:[pct,100-pct], backgroundColor:[pct>=80?'#7c5cbf':pct>=50?'#f7b731':'#e55353','#f0f2ff'], borderWidth:0, borderRadius:4 }]}, options:{ responsive:false, rotation:-90, circumference:180, plugins:{legend:{display:false},tooltip:{enabled:false}}, cutout:'72%'}});
  document.getElementById('gaugePct').textContent = pct + '%';
  const clr = {F1:'#00c8d4',F2:'#f7b731',F3:'#2ecc71'};
  document.getElementById('legendList').innerHTML = s.por_categoria.map(c=>`<div class="legend-item"><div class="legend-left"><div class="legend-dot" style="background:${clr[c.categoria]||'#ccc'}"></div>Categoría ${c.categoria}</div><span class="legend-val">${c.total}</span></div>`).join('') +
    `<div class="legend-item"><div class="legend-left"><div class="legend-dot" style="background:#e55353"></div>Vencidas</div><span class="legend-val" style="color:#e55353">${s.vencidas}</span></div>`;
}

function renderActMes() {
  const mes = STATE.mesActual;
  const acts = (STATE.datos?.actividades||[]).filter(a => { const m=a.meses[mes]||{}; return m['P']||m['E']; });
  const cont = document.getElementById('actividadesMes');
  if (!acts.length) { cont.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-xmark"></i><p>Sin actividades para este mes</p></div>'; return; }
  cont.innerHTML = `<table class="cron-table"><thead><tr><th class="left">Código</th><th class="left">Actividad</th><th>Responsable</th><th>P</th><th>E</th><th>Estado</th></tr></thead><tbody>${acts.map(a=>{
    const m=a.meses[mes]||{};const hasE=!!m['E'],hasP=!!m['P'];
    const sc=hasE?'var(--green)':hasP?'var(--orange)':'var(--text3)';
    return`<tr><td class="td-code"><span class="cat-badge cat-${a.categoria}">${a.codigo}</span></td><td class="td-name">${a.nombre}</td><td style="text-align:center">${a.responsable?`<span class="badge-resp">${a.responsable}</span>`:'—'}</td><td style="text-align:center">${hasP?'<span class="pill pill-P" style="cursor:default">P</span>':'—'}</td><td style="text-align:center">${hasE?'<span class="pill pill-E" style="cursor:default">E</span>':'—'}</td><td style="text-align:center;color:${sc};font-weight:700;font-size:11.5px">${hasE?'✓ Completado':hasP?'⏳ Pendiente':'—'}</td></tr>`;
  }).join('')}</tbody></table>`;
}

// ── CRONOGRAMA ──────────────────────────────
async function loadCronograma() {
  document.getElementById('cronogramaTable').innerHTML = '<div class="loading"><div class="spinner"></div> Cargando...</div>';
  try { if (!STATE.datos) STATE.datos = await api(cronAPI({action:'cronograma',anio:STATE.anio})); renderCronograma(STATE.datos.actividades); }
  catch(e) { toast('Error: '+e.message,'error'); }
}
function renderCronograma(acts) {
  const mes = STATE.mesActual;
  document.getElementById('cronogramaTable').innerHTML = `<table class="cron-table" id="mainTable"><thead><tr>
    <th class="left" style="min-width:72px">Cat.</th>
    <th class="left" style="min-width:90px">Código</th>
    <th class="left" style="min-width:260px">Actividad</th>
    <th>Resp.</th>
    ${MESES.map((m,i)=>`<th class="month-head ${i+1===mes?'current-month':''}">${m}</th>`).join('')}
    <th>Acciones</th>
  </tr></thead><tbody id="cronBody">${acts.map(a=>buildRow(a,mes)).join('')}</tbody></table>`;
}
function buildRow(a, mesActual) {
  const m = a.meses||{};
  const cells = Array.from({length:12},(_,i)=>{
    const mes=i+1,inf=m[mes]||{},hasP=!!inf['P'],hasE=!!inf['E'],past=mes<mesActual;
    const pP=hasP?`<div class="pill ${past&&!hasE?'pill-vencido':'pill-P'}" onclick="toggle(${a.id},${mes},'P')">P</div>`:`<div class="pill pill-empty" onclick="toggle(${a.id},${mes},'P')">+P</div>`;
    const pE=hasE?`<div class="pill pill-E" onclick="toggle(${a.id},${mes},'E')">E</div>`:`<div class="pill pill-empty" onclick="toggle(${a.id},${mes},'E')">+E</div>`;
    return`<td class="month-cell"><div class="cell-grid">${pP}${pE}</div></td>`;
  }).join('');
  return`<tr data-id="${a.id}" data-cat="${a.categoria}" data-resp="${a.responsable||''}" data-name="${a.nombre.toLowerCase()}">
    <td><span class="cat-badge cat-${a.categoria}">${a.categoria}</span></td>
    <td class="td-code">${a.codigo}</td>
    <td class="td-name">${a.nombre}</td>
    <td style="text-align:center">${a.responsable?`<span class="badge-resp">${a.responsable}</span>`:'<span style="color:var(--text3)">—</span>'}</td>
    ${cells}
    <td style="text-align:center;white-space:nowrap">
      <button class="btn btn-ghost btn-sm" onclick="editAct(${a.id})"><i class="fas fa-pen"></i></button>
      <button class="btn btn-danger btn-sm" onclick="deleteAct(${a.id})" style="margin-left:4px"><i class="fas fa-trash"></i></button>
    </td></tr>`;
}

async function toggle(id, mes, tipo) {
  try {
    const r = await api(cronAPI({action:'toggle'}), 'POST', {actividad_id:id, anio:STATE.anio, mes, tipo});
    const act = STATE.datos?.actividades?.find(x => x.id == id);
    if (act) {
      if (!act.meses) act.meses = {};
      if (!act.meses[mes]) act.meses[mes] = {};
      if (r.action === 'added') act.meses[mes][tipo] = {estado: tipo==='E'?'completado':'pendiente'};
      else delete act.meses[mes][tipo];
      const row = document.querySelector(`tr[data-id="${id}"]`);
      if (row) row.outerHTML = buildRow(act, STATE.mesActual);
    }
    STATE.stats = null;
    toast(r.action === 'added' ? `${tipo} registrado` : `${tipo} removido`, 'success');

    // ↓ Si se marcó E, enviar notificación por correo
    if (tipo === 'E' && r.action === 'added') {
      enviarNotificacionEjecucion(id, mes);
    }
  } catch(e) { toast('Error: '+e.message,'error'); }
}

async function enviarNotificacionEjecucion(actId, mes) {
  try {
    const r = await api('api/notificar.php', 'POST', {actividad_id: actId, mes});
    if (r.skipped) return; // sin destinatarios configurados, silencioso
    if (r.success) toast(`📧 Notificación enviada a ${r.destinatarios} destinatario(s)`, 'success');
    else toast('Actividad marcada, pero falló el correo. Revisa Log.', 'error');
  } catch(_) { /* silencioso */ }
}

function filterTable() {
  const q=document.getElementById('searchInput').value.toLowerCase();
  const cat=document.getElementById('catFilter').value;
  const resp=document.getElementById('respFilter').value;
  document.querySelectorAll('#mainTable tbody tr').forEach(r=>{
    r.style.display=(!q||r.dataset.name?.includes(q))&&(!cat||r.dataset.cat===cat)&&(!resp||r.dataset.resp===resp)?'':'none';
  });
}

// ── ACTIVIDADES TABLE ────────────────────────
async function loadActividadesTable() {
  document.getElementById('actividadesTable').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
  try {
    const d = await api(cronAPI({action:'actividades',anio:STATE.anio}));
    STATE.actividades = d;
    if (!d.length) { document.getElementById('actividadesTable').innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>No hay actividades</p></div>'; return; }
    document.getElementById('actividadesTable').innerHTML = `<table class="cron-table"><thead><tr><th style="width:40px">#</th><th class="left">Código</th><th class="left">Nombre</th><th>Categoría</th><th>Responsable</th><th>Año</th><th>Acciones</th></tr></thead><tbody>${d.map((a,i)=>`<tr>
      <td style="text-align:center;color:var(--text3);font-weight:700">${i+1}</td>
      <td class="td-code">${a.codigo}</td><td class="td-name">${a.nombre}</td>
      <td style="text-align:center"><span class="cat-badge cat-${a.categoria}">${a.categoria}</span></td>
      <td style="text-align:center">${a.responsable?`<span class="badge-resp">${a.responsable}</span>`:'—'}</td>
      <td style="text-align:center;color:var(--text2);font-weight:700">${a.anio}</td>
      <td style="text-align:center;white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick="editAct(${a.id})"><i class="fas fa-pen"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteAct(${a.id})" style="margin-left:4px"><i class="fas fa-trash"></i></button>
      </td></tr>`).join('')}</tbody></table>`;
  } catch(e) { toast('Error: '+e.message,'error'); }
}

// ── USUARIOS ─────────────────────────────────
async function loadUsuarios() {
  document.getElementById('usuariosTable').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
  try {
    const d = await api(usrAPI({action:'usuarios'}));
    STATE.usuarios = d;
    const rolBadge = r => `<span class="user-rol-badge rol-${r}">${{lider_ti:'Líder TI',responsable:'Responsable',admin:'Admin',viewer:'Viewer'}[r]||r}</span>`;
    document.getElementById('usuariosTable').innerHTML = `<table class="cron-table"><thead><tr>
      <th class="left">Nombre</th><th class="left">Email</th><th>Rol</th>
      <th>Notif. E</th><th>Resumen</th><th>Estado</th><th>Acciones</th>
    </tr></thead><tbody>${d.map(u=>`<tr>
      <td style="font-weight:700">${u.nombre}</td>
      <td style="font-family:var(--mono);font-size:11px;color:var(--text2)">${u.email}</td>
      <td style="text-align:center">${rolBadge(u.rol)}</td>
      <td style="text-align:center"><i class="fas ${u.recibe_notificaciones=='1'?'fa-check check-icon':'fa-xmark cross-icon'}"></i></td>
      <td style="text-align:center"><i class="fas ${u.recibe_resumen_semanal=='1'?'fa-check check-icon':'fa-xmark cross-icon'}"></i></td>
      <td style="text-align:center"><span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;background:${u.activo=='1'?'rgba(114,191,68,.2)':'rgba(110,118,129,.2)'};color:${u.activo=='1'?'var(--green)':'var(--text3)'}">${u.activo=='1'?'Activo':'Inactivo'}</span></td>
      <td style="text-align:center;white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick="editUsuario(${u.id})"><i class="fas fa-pen"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteUsuario(${u.id})" style="margin-left:4px"><i class="fas fa-trash"></i></button>
      </td></tr>`).join('')}</tbody></table>`;
  } catch(e) { toast('Error: '+e.message,'error'); }
}

async function loadAsignaciones() {
  document.getElementById('asignacionesTable').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
  try {
    const d = await api(usrAPI({action:'asignaciones'}));
    if (!d.length) { document.getElementById('asignacionesTable').innerHTML = '<div class="empty-state"><i class="fas fa-link-slash"></i><p>Sin asignaciones. Usa el botón Asignar.</p></div>'; return; }
    document.getElementById('asignacionesTable').innerHTML = `<table class="cron-table"><thead><tr>
      <th class="left">Actividad</th><th class="left">Usuario</th><th>Email</th><th>Rol asignación</th><th>Acciones</th>
    </tr></thead><tbody>${d.map(a=>`<tr>
      <td><span class="cat-badge cat-${a.codigo?.substring(0,2)||'F2'}">${a.codigo}</span> <span style="font-size:12px;color:var(--text2);margin-left:6px">${a.act_nombre}</span></td>
      <td style="font-weight:700">${a.nombre}</td>
      <td style="font-family:var(--mono);font-size:11px;color:var(--text2)">${a.email}</td>
      <td style="text-align:center"><span class="badge-resp" style="${a.rol_asignacion==='responsable'?'':'background:rgba(114,191,68,.15);color:var(--green)'}">${a.rol_asignacion==='responsable'?'Responsable':'Notificado'}</span></td>
      <td style="text-align:center"><button class="btn btn-danger btn-sm" onclick="desasignar(${a.actividad_id},${a.usuario_id})"><i class="fas fa-trash"></i></button></td>
    </tr>`).join('')}</tbody></table>`;
  } catch(e) { toast('Error: '+e.message,'error'); }
}

function openModalUsuario() {
  document.getElementById('modalUsuarioTitle').textContent = 'Nuevo Usuario';
  document.getElementById('uEditId').value = '';
  document.getElementById('usuarioForm').reset();
  document.getElementById('modalUsuarioOverlay').classList.add('open');
}
function editUsuario(id) {
  const u = STATE.usuarios.find(x => x.id == id);
  if (!u) return;
  document.getElementById('modalUsuarioTitle').textContent = 'Editar Usuario';
  document.getElementById('uEditId').value = id;
  document.getElementById('uNombre').value = u.nombre;
  document.getElementById('uEmail').value = u.email;
  document.getElementById('uRol').value = u.rol;
  document.getElementById('uNotif').checked = u.recibe_notificaciones == '1';
  document.getElementById('uResumen').checked = u.recibe_resumen_semanal == '1';
  document.getElementById('modalUsuarioOverlay').classList.add('open');
}
function closeModalUsuario(e) { if(e.target===document.getElementById('modalUsuarioOverlay')) closeModalUsuarioDirect(); }
function closeModalUsuarioDirect() { document.getElementById('modalUsuarioOverlay').classList.remove('open'); }

async function guardarUsuario(e) {
  e.preventDefault();
  const id = document.getElementById('uEditId').value;
  const p = { nombre:document.getElementById('uNombre').value.trim(), email:document.getElementById('uEmail').value.trim(), rol:document.getElementById('uRol').value, activo:1, recibe_notificaciones:document.getElementById('uNotif').checked?1:0, recibe_resumen_semanal:document.getElementById('uResumen').checked?1:0 };
  try {
    if (id) { p.id=parseInt(id); await api(usrAPI({action:'usuario'}), 'PUT', p); toast('Usuario actualizado','success'); }
    else { await api(usrAPI({action:'usuario'}), 'POST', p); toast('Usuario creado','success'); }
    closeModalUsuarioDirect(); loadUsuarios();
  } catch(e) { toast('Error: '+e.message,'error'); }
}
async function deleteUsuario(id) {
  if (!confirm('¿Desactivar este usuario?')) return;
  try { await api(usrAPI({action:'usuario',id}), 'DELETE'); toast('Usuario desactivado','success'); loadUsuarios(); }
  catch(e) { toast('Error: '+e.message,'error'); }
}

// ── ASIGNACIONES ─────────────────────────────
async function openModalAsignar() {
  // Cargar listas si no están
  if (!STATE.actividades.length) STATE.actividades = await api(cronAPI({action:'actividades',anio:STATE.anio}));
  if (!STATE.usuarios.length)    STATE.usuarios    = await api(usrAPI({action:'usuarios'}));
  document.getElementById('aActividad').innerHTML = STATE.actividades.map(a=>`<option value="${a.id}">${a.codigo} — ${a.nombre}</option>`).join('');
  document.getElementById('aUsuario').innerHTML   = STATE.usuarios.filter(u=>u.activo=='1').map(u=>`<option value="${u.id}">${u.nombre} (${u.email})</option>`).join('');
  document.getElementById('modalAsignarOverlay').classList.add('open');
}
function closeModalAsignar(e) { if(e.target===document.getElementById('modalAsignarOverlay')) closeModalAsignarDirect(); }
function closeModalAsignarDirect() { document.getElementById('modalAsignarOverlay').classList.remove('open'); }
async function guardarAsignacion() {
  const p = { actividad_id:parseInt(document.getElementById('aActividad').value), usuario_id:parseInt(document.getElementById('aUsuario').value), rol_asignacion:document.getElementById('aRolAsig').value };
  try { await api(usrAPI({action:'asignar'}), 'POST', p); toast('Asignación guardada','success'); closeModalAsignarDirect(); loadAsignaciones(); }
  catch(e) { toast('Error: '+e.message,'error'); }
}
async function desasignar(actId, usrId) {
  if (!confirm('¿Quitar esta asignación?')) return;
  try { await api(usrAPI({action:'asignar',actividad_id:actId,usuario_id:usrId}), 'DELETE'); toast('Asignación removida','success'); loadAsignaciones(); }
  catch(e) { toast('Error: '+e.message,'error'); }
}

// ── CONFIGURACIÓN ────────────────────────────
async function loadConfig() {
  try {
    const cfg = await api(usrAPI({action:'config'}));
    const set = (id, key) => { const el=document.getElementById(id); if(el) el.value=cfg[key]?.valor||''; };
    const setChk = (id, key) => { const el=document.getElementById(id); if(el) el.checked=(cfg[key]?.valor||'1')==='1'; };
    set('cfg_smtp_host','smtp_host'); set('cfg_smtp_port','smtp_port');
    set('cfg_smtp_usuario','smtp_usuario'); set('cfg_smtp_password','smtp_password');
    set('cfg_smtp_nombre','smtp_nombre'); set('cfg_app_url','app_url');
    setChk('cfg_notif_ejecucion','notif_ejecucion');
    setChk('cfg_notif_resumen','notif_resumen');
  } catch(e) { toast('Error cargando config: '+e.message,'error'); }
}
async function saveConfig() {
  const get = id => document.getElementById(id)?.value || '';
  const getChk = id => document.getElementById(id)?.checked ? '1' : '0';
  const payload = {
    smtp_host: get('cfg_smtp_host'), smtp_port: get('cfg_smtp_port'),
    smtp_usuario: get('cfg_smtp_usuario'), smtp_password: get('cfg_smtp_password'),
    smtp_nombre: get('cfg_smtp_nombre'), app_url: get('cfg_app_url'),
    notif_ejecucion: getChk('cfg_notif_ejecucion'),
    notif_resumen: getChk('cfg_notif_resumen'),
  };
  try { await api(usrAPI({action:'config'}), 'POST', payload); toast('✅ Configuración guardada','success'); }
  catch(e) { toast('Error: '+e.message,'error'); }
}
async function enviarPrueba() {
  const email = document.getElementById('testEmail').value.trim();
  if (!email) { toast('Ingresa un correo de prueba','error'); return; }
  toast('Enviando correo de prueba...','success');
  try {
    const r = await api(usrAPI({action:'test_email'}), 'POST', {email});
    toast(r.success ? '📧 Correo de prueba enviado correctamente' : '❌ Falló: revisa las credenciales SMTP', r.success?'success':'error');
  } catch(e) { toast('Error: '+e.message,'error'); }
}
async function enviarResumenAhora() {
  toast('Generando resumen semanal...', 'success');
  try {
    const r = await api(usrAPI({action:'resumen'}), 'POST');
    toast(r.success ? '📊 ' + r.message : '❌ ' + r.message, r.success ? 'success' : 'error');
    if (r.success) loadEmailLog();
  } catch(e) { toast('Error: ' + e.message, 'error'); }
}

// ── EMAIL LOG ────────────────────────────────
async function loadEmailLog() {
  document.getElementById('logTable').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
  try {
    const d = await api(usrAPI({action:'email_log'}));
    if (!d.length) { document.getElementById('logTable').innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>Sin correos registrados aún</p></div>'; return; }
    const tipoCls = t => ({ejecucion:'tipo-ejecucion',resumen_semanal:'tipo-resumen',prueba:'tipo-prueba'}[t]||'');
    document.getElementById('logTable').innerHTML = `<table class="cron-table"><thead><tr>
      <th>Tipo</th><th class="left">Asunto</th><th class="left">Destinatarios</th><th>Estado</th><th>Fecha</th>
    </tr></thead><tbody>${d.map(l=>`<tr>
      <td style="text-align:center"><span class="log-tipo ${tipoCls(l.tipo)}">${l.tipo}</span></td>
      <td style="font-size:12px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${l.asunto||'—'}</td>
      <td style="font-size:11px;color:var(--text2);font-family:var(--mono);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${l.destinatarios||'—'}</td>
      <td style="text-align:center"><span class="log-estado-${l.estado}"><i class="fas ${l.estado==='enviado'?'fa-check':'fa-xmark'}"></i> ${l.estado}</span></td>
      <td style="font-size:11px;color:var(--text2);white-space:nowrap">${l.enviado_en}</td>
    </tr>`).join('')}</tbody></table>`;
  } catch(e) { toast('Error: '+e.message,'error'); }
}

// ── MODAL ACTIVIDAD ──────────────────────────
function openModalNueva() {
  document.getElementById('modalTitle').textContent = 'Nueva Actividad';
  document.getElementById('editId').value = '';
  document.getElementById('actividadForm').reset();
  document.getElementById('fAnio').value = STATE.anio;
  document.getElementById('modalOverlay').classList.add('open');
}
function editAct(id) {
  const a = STATE.datos?.actividades?.find(x => x.id == id);
  if (!a) { toast('Actividad no encontrada','error'); return; }
  document.getElementById('modalTitle').textContent = 'Editar Actividad';
  document.getElementById('editId').value = id;
  document.getElementById('fCodigo').value = a.codigo;
  document.getElementById('fNombre').value = a.nombre;
  document.getElementById('fResponsable').value = a.responsable || '';
  document.getElementById('fCategoria').value = a.categoria || 'F2';
  document.getElementById('fAnio').value = a.anio || STATE.anio;
  document.getElementById('modalOverlay').classList.add('open');
}
function closeModal(e) { if(e.target===document.getElementById('modalOverlay')) closeModalDirect(); }
function closeModalDirect() { document.getElementById('modalOverlay').classList.remove('open'); }
async function guardarActividad(e) {
  e.preventDefault();
  const id = document.getElementById('editId').value;
  const p = { codigo:document.getElementById('fCodigo').value.trim(), nombre:document.getElementById('fNombre').value.trim(), responsable:document.getElementById('fResponsable').value.trim(), categoria:document.getElementById('fCategoria').value, anio:parseInt(document.getElementById('fAnio').value), repositorio_id:1 };
  try {
    if (id) { p.id=parseInt(id); await api(cronAPI({action:'actividad'}), 'PUT', p); toast('Actualizado','success'); }
    else { await api(cronAPI({action:'actividad'}), 'POST', p); toast('Creado','success'); }
    closeModalDirect(); STATE.datos=null; STATE.stats=null;
    if (document.getElementById('view-cronograma').classList.contains('active')) loadCronograma();
    if (document.getElementById('view-actividades').classList.contains('active')) loadActividadesTable();
  } catch(e) { toast('Error: '+e.message,'error'); }
}
async function deleteAct(id) {
  if (!confirm('¿Eliminar esta actividad?')) return;
  try { await api(cronAPI({action:'actividad',id}), 'DELETE'); toast('Eliminada','success'); STATE.datos=null; STATE.stats=null;
    if (document.getElementById('view-cronograma').classList.contains('active')) loadCronograma();
    if (document.getElementById('view-actividades').classList.contains('active')) loadActividadesTable();
  } catch(e) { toast('Error: '+e.message,'error'); }
}

// ── CSV ──────────────────────────────────────
function exportCSV() {
  if (!STATE.datos) { toast('Carga el cronograma primero','error'); return; }
  const h = ['Codigo','Nombre','Responsable','Categoria',...MESES.flatMap(m=>[m+'_P',m+'_E'])];
  const r = STATE.datos.actividades.map(a=>{const b=[a.codigo,`"${a.nombre}"`,a.responsable||'',a.categoria];const c=Array.from({length:12},(_,i)=>{const m=i+1,inf=a.meses[m]||{};return[inf['P']?'P':'',inf['E']?'E':''];}).flat();return[...b,...c].join(',');});
  const blob=new Blob([[h.join(','),...r].join('\n')],{type:'text/csv;charset=utf-8'});
  const u=URL.createObjectURL(blob);const a=document.createElement('a');a.href=u;a.download=`cronograma_${STATE.anio}.csv`;a.click();URL.revokeObjectURL(u);toast('CSV exportado','success');
}

// ── TOAST ────────────────────────────────────
function toast(msg, type='success') {
  const c=document.getElementById('toastContainer');
  const ico=type==='success'?'fa-circle-check':'fa-circle-xmark';
  const t=document.createElement('div');t.className=`toast ${type}`;
  t.innerHTML=`<i class="fas ${ico}"></i><span>${msg}</span>`;c.appendChild(t);
  setTimeout(()=>{t.style.opacity='0';t.style.transform='translateX(40px)';setTimeout(()=>t.remove(),300);},4000);
}

document.addEventListener('DOMContentLoaded', loadDashboard);
</script>
</body>
</html>
