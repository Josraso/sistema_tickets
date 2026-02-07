<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();

// Resumen general
$total_tickets     = $db->query("SELECT COUNT(*) as t FROM tickets")->fetch()['t'];
$total_abiertos    = $db->query("SELECT COUNT(*) as t FROM tickets WHERE estado='abierto'")->fetch()['t'];
$total_en_proceso  = $db->query("SELECT COUNT(*) as t FROM tickets WHERE estado='en_proceso'")->fetch()['t'];
$total_terminados  = $db->query("SELECT COUNT(*) as t FROM tickets WHERE estado='terminado'")->fetch()['t'];
$total_incidencias = $db->query("SELECT COUNT(*) as t FROM tickets WHERE tiene_incidencia=1")->fetch()['t'];

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

// =====================================================
// TIEMPO DE RESOLUCIÓN
// =====================================================

// Total global
$total_tiempo_row = $db->query("SELECT SUM(tiempo_resolucion) as total, COUNT(*) as cnt FROM tickets WHERE estado='terminado' AND tiempo_resolucion IS NOT NULL AND tiempo_resolucion > 0")->fetch();
$total_tiempo_min = $total_tiempo_row['total'] ?? 0;
$total_tickets_con_tiempo = $total_tiempo_row['cnt'] ?? 0;
$medio_tiempo_min = $total_tickets_con_tiempo > 0 ? round($total_tiempo_min / $total_tickets_con_tiempo) : 0;

