<?php
require_once 'config.php';
session_start();
if (!estaLogueado()) redirigir('login.php');
if (esAdmin() && !estaImpersonando()) redirigir('admin/');

$db = getDB();
$uid = $_SESSION['usuario_id'];
$tid = (int)($_GET['id'] ?? 0);

$st = $db->prepare("SELECT t.*, w.nombre as web_nombre FROM tickets t JOIN webs w ON t.web_id = w.id WHERE t.id = ? AND t.usuario_id = ?");
$st->execute([$tid, $uid]); $ticket = $st->fetch();
if (!$ticket) redirigir('tickets.php');

// Incidencia POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['incidencia']) && $ticket['estado'] === 'terminado') {
    verificarTokenCSRF();
    if (!$ticket['tiene_incidencia']) {
        $db->prepare("UPDATE tickets SET tiene_incidencia = 1 WHERE id = ?")->execute([$tid]);
        registrarHistorial($tid, 'Incidencia reportada', 'El cliente ha marcado una incidencia');
        registrarLog('incidencia', "Ticket #$tid");
        emailIncidencia($tid);
    }
    redirigir("ver_ticket.php?id=$tid");
}
// Respuesta POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket['estado'] !== 'terminado' && !isset($_POST['incidencia'])) {
    verificarTokenCSRF();
    $msg = limpiar($_POST['mensaje'] ?? '');
    if (!empty($msg)) {
        $db->prepare("INSERT INTO respuestas (ticket_id, usuario_id, mensaje) VALUES (?, ?, ?)")->execute([$tid, $uid, $msg]);
        $rid = $db->lastInsertId();
        if (isset($_FILES['archivos_resp'])) {
            foreach ($_FILES['archivos_resp']['tmp_name'] as $i => $tmp) {
                if ($_FILES['archivos_resp']['error'][$i] === UPLOAD_ERR_OK && $_FILES['archivos_resp']['size'][$i] > 0) {
                    uploadArchivo([
                        'tmp_name' => $_FILES['archivos_resp']['tmp_name'][$i],
                        'name'     => $_FILES['archivos_resp']['name'][$i],
                        'size'     => $_FILES['archivos_resp']['size'][$i],
                        'error'    => $_FILES['archivos_resp']['error'][$i]
                    ], $tid, $rid);
                }
            }
        }
        $db->prepare("UPDATE tickets SET fecha_actualizacion = NOW() WHERE id = ?")->execute([$tid]);
        registrarHistorial($tid, 'Respuesta del cliente', $msg);
        emailRespuestaCliente($ticket, $msg);
        redirigir("ver_ticket.php?id=$tid");
    }
}

// Refetch ticket
$st = $db->prepare("SELECT t.*, w.nombre as web_nombre FROM tickets t JOIN webs w ON t.web_id = w.id WHERE t.id = ?");
$st->execute([$tid]); $ticket = $st->fetch();

$st = $db->prepare("SELECT r.*, u.nombre as usu_nombre FROM respuestas r JOIN usuarios u ON r.usuario_id = u.id WHERE r.ticket_id = ? AND r.es_nota_interna = 0 ORDER BY r.fecha_creacion");
$st->execute([$tid]); $respuestas = $st->fetchAll();

$tags = obtenerTagsTicket($tid);
$archivos = obtenerArchivos($tid);

