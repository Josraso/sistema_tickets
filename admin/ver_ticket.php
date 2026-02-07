<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$tid = (int)($_GET['id'] ?? 0);

$st = $db->prepare("SELECT t.*, w.nombre as web_nombre, u.nombre as cliente_nombre, u.email as cliente_email, u.telefono as cliente_tel FROM tickets t JOIN webs w ON t.web_id = w.id JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ?");
$st->execute([$tid]); $ticket = $st->fetch();
if (!$ticket) redirigir('tickets.php');

// Obtener firma del admin actual para respuestas
$st_firma = $db->prepare("SELECT firma FROM usuarios WHERE id = ?");
$st_firma->execute([$_SESSION['usuario_id']]);
$admin_firma = $st_firma->fetch()['firma'] ?? '';

// Obtener respuestas rápidas del admin
$st_rr = $db->prepare("SELECT * FROM respuestas_rapidas WHERE usuario_id = ? ORDER BY titulo");
$st_rr->execute([$_SESSION['usuario_id']]);
$respuestas_rapidas = $st_rr->fetchAll();

// Cambio estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    verificarTokenCSRF();
    $nuevo_estado = $_POST['estado_nuevo'] ?? '';
    if (in_array($nuevo_estado, ['abierto','en_proceso','terminado'])) {
        $viejo = $ticket['estado'];
        $db->prepare("UPDATE tickets SET estado = ?, fecha_actualizacion = NOW() WHERE id = ?")->execute([$nuevo_estado, $tid]);
        if ($nuevo_estado === 'terminado' && $viejo !== 'terminado') {
            $db->prepare("UPDATE tickets SET fecha_cierre = NOW() WHERE id = ? AND fecha_cierre IS NULL")->execute([$tid]);
            // Registrar tiempo (sumativo)
            $horas   = (int)($_POST['tiempo_horas'] ?? 0);
            $minutos = (int)($_POST['tiempo_minutos'] ?? 0);
            $tiempo  = $horas * 60 + $minutos;
            if ($tiempo > 0) {
                $tiempo_previo = (int)($ticket['tiempo_resolucion'] ?? 0);
                $tiempo_total = $tiempo_previo + $tiempo;
                $db->prepare("UPDATE tickets SET tiempo_resolucion = ? WHERE id = ?")->execute([$tiempo_total, $tid]);
                registrarHistorial($tid, 'Tiempo registrado', formatMinutos($tiempo) . ($tiempo_previo > 0 ? ' (total: ' . formatMinutos($tiempo_total) . ')' : ''));
            }
        }
        // Si se reabre un ticket terminado, limpiar la bandera de incidencia
        if ($nuevo_estado === 'abierto' && $viejo === 'terminado') {
            $db->prepare("UPDATE tickets SET tiene_incidencia = 0 WHERE id = ?")->execute([$tid]);
            if ($ticket['tiene_incidencia']) {
                registrarHistorial($tid, 'Incidencia resuelta', 'Ticket reabierto por admin');
            }
        }
        registrarHistorial($tid, 'Cambio de estado', "$viejo → $nuevo_estado");
        registrarLog('cambio_estado', "Ticket #$tid: $viejo → $nuevo_estado");
        if ($nuevo_estado === 'terminado') { emailTicketCerrado($ticket); }
        if ($nuevo_estado === 'en_proceso' && $viejo !== 'en_proceso') { emailTicketEnProceso($ticket); }
        redirigir("ver_ticket.php?id=$tid");
    }
}

