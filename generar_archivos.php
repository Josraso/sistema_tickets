<?php
/**
 * GENERADOR AUTOMÁTICO DE ARCHIVOS
 * Ejecutar desde línea de comandos o navegador
 * Genera TODOS los archivos que faltan del sistema
 */

$archivos = [];

// ========== LOGOUT ==========
$archivos['logout.php'] = '<?php
require_once "config.php";
session_start();
session_destroy();
header("Location: login.php");
exit;
';

// ========== REGISTRO ==========
$archivos['registro.php'] = '<?php
require_once "config.php";
session_start();
if (estaLogueado()) redirigir("dashboard.php");

$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = limpiar($_POST["nombre"] ?? "");
    $email = limpiar($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        $error = "Email ya registrado";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, estado) VALUES (?, ?, ?, \'pendiente\')");
        $stmt->execute([$nombre, $email, $hash]);
        $success = "Registro exitoso. Pendiente de aprobación.";
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
';

// ========== DASHBOARD CLIENTE ==========
$archivos['dashboard.php'] = '<?php
require_once "config.php";
session_start();
if (!estaLogueado() || esAdmin()) redirigir("login.php");

$db = getDB();
$usuario_id = $_SESSION["usuario_id"];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$total_tickets = $stmt->fetch()["total"];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE usuario_id = ? AND estado = \'abierto\'");
$stmt->execute([$usuario_id]);
$abiertos = $stmt->fetch()["total"];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM webs WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$total_webs = $stmt->fetch()["total"];

$stmt = $db->prepare("SELECT t.*, w.nombre as web_nombre FROM tickets t JOIN webs w ON t.web_id = w.id WHERE t.usuario_id = ? ORDER BY t.fecha_creacion DESC LIMIT 10");
$stmt->execute([$usuario_id]);
$tickets = $stmt->fetchAll();

include "includes/header.php";
?>
<h1>Dashboard</h1>
<div class="row">
    <div class="col-md-4"><div class="card text-white bg-primary"><div class="card-body text-center">
        <h2><?=$total_tickets?></h2><p>Total Tickets</p>
    </div></div></div>
    <div class="col-md-4"><div class="card text-white bg-info"><div class="card-body text-center">
        <h2><?=$abiertos?></h2><p>Abiertos</p>
    </div></div></div>
    <div class="col-md-4"><div class="card text-white bg-success"><div class="card-body text-center">
        <h2><?=$total_webs?></h2><p>Webs</p>
    </div></div></div>
</div>

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between">
        <span>Tickets Recientes</span>
        <a href="nuevo_ticket.php" class="btn btn-sm btn-primary">Nuevo Ticket</a>
    </div>
    <div class="card-body">
        <?php if (empty($tickets)): ?>
            <p>No tienes tickets. <a href="nuevo_ticket.php">Crear primero</a></p>
        <?php else: ?>
            <table class="table">
                <tr><th>ID</th><th>Asunto</th><th>Web</th><th>Estado</th><th>Fecha</th><th></th></tr>
                <?php foreach ($tickets as $t): ?>
                <tr>
                    <td>#<?=$t["id"]?></td>
                    <td><?=e($t["asunto"])?></td>
                    <td><?=e($t["web_nombre"])?></td>
                    <td><?=estadoBadge($t["estado"])?></td>
                    <td><?=formatearFecha($t["fecha_creacion"])?></td>
                    <td><a href="ver_ticket.php?id=<?=$t["id"]?>" class="btn btn-sm btn-primary">Ver</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include "includes/footer.php"; ?>
';

// Continúa en siguiente mensaje por límite de caracteres...

echo "Generando archivos...\\n";
foreach ($archivos as $nombre => $contenido) {
    file_put_contents(__DIR__ . "/" . $nombre, $contenido);
    echo "✓ $nombre\\n";
}
echo "\\n¡Archivos generados!\\n";
