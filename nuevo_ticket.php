<?php
require_once 'config.php';
session_start();
if (!estaLogueado()) redirigir('login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

$stmt = $db->prepare("SELECT * FROM webs WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$webs = $stmt->fetchAll();

if (empty($webs)) {
    header('Location: webs.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $web_id = (int)($_POST['web_id'] ?? 0);
    $asunto = limpiar($_POST['asunto'] ?? '');
    $mensaje = limpiar($_POST['mensaje'] ?? '');
    $prioridad = $_POST['prioridad'] ?? 'media';
    
    $stmt = $db->prepare("INSERT INTO tickets (usuario_id, web_id, asunto, mensaje, prioridad) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$usuario_id, $web_id, $asunto, $mensaje, $prioridad]);
    $ticket_id = $db->lastInsertId();
    
    registrarLog('ticket_creado', "Ticket #$ticket_id creado");
    
    header("Location: ver_ticket.php?id=$ticket_id");
    exit;
}

include 'includes/header.php';
?>
<h1>Nuevo Ticket</h1>
<?php if($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post">
            <div class="mb-3">
                <label>Web *</label>
                <select name="web_id" class="form-select" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($webs as $w): ?>
                        <option value="<?=$w['id']?>"><?=e($w['nombre'])?> (<?=e($w['dominio'])?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label>Asunto *</label>
                <input type="text" name="asunto" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Mensaje *</label>
                <textarea name="mensaje" class="form-control" rows="6" required></textarea>
            </div>
            <div class="mb-3">
                <label>Prioridad</label>
                <select name="prioridad" class="form-select">
                    <option value="baja">Baja</option>
                    <option value="media" selected>Media</option>
                    <option value="alta">Alta</option>
                    <option value="critica">Crítica</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Crear Ticket</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
