<?php
require_once 'config.php';
session_start();
if (!estaLogueado()) redirigir('login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];
$ticket_id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT t.*, w.nombre as web_nombre FROM tickets t JOIN webs w ON t.web_id = w.id WHERE t.id = ? AND t.usuario_id = ?");
$stmt->execute([$ticket_id, $usuario_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}

// Procesar respuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket['estado'] !== 'terminado') {
    $mensaje = limpiar($_POST['mensaje'] ?? '');
    if (!empty($mensaje)) {
        $stmt = $db->prepare("INSERT INTO respuestas (ticket_id, usuario_id, mensaje) VALUES (?, ?, ?)");
        $stmt->execute([$ticket_id, $usuario_id, $mensaje]);
        $stmt = $db->prepare("UPDATE tickets SET fecha_actualizacion = NOW() WHERE id = ?");
        $stmt->execute([$ticket_id]);
        header("Location: ver_ticket.php?id=$ticket_id");
        exit;
    }
}

// Procesar incidencia
if (isset($_POST['incidencia']) && $ticket['estado'] === 'terminado') {
    $stmt = $db->prepare("UPDATE tickets SET tiene_incidencia = 1 WHERE id = ?");
    $stmt->execute([$ticket_id]);
    registrarLog('incidencia', "Incidencia en ticket #$ticket_id");
    header("Location: ver_ticket.php?id=$ticket_id");
    exit;
}

$stmt = $db->prepare("SELECT r.*, u.nombre FROM respuestas r JOIN usuarios u ON r.usuario_id = u.id WHERE r.ticket_id = ? AND r.es_nota_interna = 0 ORDER BY r.fecha_creacion");
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
        <p><strong>Web:</strong> <?=e($ticket['web_nombre'])?></p>
        <p><strong>Creado:</strong> <?=formatearFecha($ticket['fecha_creacion'])?></p>
        <hr>
        <p><?=nl2br(e($ticket['mensaje']))?></p>
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
        
        <?php if ($ticket['estado'] !== 'terminado'): ?>
            <form method="post">
                <div class="mb-3">
                    <label>Tu respuesta</label>
                    <textarea name="mensaje" class="form-control" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Enviar Respuesta</button>
            </form>
        <?php else: ?>
            <div class="alert alert-info">
                Este ticket está cerrado.
                <?php if (!$ticket['tiene_incidencia']): ?>
                    <form method="post" class="mt-2">
                        <button type="submit" name="incidencia" value="1" class="btn btn-warning">Tengo una Incidencia</button>
                    </form>
                <?php else: ?>
                    <strong>Incidencia reportada.</strong> El admin revisará.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3">
    <a href="tickets.php" class="btn btn-secondary">Volver a Tickets</a>
</div>
<?php include 'includes/footer.php'; ?>
