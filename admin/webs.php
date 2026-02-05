<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
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
<td>
<form method="post" style="display:inline"><?=csrfInput()?>
<input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=$w['id']?>">
<button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar web «<?=addslashes($w['nombre'])?>»?')"><i class="bi bi-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div></div>
<?php include 'includes/footer.php'; ?>
