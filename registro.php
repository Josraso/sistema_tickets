<?php
require_once 'config.php';
session_start();
if (estaLogueado()) redirigir('dashboard.php');
if (obtenerConfig('registro_activo','1') !== '1') {
    redirigir('login.php');
}

$error = $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    if (!verifyCaptcha()) {
        $error = 'Captcha incorrecto';
    } else {
        $nombre = limpiar($_POST['nombre'] ?? '');
        $email = limpiar($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $telefono = limpiar($_POST['telefono'] ?? '');

        if (empty($nombre) || empty($email) || empty($password)) {
            $error = 'Todos los campos obligatorios deben rellenarse';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            $db = getDB();
            $st = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $st->execute([$email]);
            if ($st->fetch()) {
                $error = 'Email ya registrado';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("INSERT INTO usuarios (nombre, email, password, telefono, estado) VALUES (?, ?, ?, ?, 'pendiente')")
                   ->execute([$nombre, $email, $hash, $telefono]);
                $success = 'Registro exitoso. Tu cuenta queda pendiente de aprobación por el administrador.';
                registrarLog('registro', "Nuevo usuario: $nombre ($email)");
            }
        }
    }
}
$empresa = obtenerConfig('empresa_nombre', 'Sistema de Tickets');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro — <?=$empresa?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/style.css">
<style>
body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.login-card { width: 100%; max-width: 450px; border-radius: 14px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
.login-logo { font-size: 2.5rem; color: #fff; margin-bottom: 0.3rem; }
.login-title { color: #fff; font-weight: 300; font-size: 1rem; margin-bottom: 1.5rem; }
</style>
</head>
<body>
<div class="container"><div class="row justify-content-center"><div class="col-md-5">
<div class="text-center mb-4">
<div class="login-logo"><i class="bi bi-person-plus"></i></div>
<p class="login-title"><?=$empresa?></p>
</div>
<div class="card login-card">
<div class="card-body p-4">
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle"></i> <?=e($success)?></div>
<a href="login.php" class="btn btn-primary w-100">Ir al Login</a>
<?php else: ?>
<form method="post">
<?=csrfInput()?>
<div class="mb-3">
<label class="form-label"><i class="bi bi-person"></i> Nombre *</label>
<input type="text" name="nombre" class="form-control" required placeholder="Tu nombre completo">
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-envelope"></i> Email *</label>
<input type="email" name="email" class="form-control" required placeholder="tu@email.com">
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-telephone"></i> Teléfono móvil</label>
<input type="tel" name="telefono" class="form-control" placeholder="+34 600 000 000">
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-lock"></i> Contraseña * (mín. 6 caracteres)</label>
<input type="password" name="password" class="form-control" required minlength="6" placeholder="••••••••">
</div>
<div class="mb-4"><?=generarCaptcha()?></div>
<button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-person-plus"></i> Registrarse</button>
</form>
<?php endif; ?>
<hr>
<p class="text-center text-muted mb-0 small"><i class="bi bi-box-arrow-in-right"></i> ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
</div>
</div>
</div></div></div>
</body>
</html>
