<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$q = limpiar($_GET['q'] ?? '');

$resultados = [];
if (!empty($q)) {
    // Buscar por ID exacto
    if (is_numeric($q)) {
        $st = $db->prepare("SELECT t.*, u.nombre as cliente_nombre, w.nombre as web_nombre FROM tickets t JOIN usuarios u ON t.usuario_id = u.id JOIN webs w ON t.web_id = w.id WHERE t.id = ?");
        $st->execute([$q]);
        $resultados = array_merge($resultados, $st->fetchAll());
    }

    // Buscar en asunto, mensaje, cliente
    $like = '%' . $q . '%';
    $st = $db->prepare("SELECT DISTINCT t.*, u.nombre as cliente_nombre, w.nombre as web_nombre
        FROM tickets t
        JOIN usuarios u ON t.usuario_id = u.id
        JOIN webs w ON t.web_id = w.id
        LEFT JOIN respuestas r ON t.id = r.ticket_id
        WHERE t.asunto LIKE ? OR t.mensaje LIKE ? OR u.nombre LIKE ? OR u.email LIKE ? OR r.mensaje LIKE ?
        ORDER BY t.fecha_actualizacion DESC
        LIMIT 50");
    $st->execute([$like, $like, $like, $like, $like]);
    $resultados = array_merge($resultados, $st->fetchAll());

    // Eliminar duplicados por ID
    $unicos = [];
    $ids_vistos = [];
    foreach ($resultados as $r) {
        if (!in_array($r['id'], $ids_vistos)) {
            $unicos[] = $r;
            $ids_vistos[] = $r['id'];
        }
    }
    $resultados = $unicos;
}

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-search"></i> Resultados: «<?=e($q)?>»</h4>
<a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<?php if (empty($q)): ?>
<div class="alert alert-info"><i class="bi bi-info-circle"></i> Escribe algo en el buscador para empezar</div>
<?php elseif (empty($resultados)): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No se encontraron resultados para «<?=e($q)?>»</div>
<?php else: ?>
<div class="card">
<div class="card-body">
<p class="text-muted small mb-3"><i class="bi bi-check-circle"></i> <?=count($resultados)?> resultado<?=count($resultados)!=1?'s':''?> encontrado<?=count($resultados)!=1?'s':''?></p>
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead><tr><th>ID</th><th>Cliente</th><th>Asunto</th><th>Web</th><th>Estado</th><th>Prioridad</th><th>Actualizado</th><th></th></tr></thead>
<tbody>
<?php foreach ($resultados as $t): ?>
<?php $clases = ['prio-' . $t['prioridad']]; if($t['tiene_incidencia']) $clases[] = 'table-danger'; ?>
<tr class="<?=implode(' ', $clases)?>">
<td><strong>#<?=$t['id']?></strong></td>
<td><?=e($t['cliente_nombre'])?></td>
<td><?=e($t['asunto'])?> <?php if($t['tiene_incidencia']): ?><span class="etiqueta-incidencia">Incidencia</span><?php endif; ?></td>
<td><?=e($t['web_nombre'])?></td>
<td><?=estadoBadge($t['estado'])?></td>
<td><?=prioridadBadge($t['prioridad'])?></td>
<td><?=formatearFecha($t['fecha_actualizacion'])?></td>
<td><a href="ver_ticket.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
