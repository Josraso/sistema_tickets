<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();

$total        = $db->query("SELECT COUNT(*) as t FROM tickets")->fetch()['t'];
$abiertos     = $db->query("SELECT COUNT(*) as t FROM tickets WHERE estado = 'abierto'")->fetch()['t'];
$en_proceso   = $db->query("SELECT COUNT(*) as t FROM tickets WHERE estado = 'en_proceso'")->fetch()['t'];
$clientes     = $db->query("SELECT COUNT(*) as t FROM usuarios WHERE rol = 'cliente'")->fetch()['t'];
$pendientes   = $db->query("SELECT COUNT(*) as t FROM usuarios WHERE rol = 'cliente' AND estado = 'pendiente'")->fetch()['t'];
$incidencias  = $db->query("SELECT COUNT(*) as t FROM tickets WHERE tiene_incidencia = 1 AND estado = 'terminado'")->fetch()['t'];

$st = $db->prepare("SELECT t.*, w.nombre as web_nombre, u.nombre as cliente_nombre FROM tickets t JOIN webs w ON t.web_id = w.id JOIN usuarios u ON t.usuario_id = u.id WHERE t.estado != 'terminado' ORDER BY t.tiene_incidencia DESC, t.fecha_actualizacion DESC LIMIT 15");
$st->execute([]); $tickets = $st->fetchAll();

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
<h4><i class="bi bi-speedometer2"></i> Dashboard Admin</h4>
</div>
<!-- Stats clicables -->
<div class="row">
<div class="col-lg-2 col-md-4 col-6 mb-3">
<a href="tickets.php" class="stat-card-link"><div class="card text-white bg-primary stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total?></div><p class="mb-0"><i class="bi bi-ticket"></i> Total Tickets</p></div></div></a>
</div>
<div class="col-lg-2 col-md-4 col-6 mb-3">
<a href="tickets.php?estado=abierto" class="stat-card-link"><div class="card text-white bg-success stat-card"><div class="card-body text-center"><div class="stat-number"><?=$abiertos?></div><p class="mb-0"><i class="bi bi-circle"></i> Abiertos</p></div></div></a>
</div>
<div class="col-lg-2 col-md-4 col-6 mb-3">
<a href="tickets.php?estado=en_proceso" class="stat-card-link"><div class="card text-white bg-warning stat-card"><div class="card-body text-center"><div class="stat-number"><?=$en_proceso?></div><p class="mb-0"><i class="bi bi-arrow-clockwise"></i> En Proceso</p></div></div></a>
</div>
<div class="col-lg-2 col-md-4 col-6 mb-3">
<a href="clientes.php" class="stat-card-link"><div class="card text-white bg-info stat-card"><div class="card-body text-center"><div class="stat-number"><?=$clientes?></div><p class="mb-0"><i class="bi bi-people"></i> Clientes</p></div></div></a>
</div>
<div class="col-lg-2 col-md-4 col-6 mb-3">
<a href="clientes.php" class="stat-card-link"><div class="card text-white stat-card" style="background:#6f42c1;"><div class="card-body text-center"><div class="stat-number"><?=$pendientes?></div><p class="mb-0"><i class="bi bi-hourglass"></i> Pendientes</p></div></div></a>
</div>
<div class="col-lg-2 col-md-4 col-6 mb-3">
<a href="tickets.php?incidencias=1" class="stat-card-link"><div class="card text-white bg-danger stat-card"><div class="card-body text-center"><div class="stat-number"><?=$incidencias?></div><p class="mb-0"><i class="bi bi-exclamation-triangle"></i> Incidencias</p></div></div></a>
</div>
</div>
<!-- Tickets recientes -->
<div class="card">
<div class="card-header d-flex justify-content-between"><span><i class="bi bi-clock-history"></i> Tickets Recientes</span><a href="tickets.php" class="btn btn-sm btn-outline-primary">Ver todos</a></div>
<div class="card-body">
<div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>ID</th><th>Cliente</th><th>Asunto</th><th>Web</th><th>Estado</th><th>Prioridad</th><th>Fecha</th><th></th></tr></thead>
<tbody>
<?php foreach ($tickets as $t): ?>
<?php $tags = obtenerTagsTicket($t['id']); ?>
<tr <?php if($t['tiene_incidencia']): ?>class="table-danger"<?php endif; ?>>
<td><strong>#<?=$t['id']?></strong></td>
<td><?=e($t['cliente_nombre'])?></td>
<td><?=e($t['asunto'])?> <?php if($t['tiene_incidencia']): ?><span class="etiqueta-incidencia">Incidencia</span><?php endif; ?><?=renderTags($tags)?></td>
<td><?=e($t['web_nombre'])?></td>
<td><?=estadoBadge($t['estado'])?></td>
<td><?=prioridadBadge($t['prioridad'])?></td>
<td><?=formatearFecha($t['fecha_actualizacion'])?></td>
<td><a href="ver_ticket.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; ?>
<?php if (empty($tickets)): ?><tr><td colspan="8" class="text-center text-muted py-4">Sin tickets</td></tr><?php endif; ?>
</tbody></table></div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
