<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $tab = $_POST['tab'] ?? 'general';

    if ($tab === 'general') {
        guardarConfig('empresa_nombre', limpiar($_POST['empresa_nombre'] ?? 'Sistema de Tickets'));
        guardarConfig('dominio_base', limpiar($_POST['dominio_base'] ?? ''));
        guardarConfig('dominio_mail', limpiar($_POST['dominio_mail'] ?? ''));
        guardarConfig('max_subida', (int)($_POST['max_subida'] ?? 30));
        guardarConfig('registro_activo', isset($_POST['registro_activo']) ? '1' : '0');
        guardarConfig('asignado_por_defecto', (string)(int)($_POST['asignado_por_defecto'] ?? 0));
        $success = 'Configuración general guardada';
    } elseif ($tab === 'smtp') {
        guardarConfig('smtp_host', limpiar($_POST['smtp_host'] ?? ''));
        guardarConfig('smtp_port', (int)($_POST['smtp_port'] ?? 587));
        guardarConfig('smtp_security', limpiar($_POST['smtp_security'] ?? 'starttls'));
        guardarConfig('smtp_user', limpiar($_POST['smtp_user'] ?? ''));
        guardarConfig('smtp_pass', $_POST['smtp_pass'] ?? '');
        guardarConfig('smtp_from', limpiar($_POST['smtp_from'] ?? ''));
        $success = 'Configuración SMTP guardada';
    } elseif ($tab === 'test_smtp') {
        $test_para = limpiar($_POST['test_email'] ?? '');
        if (!empty($test_para)) {
            $err = '';
            $ok = enviarEmail($test_para, 'Test SMTP — ' . obtenerConfig('empresa_nombre','Sistema de Tickets'), '<h3 style="color:#28a745;">&#10003; Test SMTP Exitoso</h3><p>Este email fue enviado desde el Sistema de Tickets para verificar la configuración SMTP.</p><p><em>Fecha: ' . date('d/m/Y H:i') . '</em></p>', $err);
            if ($ok) {
                $success = 'Email de prueba enviado correctamente a ' . $test_para;
            } else {
                $error = 'Error al enviar email: ' . ($err ?: 'desconocido');
            }
        }
    } elseif ($tab === 'plantillas') {
        $tipos = ['ticket_creado','respuesta_admin','ticket_cerrado','incidencia','nuevo_ticket_admin','ticket_en_proceso'];
        foreach ($tipos as $t) {
            if (isset($_POST[$t])) guardarConfig('plantilla_' . $t, $_POST[$t]);
        }
        $success = 'Plantillas guardadas';
    } elseif ($tab === 'pipe_imap') {
        guardarConfig('imap_host', limpiar($_POST['imap_host'] ?? ''));
        guardarConfig('imap_port', (int)($_POST['imap_port'] ?? 993));
        guardarConfig('imap_security', limpiar($_POST['imap_security'] ?? 'ssl'));
        guardarConfig('imap_user', limpiar($_POST['imap_user'] ?? ''));
        guardarConfig('imap_pass', $_POST['imap_pass'] ?? '');
        guardarConfig('imap_mailbox', limpiar($_POST['imap_mailbox'] ?? 'INBOX'));
        $success = 'Configuración IMAP guardada';
    }
    registrarLog('config_actualizada', $success);
}

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-gear"></i> Configuración</h4>
<?php if ($success): ?><div class="alert alert-success alert-dismissible mb-0 py-1"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible mb-0 py-1"><i class="bi bi-exclamation-circle"></i> <?=e($error)?></div><?php endif; ?>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3">
<li class="nav-item"><a class="nav-link active" id="tabGeneral" data-bs-toggle="tab" href="#panelGeneral">General</a></li>
<li class="nav-item"><a class="nav-link" id="tabSMTP" data-bs-toggle="tab" href="#panelSMTP">SMTP / Correo</a></li>
<li class="nav-item"><a class="nav-link" id="tabPlant" data-bs-toggle="tab" href="#panelPlant">Plantillas Email</a></li>
<li class="nav-item"><a class="nav-link" id="tabPIPE" data-bs-toggle="tab" href="#panelPIPE">PIPE / Recepción</a></li>
</ul>

