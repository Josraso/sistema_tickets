<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();

$stmt = $db->query("SELECT t.*, u.nombre as cliente, w.nombre as web FROM tickets t JOIN usuarios u ON t.usuario_id = u.id JOIN webs w ON t.web_id = w.id ORDER BY t.fecha_creacion DESC");
$tickets = $stmt->fetchAll();

include 'includes/header.php';
?>
<h1>Gestión de Tickets</h1>
<div class="card">
    <div class="card-body">
        <table class="table">
            <tr><th>ID</th><th>Cliente</th><th>Web</th><th>Asunto</th><th>Estado</th><th>Prioridad</th><th>Fecha</th><th></th></tr>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td>#<?=$t['id']?></td>
                <td><?=e($t['cliente'])?></td>
                <td><?=e($t['web'])?></td>
                <td><?=e($t['asunto'])?></td>
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