// Respuesta admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder'])) {
    verificarTokenCSRF();
    $msg = trim($_POST['mensaje'] ?? '');
    $es_nota = isset($_POST['es_nota_interna']) ? 1 : 0;
    if (!empty($msg)) {
        // Añadir firma si existe y no es nota interna
        if (!$es_nota && !empty($admin_firma)) {
            $msg .= "\n\n---\n\n" . $admin_firma;
        }
        $db->prepare("INSERT INTO respuestas (ticket_id, usuario_id, mensaje, es_nota_interna) VALUES (?, ?, ?, ?)")
           ->execute([$tid, $_SESSION['usuario_id'], $msg, $es_nota]);
        $rid = $db->lastInsertId();
        if (isset($_FILES['archivos_admin'])) {
            $cnt = 0;
            foreach ($_FILES['archivos_admin']['tmp_name'] as $i => $tmp) {
                if ($cnt >= 5) break;
                if ($_FILES['archivos_admin']['error'][$i] === UPLOAD_ERR_OK && $_FILES['archivos_admin']['size'][$i] > 0) {
                    if (uploadArchivo([
                        'tmp_name' => $_FILES['archivos_admin']['tmp_name'][$i],
                        'name'     => $_FILES['archivos_admin']['name'][$i],
                        'size'     => $_FILES['archivos_admin']['size'][$i],
                        'error'    => $_FILES['archivos_admin']['error'][$i]
                    ], $tid, $rid)) $cnt++;
                }
            }
        }
        $db->prepare("UPDATE tickets SET fecha_actualizacion = NOW() WHERE id = ?")->execute([$tid]);
        if ($es_nota) { registrarHistorial($tid, 'Nota interna admin', $msg); }
        else { registrarHistorial($tid, 'Respuesta admin', $msg); emailRespuestaAdmin($ticket, $msg); }
        registrarLog($es_nota ? 'nota_interna' : 'respuesta_admin', "Ticket #$tid");
        redirigir("ver_ticket.php?id=$tid");
    }
}

// Resolver incidencia sin reabrir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolver_incidencia'])) {
    verificarTokenCSRF();
    $db->prepare("UPDATE tickets SET tiene_incidencia = 0 WHERE id = ?")->execute([$tid]);
    registrarHistorial($tid, 'Incidencia resuelta', 'Admin la marcó como revisada');
    registrarLog('incidencia_resuelta', "Ticket #$tid");
    redirigir("ver_ticket.php?id=$tid");
}

// Asignar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignar'])) {
    verificarTokenCSRF();
    $asignar_a = (int)($_POST['asignado_a'] ?? 0);
    $db->prepare("UPDATE tickets SET asignado_a = ? WHERE id = ?")->execute([$asignar_a ?: null, $tid]);
    registrarHistorial($tid, 'Asignación cambiada', $asignar_a ? "Asignado a usuario #$asignar_a" : 'Sin asignar');
    redirigir("ver_ticket.php?id=$tid");
}

// Editar respuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_respuesta'])) {
    verificarTokenCSRF();
    $rid = (int)($_POST['respuesta_id'] ?? 0);
    $nuevo_msg = trim($_POST['respuesta_mensaje'] ?? '');
    if (!empty($nuevo_msg) && $rid > 0) {
        $db->prepare("UPDATE respuestas SET mensaje = ? WHERE id = ? AND ticket_id = ?")->execute([$nuevo_msg, $rid, $tid]);
        registrarHistorial($tid, 'Respuesta editada', "Respuesta #$rid modificada por admin");
        registrarLog('respuesta_editada', "Ticket #$tid, Respuesta #$rid");
    }
    redirigir("ver_ticket.php?id=$tid");
}

// Borrar respuesta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar_respuesta'])) {
    verificarTokenCSRF();
    $rid = (int)($_POST['respuesta_id'] ?? 0);
    if ($rid > 0) {
        // Borrar archivos asociados primero
        $st = $db->prepare("SELECT * FROM archivos WHERE respuesta_id = ?");
        $st->execute([$rid]);
        foreach ($st->fetchAll() as $arch) {
            if (file_exists($arch['ruta'])) unlink($arch['ruta']);
        }
        $db->prepare("DELETE FROM archivos WHERE respuesta_id = ?")->execute([$rid]);
        // Borrar respuesta
        $db->prepare("DELETE FROM respuestas WHERE id = ? AND ticket_id = ?")->execute([$rid, $tid]);
        registrarHistorial($tid, 'Respuesta eliminada', "Respuesta #$rid borrada por admin");
        registrarLog('respuesta_borrada', "Ticket #$tid, Respuesta #$rid");
    }
    redirigir("ver_ticket.php?id=$tid");
}

// Refetch completo (incluye cliente_email)
$st = $db->prepare("SELECT t.*, w.nombre as web_nombre, u.nombre as cliente_nombre, u.email as cliente_email, u.telefono as cliente_tel FROM tickets t JOIN webs w ON t.web_id = w.id JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ?");
$st->execute([$tid]); $ticket = $st->fetch();

