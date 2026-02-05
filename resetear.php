<?php
require_once 'config.php';
session_start();
if (estaLogueado()) redirigir('login.php');

$db = getDB();
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = $success = '';
$usuario = null;

if (!empty($token)) {
    $st = $db->prepare("SELECT * FROM usuarios WHERE token_recordar = ? AND token_recordar != ''");
    $st->execute([$token]);
    $usuario = $st->fetch();
}

if (empty($token) || !$usuario) {
    $error = 'Enlace inválido o ya fue utilizado. Solicita uno nuevo.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    verificarTokenCSRF();
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (empty($pass)) {
        $error = 'Introduce una contraseña';
    } elseif (strlen($pass) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($pass !== $pass2) {
        $error = 'Las contraseñas no coinciden';
    } else {
        $db->prepare("UPDATE usuarios SET password = ?, token_recordar = NULL WHERE id = ?")
           ->execute([password_hash($pass, PASSWORD_DEFAULT), $usuario['id']]);
        registrarLog('contrasena_cambiada', "Usuario: " . $usuario['email']);
        $success = 'Contraseña cambiada correctamente. Ahora puedes hacer login.';
        $usuario = null; // Ocultar formulario
    }
}

$empresa = obtenerConfig('empresa_nombre', 'Sistema de Tickets');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cambiar contraseña — <?=e($empresa)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/style.css">
<style>
body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.rec-card { width: 100%; max-width: 420px; border-radius: 14px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
</style>
</head>
<body>
<div class="container"><div class="row justify-content-center"><div class="col-md-5">
<div class="text-center mb-4">
<div style="font-size:2.5rem; color:#fff;"><i class="bi bi-lock"></i></div>
<p style="color:#fff; font-weight:300; margin-bottom:0;">Nueva contraseña</p>
</div>
<div class="card rec-card">
<div class="card-body p-4">
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success text-center"><i class="bi bi-check-circle"></i> <?=e($success)?></div>
<div class="text-center mt-3"><a href="login.php" class="btn btn-primary py-2"><i class="bi bi-box-arrow-in-right"></i> Ir al Login</a></div>
<?php elseif ($usuario): ?>
<p class="text-muted small">Introduce tu nueva contraseña. Este enlace es de un solo uso.</p>
<form method="post">
<?=csrfInput()?>
<input type="hidden" name="token" value="<?=e($token)?>">
<div class="mb-3">
<label class="form-label"><i class="bi bi-lock"></i> Nueva contraseña</label>
<input type="password" name="password" class="form-control" required placeholder="Al menos 6 caracteres">
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-lock-fill"></i> Repetir contraseña</label>
<input type="password" name="password2" class="form-control" required placeholder="••••••••">
</div>
<button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-check-lg"></i> Cambiar Contraseña</button>
</form>
<?php endif; ?>
<hr>
<p class="text-center text-muted mb-0 small"><a href="login.php"><i class="bi bi-arrow-left"></i> Volver al Login</a></p>
</div>
</div>
</div></div></div>
</body>
</html>
