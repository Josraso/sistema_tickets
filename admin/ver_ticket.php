<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$ticket_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT t.*, u.nombre as cliente, w.nombre as web FROM tickets t JOIN usuarios u ON t.usuario_id = u.id JOIN webs w ON t.web_id = w.id WHERE t.id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) redirigir('tickets.php');

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'responder') {
        $mensaje = limpiar($_POST['mensaje'] ?? '');
        $stmt = $db->prepare("INSERT INTO respuestas (ticket_id, usuario_id, mensaje) VALUES (?, ?, ?)");
        $stmt->execute([$ticket_id, $_SESSION['usuario_id'], $mensaje]);
        header("Location: ver_ticket.php?id=$ticket_id");
        exit;
    }
    elseif ($accion === 'cambiar_estado') {
        $nuevo_estado = $_POST['estado'] ?? '';
        $stmt = $db->prepare("UPDATE tickets SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $ticket_id]);
        header("Location: ver_ticket.php?id=$ticket_id");
        exit;
    }
}

$stmt = $db->prepare("SELECT r.*, u.nombre FROM respuestas r JOIN usuarios u ON r.usuario_id = u.id WHERE r.ticket_id = ? ORDER BY r.fecha_creacion");
$stmt->execute([$ticket_id]);
$respuestas = $stmt->fetchAll();

include 'includes/header.php';
?>
<h1>Ticket #<?=$ticket['id']?></h1>
<div class="card">
    <div class="card-header">
        <strong><?=e($ticket['asunto'])?></strong>
        <?=estadoBadge($ticket['estado'])?> <?=prioridadBadge($ticket['prioridad'])?>
    </div>
    <div class="card-body">
        <p><strong>Cliente:</strong> <?=e($ticket['cliente'])?></p>
        <p><strong>Web:</strong> <?=e($ticket['web'])?></p>
        <p><strong>Creado:</strong> <?=formatearFecha($ticket['fecha_creacion'])?></p>
        <hr>
        <p><?=nl2br(e($ticket['mensaje']))?></p>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Cambiar Estado</div>
    <div class="card-body">
        <form method="post" class="row g-2">
            <input type="hidden" name="accion" value="cambiar_estado">
            <div class="col-auto">
                <select name="estado" class="form-select">
                    <option value="abierto">Abierto</option>
                    <option value="en_proceso">En Proceso</option>
                    <option value="terminado">Terminado</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Cambiar</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Respuestas</div>
    <div class="card-body">
        <?php foreach ($respuestas as $r): ?>
            <div class="mb-3">
                <strong><?=e($r['nombre'])?></strong> - <?=formatearFecha($r['fecha_creacion'])?>
                <p class="mb-0"><?=nl2br(e($r['mensaje']))?></p>
            </div>
            <hr>
        <?php endforeach; ?>
        
        <form method="post">
            <input type="hidden" name="accion" value="responder">
            <div class="mb-3">
                <label>Nueva Respuesta</label>
                <textarea name="mensaje" class="form-control" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Enviar Respuesta</button>
        </form>
    </div>
</div>

<div class="mt-3">
    <a href="tickets.php" class="btn btn-secondary">Volver</a>
</div>
<?php include 'includes/footer.php'; ?>
