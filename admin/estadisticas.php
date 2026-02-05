<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();

// Resumen general
$total_tickets = $db->query("SELECT COUNT(*) as t FROM tickets")->fetch()['t'];
$total_abiertos = $db->query("SELECT COUNT(*) as t FROM tickets WHERE estado='abierto'")->fetch()['t'];
$total_en_proceso = $db->query("SELECT COUNT(*) as t FROM tickets WHERE estado='en_proceso'")->fetch()['t'];
$total_terminados = $db->query("SELECT COUNT(*) as t FROM tickets WHERE estado='terminado'")->fetch()['t'];
$total_incidencias = $db->query("SELECT COUNT(*) as t FROM tickets WHERE tiene_incidencia=1")->fetch()['t'];
$total_clientes = $db->query("SELECT COUNT(*) as t FROM usuarios WHERE rol='cliente' AND estado='activo'")->fetch()['t'];
$total_webs = $db->query("SELECT COUNT(*) as t FROM webs")->fetch()['t'];

// Por prioridad
$por_prioridad = $db->query("SELECT prioridad, COUNT(*) as t FROM tickets GROUP BY prioridad ORDER BY t DESC")->fetchAll();

// Por cliente (top 10)
$por_cliente = $db->query("SELECT u.nombre, COUNT(t.id) as t FROM tickets t JOIN usuarios u ON t.usuario_id=u.id GROUP BY u.id ORDER BY t DESC LIMIT 10")->fetchAll();

// Por web
$por_web = $db->query("SELECT w.nombre, COUNT(t.id) as t FROM tickets t JOIN webs w ON t.web_id=w.id GROUP BY w.id ORDER BY t DESC LIMIT 10")->fetchAll();

// Por tag
$por_tag = $db->query("SELECT tg.nombre, COUNT(tt.ticket_id) as t FROM tags tg JOIN ticket_tags tt ON tg.id=tt.tag_id GROUP BY tg.id ORDER BY t DESC")->fetchAll();

// Tickets por día (últimos 30 días)
$por_dia = $db->query("SELECT DATE(fecha_creacion) as dia, COUNT(*) as t FROM tickets WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY dia ORDER BY dia")->fetchAll();

// Tiempo medio resolución (tickets terminados con fecha_cierre)
$tiempo = $db->query("SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_cierre)) as horas FROM tickets WHERE estado='terminado' AND fecha_cierre IS NOT NULL")->fetch();
$horas_medio = $tiempo['horas'] ? round($tiempo['horas'], 1) : '—';

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
<h4><i class="bi bi-bar-chart"></i> Estadísticas</h4>
</div>

<!-- Stats principales -->
<div class="row">
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-primary stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_tickets?></div><p class="mb-0">Total Tickets</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-success stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_abiertos?></div><p class="mb-0">Abiertos</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-warning stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_en_proceso?></div><p class="mb-0">En Proceso</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-secondary stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_terminados?></div><p class="mb-0">Terminados</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-danger stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_incidencias?></div><p class="mb-0">Incidencias</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white" style="background:#6f42c1;" stat-card><div class="card-body text-center"><div class="stat-number"><?=$horas_medio?></div><p class="mb-0">Horas resolución<br><small>(medio)</small></p></div></div></div>
</div>

<!-- Gráfico barras por día (últimos 30 días) usando divs -->
<div class="card">
<div class="card-header"><i class="bi bi-calendar3-event"></i> Tickets por día (últimos 30 días)</div>
<div class="card-body">
<?php if (!empty($por_dia)): ?>
<?php $max_dia = max(array_column($por_dia, 't')); if($max_dia<1) $max_dia=1; ?>
<div class="d-flex align-items-end gap-1" style="height:140px;">
<?php foreach($por_dia as $d): ?>
<?php $h = ($d['t']/$max_dia)*120; $dt=new DateTime($d['dia']); ?>
<div class="d-flex flex-column align-items-center flex-grow-1" style="min-width:0;">
<small class="text-muted" style="font-size:0.7rem;"><?=$d['t']?></small>
<div class="bg-primary rounded-top" style="height:<?=$h?>px;width:100%;min-width:8px;"></div>
<small class="text-muted" style="font-size:0.6rem;writing-mode:vertical-rl;transform:rotate(180deg);max-height:40px;overflow:hidden;"><?=$dt->format('d/m')?></small>
</div>
<?php endforeach; ?>
</div>
<?php else: ?><p class="text-muted">Sin datos</p><?php endif; ?>
</div>
</div>

