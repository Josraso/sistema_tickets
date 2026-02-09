<?php
require_once 'config.php';
require_once __DIR__ . "/includes/session_config.php";
if (!estaLogueado()) redirigir('login.php');
if (esAdmin() && !estaImpersonando()) redirigir('admin/');

$db = getDB();
$uid = $_SESSION['usuario_id'];
$st = $db->prepare("SELECT * FROM webs WHERE usuario_id = ? ORDER BY nombre"); $st->execute([$uid]); $webs = $st->fetchAll();
if (empty($webs)) { redirigir('webs.php'); }
$todos_tags = $db->query("SELECT * FROM tags ORDER BY nombre")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $web_id = (int)($_POST['web_id'] ?? 0);
    $asunto = limpiar($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $prioridad = $_POST['prioridad'] ?? 'media';
    $tags_sel = $_POST['tags'] ?? [];

    if (empty($asunto) || empty($mensaje)) { $error = 'Asunto y mensaje son obligatorios'; }
    elseif ($web_id === 0) { $error = 'Selecciona una web'; }
    else {
        $db->prepare("INSERT INTO tickets (usuario_id, web_id, asunto, mensaje, prioridad) VALUES (?, ?, ?, ?, ?)")
           ->execute([$uid, $web_id, $asunto, $mensaje, $prioridad]);
        $tid = $db->lastInsertId();
        // Tags
        foreach ($tags_sel as $tag_id) {
            $tag_id = (int)$tag_id;
            if ($tag_id > 0) $db->prepare("INSERT IGNORE INTO ticket_tags (ticket_id, tag_id) VALUES (?, ?)")->execute([$tid, $tag_id]);
        }
        // Archivos
        if (isset($_FILES['archivos'])) {
            foreach ($_FILES['archivos']['tmp_name'] as $i => $tmp) {
                if ($_FILES['archivos']['error'][$i] === UPLOAD_ERR_OK && $_FILES['archivos']['size'][$i] > 0) {
                    uploadArchivo([
                        'tmp_name' => $_FILES['archivos']['tmp_name'][$i],
                        'name'     => $_FILES['archivos']['name'][$i],
                        'size'     => $_FILES['archivos']['size'][$i],
                        'error'    => $_FILES['archivos']['error'][$i]
                    ], $tid);
                }
            }
        }
        // Asignar por defecto
        $default_asign = (int)obtenerConfig('asignado_por_defecto', 0);
        if ($default_asign > 0) {
            $db->prepare("UPDATE tickets SET asignado_a = ? WHERE id = ?")->execute([$default_asign, $tid]);
            registrarHistorial($tid, 'Asignación automática', "Asignado por defecto");
        }
        registrarHistorial($tid, 'Ticket creado', "Creado por " . $_SESSION['usuario_nombre']);
        registrarLog('ticket_creado', "Ticket #$tid");
        // Email
        $st2 = $db->prepare("SELECT * FROM tickets WHERE id = ?"); $st2->execute([$tid]); $nuevo = $st2->fetch();
        emailTicketCreado($nuevo);
        redirigir("ver_ticket.php?id=$tid");
    }
}
include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-ticket-fill"></i> Nuevo Ticket</h4>
<a href="tickets.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?=e($error)?></div><?php endif; ?>
<div class="card"><div class="card-body">
<form method="post" enctype="multipart/form-data">
<?=csrfInput()?>
<div class="mb-3"><label class="form-label"><i class="bi bi-globe"></i> Web *</label>
<select name="web_id" class="form-select" required>
<option value="">Selecciona una web...</option>
<?php foreach ($webs as $w): ?><option value="<?=$w['id']?>"><?=e($w['nombre'])?> — <?=e($w['dominio'])?></option><?php endforeach; ?>
</select></div>
<div class="mb-3"><label class="form-label"><i class="bi bi-chat-text"></i> Asunto *</label>
<input type="text" name="asunto" class="form-control" required placeholder="Describe brevemente el problema"></div>
<div class="mb-3"><label class="form-label"><i class="bi bi-text-paragraph"></i> Mensaje *</label>
<textarea name="mensaje" class="form-control" rows="6" required placeholder="Describe el problema con detalle..."></textarea></div>
<div class="row">
<div class="col-md-6 mb-3"><label class="form-label"><i class="bi bi-exclamation-circle"></i> Prioridad</label>
<select name="prioridad" class="form-select">
<option value="baja">Baja</option><option value="media" selected>Media</option><option value="alta">Alta</option><option value="critica">Crítica</option>
</select></div>
<div class="col-md-6 mb-3"><label class="form-label"><i class="bi bi-tag"></i> Tags</label>
<select name="tags[]" class="form-select" multiple>
<?php foreach ($todos_tags as $tag): ?><option value="<?=$tag['id']?>" style="background:<?=e($tag['color'])?>;color:<?=colorContraste($tag['color'])?>"><?=e($tag['nombre'])?></option><?php endforeach; ?>
</select><div class="form-text small">Ctrl+clic para seleccionar varios</div></div>
</div>
<?=renderArchivoUpload('archivos', true)?>
<button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Crear Ticket</button>
<a href="dashboard.php" class="btn btn-secondary ms-2">Cancelar</a>
</form>
</div></div>
<?php include 'includes/footer.php'; ?>
