<?php
require_once 'config.php';
session_start();
if (!estaLogueado()) redirigir('login.php');
if (esAdmin() && !estaImpersonando()) redirigir('admin/');

$db = getDB();
$uid = $_SESSION['usuario_id'];

$st = $db->prepare("SELECT * FROM webs WHERE usuario_id = ? ORDER BY nombre"); $st->execute([$uid]); $webs = $st->fetchAll();
$todos_tags = $db->query("SELECT * FROM tags ORDER BY nombre")->fetchAll();

$fe = $_GET['estado'] ?? ''; $fw = $_GET['web'] ?? ''; $fp = $_GET['prioridad'] ?? ''; $ft = $_GET['tag'] ?? ''; $ftxt = $_GET['texto'] ?? '';

$where = ["t.usuario_id = ?"]; $params = [$uid];
if (!empty($fe)) { $where[] = "t.estado = ?"; $params[] = $fe; }
if (!empty($fw)) { $where[] = "t.web_id = ?"; $params[] = (int)$fw; }
if (!empty($fp)) { $where[] = "t.prioridad = ?"; $params[] = $fp; }
if (!empty($ftxt)) { $where[] = "(t.asunto LIKE ? OR t.mensaje LIKE ?)"; $params[] = '%'.$ftxt.'%'; $params[] = '%'.$ftxt.'%'; }
$jt = '';
if (!empty($ft)) { $jt = " JOIN ticket_tags tt ON t.id = tt.ticket_id"; $where[] = "tt.tag_id = ?"; $params[] = (int)$ft; }

$sql = "SELECT t.*, w.nombre as web_nombre,
        (SELECT COUNT(*) FROM respuestas WHERE ticket_id = t.id AND leido_cliente = 0 AND es_nota_interna = 0 AND usuario_id != t.usuario_id) as resp_nuevas
        FROM tickets t JOIN webs w ON t.web_id = w.id $jt WHERE " . implode(" AND ", $where) . " ORDER BY t.tiene_incidencia DESC, t.fecha_creacion DESC";
$st = $db->prepare($sql); $st->execute($params); $tickets = $st->fetchAll();

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-ticket"></i> Mis Tickets</h4>
<a href="nuevo_ticket.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo Ticket</a>
</div>
<div class="filtros">
<form method="get" class="row g-2 align-items-end">
<div class="col-md-2 col-6"><label class="form-label small text-muted">Estado</label>
<select name="estado" class="form-select form-select-sm">
<option value="">Todos</option>
<option value="abierto" <?=$fe=='abierto'?'selected':'';?>>Abierto</option>
<option value="en_proceso" <?=$fe=='en_proceso'?'selected':'';?>>En Proceso</option>
<option value="terminado" <?=$fe=='terminado'?'selected':'';?>>Terminado</option>
</select></div>
<div class="col-md-2 col-6"><label class="form-label small text-muted">Web</label>
<select name="web" class="form-select form-select-sm"><option value="">Todas</option>
<?php foreach ($webs as $w): ?><option value="<?=$w['id']?>" <?=$fw==$w['id']?'selected':'';?>><?=e($w['nombre'])?></option><?php endforeach; ?>
</select></div>
<div class="col-md-2 col-6"><label class="form-label small text-muted">Prioridad</label>
<select name="prioridad" class="form-select form-select-sm"><option value="">Todas</option>
<option value="baja" <?=$fp=='baja'?'selected':'';?>>Baja</option>
<option value="media" <?=$fp=='media'?'selected':'';?>>Media</option>
<option value="alta" <?=$fp=='alta'?'selected':'';?>>Alta</option>
<option value="critica" <?=$fp=='critica'?'selected':'';?>>Crítica</option>
</select></div>
<div class="col-md-2 col-6"><label class="form-label small text-muted">Tag</label>
<select name="tag" class="form-select form-select-sm"><option value="">Todos</option>
<?php foreach ($todos_tags as $tag): ?><option value="<?=$tag['id']?>" <?=$ft==$tag['id']?'selected':'';?>><?=e($tag['nombre'])?></option><?php endforeach; ?>
</select></div>
<div class="col-md-2 col-6"><label class="form-label small text-muted">Buscar</label>
<input type="text" name="texto" class="form-control form-select-sm" value="<?=e($ftxt)?>" placeholder="Texto libre..."></div>
<div class="col-md-2 col-6 d-flex gap-2 align-items-end">
<button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Buscar</button>
<a href="tickets.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
</div>
</form>
</div>
<div class="card"><div class="card-body">
<div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>ID</th><th>Asunto</th><th>Web</th><th>Tags</th><th>Estado</th><th>Prioridad</th><th>Fecha</th><th></th></tr></thead>
<tbody>
<?php if (empty($tickets)): ?><tr><td colspan="8" class="text-center text-muted py-4">No hay tickets que coincidan con los filtros</td></tr><?php endif; ?>
<?php foreach ($tickets as $t): ?>
<?php $tags = obtenerTagsTicket($t['id']); ?>
<tr <?php if($t['resp_nuevas']>0): ?>class="table-info"<?php endif; ?>>
<td><strong>#<?=$t['id']?></strong></td>
<td><?=e($t['asunto'])?>
<?php if ($t['resp_nuevas'] > 0): ?><span class="badge bg-info text-dark ms-1"><i class="bi bi-chat-fill"></i> <?=$t['resp_nuevas']?> nueva<?=$t['resp_nuevas']>1?'s':''?></span><?php endif; ?>
<?php if ($t['tiene_incidencia']): ?><span class="etiqueta-incidencia">Incidencia</span><?php endif; ?></td>
<td><?=e($t['web_nombre'])?></td>
<td><?=renderTags($tags)?></td>
<td><?=estadoBadge($t['estado'])?></td>
<td><?=prioridadBadge($t['prioridad'])?></td>
<td><?=formatearFecha($t['fecha_creacion'])?></td>
<td><a href="ver_ticket.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div></div>
<?php include 'includes/footer.php'; ?>
