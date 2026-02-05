<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $accion = $_POST['accion'] ?? '';
    $cid = (int)($_POST['cliente_id'] ?? 0);

    if ($accion === 'aprobar') {
        $db->prepare("UPDATE usuarios SET estado = 'activo' WHERE id = ? AND rol = 'cliente'")->execute([$cid]);
        $success = 'Cliente aprobado';
        registrarLog('aprobar_cliente', "Cliente #$cid aprobado");
    } elseif ($accion === 'bloquear') {
        $db->prepare("UPDATE usuarios SET estado = 'bloqueado' WHERE id = ? AND rol = 'cliente'")->execute([$cid]);
        $success = 'Cliente bloqueado';
        registrarLog('bloquear_cliente', "Cliente #$cid bloqueado");
    } elseif ($accion === 'desbloquear') {
        $db->prepare("UPDATE usuarios SET estado = 'activo' WHERE id = ? AND rol = 'cliente'")->execute([$cid]);
        $success = 'Cliente desbloqueado';
    }
}

$clientes = $db->query("SELECT * FROM usuarios WHERE rol = 'cliente' ORDER BY estado, nombre")->fetchAll();
include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-people"></i> Gestión Clientes</h4>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>

<!-- Resumen -->
<?php
$pendientes = array_filter($clientes, fn($c) => $c['estado']==='pendiente');
$activos = array_filter($clientes, fn($c) => $c['estado']==='activo');
$bloqueados = array_filter($clientes, fn($c) => $c['estado']==='bloqueado');
?>
<div class="row mb-3">
<div class="col-md-4"><div class="card text-center"><div class="card-body"><strong style="color:#6f42c1"><?=count($pendientes)?></strong> Pendientes</div></div></div>
<div class="col-md-4"><div class="card text-center"><div class="card-body"><strong class="text-success"><?=count($activos)?></strong> Activos</div></div></div>
<div class="col-md-4"><div class="card text-center"><div class="card-body"><strong class="text-danger"><?=count($bloqueados)?></strong> Bloqueados</div></div></div>
</div>

<div class="card"><div class="card-body">
<div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Estado</th><th>Tickets</th><th>Registro</th><th>Acciones</th></tr></thead>
<tbody>
<?php foreach ($clientes as $c): ?>
<?php $tc=$db->prepare("SELECT COUNT(*) as t FROM tickets WHERE usuario_id=?"); $tc->execute([$c['id']]); $ntickets=$tc->fetch()['t']; ?>
<tr>
<td><?=$c['id']?></td>
<td><strong><?=e($c['nombre'])?></strong></td>
<td><?=e($c['email'])?></td>
<td><?=e($c['telefono'])?></td>
<td>
<?php
if ($c['estado']==='pendiente') echo '<span class="badge" style="background:#6f42c1">Pendiente</span>';
elseif ($c['estado']==='activo') echo '<span class="badge bg-success">Activo</span>';
else echo '<span class="badge bg-danger">Bloqueado</span>';
?>
</td>
<td><span class="badge bg-primary"><?=$ntickets?></span></td>
<td><small class="text-muted"><?=formatearFecha($c['fecha_registro'])?></small></td>
<td class="d-flex flex-wrap gap-1">
<?php if ($c['estado']==='pendiente'): ?>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="accion" value="aprobar"><input type="hidden" name="cliente_id" value="<?=$c['id']?>">
<button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Aprobar</button></form>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="accion" value="bloquear"><input type="hidden" name="cliente_id" value="<?=$c['id']?>">
<button class="btn btn-danger btn-sm"><i class="bi bi-ban"></i> Bloquear</button></form>
<?php elseif ($c['estado']==='activo'): ?>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="accion" value="bloquear"><input type="hidden" name="cliente_id" value="<?=$c['id']?>">
<button class="btn btn-outline-danger btn-sm"><i class="bi bi-ban"></i> Bloquear</button></form>
<a href="impersonar.php?id=<?=$c['id']?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-person-fill-exclamation"></i> Entrar como</a>
<?php else: ?>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="accion" value="desbloquear"><input type="hidden" name="cliente_id" value="<?=$c['id']?>">
<button class="btn btn-outline-success btn-sm"><i class="bi bi-unlock"></i> Desbloquear</button></form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (empty($clientes)): ?><tr><td colspan="8" class="text-center text-muted py-4">Sin clientes</td></tr><?php endif; ?>
</tbody></table></div>
</div></div>
<?php include 'includes/footer.php'; ?>
