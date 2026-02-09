<?php
require_once 'config.php';
require_once __DIR__ . "/includes/session_config.php";
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
    $sesion_duracion = $_POST['sesion_duracion'] ?? null;
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
            // Convertir sesion_duracion a NULL si es vacío
            $sesion_val = ($sesion_duracion === '' || $sesion_duracion === null) ? null : (int)$sesion_duracion;
            $sesion_cambiada = false;
            // Detectar si cambió la configuración de sesión
            $st_check = $db->prepare("SELECT sesion_duracion FROM usuarios WHERE id = ?");
            $st_check->execute([$uid]);
            $old_sesion = $st_check->fetch()['sesion_duracion'];
            if ($old_sesion != $sesion_val) {
                $sesion_cambiada = true;
            }
            $db->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, sesion_duracion = ? WHERE id = ?")->execute([$nombre, $email, $telefono, $sesion_val, $uid]);
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
            if ($sesion_cambiada) {
                $success .= '. La nueva duración de sesión se aplicará en tu próximo inicio de sesión';
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
<div class="mb-3">
<label class="form-label"><i class="bi bi-clock-history"></i> Duración de sesión</label>
<select name="sesion_duracion" class="form-select">
<option value="">Automático (30 minutos)</option>
<option value="30" <?=($usuario['sesion_duracion']??null)==30?'selected':''?>>30 minutos</option>
<option value="60" <?=($usuario['sesion_duracion']??null)==60?'selected':''?>>1 hora</option>
<option value="240" <?=($usuario['sesion_duracion']??null)==240?'selected':''?>>4 horas</option>
<option value="480" <?=($usuario['sesion_duracion']??null)==480?'selected':''?>>8 horas</option>
<option value="1440" <?=($usuario['sesion_duracion']??null)==1440?'selected':''?>>24 horas (1 día)</option>
<option value="43200" <?=($usuario['sesion_duracion']??null)==43200?'selected':''?>>30 días (modo App)</option>
</select>
<div class="form-text">Tiempo antes de que se cierre tu sesión por inactividad. Elige "30 días" si usas la app instalada.</div>
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
