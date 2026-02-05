<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();

// Datos filtros
$clientes = $db->query("SELECT DISTINCT u.id, u.nombre FROM usuarios u JOIN tickets t ON u.id = t.usuario_id ORDER BY u.nombre")->fetchAll();
$webs_all = $db->query("SELECT DISTINCT w.id, w.nombre FROM webs w JOIN tickets t ON w.id = t.web_id ORDER BY w.nombre")->fetchAll();
$todos_tags = $db->query("SELECT * FROM tags ORDER BY nombre")->fetchAll();

$fe = $_GET['estado'] ?? ''; $fw = $_GET['web'] ?? ''; $fp = $_GET['prioridad'] ?? '';
$ft = $_GET['tag'] ?? ''; $ftxt = $_GET['texto'] ?? ''; $fc = $_GET['cliente'] ?? '';
$finc = $_GET['incidencia'] ?? '';

$where = ["1=1"]; $params = [];
if (!empty($fe)) { $where[] = "t.estado = ?"; $params[] = $fe; }
if (!empty($fw)) { $where[] = "t.web_id = ?"; $params[] = (int)$fw; }
if (!empty($fp)) { $where[] = "t.prioridad = ?"; $params[] = $fp; }
if (!empty($fc)) { $where[] = "t.usuario_id = ?"; $params[] = (int)$fc; }
if ($finc === '1') { $where[] = "t.tiene_incidencia = 1"; }
if (!empty($ftxt)) { $where[] = "(t.asunto LIKE ? OR t.mensaje LIKE ?)"; $params[] = '%'.$ftxt.'%'; $params[] = '%'.$ftxt.'%'; }
$jt = '';
if (!empty($ft)) { $jt = " JOIN ticket_tags tt ON t.id = tt.ticket_id"; $where[] = "tt.tag_id = ?"; $params[] = (int)$ft; }

$sql = "SELECT t.*, w.nombre as web_nombre, u.nombre as cliente_nombre,
        (SELECT COUNT(*) FROM respuestas WHERE ticket_id = t.id AND leido_admin = 0 AND es_nota_interna = 0) as resp_nuevas
        FROM tickets t JOIN webs w ON t.web_id = w.id JOIN usuarios u ON t.usuario_id = u.id $jt WHERE " . implode(" AND ", $where) . " ORDER BY t.tiene_incidencia DESC, t.fecha_actualizacion DESC";
$st = $db->prepare($sql); $st->execute($params); $tickets = $st->fetchAll();

