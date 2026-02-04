<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();

$stmt = $db->query("SELECT COUNT(*) as total FROM tickets");
$total_tickets = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM tickets WHERE estado = 'abierto'");
$abiertos = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE rol = 'cliente'");
$total_clientes = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 'pendiente'");
$pendientes = $stmt->fetch()['total'];

$stmt = $db->query("SELECT t.*, u.nombre as cliente, w.nombre as web FROM tickets t JOIN usuarios u ON t.usuario_id = u.id JOIN webs w ON t.web_id = w.id ORDER BY t.fecha_creacion DESC LIMIT 10");
$tickets = $stmt->fetchAll();

include 'includes/header.php';
?>
<h1>Admin Dashboard</h1>
<div class="row">
    <div class="col-md-3"><div class="card text-white bg-primary"><div class="card-body text-center">
        <h2><?=$total_tickets?></h2><p>Total Tickets</p>
    </div></div></div>
    <div class="col-md-3"><div class="card text-white bg-info"><div class="card-body text-center">
        <h2><?=$abiertos?></h2><p>Abiertos</p>
    </div></div></div>
    <div class="col-md-3"><div class="card text-white bg-success"><div class="card-body text-center">
        <h2><?=$total_clientes?></h2><p>Clientes</p>
    </div></div></div>
    <div class="col-md-3"><div class="card text-white bg-warning"><div class="card-body text-center">
        <h2><?=$pendientes?></h2><p>Pendientes</p>
    </div></div></div>
</div>

<div class="card mt-4">
    <div class="card-header">Tickets Recientes</div>
    <div class="card-body">
        <table class="table">
            <tr><th>ID</th><th>Cliente</th><th>Web</th><th>Asunto</th><th>Estado</th><th></th></tr>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td>#<?=$t['id']?></td>
                <td><?=e($t['cliente'])?></td>
                <td><?=e($t['web'])?></td>
                <td><?=e($t['asunto'])?></td>
                <td><?=estadoBadge($t['estado'])?></td>
                <td><a href="ver_ticket.php?id=<?=$t['id']?>" class="btn btn-sm btn-primary">Ver</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