$st = $db->prepare("SELECT r.*, u.nombre as usu_nombre FROM respuestas r JOIN usuarios u ON r.usuario_id = u.id WHERE r.ticket_id = ? ORDER BY r.fecha_creacion");
$st->execute([$tid]); $respuestas = $st->fetchAll();

$tags      = obtenerTagsTicket($tid);
$archivos  = obtenerArchivos($tid);

$st = $db->prepare("SELECT h.*, u.nombre as usu_nombre FROM historial_tickets h LEFT JOIN usuarios u ON h.usuario_id = u.id WHERE h.ticket_id = ? ORDER BY h.fecha DESC");
$st->execute([$tid]); $historial = $st->fetchAll();

$admins = $db->query("SELECT * FROM usuarios WHERE rol = 'admin'")->fetchAll();

// Marcar respuestas del cliente como leídas
marcarRespuestaLeidas($tid);

include 'includes/header.php';
?>

<!-- Barra de acciones superior -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
<h4><i class="bi bi-ticket"></i> Ticket #<?=$ticket['id']?></h4>
<div class="d-flex gap-2">
<?php if ($ticket['estado'] === 'abierto'): ?>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="cambiar_estado" value="1"><input type="hidden" name="estado_nuevo" value="en_proceso">
<button class="btn btn-warning btn-sm"><i class="bi bi-arrow-clockwise"></i> En Proceso</button></form>
<button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalCerrar"><i class="bi bi-x-lg"></i> Cerrar Ticket</button>
<?php elseif ($ticket['estado'] === 'en_proceso'): ?>
<button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalCerrar"><i class="bi bi-x-lg"></i> Cerrar Ticket</button>
<?php else: ?>
<?php if ($ticket['tiene_incidencia']): ?>
<form method="post" style="display:inline"><?=csrfInput()?><input type="hidden" name="resolver_incidencia" value="1">
<button class="btn btn-outline-warning btn-sm"><i class="bi bi-check-circle"></i> Resolver Incidencia</button></form>
<?php endif; ?>
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
<?php if ($ticket['tiempo_resolucion']): ?><span class="badge bg-light text-dark border ms-1"><i class="bi bi-clock"></i> <?=formatMinutos($ticket['tiempo_resolucion'])?></span><?php endif; ?>
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
<div class="respuesta-<?=$r['es_nota_interna'] ? 'nota' : ($r['usuario_id'] == $ticket['usuario_id'] ? 'cliente' : 'admin')?>" id="respuesta-<?=$r['id']?>">
<div class="d-flex justify-content-between">
<div>
<strong><?=e($r['usu_nombre'])?> <?php if($r['es_nota_interna']): ?><span class="nota-label"><i class="bi bi-lock"></i> Nota interna</span><?php endif; ?></strong>
</div>
<div class="d-flex align-items-center gap-2">
<small class="text-muted"><?=formatearFecha($r['fecha_creacion'])?></small>
<div class="btn-group btn-group-sm" role="group">
<button class="btn btn-outline-secondary btn-sm" onclick="editarRespuesta(<?=$r['id']?>, <?=htmlspecialchars(json_encode($r['mensaje']), ENT_QUOTES)?>)" title="Editar"><i class="bi bi-pencil"></i></button>
<form method="post" style="display:inline" onsubmit="return confirm('¿Borrar esta respuesta?')">
<?=csrfInput()?>
<input type="hidden" name="borrar_respuesta" value="1">
<input type="hidden" name="respuesta_id" value="<?=$r['id']?>">
<button class="btn btn-outline-danger btn-sm" title="Borrar"><i class="bi bi-trash"></i></button>
</form>
</div>
</div>
</div>
<p class="mt-1 mb-1"><?=nl2br(e($r['mensaje']))?></p>
<?=renderArchivos($ar)?>
</div>
<?php endforeach; ?>