// CSV export
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="tickets_export_' . date('Y-m-d_H-i') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Cliente','Asunto','Web','Estado','Prioridad','Incidencia','Tags','Fecha Creación','Fecha Actualización'], ';');
    foreach ($tickets as $t) {
        $tags_t = obtenerTagsTicket($t['id']);
        $tags_str = implode(', ', array_column($tags_t, 'nombre'));
        fputcsv($out, [
            $t['id'], $t['cliente_nombre'], $t['asunto'], $t['web_nombre'],
            $t['estado'], $t['prioridad'], $t['tiene_incidencia'] ? 'Sí' : 'No',
            $tags_str, formatearFecha($t['fecha_creacion']), formatearFecha($t['fecha_actualizacion'])
        ], ';');
    }
    fclose($out);
    exit;
}

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-ticket"></i> Tickets <?php $inc=contadorIncidencias(); if($inc>0): ?><span class="badge-inc"><?=$inc?> incidencias</span><?php endif; ?></h4>
<div class="d-flex gap-2">
<!-- Construir URL para export con los mismos filtros -->
<?php
$exp_params = $_GET;
$exp_params['exportar'] = 'csv';
$exp_url = 'tickets.php?' . http_build_query($exp_params);
?>
<a href="<?=e($exp_url)?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> CSV</a>
</div>
</div>
<!-- Filtros -->
<div class="filtros">
<form method="get" class="row g-2 align-items-end">
<div class="col-lg-1 col-md-2 col-6"><label class="form-label small text-muted">Estado</label>
<select name="estado" class="form-select form-select-sm">
<option value="">Todos</option>
<option value="abierto" <?=$fe=='abierto'?'selected':'';?>>Abierto</option>
<option value="en_proceso" <?=$fe=='en_proceso'?'selected':'';?>>En Proceso</option>
<option value="terminado" <?=$fe=='terminado'?'selected':'';?>>Terminado</option>
</select></div>
<div class="col-lg-2 col-md-2 col-6"><label class="form-label small text-muted">Cliente</label>
<select name="cliente" class="form-select form-select-sm"><option value="">Todos</option>
<?php foreach ($clientes as $c): ?><option value="<?=$c['id']?>" <?=$fc==$c['id']?'selected':'';?>><?=e($c['nombre'])?></option><?php endforeach; ?>
</select></div>
<div class="col-lg-2 col-md-2 col-6"><label class="form-label small text-muted">Web</label>
<select name="web" class="form-select form-select-sm"><option value="">Todas</option>
<?php foreach ($webs_all as $w): ?><option value="<?=$w['id']?>" <?=$fw==$w['id']?'selected':'';?>><?=e($w['nombre'])?></option><?php endforeach; ?>
</select></div>
<div class="col-lg-1 col-md-2 col-6"><label class="form-label small text-muted">Prioridad</label>
<select name="prioridad" class="form-select form-select-sm"><option value="">Todas</option>
<option value="baja" <?=$fp=='baja'?'selected':'';?>>Baja</option>
<option value="media" <?=$fp=='media'?'selected':'';?>>Media</option>
<option value="alta" <?=$fp=='alta'?'selected':'';?>>Alta</option>
<option value="critica" <?=$fp=='critica'?'selected':'';?>>Crítica</option>
</select></div>
<div class="col-lg-2 col-md-2 col-6"><label class="form-label small text-muted">Tag</label>
<select name="tag" class="form-select form-select-sm"><option value="">Todos</option>
<?php foreach ($todos_tags as $tag): ?><option value="<?=$tag['id']?>" <?=$ft==$tag['id']?'selected':'';?>><?=e($tag['nombre'])?></option><?php endforeach; ?>
</select></div>
<div class="col-lg-2 col-md-3 col-6"><label class="form-label small text-muted">Buscar</label>
<input type="text" name="texto" class="form-control form-select-sm" value="<?=e($ftxt)?>" placeholder="Texto libre..."></div>
<div class="col-lg-2 col-md-3 col-6 d-flex gap-2 align-items-end">
<div class="form-check form-check-inline">
<input class="form-check-input" type="checkbox" name="incidencia" id="chkInc" value="1" <?=$finc?'checked':'';?>>
<label class="form-check-label small" for="chkInc"><i class="bi bi-exclamation-triangle text-danger"></i> Solo incidencias</label>
</div>
</div>
<div class="col-12 d-flex gap-2 mt-1">
<button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Buscar</button>
<a href="tickets.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
</div>
</form>
</div>
<!-- Tabla -->
<div class="card"><div class="card-body">
<div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>ID</th><th>Cliente</th><th>Asunto</th><th>Web</th><th>Tags</th><th>Estado</th><th>Prioridad</th><th>Actualizado</th><th></th></tr></thead>
<tbody>
<?php if (empty($tickets)): ?><tr><td colspan="9" class="text-center text-muted py-4">Sin tickets</td></tr><?php endif; ?>
<?php foreach ($tickets as $t): ?>
<?php $tags = obtenerTagsTicket($t['id']); ?>
<tr <?php if($t['tiene_incidencia']): ?>class="table-danger"<?php elseif($t['resp_nuevas']>0): ?>class="table-info"<?php endif; ?>>
<td><strong>#<?=$t['id']?></strong></td>
<td><?=e($t['cliente_nombre'])?></td>
<td><?=e($t['asunto'])?>
<?php if ($t['resp_nuevas'] > 0): ?><span class="badge bg-info text-dark ms-1"><i class="bi bi-chat-fill"></i> <?=$t['resp_nuevas']?> nueva<?=$t['resp_nuevas']>1?'s':''?></span><?php endif; ?>
<?php if($t['tiene_incidencia']): ?><span class="etiqueta-incidencia">Incidencia</span><?php endif; ?></td>
<td><?=e($t['web_nombre'])?></td>
<td><?=renderTags($tags)?></td>
<td><?=estadoBadge($t['estado'])?></td>
<td><?=prioridadBadge($t['prioridad'])?></td>
<td><?=formatearFecha($t['fecha_actualizacion'])?></td>
<td><a href="ver_ticket.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div></div>
<?php include 'includes/footer.php'; ?>