<!-- Tablas -->
<div class="row mt-3">
<!-- Por prioridad -->
<div class="col-md-4">
<div class="card"><div class="card-header"><i class="bi bi-exclamation-circle"></i> Por Prioridad</div>
<div class="card-body">
<?php foreach($por_prioridad as $p): ?>
<div class="d-flex justify-content-between align-items-center mb-2">
<?=prioridadBadge($p['prioridad'])?>
<span class="text-muted"><?=$p['t']?> (<?=round($total_tickets>0 ? ($p['t']/$total_tickets)*100 : 0, 1)?>%)</span>
</div>
<?php endforeach; ?>
<?php if(empty($por_prioridad)): ?><p class="text-muted small">Sin datos</p><?php endif; ?>
</div></div>
</div>
<!-- Por cliente top 10 -->
<div class="col-md-4">
<div class="card"><div class="card-header"><i class="bi bi-people"></i> Top Clientes</div>
<div class="card-body">
<table class="table table-sm mb-0">
<thead><tr><th>#</th><th>Cliente</th><th>Tickets</th></tr></thead>
<tbody>
<?php foreach($por_cliente as $i=>$c): ?><tr><td><?=$i+1?></td><td><?=e($c['nombre'])?></td><td><span class="badge bg-primary"><?=$c['t']?></span></td></tr><?php endforeach; ?>
<?php if(empty($por_cliente)): ?><tr><td colspan="3" class="text-muted">Sin datos</td></tr><?php endif; ?>
</tbody></table>
</div></div>
</div>
<!-- Por web -->
<div class="col-md-4">
<div class="card"><div class="card-header"><i class="bi bi-globe"></i> Top Webs</div>
<div class="card-body">
<table class="table table-sm mb-0">
<thead><tr><th>#</th><th>Web</th><th>Tickets</th></tr></thead>
<tbody>
<?php foreach($por_web as $i=>$w): ?><tr><td><?=$i+1?></td><td><?=e($w['nombre'])?></td><td><span class="badge bg-primary"><?=$w['t']?></span></td></tr><?php endforeach; ?>
<?php if(empty($por_web)): ?><tr><td colspan="3" class="text-muted">Sin datos</td></tr><?php endif; ?>
</tbody></table>
</div></div>
</div>
</div>

<!-- Por tag -->
<?php if(!empty($por_tag)): ?>
<div class="card mt-3"><div class="card-header"><i class="bi bi-tag"></i> Uso de Tags</div>
<div class="card-body">
<div class="d-flex flex-wrap gap-2">
<?php foreach($por_tag as $tg): ?>
<span class="badge bg-light text-dark border p-2" style="font-size:0.9rem;"><?=e($tg['nombre'])?> <span class="badge bg-primary"><?=$tg['t']?></span></span>
<?php endforeach; ?>
</div>
</div></div>
<?php endif; ?>

<div class="row mt-3">
<div class="col-md-6">
<div class="card"><div class="card-header"><i class="bi bi-people"></i> Resumen Clientes</div>
<div class="card-body">
<p><strong><?=$total_clientes?></strong> clientes activos, <strong><?=$total_webs?></strong> webs registradas</p>
<?php $pendientes = $db->query("SELECT COUNT(*) as t FROM usuarios WHERE rol='cliente' AND estado='pendiente'")->fetch()['t']; ?>
<?php if($pendientes > 0): ?><div class="alert alert-warning py-1"><i class="bi bi-hourglass"></i> <strong><?=$pendientes?></strong> cliente(s) pendiente(s) de aprobación</div><?php endif; ?>
</div></div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
