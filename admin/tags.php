<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'crear') {
        $nombre = limpiar($_POST['nombre'] ?? '');
        $color = $_POST['color'] ?? '#6c757d';
        // validar color hex
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#6c757d';
        if (empty($nombre)) { $error = 'El nombre es obligatorio'; }
        else {
            $st = $db->prepare("SELECT id FROM tags WHERE nombre = ?"); $st->execute([$nombre]);
            if ($st->fetch()) { $error = 'Ya existe un tag con ese nombre'; }
            else {
                $db->prepare("INSERT INTO tags (nombre, color) VALUES (?, ?)")->execute([$nombre, $color]);
                $success = "Tag «$nombre» creado";
            }
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM ticket_tags WHERE tag_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
        $success = 'Tag eliminado';
    } elseif ($accion === 'editar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = limpiar($_POST['nombre'] ?? '');
        $color = $_POST['color'] ?? '#6c757d';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#6c757d';
        if (!empty($nombre)) {
            $db->prepare("UPDATE tags SET nombre = ?, color = ? WHERE id = ?")->execute([$nombre, $color, $id]);
            $success = 'Tag actualizado';
        }
    }
}

$tags = $db->query("SELECT * FROM tags ORDER BY nombre")->fetchAll();
include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-tag"></i> Gestión Tags</h4>
<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTag"><i class="bi bi-plus-lg"></i> Nuevo Tag</button>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>

<div class="card"><div class="card-body">
<?php if (empty($tags)): ?>
<div class="text-center py-4 text-muted"><i class="bi bi-tag" style="font-size:2rem;"></i><p>No hay tags. Crea uno.</p></div>
<?php else: ?>
<div class="d-flex flex-wrap gap-3">
<?php foreach ($tags as $tag): ?>
<?php $st=$db->prepare("SELECT COUNT(*) as t FROM ticket_tags WHERE tag_id=?"); $st->execute([$tag['id']]); $uso=$st->fetch()['t']; ?>
<div class="card" style="min-width:180px; border-left: 4px solid <?=e($tag['color'])?>;">
<div class="card-body p-3">
<div class="d-flex justify-content-between align-items-center">
<span class="badge tag-badge" style="background:<?=e($tag['color'])?>;color:<?=colorContraste($tag['color'])?>;font-size:1rem;"><?=e($tag['nombre'])?></span>
<small class="text-muted"><?=$uso?> uso<?=$uso!==1?'s':''?></small>
</div>
<div class="d-flex gap-2 mt-2">
<button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditar" onclick="editarTag(<?=$tag['id']?>, '<?=addslashes($tag['nombre'])?>', '<?=$tag['color']?>')"><i class="bi bi-pencil"></i></button>
<form method="post" style="display:inline"><?=csrfInput()?>
<input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?=$tag['id']?>">
<button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar? Se eliminará de todos los tickets.')"><i class="bi bi-trash"></i></button>
</form>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div></div>

<!-- Modal nuevo tag -->
<div class="modal fade" id="modalTag" tabindex="-1">
<div class="modal-dialog modal-sm"><div class="modal-content">
<form method="post"><?=csrfInput()?><input type="hidden" name="accion" value="crear">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-tag"></i> Nuevo Tag</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="mb-3"><label class="form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Color</label><input type="color" name="color" class="form-control form-control-color" value="#0d6efd"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Crear</button></div>
</form></div></div>
</div>

<!-- Modal editar tag -->
<div class="modal fade" id="modalEditar" tabindex="-1">
<div class="modal-dialog modal-sm"><div class="modal-content">
<form method="post" id="formEditar"><?=csrfInput()?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id" id="editId" value="">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Tag</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="mb-3"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="editNombre" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Color</label><input type="color" name="color" id="editColor" class="form-control form-control-color" value="#6c757d"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar</button></div>
</form></div></div>
</div>

<script>
function editarTag(id, nombre, color) {
    document.getElementById('editId').value = id;
    document.getElementById('editNombre').value = nombre;
    document.getElementById('editColor').value = color;
}
</script>
<?php include 'includes/footer.php'; ?>