<!-- Botón expander + formulario oculto -->
<div class="mt-3">
<button class="btn btn-outline-primary btn-sm" id="btnMostrarResp" onclick="toggleRespuesta()"><i class="bi bi-chat-text"></i> Responder</button>
</div>
<div id="formRespuesta" style="display:none; margin-top:1rem;">
<form method="post" enctype="multipart/form-data" id="formResponder">
<?=csrfInput()?>
<div class="mb-3">
<div class="d-flex justify-content-between align-items-center mb-1">
<label class="form-label"><i class="bi bi-chat-text"></i> Mensaje</label>
<div class="form-check form-switch">
<input class="form-check-input" type="checkbox" name="es_nota_interna" id="chkNota" role="switch">
<label class="form-check-label small" for="chkNota"><i class="bi bi-lock"></i> Nota interna (invisible al cliente)</label>
</div>
</div>
<?php if (!empty($respuestas_rapidas)): ?>
<select class="form-select form-select-sm mb-2" id="selRR" onchange="insertarRR()">
<option value="">⚡ Insertar respuesta rápida...</option>
<?php foreach ($respuestas_rapidas as $rr): ?>
<option value="<?=e($rr['contenido'])?>"><?=e($rr['titulo'])?></option>
<?php endforeach; ?>
</select>
<?php endif; ?>
<!-- Barra herramientas editor -->
<div class="btn-toolbar mb-1" role="toolbar">
<div class="btn-group btn-group-sm me-2">
<button type="button" class="btn btn-outline-secondary" onclick="formatDoc('bold')" title="Negrita"><i class="bi bi-type-bold"></i></button>
<button type="button" class="btn btn-outline-secondary" onclick="formatDoc('italic')" title="Cursiva"><i class="bi bi-type-italic"></i></button>
<button type="button" class="btn btn-outline-secondary" onclick="formatDoc('underline')" title="Subrayado"><i class="bi bi-type-underline"></i></button>
</div>
<div class="btn-group btn-group-sm me-2">
<button type="button" class="btn btn-outline-secondary" onclick="formatDoc('insertUnorderedList')" title="Lista"><i class="bi bi-list-ul"></i></button>
<button type="button" class="btn btn-outline-secondary" onclick="formatDoc('insertOrderedList')" title="Lista numerada"><i class="bi bi-list-ol"></i></button>
</div>
</div>
<div id="editor" contenteditable="true" class="form-control" style="min-height:120px;max-height:400px;overflow-y:auto;"></div>
<textarea name="mensaje" id="txtMensaje" style="display:none;"></textarea>
</div>
<?=renderArchivoUpload('archivos_admin', true)?>
<button type="submit" name="responder" value="1" class="btn btn-primary btn-sm" onclick="return enviarRespuesta()"><i class="bi bi-send"></i> Enviar</button>
<button type="button" class="btn btn-link btn-sm text-muted p-0 ms-2" onclick="toggleRespuesta()">Cancelar</button>
</form>
</div>
</div>
</div>

<!-- Historial colapsible -->
<div class="card mt-3">
<div class="card-header d-flex justify-content-between align-items-center">
<span><i class="bi bi-clock-history"></i> Historial</span>
<button class="btn btn-sm btn-outline-secondary" id="btnHistorial" onclick="toggleHistorial()"><i class="bi bi-chevron-down" id="iconHist"></i> Mostrar (<?=count($historial)?>)</button>
</div>
<div id="historialBody" class="card-body" style="display:none;">
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
</div><!-- /col-lg-8 -->

<!-- Columna derecha -->
<div class="col-lg-4">

<!-- Asignar -->
<div class="card">
<div class="card-header"><i class="bi bi-person-plus"></i> Asignar</div>
<div class="card-body">
<form method="post">
<?=csrfInput()?>
<select name="asignado_a" class="form-select form-select-sm mb-2">
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
<p class="text-muted small mb-0"><i class="bi bi-envelope"></i> <?=e($ticket['cliente_email'])?></p>
<?php if (!empty($ticket['cliente_tel'])): ?><p class="text-muted small mb-0"><i class="bi bi-phone"></i> <?=e($ticket['cliente_tel'])?></p><?php endif; ?>
<a href="impersonar.php?id=<?=$ticket['usuario_id']?>" class="btn btn-outline-warning btn-sm mt-2 w-100"><i class="bi bi-person-fill-exclamation"></i> Entrar como este cliente</a>
</div>
</div>

<!-- Tiempo registrado -->
<?php if ($ticket['tiempo_resolucion']): ?>
<div class="card mt-3">
<div class="card-header"><i class="bi bi-clock"></i> Tiempo Registrado</div>
<div class="card-body text-center">
<div class="stat-number text-secondary"><?=formatMinutos($ticket['tiempo_resolucion'])?></div>
</div>
</div>
<?php endif; ?>

