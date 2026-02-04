<?php
require_once 'config.php';
session_start();

if (estaLogueado()) redirigir(esAdmin() ? 'admin/' : 'dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario && password_verify($password, $usuario['password'])) {
        if ($usuario['estado'] !== 'activo') {
            $error = 'Cuenta no activa';
        } else {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];
            redirigir($usuario['rol'] === 'admin' ? 'admin/' : 'dashboard.php');
        }
    } else {
        $error = 'Email o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#667eea;padding-top:100px;}</style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header bg-primary text-white"><h3>Login</h3></div>
                <div class="card-body">
                    <?php if($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Contraseña</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>
                    </form>
                    <hr>
                    <a href="registro.php" class="btn btn-outline-primary w-100">Registrarse</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
