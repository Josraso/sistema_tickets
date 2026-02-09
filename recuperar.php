<?php
require_once 'config.php';
require_once __DIR__ . "/includes/session_config.php";
if (estaLogueado()) redirigir('login.php');

$db = getDB();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $email = limpiar($_POST['email'] ?? '');
    if (empty($email)) {
        $error = 'Introduce tu email';
    } else {
        $st = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
        $st->execute([$email]);
        $usuario = $st->fetch();

        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $db->prepare("UPDATE usuarios SET token_recordar = ? WHERE id = ?")->execute([$token, $usuario['id']]);

            $empresa = obtenerConfig('empresa_nombre', 'Sistema de Tickets');
            $url = obtenerConfig('dominio_base', '') . '/resetear.php?token=' . urlencode($token);
            $html = '<h3 style="color:#0d6efd;">Recuperación de contraseña</h3>'
                  . '<p>Has solicitado cambiar tu contraseña en <strong>' . htmlspecialchars($empresa) . '</strong>.</p>'
                  . '<p style="margin:1.5rem 0;"><a href="' . $url . '" style="font-size:1.1rem; color:#0d6efd;">Haz clic aquí para cambiar tu contraseña</a></p>'
                  . '<p style="font-size:0.85rem; color:#6c757d;">Si no has solicitado esto, ignora este email.<br>Este enlace es de un solo uso.</p>'
                  . '<hr><p style="font-size:0.8rem; color:#aaa;"><em>' . htmlspecialchars($empresa) . '</em></p>';
            enviarEmail($email, 'Recuperación de contraseña — ' . $empresa, $html);
            registrarLog('recuperar_solicitado', "Email: $email");
        }
        // No revelar si el email existe
        $success = 'Si tu email está registrado, recibirás un enlace de recuperación en unos segundos.';
    }
}

$empresa = obtenerConfig('empresa_nombre', 'Sistema de Tickets');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recuperar contraseña — <?=e($empresa)?></title>
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
<div style="font-size:2.5rem; color:#fff;"><i class="bi bi-key"></i></div>
<p style="color:#fff; font-weight:300; margin-bottom:0;">Recuperar contraseña</p>
</div>
<div class="card rec-card">
<div class="card-body p-4">
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success text-center"><i class="bi bi-check-circle"></i> <?=e($success)?></div>
<div class="text-center mt-3"><a href="login.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Volver al Login</a></div>
<?php else: ?>
<p class="text-muted small">Introduce tu email y te enviaremos un enlace para cambiar tu contraseña.</p>
<form method="post">
<?=csrfInput()?>
<div class="mb-3">
<label class="form-label"><i class="bi bi-envelope"></i> Email</label>
<input type="email" name="email" class="form-control" required placeholder="tu@email.com">
</div>
<button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-send"></i> Solicitar Enlace</button>
</form>
<?php endif; ?>
<hr>
<p class="text-center text-muted mb-0 small"><a href="login.php"><i class="bi bi-arrow-left"></i> Volver al Login</a></p>
</div>
</div>
</div></div></div>
</body>
</html>
