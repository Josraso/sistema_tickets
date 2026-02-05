<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF();
    $tab = $_POST['tab'] ?? 'general';

    if ($tab === 'general') {
        guardarConfig('empresa_nombre', limpiar($_POST['empresa_nombre'] ?? 'Sistema de Tickets'));
        guardarConfig('dominio_base', limpiar($_POST['dominio_base'] ?? ''));
        guardarConfig('dominio_mail', limpiar($_POST['dominio_mail'] ?? ''));
        guardarConfig('max_subida', (int)($_POST['max_subida'] ?? 30));
        guardarConfig('registro_activo', isset($_POST['registro_activo']) ? '1' : '0');
        $success = 'Configuración general guardada';
    } elseif ($tab === 'smtp') {
        guardarConfig('smtp_host', limpiar($_POST['smtp_host'] ?? ''));
        guardarConfig('smtp_port', (int)($_POST['smtp_port'] ?? 587));
        guardarConfig('smtp_user', limpiar($_POST['smtp_user'] ?? ''));
        guardarConfig('smtp_pass', $_POST['smtp_pass'] ?? '');
        guardarConfig('smtp_from', limpiar($_POST['smtp_from'] ?? ''));
        $success = 'Configuración SMTP guardada';
    } elseif ($tab === 'plantillas') {
        $tipos = ['ticket_creado','respuesta_admin','ticket_cerrado','incidencia','nuevo_ticket_admin'];
        foreach ($tipos as $t) {
            if (isset($_POST[$t])) guardarConfig('plantilla_' . $t, $_POST[$t]);
        }
        $success = 'Plantillas guardadas';
    }
    registrarLog('config_actualizada', $success);
}

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
<h4><i class="bi bi-gear"></i> Configuración</h4>
<?php if ($success): ?><div class="alert alert-success alert-dismissible mb-0 py-1"><i class="bi bi-check-circle"></i> <?=e($success)?></div><?php endif; ?>
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
<div class="col-md-6 mb-3"><label class="form-label">Puerto</label>
<input type="number" name="smtp_port" class="form-control" value="<?=e(obtenerConfig('smtp_port','587'))?>"></div>
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
</div></div>
</div>

<!-- Plantillas -->
<div class="tab-pane fade" id="panelPlant">
<div class="card"><div class="card-body">
<form method="post">
<?=csrfInput()?><input type="hidden" name="tab" value="plantillas">
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> <strong>Variables disponibles:</strong>
{{id}} {{asunto}} {{mensaje}} {{web}} {{prioridad}} {{cliente}} {{respuesta}} {{url}} {{url_admin}} {{empresa}}</div>
<?php $tipos = ['ticket_creado'=>'Ticket creado (al cliente)','respuesta_admin'=>'Respuesta admin (al cliente)','ticket_cerrado'=>'Ticket cerrado (al cliente)','incidencia'=>'Incidencia reportada (al admin)','nuevo_ticket_admin'=>'Nuevo ticket (al admin)']; ?>
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

<!-- PIPE info -->
<div class="tab-pane fade" id="panelPIPE">
<div class="card"><div class="card-body">
<h5><i class="bi bi-envelope-arrow-in"></i> Configuración PIPE — Recepción de emails</h5>
<div class="alert alert-info">
<p><strong>Sistema PIPE:</strong> Cuando un cliente responde al email <code>ticket+ID@<?=e(obtenerConfig('dominio_mail','tudominio.com'))?></code>, la respuesta se añade automáticamente al ticket.</p>
<p><strong>Método 1 — Pipe del servidor (recomendado):</strong></p>
<ol>
<li>En tu servidor, edita <code>/etc/aliases</code> o el fichero de alias de tu MTA (Postfix, Exim…)</li>
<li>Añade la línea:<br><code>default: |"/usr/bin/php /var/www/html/pipe.php"</code></li>
<li>Haz un <code>newaliases</code> para recargar</li>
</ol>
<p><strong>Método 2 — IMAP Polling (cron):</strong></p>
<ol>
<li>Configura una cuenta IMAP/email dedicada para recibir los tickets</li>
<li>Añade esta línea a tu cron:<br><code>* * * * * php /var/www/html/imap_poll.php</code></li>
<li>Edita <code>imap_poll.php</code> con las credenciales IMAP</li>
</ol>
</div>
<h6><i class="bi bi-file-code"></i> Archivos necesarios en el servidor:</h6>
<ul>
<li><code>pipe.php</code> — Procesa emails recibidos por pipe del servidor</li>
<li><code>imap_poll.php</code> — Polling IMAP vía cron</li>
</ul>
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
</script>
<?php include 'includes/footer.php'; ?>