</div><!-- /col-lg-4 -->
</div><!-- /row -->

<div class="mt-3"><a href="tickets.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a></div>

<!-- Modal cerrar ticket con tiempo -->
<div class="modal fade" id="modalCerrar" tabindex="-1">
<div class="modal-dialog modal-sm"><div class="modal-content">
<form method="post">
<?=csrfInput()?>
<input type="hidden" name="cambiar_estado" value="1">
<input type="hidden" name="estado_nuevo" value="terminado">
<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-clock"></i> Cerrar Ticket</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<p class="text-muted small mb-3">Registra el tiempo invertido antes de cerrar.</p>
<div class="row">
<div class="col-6">
<label class="form-label small text-muted">Horas</label>
<select name="tiempo_horas" class="form-select form-select-sm">
<?php for($i=0;$i<=12;$i++): ?><option value="<?=$i?>"><?=$i?></option><?php endfor; ?>
</select>
</div>
<div class="col-6">
<label class="form-label small text-muted">Minutos</label>
<select name="tiempo_minutos" class="form-select form-select-sm">
<option value="0">0</option>
<option value="5">5</option>
<option value="10">10</option>
<option value="15">15</option>
<option value="20">20</option>
<option value="25">25</option>
<option value="30">30</option>
<option value="35">35</option>
<option value="40">40</option>
<option value="45">45</option>
<option value="50">50</option>
<option value="55">55</option>
</select>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-lg"></i> Cerrar Ticket</button>
</div>
</form>
</div></div>
</div>

<!-- Modal editar respuesta -->
<div class="modal fade" id="modalEditarRespuesta" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form method="post">
<?=csrfInput()?>
<input type="hidden" name="editar_respuesta" value="1">
<input type="hidden" name="respuesta_id" id="edit_resp_id">
<div class="modal-header">
<h5 class="modal-title"><i class="bi bi-pencil"></i> Editar Respuesta</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label">Mensaje</label>
<textarea name="respuesta_mensaje" id="edit_resp_mensaje" class="form-control" rows="6" required></textarea>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar</button>
</div>
</form>
</div></div>
</div>

<script>
// Editor simple
function formatDoc(cmd) {
    document.execCommand(cmd, false, null);
    document.getElementById('editor').focus();
}
function insertarRR() {
    var sel = document.getElementById('selRR');
    var editor = document.getElementById('editor');
    if (sel.value) {
        editor.innerText = sel.value;
        sel.selectedIndex = 0;
    }
}
// Enviar respuesta
function enviarRespuesta() {
    var editor = document.getElementById('editor');
    var textarea = document.getElementById('txtMensaje');

    // Convertir HTML a texto plano con saltos de línea
    var html = editor.innerHTML;
    var text = html
        .replace(/<div>/gi, '\n')
        .replace(/<\/div>/gi, '')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n')
        .replace(/<p>/gi, '')
        .replace(/<li>/gi, '• ')
        .replace(/<\/li>/gi, '\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&')
        .trim();

    if (!text) {
        alert('El mensaje no puede estar vacío');
        return false;
    }

    textarea.value = text;
    return true;
}
function editarRespuesta(id, mensaje) {
    document.getElementById('edit_resp_id').value = id;
    document.getElementById('edit_resp_mensaje').value = mensaje;
    new bootstrap.Modal(document.getElementById('modalEditarRespuesta')).show();
}
function toggleRespuesta() {
    var f = document.getElementById('formRespuesta');
    var b = document.getElementById('btnMostrarResp');
    if (f.style.display === 'none') {
        f.style.display = 'block';
        b.style.display = 'none';
    } else {
        f.style.display = 'none';
        b.style.display = 'inline-block';
    }
}
function toggleHistorial() {
    var body = document.getElementById('historialBody');
    var btn  = document.getElementById('btnHistorial');
    var icon = document.getElementById('iconHist');
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.className = 'bi bi-chevron-up';
        btn.innerHTML = '<i class="bi bi-chevron-up"></i> Ocultar';
    } else {
        body.style.display = 'none';
        icon.className = 'bi bi-chevron-down';
        btn.innerHTML = '<i class="bi bi-chevron-down"></i> Mostrar (<?=count($historial)?>)';
    }
}
</script>
<?php include 'includes/footer.php'; ?>