<div class="tab-content">
<!-- General -->
<div class="tab-pane fade show active" id="panelGeneral">
<div class="card"><div class="card-body">
<form method="post">
<?=csrfInput()?><input type="hidden" name="tab" value="general">
<div class="row">
<div class="col-md-6 mb-3"><label class="form-label"><i class="bi bi-building"></i> Nombre empresa</label>
<input type="text" name="empresa_nombre" class="form-control" value="<?=e(obtenerConfig('empresa_nombre','Sistema de Tickets'))?>"></div>
<div class="col-md-6 mb-3"><label class="form-label"><i class="bi bi-globe"></i> Dominio base (URL)</label>
<input type="text" name="dominio_base" class="form-control" placeholder="https://tudominio.com" value="<?=e(obtenerConfig('dominio_base',''))?>"></div>
<div class="col-md-6 mb-3"><label class="form-label"><i class="bi bi-envelope"></i> Dominio mail (para PIPE)</label>
<input type="text" name="dominio_mail" class="form-control" placeholder="tudominio.com" value="<?=e(obtenerConfig('dominio_mail',''))?>">
<div class="form-text">Ej: si pones aquí <strong>soporte.com</strong>, los emails serán <strong>ticket+123@soporte.com</strong></div></div>
<div class="col-md-6 mb-3"><label class="form-label"><i class="bi bi-hdd"></i> Máximo subida archivos (MB)</label>
<input type="number" name="max_subida" class="form-control" min="1" max="500" value="<?=e(obtenerConfig('max_subida','30'))?>"></div>
<div class="col-12 mb-3">
<div class="form-check form-switch">
<input class="form-check-input" type="checkbox" id="chkRegistro" name="registro_activo" <?=obtenerConfig('registro_activo','1')==='1'?'checked':'';?>>
<label class="form-check-label" for="chkRegistro"><i class="bi bi-person-plus"></i> Permitir registro libre de clientes</label>
</div>
</div>
<div class="col-12 mb-3">
<label class="form-label"><i class="bi bi-person-plus"></i> Asignar tickets por defecto a</label>
<select name="asignado_por_defecto" class="form-select">
<option value="0" <?=obtenerConfig('asignado_por_defecto','0')==='0'?'selected':'';?>>Sin asignar</option>
<?php foreach ($db->query("SELECT * FROM usuarios WHERE rol = 'admin' ORDER BY nombre")->fetchAll() as $adm): ?>
<option value="<?=$adm['id']?>" <?=obtenerConfig('asignado_por_defecto','0')===(string)$adm['id']?'selected':'';?>><?=e($adm['nombre'])?> (<?=e($adm['email'])?>)</option>
<?php endforeach; ?>
</select>
<div class="form-text">Cuando se cree un ticket nuevo se asignará automáticamente a esta persona</div>
</div>
</div>
<button type="submit" class="btn btn-primary"><i class="bi bi-floppy-disk"></i> Guardar</button>
</form>
</div></div>
</div>

