<?php
require_once '../config.php';
require_once __DIR__ . "/../includes/session_config.php";
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$uid = $_SESSION['usuario_id'];
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $nombre   = limpiar($_POST['nombre'] ?? '');
    $email    = limpiar($_POST['email'] ?? '');
    $telefono = limpiar($_POST['telefono'] ?? '');
    $firma    = trim($_POST['firma'] ?? '');
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
            $db->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, firma = ?, sesion_duracion = ? WHERE id = ?")->execute([$nombre, $email, $telefono, $firma, $sesion_val, $uid]);
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
            registrarLog('perfil_admin_actualizado', $success ?: $error);
        }
    }
}

// CRUD respuestas rápidas
if (isset($_POST['accion_rr'])) {
    verificarTokenCSRF();
    $accion = $_POST['accion_rr'];
    if ($accion === 'crear') {
        $titulo = limpiar($_POST['rr_titulo'] ?? '');
        $contenido = limpiar($_POST['rr_contenido'] ?? '');
        if (!empty($titulo) && !empty($contenido)) {
            $db->prepare("INSERT INTO respuestas_rapidas (usuario_id, titulo, contenido) VALUES (?, ?, ?)")->execute([$uid, $titulo, $contenido]);
            $success = 'Respuesta rápida creada';
        }
    } elseif ($accion === 'eliminar') {
        $rr_id = (int)($_POST['rr_id'] ?? 0);
        $db->prepare("DELETE FROM respuestas_rapidas WHERE id = ? AND usuario_id = ?")->execute([$rr_id, $uid]);
        $success = 'Respuesta rápida eliminada';
    }
}

$st = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$st->execute([$uid]);
$usuario = $st->fetch();

// Obtener respuestas rápidas del usuario
$respuestas_rapidas = $db->prepare("SELECT * FROM respuestas_rapidas WHERE usuario_id = ? ORDER BY titulo");
$respuestas_rapidas->execute([$uid]);
$rr_list = $respuestas_rapidas->fetchAll();

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-person-circle"></i> Mi Perfil (Admin)</h4>
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
<label class="form-label"><i class="bi bi-pencil-square"></i> Firma (aparece al final de tus respuestas)</label>
<textarea name="firma" class="form-control" rows="3" placeholder="Ejemplo: Saludos,&#10;Juan Pérez&#10;Soporte Técnico"><?=e($usuario['firma'] ?? '')?></textarea>
<div class="form-text">La firma se añadirá automáticamente al final de cada respuesta que envíes</div>
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
<a href="index.php" class="btn btn-secondary">Cancelar</a>
</form>
</div></div>
</div>
<div class="col-lg-4">
<div class="card"><div class="card-body text-center">
<div style="font-size:3.5rem; color:#dc3545;"><i class="bi bi-shield-fill"></i></div>
<h5 class="mt-2"><?=e($usuario['nombre'])?></h5>
<p class="text-muted mb-1"><?=e($usuario['email'])?></p>
<?php if ($usuario['telefono']): ?><p class="text-muted mb-1 small"><i class="bi bi-phone"></i> <?=e($usuario['telefono'])?></p><?php endif; ?>
<span class="badge bg-danger">Administrador</span>
<hr>
<div class="text-start small text-muted">
<p class="mb-1"><strong>Registrado:</strong> <?=formatearFecha($usuario['fecha_registro'])?></p>
<?php if ($usuario['ultimo_acceso']): ?><p class="mb-0"><strong>Último acceso:</strong> <?=formatearFecha($usuario['ultimo_acceso'])?></p><?php endif; ?>
</div>
</div></div>
</div>
</div>

<!-- Respuestas rápidas -->
<div class="card mt-3">
<div class="card-header d-flex justify-content-between align-items-center">
<span><i class="bi bi-lightning"></i> Respuestas Rápidas</span>
<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalRR"><i class="bi bi-plus-lg"></i> Nueva</button>
</div>
<div class="card-body">
<p class="text-muted small">Guarda respuestas que usas frecuentemente para insertarlas con un clic al responder tickets.</p>
<?php if (empty($rr_list)): ?>
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> No tienes respuestas rápidas. Crea una para empezar.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm table-hover mb-0">
<thead><tr><th>Título</th><th>Contenido</th><th></th></tr></thead>
<tbody>
<?php foreach ($rr_list as $rr): ?>
<tr>
<td><strong><?=e($rr['titulo'])?></strong></td>
<td class="small"><?=e(mb_substr($rr['contenido'], 0, 80) . (mb_strlen($rr['contenido']) > 80 ? '...' : ''))?></td>
<td>
<form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar?')"><?=csrfInput()?>
<input type="hidden" name="accion_rr" value="eliminar">
<input type="hidden" name="rr_id" value="<?=$rr['id']?>">
<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
</div>

<!-- Modal crear respuesta rápida -->
<div class="modal fade" id="modalRR" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="post">
<?=csrfInput()?>
<input type="hidden" name="accion_rr" value="crear">
<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-lightning"></i> Nueva Respuesta Rápida</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label">Título *</label>
<input type="text" name="rr_titulo" class="form-control" placeholder="Ej: Ticket resuelto" required>
</div>
<div class="mb-3">
<label class="form-label">Contenido *</label>
<textarea name="rr_contenido" class="form-control" rows="4" placeholder="Ej: Hemos revisado tu ticket y el problema ha sido resuelto. Por favor, confirma que todo funciona correctamente." required></textarea>
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
