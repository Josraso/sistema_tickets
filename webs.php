<?php
require_once 'config.php';
session_start();
if (!estaLogueado()) redirigir('login.php');
if (esAdmin() && !estaImpersonando()) redirigir('admin/');

$db = getDB();
$uid = $_SESSION['usuario_id'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $nombre = limpiar($_POST['nombre'] ?? '');
        $dominio = limpiar($_POST['dominio'] ?? '');
        $notas = limpiar($_POST['notas'] ?? '');
        if (!empty($nombre) && !empty($dominio)) {
            $db->prepare("INSERT INTO webs (usuario_id, nombre, dominio, notas) VALUES (?, ?, ?, ?)")->execute([$uid, $nombre, $dominio, $notas]);
            $success = 'Web añadida correctamente';
            registrarLog('web_creada', "$nombre ($dominio)");
        } else { $error = 'Nombre y dominio son obligatorios'; }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $db->prepare("SELECT COUNT(*) as t FROM tickets WHERE web_id = ?"); $st->execute([$id]);
        if ($st->fetch()['t'] > 0) { $error = 'No se puede eliminar: tiene tickets asociados'; }
        else { $db->prepare("DELETE FROM webs WHERE id = ? AND usuario_id = ?")->execute([$id, $uid]); $success = 'Web eliminada'; }
    }
}

$st = $db->prepare("SELECT * FROM webs WHERE usuario_id = ? ORDER BY nombre"); $st->execute([$uid]); $webs = $st->fetchAll();
include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-globe"></i> Mis Webs</h4>
<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNueva"><i class="bi bi-plus-lg"></i> Añadir Web</button>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>
<div class="card"><div class="card-body">
<?php if (empty($webs)): ?>
<div class="text-center py-4 text-muted"><i class="bi bi-globe" style="font-size:2rem;"></i><p>No tienes webs aún. Añade una para poder crear tickets.</p></div>
<?php else: ?>
<div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>Nombre</th><th>Dominio</th><th>Notas</th><th>Tickets</th><th></th></tr></thead>
<tbody>
<?php foreach ($webs as $w): ?>
<?php $st=$db->prepare("SELECT COUNT(*) as t FROM tickets WHERE web_id=?"); $st->execute([$w['id']]); $tc=$st->fetch()['t']; ?>
<tr>
<td><strong><?=e($w['nombre'])?></strong></td>
<td><?=e($w['dominio'])?></td>
<td><?=e($w['notas'])?></td>
<td><span class="badge bg-primary"><?=$tc?></span></td>
<td>
<form method="post" style="display:inline"><?=csrfInput()?>
<input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=$w['id']?>">
<button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar esta web?')"><i class="bi bi-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div></div>
<!-- Modal nueva web -->
<div class="modal fade" id="modalNueva" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="post"><?=csrfInput()?><input type="hidden" name="accion" value="crear">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-globe"></i> Nueva Web</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="mb-3"><label class="form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required placeholder="Mi página web"></div>
<div class="mb-3"><label class="form-label">Dominio *</label><input type="text" name="dominio" class="form-control" required placeholder="ejemplo.com"></div>
<div class="mb-3"><label class="form-label">Notas</label><textarea name="notas" class="form-control" rows="3" placeholder="Información adicional sobre esta web..."></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button></div>
</form></div></div>
</div>
<?php include 'includes/footer.php'; ?>
