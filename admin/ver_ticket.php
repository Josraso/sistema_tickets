<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$tid = (int)($_GET['id'] ?? 0);

$st = $db->prepare("SELECT t.*, w.nombre as web_nombre, u.nombre as cliente_nombre, u.email as cliente_email FROM tickets t JOIN webs w ON t.web_id = w.id JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ?");
$st->execute([$tid]); $ticket = $st->fetch();
if (!$ticket) redirigir('tickets.php');

// Cambio estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    verificarTokenCSRF();
    $nuevo_estado = $_POST['estado_nuevo'] ?? '';
    if (in_array($nuevo_estado, ['abierto','en_proceso','terminado'])) {
        $viejo = $ticket['estado'];
        $db->prepare("UPDATE tickets SET estado = ?, fecha_actualizacion = NOW() WHERE id = ?")->execute([$nuevo_estado, $tid]);
        if ($nuevo_estado === 'terminado' && $viejo !== 'terminado') {
            $db->prepare("UPDATE tickets SET fecha_cierre = NOW() WHERE id = ? AND fecha_cierre IS NULL")->execute([$tid]);
        }
        registrarHistorial($tid, 'Cambio de estado', "$viejo → $nuevo_estado");
        registrarLog('cambio_estado', "Ticket #$tid: $viejo → $nuevo_estado");
        if ($nuevo_estado === 'terminado') { emailTicketCerrado($ticket); }
        redirigir("ver_ticket.php?id=$tid");
    }
}

// Respuesta admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder'])) {
    verificarTokenCSRF();
    $msg = limpiar($_POST['mensaje'] ?? '');
    $es_nota = isset($_POST['es_nota_interna']) ? 1 : 0;
    if (!empty($msg)) {
        $db->prepare("INSERT INTO respuestas (ticket_id, usuario_id, mensaje, es_nota_interna) VALUES (?, ?, ?, ?)")
           ->execute([$tid, $_SESSION['usuario_id'], $msg, $es_nota]);
        $rid = $db->lastInsertId();
        // Archivos
        if (isset($_FILES['archivos_admin'])) {
            foreach ($_FILES['archivos_admin']['tmp_name'] as $i => $tmp) {
                if ($_FILES['archivos_admin']['error'][$i] === UPLOAD_ERR_OK && $_FILES['archivos_admin']['size'][$i] > 0) {
                    uploadArchivo([
                        'tmp_name' => $_FILES['archivos_admin']['tmp_name'][$i],
                        'name'     => $_FILES['archivos_admin']['name'][$i],
                        'size'     => $_FILES['archivos_admin']['size'][$i],
                        'error'    => $_FILES['archivos_admin']['error'][$i]
                    ], $tid, $rid);
                }
            }
        }
        $db->prepare("UPDATE tickets SET fecha_actualizacion = NOW() WHERE id = ?")->execute([$tid]);
        if ($es_nota) {
            registrarHistorial($tid, 'Nota interna admin', $msg);
        } else {
            registrarHistorial($tid, 'Respuesta admin', $msg);
            emailRespuestaAdmin($ticket, $msg);
        }
        registrarLog($es_nota ? 'nota_interna' : 'respuesta_admin', "Ticket #$tid");
        redirigir("ver_ticket.php?id=$tid");
    }
}

// Asignar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignar'])) {
    verificarTokenCSRF();
    $asignar_a = (int)($_POST['asignado_a'] ?? 0);
    $db->prepare("UPDATE tickets SET asignado_a = ? WHERE id = ?")->execute([$asignar_a ?: null, $tid]);
    registrarHistorial($tid, 'Asignación cambiada', $asignar_a ? "Asignado a usuario #$asignar_a" : 'Sin asignar');
    redirigir("ver_ticket.php?id=$tid");
}

// Refetch
$st = $db->prepare("SELECT t.*, w.nombre as web_nombre, u.nombre as cliente_nombre FROM tickets t JOIN webs w ON t.web_id = w.id JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ?");
$st->execute([$tid]); $ticket = $st->fetch();

