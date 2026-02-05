<?php
require_once 'config.php';
session_start();
if (!estaLogueado()) redirigir('login.php');
if (esAdmin() && !estaImpersonando()) redirigir('admin/');

$db = getDB();
$uid = $_SESSION['usuario_id'];

$st = $db->prepare("SELECT COUNT(*) as t FROM tickets WHERE usuario_id = ?"); $st->execute([$uid]); $total = $st->fetch()['t'];
$st = $db->prepare("SELECT COUNT(*) as t FROM tickets WHERE usuario_id = ? AND estado = 'abierto'"); $st->execute([$uid]); $abiertos = $st->fetch()['t'];
$st = $db->prepare("SELECT COUNT(*) as t FROM tickets WHERE usuario_id = ? AND estado = 'en_proceso'"); $st->execute([$uid]); $en_proceso = $st->fetch()['t'];
$st = $db->prepare("SELECT COUNT(*) as t FROM webs WHERE usuario_id = ?"); $st->execute([$uid]); $webs = $st->fetch()['t'];
$st = $db->prepare("SELECT t.*, w.nombre as web_nombre FROM tickets t JOIN webs w ON t.web_id = w.id WHERE t.usuario_id = ? ORDER BY t.tiene_incidencia DESC, t.fecha_creacion DESC LIMIT 10"); $st->execute([$uid]); $tickets = $st->fetchAll();

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
<h4><i class="bi bi-speedometer2"></i> Dashboard</h4>
<a href="nuevo_ticket.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo Ticket</a>
</div>
<div class="row">
<div class="col-md-3 col-6 mb-3"><div class="card text-white bg-primary stat-card"><div class="card-body text-center"><div class="stat-number"><?=$total?></div><p class="mb-0"><i class="bi bi-ticket"></i> Total Tickets</p></div></div></div>
<div class="col-md-3 col-6 mb-3"><div class="card text-white bg-info stat-card"><div class="card-body text-center"><div class="stat-number"><?=$abiertos?></div><p class="mb-0"><i class="bi bi-circle"></i> Abiertos</p></div></div></div>
<div class="col-md-3 col-6 mb-3"><div class="card text-white bg-warning stat-card"><div class="card-body text-center"><div class="stat-number"><?=$en_proceso?></div><p class="mb-0"><i class="bi bi-arrow-clockwise"></i> En Proceso</p></div></div></div>
<div class="col-md-3 col-6 mb-3"><div class="card text-white bg-success stat-card"><div class="card-body text-center"><div class="stat-number"><?=$webs?></div><p class="mb-0"><i class="bi bi-globe"></i> Webs</p></div></div></div>
</div>
<div class="card">
<div class="card-header"><i class="bi bi-clock-history"></i> Tickets Recientes</div>
<div class="card-body">
<?php if (empty($tickets)): ?>
<div class="text-center py-4 text-muted"><i class="bi bi-ticket" style="font-size:2rem;"></i><p>No tienes tickets aún. <a href="nuevo_ticket.php">Crea el primero</a></p></div>
<?php else: ?>
<div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>ID</th><th>Asunto</th><th>Web</th><th>Tags</th><th>Estado</th><th>Prioridad</th><th>Fecha</th><th></th></tr></thead>
<tbody>
<?php foreach ($tickets as $t): ?>
<?php $tags = obtenerTagsTicket($t['id']); ?>
<tr>
<td><strong>#<?=$t['id']?></strong></td>
<td><?=e($t['asunto'])?> <?php if ($t['tiene_incidencia']): ?><span class="etiqueta-incidencia">Incidencia</span><?php endif; ?></td>
<td><?=e($t['web_nombre'])?></td>
<td><?=renderTags($tags)?></td>
<td><?=estadoBadge($t['estado'])?></td>
<td><?=prioridadBadge($t['prioridad'])?></td>
<td><?=formatearFecha($t['fecha_creacion'])?></td>
<td><a href="ver_ticket.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div>
</div>
<?php include 'includes/footer.php'; ?>