$st = $db->prepare("SELECT h.*, u.nombre as usu_nombre FROM historial_tickets h LEFT JOIN usuarios u ON h.usuario_id = u.id WHERE h.ticket_id = ? ORDER BY h.fecha DESC");
$st->execute([$tid]); $historial = $st->fetchAll();

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-ticket"></i> Ticket #<?=$ticket['id']?></h4>
<a href="tickets.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>
<!-- Info ticket -->
<div class="card">
<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
<strong><?=e($ticket['asunto'])?></strong>
<div>
<?=estadoBadge($ticket['estado'])?> <?=prioridadBadge($ticket['prioridad'])?>
<?php if ($ticket['tiene_incidencia']): ?><span class="etiqueta-incidencia">Incidencia</span><?php endif; ?>
</div>
</div>
<div class="card-body">
<div class="row text-muted small mb-2">
<div class="col-md-4"><i class="bi bi-globe"></i> <strong>Web:</strong> <?=e($ticket['web_nombre'])?></div>
<div class="col-md-4"><i class="bi bi-calendar3"></i> <strong>Creado:</strong> <?=formatearFecha($ticket['fecha_creacion'])?></div>
<div class="col-md-4"><i class="bi bi-clock"></i> <strong>Actualizado:</strong> <?=formatearFecha($ticket['fecha_actualizacion'])?></div>
</div>
<?php if (!empty($tags)): ?><div class="mb-2"><?=renderTags($tags)?></div><?php endif; ?>
<hr>
<p class="respuesta-cliente"><?=nl2br(e($ticket['mensaje']))?></p>
<?=renderArchivos($archivos)?>
<div class="mt-2 text-muted small"><i class="bi bi-envelope"></i> Responder por email: <strong>ticket+<?=$ticket['id']?>@<?=e(obtenerConfig('dominio_mail','tudominio.com'))?></strong></div>
</div>
</div>

<!-- Conversación -->
<div class="card mt-3">
<div class="card-header"><i class="bi bi-chat-dots"></i> Conversación</div>
<div class="card-body">
<?php foreach ($respuestas as $r): ?>
<?php $ar = obtenerArchivos($tid, $r['id']); ?>
<div class="respuesta-admin">
<div class="d-flex justify-content-between"><strong><?=e($r['usu_nombre'])?></strong><small class="text-muted"><?=formatearFecha($r['fecha_creacion'])?></small></div>
<p class="mt-1 mb-1"><?=nl2br(e($r['mensaje']))?></p>
<?=renderArchivos($ar)?>
</div>
<?php endforeach; ?>

<?php if ($ticket['estado'] !== 'terminado'): ?>
<form method="post" enctype="multipart/form-data" class="mt-3">
<?=csrfInput()?>
<div class="mb-3"><label class="form-label"><i class="bi bi-chat-text"></i> Tu respuesta</label>
<textarea name="mensaje" class="form-control" rows="4" required></textarea></div>
<?=renderArchivoUpload('archivos_resp', true)?>
<button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button>
</form>
<?php else: ?>
<div class="alert alert-info mt-3">
<i class="bi bi-info-circle"></i> Este ticket está <strong>cerrado</strong>.
<?php if (!$ticket['tiene_incidencia']): ?>
<form method="post" class="mt-2"><?=csrfInput()?>
<button type="submit" name="incidencia" value="1" class="btn btn-warning btn-sm"><i class="bi bi-exclamation-triangle"></i> Tengo una Incidencia</button>
</form>
<?php else: ?>
<p class="mb-0 mt-1"><strong>Incidencia reportada.</strong> El administrador la revisará.</p>
<?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>

<!-- Historial -->
<div class="card mt-3">
<div class="card-header"><i class="bi bi-clock-history"></i> Historial</div>
<div class="card-body">
<?php if (empty($historial)): ?><p class="text-muted small">Sin historial registrado</p>
<?php else: ?>
<?php foreach ($historial as $h): ?>
<?php
$cls = 'ev-estado';
if (str_contains($h['accion'], 'Incidencia')) $cls = 'ev-inc';
elseif (str_contains($h['accion'], 'Nota')) $cls = 'ev-nota';
elseif (str_contains($h['accion'], 'Respuesta')) $cls = 'ev-resp';
?>
<div class="historial-item <?=$cls?>">
<small class="text-muted"><?=formatearFecha($h['fecha'])?></small>
<p class="mb-0"><strong><?=e($h['accion'])?></strong> <?php if ($h['usu_nombre']): ?>— <?=e($h['usu_nombre'])?><?php endif; ?></p>
<?php if (!empty($h['detalles'])): ?><p class="mb-0 text-muted small"><?=e($h['detalles'])?></p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
<div class="mt-3"><a href="tickets.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a></div>
<?php include 'includes/footer.php'; ?>