$st = $db->prepare("SELECT r.*, u.nombre as usu_nombre FROM respuestas r JOIN usuarios u ON r.usuario_id = u.id WHERE r.ticket_id = ? ORDER BY r.fecha_creacion");
$st->execute([$tid]); $respuestas = $st->fetchAll();

$tags = obtenerTagsTicket($tid);
$archivos = obtenerArchivos($tid);

$st = $db->prepare("SELECT h.*, u.nombre as usu_nombre FROM historial_tickets h LEFT JOIN usuarios u ON h.usuario_id = u.id WHERE h.ticket_id = ? ORDER BY h.fecha DESC");
$st->execute([$tid]); $historial = $st->fetchAll();

$admins = $db->query("SELECT * FROM usuarios WHERE rol = 'admin'")->fetchAll();

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
<h4><i class="bi bi-ticket"></i> Ticket #<?=$ticket['id']?></h4>
<div class="d-flex gap-2">
<?php if ($ticket['estado'] === 'abierto'): ?>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="cambiar_estado" value="1"><input type="hidden" name="estado_nuevo" value="en_proceso">
<button class="btn btn-warning btn-sm"><i class="bi bi-arrow-clockwise"></i> En Proceso</button></form>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="cambiar_estado" value="1"><input type="hidden" name="estado_nuevo" value="terminado">
<button class="btn btn-danger btn-sm"><i class="bi bi-x-lg"></i> Cerrar Ticket</button></form>
<?php elseif ($ticket['estado'] === 'en_proceso'): ?>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="cambiar_estado" value="1"><input type="hidden" name="estado_nuevo" value="terminado">
<button class="btn btn-danger btn-sm"><i class="bi bi-x-lg"></i> Cerrar Ticket</button></form>
<?php else: ?>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="cambiar_estado" value="1"><input type="hidden" name="estado_nuevo" value="abierto">
<button class="btn btn-success btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Reabrir</button></form>
<?php endif; ?>
<a href="tickets.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>
</div>

<div class="row">
<!-- Columna principal -->
<div class="col-lg-8">
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
<div class="col-md-4"><i class="bi bi-person"></i> <strong>Cliente:</strong> <?=e($ticket['cliente_nombre'])?></div>
<div class="col-md-4"><i class="bi bi-globe"></i> <strong>Web:</strong> <?=e($ticket['web_nombre'])?></div>
<div class="col-md-4"><i class="bi bi-calendar3"></i> <strong>Creado:</strong> <?=formatearFecha($ticket['fecha_creacion'])?></div>
</div>
<?php if (!empty($tags)): ?><div class="mb-2"><?=renderTags($tags)?></div><?php endif; ?>
<hr>
<p class="respuesta-cliente"><?=nl2br(e($ticket['mensaje']))?></p>
<?=renderArchivos($archivos)?>
<div class="mt-2 text-muted small"><i class="bi bi-envelope"></i> Email PIPE: <strong>ticket+<?=$ticket['id']?>@<?=e(obtenerConfig('dominio_mail','tudominio.com'))?></strong></div>
</div>
</div>

<!-- Conversación -->
<div class="card mt-3">
<div class="card-header"><i class="bi bi-chat-dots"></i> Conversación</div>
<div class="card-body">
<?php foreach ($respuestas as $r): ?>
<?php $ar = obtenerArchivos($tid, $r['id']); ?>
<div class="respuesta-<?=$r['es_nota_interna'] ? 'nota' : 'admin'?>">
<div class="d-flex justify-content-between">
<strong><?=e($r['usu_nombre'])?> <?php if($r['es_nota_interna']): ?><span class="nota-label"><i class="bi bi-lock"></i> Nota interna</span><?php endif; ?></strong>
<small class="text-muted"><?=formatearFecha($r['fecha_creacion'])?></small>
</div>
<p class="mt-1 mb-1"><?=nl2br(e($r['mensaje']))?></p>
<?=renderArchivos($ar)?>
</div>
<?php endforeach; ?>