<!-- SMTP -->
<div class="tab-pane fade" id="panelSMTP">
<div class="card"><div class="card-body">
<form method="post">
<?=csrfInput()?><input type="hidden" name="tab" value="smtp">
<div class="row">
<div class="col-md-6 mb-3"><label class="form-label">Servidor SMTP (Host)</label>
<input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?=e(obtenerConfig('smtp_host',''))?>"></div>
<div class="col-md-6 mb-3"><label class="form-label">Cifrado / Seguridad</label>
<select name="smtp_security" class="form-select" id="smtp_security">
<option value="none"     <?=obtenerConfig('smtp_security','starttls')==='none'    ?'selected':'';?>>Sin cifrado (puerto 25)</option>
<option value="starttls" <?=obtenerConfig('smtp_security','starttls')==='starttls'?'selected':'';?>>STARTTLS (puerto 587)</option>
<option value="ssl"      <?=obtenerConfig('smtp_security','starttls')==='ssl'     ?'selected':'';?>>SSL/TLS (puerto 465)</option>
</select>
<div class="form-text">STARTTLS es la más común. SSL/TLS para proveedores que requieren conexión cifrada desde el inicio.</div></div>
<div class="col-md-6 mb-3"><label class="form-label">Puerto</label>
<input type="number" name="smtp_port" class="form-control" id="smtp_port" value="<?=e(obtenerConfig('smtp_port','587'))?>"></div>
<div class="col-md-6 mb-3"><label class="form-label">Usuario SMTP</label>
<input type="email" name="smtp_user" class="form-control" placeholder="tu@gmail.com" value="<?=e(obtenerConfig('smtp_user',''))?>"></div>
<div class="col-md-6 mb-3"><label class="form-label">Contraseña SMTP</label>
<input type="password" name="smtp_pass" class="form-control" value="<?=e(obtenerConfig('smtp_pass',''))?>">
<div class="form-text">Para Gmail necesitas generar un <strong>app password</strong> en tu cuenta Google.</div></div>
<div class="col-md-6 mb-3"><label class="form-label">Email remitente (From)</label>
<input type="email" name="smtp_from" class="form-control" placeholder="soporte@empresa.com" value="<?=e(obtenerConfig('smtp_from',''))?>"></div>
</div>
<button type="submit" class="btn btn-primary"><i class="bi bi-floppy-disk"></i> Guardar</button>
</form>
<hr class="mt-4">
<h6><i class="bi bi-send"></i> Probar configuración SMTP</h6>
<p class="text-muted small">Guarda la configuración primero, luego envía un email de prueba.</p>
<form method="post" class="d-flex gap-2">
<?=csrfInput()?><input type="hidden" name="tab" value="test_smtp">
<input type="email" name="test_email" class="form-control form-control-sm" placeholder="email@prueba.com" required>
<button type="submit" class="btn btn-outline-primary btn-sm text-nowrap"><i class="bi bi-send"></i> Enviar Prueba</button>
</form>
</div></div>
</div>

<!-- Plantillas -->
<div class="tab-pane fade" id="panelPlant">
<div class="card"><div class="card-body">
<form method="post">
<?=csrfInput()?><input type="hidden" name="tab" value="plantillas">
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> <strong>Variables disponibles:</strong>
{{id}} {{asunto}} {{mensaje}} {{web}} {{prioridad}} {{cliente}} {{respuesta}} {{url}} {{url_admin}} {{empresa}}</div>
<?php $tipos = ['ticket_creado'=>'Ticket creado (al cliente)','respuesta_admin'=>'Respuesta admin (al cliente)','ticket_cerrado'=>'Ticket cerrado (al cliente)','ticket_en_proceso'=>'Ticket en proceso (al cliente)','incidencia'=>'Incidencia reportada (al admin)','nuevo_ticket_admin'=>'Nuevo ticket (al admin)']; ?>
<?php foreach ($tipos as $tipo => $label): ?>
<div class="mb-4">
<label class="form-label"><i class="bi bi-envelope"></i> <?=e($label)?></label>
<textarea name="<?=$tipo?>" class="form-control" rows="5" style="font-family:monospace;font-size:0.85rem;"><?=e(obtenerPlantilla($tipo))?></textarea>
</div>
<?php endforeach; ?>
<button type="submit" class="btn btn-primary"><i class="bi bi-floppy-disk"></i> Guardar Plantillas</button>
</form>
</div></div>
</div>

<!-- PIPE / IMAP -->
<div class="tab-pane fade" id="panelPIPE">
<div class="card"><div class="card-body">
<h5><i class="bi bi-envelope-arrow-in"></i> Recepción de emails (IMAP Polling)</h5>
<div class="alert alert-info small mb-3"><i class="bi bi-info-circle"></i>
Cuando un cliente responde al email <strong>ticket+ID@<?=e(obtenerConfig('dominio_mail','tudominio.com'))?></strong>, la respuesta se añade automáticamente al ticket correspondiente.</div>

