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
    } elseif ($accion === 'crear_usuario') {
        $nombre   = limpiar($_POST['nuevo_nombre'] ?? '');
        $email    = limpiar($_POST['nuevo_email'] ?? '');
        $telefono = limpiar($_POST['nuevo_telefono'] ?? '');
        $password = $_POST['nuevo_password'] ?? '';
        $rol      = in_array($_POST['nuevo_rol'] ?? '', ['admin','cliente']) ? $_POST['nuevo_rol'] : 'cliente';

        if (empty($nombre) || empty($email) || empty($password)) {
            $error = 'Nombre, email y contraseña son obligatorios';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            $st = $db->prepare("SELECT id FROM usuarios WHERE email = ?"); $st->execute([$email]);
            if ($st->fetch()) {
                $error = 'Ya existe un usuario con ese email';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO usuarios (nombre, email, password, telefono, rol, estado) VALUES (?, ?, ?, ?, ?, 'activo')")
                   ->execute([$nombre, $email, $hash, $telefono, $rol]);
                $success = "Usuario «$nombre» creado correctamente";
                registrarLog('crear_usuario', "$nombre ($email) — rol: $rol");
            }
        }
    }
}

$clientes = $db->query("SELECT * FROM usuarios WHERE rol = 'cliente' ORDER BY estado, nombre")->fetchAll();
include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-people"></i> Gestión Clientes</h4>
<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoUser"><i class="bi bi-plus-lg"></i> Crear Usuario</button>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>

<!-- Resumen -->
<?php
$pendientes = array_filter($clientes, fn($c) => $c['estado']==='pendiente');
$activos    = array_filter($clientes, fn($c) => $c['estado']==='activo');
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

<!-- Modal crear usuario -->
<div class="modal fade" id="modalNuevoUser" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="post">
<?=csrfInput()?>
<input type="hidden" name="accion" value="crear_usuario">
<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-person-plus"></i> Crear Usuario</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label"><i class="bi bi-person"></i> Nombre *</label>
<input type="text" name="nuevo_nombre" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-envelope"></i> Email *</label>
<input type="email" name="nuevo_email" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-lock"></i> Contraseña *</label>
<input type="password" name="nuevo_password" class="form-control" required minlength="6" placeholder="Al menos 6 caracteres">
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-phone"></i> Teléfono</label>
<input type="tel" name="nuevo_telefono" class="form-control" placeholder="+34 612 345 678">
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-person-circle"></i> Rol</label>
<select name="nuevo_rol" class="form-select">
<option value="cliente">Cliente</option>
<option value="admin">Admin</option>
</select>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Crear</button>
</div>
</form>
</div></div>
</div>
<?php include 'includes/footer.php'; ?>