<!-- Formulario respuesta -->
<form method="post" enctype="multipart/form-data" class="mt-3">
<?=csrfInput()?>
<div class="mb-3">
<div class="d-flex justify-content-between align-items-center mb-1">
<label class="form-label"><i class="bi bi-chat-text"></i> Respuesta</label>
<div class="form-check form-switch">
<input class="form-check-input" type="checkbox" name="es_nota_interna" id="chkNota" role="switch">
<label class="form-check-label small" for="chkNota"><i class="bi bi-lock"></i> Nota interna (invisible al cliente)</label>
</div>
</div>
<textarea name="mensaje" class="form-control" rows="4" required></textarea>
</div>
<?=renderArchivoUpload('archivos_admin', true)?>
<button type="submit" name="responder" value="1" class="btn btn-primary"><i class="bi bi-send"></i> Enviar</button>
</form>
</div>
</div>

<!-- Historial -->
<div class="card mt-3">
<div class="card-header"><i class="bi bi-clock-history"></i> Historial</div>
<div class="card-body">
<?php if (empty($historial)): ?><p class="text-muted small">Sin historial</p>
<?php else: ?>
<?php foreach ($historial as $h): ?>
<?php
$cls = 'ev-estado';
if (strpos($h['accion'], 'Incidencia') !== false) $cls = 'ev-inc';
elseif (strpos($h['accion'], 'Nota') !== false) $cls = 'ev-nota';
elseif (strpos($h['accion'], 'Respuesta') !== false) $cls = 'ev-resp';
?>
<div class="historial-item <?=$cls?>">
<small class="text-muted"><?=formatearFecha($h['fecha'])?></small>
<p class="mb-0"><strong><?=e($h['accion'])?></strong> <?php if($h['usu_nombre']): ?>— <?=e($h['usu_nombre'])?><?php endif; ?></p>
<?php if(!empty($h['detalles'])): ?><p class="mb-0 text-muted small"><?=e($h['detalles'])?></p><?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
</div>

<!-- Columna derecha: controles -->
<div class="col-lg-4">
<!-- Cambio estado -->
<div class="card">
<div class="card-header"><i class="bi bi-arrow-repeat"></i> Estado</div>
<div class="card-body">
<form method="post">
<?=csrfInput()?>
<select name="estado_nuevo" class="form-select mb-3">
<option value="abierto" <?=$ticket['estado']==='abierto'?'selected':'';?>>Abierto</option>
<option value="en_proceso" <?=$ticket['estado']==='en_proceso'?'selected':'';?>>En Proceso</option>
<option value="terminado" <?=$ticket['estado']==='terminado'?'selected':'';?>>Terminado</option>
</select>
<button type="submit" name="cambiar_estado" value="1" class="btn btn-warning btn-sm w-100"><i class="bi bi-check-lg"></i> Cambiar Estado</button>
</form>
</div>
</div>
<!-- Asignar -->
<div class="card mt-3">
<div class="card-header"><i class="bi bi-person-plus"></i> Asignar</div>
<div class="card-body">
<form method="post">
<?=csrfInput()?>
<select name="asignado_a" class="form-select mb-3">
<option value="0" <?=!$ticket['asignado_a']?'selected':'';?>>Sin asignar</option>
<?php foreach ($admins as $a): ?>
<option value="<?=$a['id']?>" <?=$ticket['asignado_a']==$a['id']?'selected':'';?>><?=e($a['nombre'])?></option>
<?php endforeach; ?>
</select>
<button type="submit" name="asignar" value="1" class="btn btn-info btn-sm w-100"><i class="bi bi-check-lg"></i> Asignar</button>
</form>
</div>
</div>
<!-- Info cliente -->
<div class="card mt-3">
<div class="card-header"><i class="bi bi-person"></i> Cliente</div>
<div class="card-body">
<p class="mb-1"><strong><?=e($ticket['cliente_nombre'])?></strong></p>
<p class="text-muted small mb-0"><?=e($ticket['cliente_email'])?></p>
<a href="impersonar.php?id=<?=$ticket['usuario_id']?>" class="btn btn-outline-warning btn-sm mt-2 w-100"><i class="bi bi-person-fill-exclamation"></i> Entrar como este cliente</a>
</div>
</div>
</div>
</div><!-- /row -->

<div class="mt-3"><a href="tickets.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Volver</a></div>
<?php include 'includes/footer.php'; ?>