<h6><i class="bi bi-envelope"></i> Configuración IMAP</h6>
<p class="text-muted small">Crea una cuenta de correo en Plesk (ej: <em>soporte@tudominio.com</em>) y pon aquí sus datos IMAP. El script se ejecutará cada minuto vía cron y leerá los emails de esa bandeja.</p>
<form method="post"><?=csrfInput()?><input type="hidden" name="tab" value="pipe_imap">
<div class="row">
<div class="col-md-6 mb-3"><label class="form-label">Servidor IMAP (Host)</label>
<input type="text" name="imap_host" class="form-control" placeholder="mail.tudominio.com" value="<?=e(obtenerConfig('imap_host',''))?>"></div>
<div class="col-md-6 mb-3"><label class="form-label">Cifrado</label>
<select name="imap_security" class="form-select" id="imap_security">
<option value="ssl"  <?=obtenerConfig('imap_security','ssl')==='ssl' ?'selected':'';?>>SSL (puerto 993)</option>
<option value="none" <?=obtenerConfig('imap_security','ssl')==='none'?'selected':'';?>>Sin cifrado (puerto 143)</option>
</select></div>
<div class="col-md-6 mb-3"><label class="form-label">Puerto</label>
<input type="number" name="imap_port" class="form-control" id="imap_port" value="<?=e(obtenerConfig('imap_port','993'))?>"></div>
<div class="col-md-6 mb-3"><label class="form-label">Bandeja</label>
<input type="text" name="imap_mailbox" class="form-control" value="<?=e(obtenerConfig('imap_mailbox','INBOX'))?>"></div>
<div class="col-md-6 mb-3"><label class="form-label">Usuario (email de la cuenta IMAP)</label>
<input type="email" name="imap_user" class="form-control" placeholder="soporte@tudominio.com" value="<?=e(obtenerConfig('imap_user',''))?>"></div>
<div class="col-md-6 mb-3"><label class="form-label">Contraseña</label>
<input type="password" name="imap_pass" class="form-control" value="<?=e(obtenerConfig('imap_pass',''))?>"></div>
</div>
<button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-floppy-disk"></i> Guardar IMAP</button>
</form>

<hr>
<h6><i class="bi bi-clock-repeat"></i> Cron en Plesk</h6>
<p class="text-muted small">Ve a <strong>Plesk → Dominios → [tu dominio] → Programador de tareas</strong> y añade una tarea nueva con frecuencia <strong>cada 1 minuto</strong>:</p>
<div class="bg-light border rounded p-2 mb-2"><code class="small">php /var/www/vhost/[tudominio]/docroot/imap_poll.php</code></div>
<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> La ruta <code>/var/www/vhost/[tudominio]/docroot/</code> es un ejemplo. Comprueba la ruta real de tu dominio en Plesk → Info del dominio.</div>
</div></div>
</div>
</div><!-- /tab-content -->

<script>
// Conservar tab activo en recarga
document.querySelectorAll('.nav-link[data-bs-toggle="tab"]').forEach(function(el) {
    el.addEventListener('shown.bs.tab', function(e) {
        sessionStorage.setItem('configTab', e.target.getAttribute('href'));
    });
});
var savedTab = sessionStorage.getItem('configTab');
if (savedTab) {
    var tabEl = document.querySelector('a[href="' + savedTab + '"]');
    if (tabEl) { var tab = new bootstrap.Tab(tabEl); tab.show(); }
}
// Auto-cambiar puerto al seleccionar tipo de cifrado SMTP
var selSec = document.getElementById('smtp_security');
if (selSec) {
    selSec.addEventListener('change', function() {
        var puertos = {none:'25', starttls:'587', ssl:'465'};
        document.getElementById('smtp_port').value = puertos[this.value] || '587';
    });
}
// Auto-cambiar puerto al seleccionar cifrado IMAP
var selImapSec = document.getElementById('imap_security');
if (selImapSec) {
    selImapSec.addEventListener('change', function() {
        var puertos = {ssl:'993', none:'143'};
        document.getElementById('imap_port').value = puertos[this.value] || '993';
    });
}
</script>
<?php include 'includes/footer.php'; ?>
