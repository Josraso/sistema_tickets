<?php
require_once 'config.php';
session_start();
if (!estaLogueado()) redirigir('login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'crear') {
        $nombre = limpiar($_POST['nombre'] ?? '');
        $dominio = limpiar($_POST['dominio'] ?? '');
        $stmt = $db->prepare("INSERT INTO webs (usuario_id, nombre, dominio) VALUES (?, ?, ?)");
        $stmt->execute([$usuario_id, $nombre, $dominio]);
        $success = 'Web añadida';
    }
    elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM webs WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $usuario_id]);
        $success = 'Web eliminada';
    }
}

$stmt = $db->prepare("SELECT * FROM webs WHERE usuario_id = ? ORDER BY nombre");
$stmt->execute([$usuario_id]);
$webs = $stmt->fetchAll();

include 'includes/header.php';
?>
<h1>Mis Webs</h1>
<?php if($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNueva">Añadir Web</button>
    </div>
    <div class="card-body">
        <table class="table">
            <tr><th>Nombre</th><th>Dominio</th><th></th></tr>
            <?php foreach ($webs as $w): ?>
            <tr>
                <td><?=e($w['nombre'])?></td>
                <td><?=e($w['dominio'])?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?=$w['id']?>">
                        <button class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div class="modal fade" id="modalNueva">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header"><h5>Nueva Web</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label>Nombre</label><input type="text" name="nombre" class="form-control" required></div>
                    <div class="mb-3"><label>Dominio</label><input type="text" name="dominio" class="form-control" required></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
