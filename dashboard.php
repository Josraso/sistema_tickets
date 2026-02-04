<?php
require_once 'config.php';
session_start();
if (!estaLogueado() || esAdmin()) redirigir('login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$total_tickets = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM tickets WHERE usuario_id = ? AND estado = 'abierto'");
$stmt->execute([$usuario_id]);
$abiertos = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM webs WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$total_webs = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT t.*, w.nombre as web_nombre FROM tickets t JOIN webs w ON t.web_id = w.id WHERE t.usuario_id = ? ORDER BY t.fecha_creacion DESC LIMIT 10");
$stmt->execute([$usuario_id]);
$tickets = $stmt->fetchAll();

include 'includes/header.php';
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
                    <td>#<?=$t['id']?></td>
                    <td><?=e($t['asunto'])?></td>
                    <td><?=e($t['web_nombre'])?></td>
                    <td><?=estadoBadge($t['estado'])?></td>
                    <td><?=formatearFecha($t['fecha_creacion'])?></td>
                    <td><a href="ver_ticket.php?id=<?=$t['id']?>" class="btn btn-sm btn-primary">Ver</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
