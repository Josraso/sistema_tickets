<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'editar') {
        $nombre  = limpiar($_POST['edit_nombre'] ?? '');
        $dominio = limpiar($_POST['edit_dominio'] ?? '');
        $notas   = limpiar($_POST['edit_notas'] ?? '');
        if (empty($nombre)) {
            $error = 'El nombre es obligatorio';
        } else {
            $db->prepare("UPDATE webs SET nombre = ?, dominio = ?, notas = ? WHERE id = ?")->execute([$nombre, $dominio, $notas, $id]);
            $success = 'Web actualizada';
        }
    } elseif ($accion === 'eliminar') {
        $st = $db->prepare("SELECT COUNT(*) as t FROM tickets WHERE web_id = ?"); $st->execute([$id]);
        if ($st->fetch()['t'] > 0) { $error = 'No se puede eliminar: tiene tickets asociados'; }
        else { $db->prepare("DELETE FROM webs WHERE id = ?")->execute([$id]); $success = 'Web eliminada'; }
    }
}

// Filtro cliente
$fc = $_GET['cliente'] ?? '';
$clientes = $db->query("SELECT DISTINCT u.id, u.nombre FROM usuarios u JOIN webs w ON u.id = w.usuario_id ORDER BY u.nombre")->fetchAll();

$where = ["1=1"]; $params = [];
if (!empty($fc)) { $where[] = "w.usuario_id = ?"; $params[] = (int)$fc; }
$sql = "SELECT w.*, u.nombre as cliente_nombre FROM webs w JOIN usuarios u ON w.usuario_id = u.id WHERE " . implode(" AND ", $where) . " ORDER BY u.nombre, w.nombre";
$st = $db->prepare($sql); $st->execute($params); $webs = $st->fetchAll();

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-globe"></i> Gestión Webs</h4>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>

<!-- Filtro -->
<div class="filtros">
<form method="get" class="row g-2 align-items-end">
<div class="col-md-4"><label class="form-label small text-muted">Cliente</label>
<select name="cliente" class="form-select form-select-sm"><option value="">Todos</option>
<?php foreach ($clientes as $c): ?><option value="<?=$c['id']?>" <?=$fc==$c['id']?'selected':'';?>><?=e($c['nombre'])?></option><?php endforeach; ?>
</select></div>
<div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Buscar</button>
<a href="webs.php" class="btn btn-outline-secondary btn-sm">Limpiar</a></div>
</form>
</div>

<div class="card"><div class="card-body">
<div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>ID</th><th>Cliente</th><th>Nombre</th><th>Dominio</th><th>Notas</th><th>Tickets</th><th>Creado</th><th></th></tr></thead>
<tbody>
<?php if (empty($webs)): ?><tr><td colspan="8" class="text-center text-muted py-4">Sin webs</td></tr><?php endif; ?>
<?php foreach ($webs as $w): ?>
<?php $st=$db->prepare("SELECT COUNT(*) as t FROM tickets WHERE web_id=?"); $st->execute([$w['id']]); $tc=$st->fetch()['t']; ?>
<tr>
<td><?=$w['id']?></td>
<td><?=e($w['cliente_nombre'])?></td>
<td><strong><?=e($w['nombre'])?></strong></td>
<td><?=e($w['dominio'])?></td>
<td><?=e($w['notas'])?></td>
<td><span class="badge bg-primary"><?=$tc?></span></td>
<td><small class="text-muted"><?=formatearFecha($w['fecha_creacion'])?></small></td>
<td class="d-flex gap-1">
<button class="btn btn-sm btn-outline-primary"
    data-id="<?=$w['id']?>"
    data-nombre="<?=e($w['nombre'])?>"
    data-dominio="<?=e($w['dominio'])?>"
    data-notas="<?=e($w['notas'])?>"
    onclick="editarWeb(this)"><i class="bi bi-pencil"></i></button>
<form method="post" style="display:inline"><?=csrfInput()?>
<input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=$w['id']?>">
<button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar web «<?=e($w['nombre'])?>»?')"><i class="bi bi-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div></div>

<!-- Modal editar web -->
<div class="modal fade" id="modalEditarWeb" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="post">
<?=csrfInput()?>
<input type="hidden" name="accion" value="editar">
<input type="hidden" name="id" id="edit_web_id">
<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Web</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label"><i class="bi bi-tag"></i> Nombre *</label>
<input type="text" name="edit_nombre" id="edit_nombre" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-globe"></i> Dominio</label>
<input type="text" name="edit_dominio" id="edit_dominio" class="form-control" placeholder="ejemplo.com">
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-sticky"></i> Notas</label>
<textarea name="edit_notas" id="edit_notas" class="form-control" rows="3"></textarea>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar</button>
</div>
</form>
</div></div>
</div>

<script>
function editarWeb(btn) {
    document.getElementById('edit_web_id').value = btn.dataset.id;
    document.getElementById('edit_nombre').value = btn.dataset.nombre;
    document.getElementById('edit_dominio').value = btn.dataset.dominio;
    document.getElementById('edit_notas').value = btn.dataset.notas;
    new bootstrap.Modal(document.getElementById('modalEditarWeb')).show();
}
</script>
<?php include 'includes/footer.php'; ?>
