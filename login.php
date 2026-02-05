<?php
require_once 'config.php';
session_start();
if (estaLogueado()) {
    redirigir(esAdmin() ? 'admin/' : 'dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    if (!verifyCaptcha()) {
        $error = 'Captcha incorrecto';
    } else {
        $email = limpiar($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $db = getDB();
        $st = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
        $st->execute([$email]);
        $usuario = $st->fetch();

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $error = 'Email o contraseña incorrectos';
        } elseif ($usuario['estado'] === 'pendiente') {
            $error = 'Tu cuenta está pendiente de aprobación';
        } elseif ($usuario['estado'] === 'bloqueado') {
            $error = 'Tu cuenta está bloqueada';
        } else {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];
            $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$usuario['id']]);
            registrarLog('login', 'Login exitoso');
            redirigir($usuario['rol'] === 'admin' ? 'admin/' : 'dashboard.php');
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
<title>Inicio Sesión — <?=$empresa?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/style.css">
<style>
body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.login-card { width: 100%; max-width: 420px; border-radius: 14px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
.login-logo { font-size: 2.5rem; color: #fff; margin-bottom: 0.3rem; }
.login-title { color: #fff; font-weight: 300; font-size: 1rem; margin-bottom: 1.5rem; }
</style>
</head>
<body>
<div class="container"><div class="row justify-content-center"><div class="col-md-5">
<div class="text-center mb-4">
<div class="login-logo"><i class="bi bi-ticket-fill"></i></div>
<p class="login-title"><?=$empresa?></p>
</div>
<div class="card login-card">
<div class="card-body p-4">
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
<form method="post">
<?=csrfInput()?>
<div class="mb-3">
<label class="form-label"><i class="bi bi-envelope"></i> Email</label>
<input type="email" name="email" class="form-control" required autocomplete="email" placeholder="tu@email.com">
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-lock"></i> Contraseña</label>
<input type="password" name="password" class="form-control" required autocomplete="password" placeholder="••••••••">
</div>
<div class="text-end mb-2"><a href="recuperar.php" class="small text-muted"><i class="bi bi-key"></i> ¿Olvidaste tu contraseña?</a></div>
<div class="mb-4"><?=generarCaptcha()?></div>
<button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right"></i> Inicia Sesión</button>
</form>
<hr>
<?php if (obtenerConfig('registro_activo','1') === '1'): ?>
<p class="text-center text-muted mb-0 small"><i class="bi bi-person-plus"></i> ¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
<?php else: ?>
<p class="text-center text-muted mb-0 small">El registro está desactivado. Contacta al administrador.</p>
<?php endif; ?>
</div>
</div>
</div></div></div>
</body>
</html>