// Por cliente (agrupado)
$tiempo_por_cliente = $db->query("
    SELECT u.id as uid, u.nombre as cliente,
           COUNT(*) as tickets_cerrados,
           SUM(t.tiempo_resolucion) as minutos_total,
           AVG(t.tiempo_resolucion) as minutos_medio
    FROM tickets t
    JOIN usuarios u ON t.usuario_id = u.id
    WHERE t.estado = 'terminado' AND t.tiempo_resolucion IS NOT NULL AND t.tiempo_resolucion > 0
    GROUP BY u.id
    ORDER BY minutos_total DESC
")->fetchAll();

// Por cliente + web (desglose)
$tiempo_por_cliente_web = $db->query("
    SELECT u.nombre as cliente, w.nombre as web,
           COUNT(*) as tickets_cerrados,
           SUM(t.tiempo_resolucion) as minutos_total,
           AVG(t.tiempo_resolucion) as minutos_medio
    FROM tickets t
    JOIN usuarios u ON t.usuario_id = u.id
    JOIN webs w ON t.web_id = w.id
    WHERE t.estado = 'terminado' AND t.tiempo_resolucion IS NOT NULL AND t.tiempo_resolucion > 0
    GROUP BY u.id, w.id
    ORDER BY minutos_total DESC
")->fetchAll();

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
<h4><i class="bi bi-bar-chart"></i> Estadísticas</h4>
<a href="exportar_estadisticas.php" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Exportar a Excel</a>
</div>

<!-- Stats principales -->
<div class="row">
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-primary stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_tickets?></div><p class="mb-0">Total Tickets</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-success stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_abiertos?></div><p class="mb-0">Abiertos</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-warning stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_en_proceso?></div><p class="mb-0">En Proceso</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-secondary stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_terminados?></div><p class="mb-0">Terminados</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white bg-danger stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total_incidencias?></div><p class="mb-0">Incidencias</p></div></div></div>
<div class="col-lg-2 col-md-4 col-6 mb-3"><div class="card text-white stat-card" style="background:#6f42c1;"><div class="card-body text-center"><div class="stat-number"><?=formatMinutos($total_tiempo_min)?></div><p class="mb-0">Tiempo Total<br><small>(registrado)</small></p></div></div></div>
</div>

<!-- Gráfico barras por día -->
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

<!-- Tablas prioridad / clientes / webs -->
<div class="row mt-3">
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

<!-- Tags -->
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

<!-- =====================================================
     TIEMPO DE RESOLUCIÓN POR CLIENTE
     ============================================= -->
<div class="card mt-3">
<div class="card-header d-flex justify-content-between align-items-center">
<span><i class="bi bi-clock"></i> Tiempo de Resolución — Resumen por Cliente</span>
</div>
<div class="card-body">
<?php if (empty($tiempo_por_cliente)): ?>
<p class="text-muted small">Sin datos de tiempo registrado. El tiempo se registra al cerrar tickets.</p>
<?php else: ?>
<!-- Cards resumen -->
<div class="row mb-3">
<div class="col-md-4"><div class="card text-center border-0 bg-light"><div class="card-body py-2"><strong class="text-secondary" style="font-size:1.4rem;"><?=formatMinutos($total_tiempo_min)?></strong><p class="mb-0 text-muted small">Total registrado</p></div></div></div>
<div class="col-md-4"><div class="card text-center border-0 bg-light"><div class="card-body py-2"><strong class="text-secondary" style="font-size:1.4rem;"><?=formatMinutos($medio_tiempo_min)?></strong><p class="mb-0 text-muted small">Medio por ticket</p></div></div></div>
<div class="col-md-4"><div class="card text-center border-0 bg-light"><div class="card-body py-2"><strong class="text-secondary" style="font-size:1.4rem;"><?=$total_tickets_con_tiempo?></strong><p class="mb-0 text-muted small">Tickets con tiempo</p></div></div></div>
</div>
<!-- Tabla por cliente -->
<table class="table table-hover mb-0">
<thead><tr><th>Cliente</th><th class="text-end">Tickets cerrados</th><th class="text-end">Tiempo total</th><th class="text-end">Tiempo medio</th></tr></thead>
<tbody>
<?php foreach ($tiempo_por_cliente as $tc): ?>
<tr>
<td><strong><?=e($tc['cliente'])?></strong></td>
<td class="text-end"><?=$tc['tickets_cerrados']?></td>
<td class="text-end"><?=formatMinutos($tc['minutos_total'])?></td>
<td class="text-end"><?=formatMinutos(round($tc['minutos_medio']))?></td>
</tr>
<?php endforeach; ?>
<!-- Total -->
<tr class="table-secondary">
<td><strong>TOTAL</strong></td>
<td class="text-end"><strong><?=$total_tickets_con_tiempo?></strong></td>
<td class="text-end"><strong><?=formatMinutos($total_tiempo_min)?></strong></td>
<td class="text-end"><strong><?=formatMinutos($medio_tiempo_min)?></strong></td>
</tr>
</tbody></table>
<?php endif; ?>
</div>
</div>

<!-- Desglose por cliente + web -->
<div class="card mt-3">
<div class="card-header"><i class="bi bi-clock"></i> Tiempo de Resolución — Desglose por Web</div>
<div class="card-body">
<?php if (empty($tiempo_por_cliente_web)): ?>
<p class="text-muted small">Sin datos de tiempo registrado.</p>
<?php else: ?>
<div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>Cliente</th><th>Web</th><th class="text-end">Tickets cerrados</th><th class="text-end">Tiempo total</th><th class="text-end">Tiempo medio</th></tr></thead>
<tbody>
<?php
$cliente_actual = '';
foreach ($tiempo_por_cliente_web as $row):
?>
<?php if ($row['cliente'] !== $cliente_actual): ?>
<?php if ($cliente_actual !== ''): ?>
<!-- Subtotal cliente anterior -->
<?php endif; ?>
<?php $cliente_actual = $row['cliente']; ?>
<?php endif; ?>
<tr>
<td><strong><?=e($row['cliente'])?></strong></td>
<td><?=e($row['web'])?></td>
<td class="text-end"><?=$row['tickets_cerrados']?></td>
<td class="text-end"><?=formatMinutos($row['minutos_total'])?></td>
<td class="text-end"><?=formatMinutos(round($row['minutos_medio']))?></td>
</tr>
<?php endforeach; ?>
<!-- Total global -->
<tr class="table-secondary">
<td colspan="2"><strong>TOTAL</strong></td>
<td class="text-end"><strong><?=$total_tickets_con_tiempo?></strong></td>
<td class="text-end"><strong><?=formatMinutos($total_tiempo_min)?></strong></td>
<td class="text-end"><strong><?=formatMinutos($medio_tiempo_min)?></strong></td>
</tr>
</tbody></table></div>
<?php endif; ?>
</div>
</div>

<?php include 'includes/footer.php'; ?>
