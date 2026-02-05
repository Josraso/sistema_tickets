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
    $nombre   = limpiar($_POST['nombre'] ?? '');
    $email    = limpiar($_POST['email'] ?? '');
    $telefono = limpiar($_POST['telefono'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $pass2    = $_POST['password2'] ?? '';

    if (empty($nombre) || empty($email)) {
        $error = 'Nombre y email son obligatorios';
    } else {
        $st = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $st->execute([$email, $uid]);
        if ($st->fetch()) {
            $error = 'Ese email ya está en uso por otra cuenta';
        } else {
            $db->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ? WHERE id = ?")->execute([$nombre, $email, $telefono, $uid]);
            $_SESSION['usuario_nombre'] = $nombre;

            if (!empty($pass) || !empty($pass2)) {
                if ($pass !== $pass2)       { $error = 'Las contraseñas no coinciden'; }
                elseif (strlen($pass) < 6)  { $error = 'Mínimo 6 caracteres'; }
                else {
                    $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
                    $success = 'Perfil y contraseña actualizados';
                }
            } else {
                $success = 'Perfil actualizado';
            }
            registrarLog('perfil_actualizado', $success ?: $error);
        }
    }
}

$st = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$st->execute([$uid]);
$usuario = $st->fetch();

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-person-circle"></i> Mi Perfil</h4>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>

<div class="row">
<div class="col-lg-8">
<div class="card"><div class="card-body">
<form method="post">
<?=csrfInput()?>
<div class="mb-3">
<label class="form-label"><i class="bi bi-person"></i> Nombre *</label>
<input type="text" name="nombre" class="form-control" value="<?=e($usuario['nombre'])?>" required>
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-envelope"></i> Email *</label>
<input type="email" name="email" class="form-control" value="<?=e($usuario['email'])?>" required>
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-phone"></i> Teléfono móvil</label>
<input type="tel" name="telefono" class="form-control" value="<?=e($usuario['telefono'])?>" placeholder="+34 612 345 678">
</div>
<hr>
<h6><i class="bi bi-lock"></i> Cambiar contraseña</h6>
<p class="text-muted small">Si no quieres cambiarla, deja estos campos vacíos.</p>
<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Nueva contraseña</label>
<input type="password" name="password" class="form-control" placeholder="Al menos 6 caracteres">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Repetir contraseña</label>
<input type="password" name="password2" class="form-control" placeholder="••••••••">
</div>
</div>
<button type="submit" class="btn btn-primary"><i class="bi bi-floppy-disk"></i> Guardar</button>
<a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
</form>
</div></div>
</div>
<div class="col-lg-4">
<div class="card"><div class="card-body text-center">
<div style="font-size:3.5rem; color:#0d6efd;"><i class="bi bi-person-circle"></i></div>
<h5 class="mt-2"><?=e($usuario['nombre'])?></h5>
<p class="text-muted mb-1"><?=e($usuario['email'])?></p>
<?php if ($usuario['telefono']): ?><p class="text-muted mb-1 small"><i class="bi bi-phone"></i> <?=e($usuario['telefono'])?></p><?php endif; ?>
<hr>
<div class="text-start small text-muted">
<p class="mb-1"><strong>Rol:</strong> <?=ucfirst($usuario['rol'])?></p>
<p class="mb-1"><strong>Estado:</strong> <?=ucfirst($usuario['estado'])?></p>
<p class="mb-1"><strong>Registrado:</strong> <?=formatearFecha($usuario['fecha_registro'])?></p>
<?php if ($usuario['ultimo_acceso']): ?><p class="mb-0"><strong>Último acceso:</strong> <?=formatearFecha($usuario['ultimo_acceso'])?></p><?php endif; ?>
</div>
</div></div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
