<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    if ($accion === 'aprobar') {
        $stmt = $db->prepare("UPDATE usuarios SET estado = 'activo' WHERE id = ?");
        $stmt->execute([$id]);
    }
    elseif ($accion === 'bloquear') {
        $stmt = $db->prepare("UPDATE usuarios SET estado = 'bloqueado' WHERE id = ?");
        $stmt->execute([$id]);
    }
}

$stmt = $db->query("SELECT * FROM usuarios WHERE rol = 'cliente' ORDER BY fecha_registro DESC");
$clientes = $stmt->fetchAll();

include 'includes/header.php';
?>
<h1>Gestión de Clientes</h1>
<div class="card">
    <div class="card-body">
        <table class="table">
            <tr><th>ID</th><th>Nombre</th><th>Email</th><th>Estado</th><th>Fecha Registro</th><th></th></tr>
            <?php foreach ($clientes as $c): ?>
            <tr>
                <td><?=$c['id']?></td>
                <td><?=e($c['nombre'])?></td>
                <td><?=e($c['email'])?></td>
                <td><?=estadoBadge($c['estado'])?></td>
                <td><?=formatearFecha($c['fecha_registro'])?></td>
                <td>
                    <?php if ($c['estado'] === 'pendiente'): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="accion" value="aprobar">
                            <input type="hidden" name="id" value="<?=$c['id']?>">
                            <button class="btn btn-sm btn-success">Aprobar</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($c['estado'] === 'activo'): ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="accion" value="bloquear">
                            <input type="hidden" name="id" value="<?=$c['id']?>">
                            <button class="btn btn-sm btn-danger">Bloquear</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
