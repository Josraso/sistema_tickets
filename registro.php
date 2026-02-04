<?php
require_once 'config.php';
session_start();
if (estaLogueado()) redirigir('dashboard.php');

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiar($_POST['nombre'] ?? '');
    $email = limpiar($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        $error = 'Email ya registrado';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, estado) VALUES (?, ?, ?, 'pendiente')");
        $stmt->execute([$nombre, $email, $hash]);
        $success = 'Registro exitoso. Pendiente de aprobación.';
    }
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Registro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#667eea;padding-top:50px;}</style></head>
<body><div class="container"><div class="row justify-content-center"><div class="col-md-6">
<div class="card"><div class="card-header bg-primary text-white"><h3>Registro</h3></div>
<div class="card-body">
<?php if($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><?=$success?></div>
<a href="login.php" class="btn btn-primary">Ir al Login</a>
<?php else: ?>
<form method="post">
    <div class="mb-3"><label>Nombre</label><input type="text" name="nombre" class="form-control" required></div>
    <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="mb-3"><label>Contraseña</label><input type="password" name="password" class="form-control" required></div>
    <button type="submit" class="btn btn-primary w-100">Registrarse</button>
</form>
<hr><a href="login.php">Ya tengo cuenta</a>
<?php endif; ?>
</div></div></div></div></div></body></html>
