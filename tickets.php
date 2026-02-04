<?php
require_once 'config.php';
session_start();
if (!estaLogueado()) redirigir('login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

$stmt = $db->prepare("SELECT t.*, w.nombre as web_nombre FROM tickets t JOIN webs w ON t.web_id = w.id WHERE t.usuario_id = ? ORDER BY t.fecha_creacion DESC");
$stmt->execute([$usuario_id]);
$tickets = $stmt->fetchAll();

include 'includes/header.php';
?>
<h1>Mis Tickets</h1>
<div class="mb-3">
    <a href="nuevo_ticket.php" class="btn btn-primary">Nuevo Ticket</a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <tr><th>ID</th><th>Asunto</th><th>Web</th><th>Estado</th><th>Prioridad</th><th>Fecha</th><th></th></tr>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td>#<?=$t['id']?></td>
                <td><?=e($t['asunto'])?></td>
                <td><?=e($t['web_nombre'])?></td>
                <td><?=estadoBadge($t['estado'])?></td>
                <td><?=prioridadBadge($t['prioridad'])?></td>
                <td><?=formatearFecha($t['fecha_creacion'])?></td>
                <td><a href="ver_ticket.php?id=<?=$t['id']?>" class="btn btn-sm btn-primary">Ver</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
