<?php
/**
 * DIAGNÓSTICO DEL SISTEMA
 * Acceder vía navegador para comprobar el estado del servidor.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnóstico — Sistema de Tickets</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
<div class="container mt-4">
<h3><i class="bi bi-bug"></i> Diagnóstico del Sistema</h3>

<!-- PHP -->
<div class="card mb-3"><div class="card-header"><i class="bi bi-code-slash"></i> PHP</div><div class="card-body">
<table class="table table-sm mb-0">
<tr><td>Versión PHP</td><td><?=phpversion()?></td><td><?=version_compare(phpversion(),'7.4','>=') ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Necesita PHP 7.4+</span>'?></td></tr>
<tr><td>upload_max_filesize</td><td><?=ini_get('upload_max_filesize')?></td><td></td></tr>
<tr><td>post_max_size</td><td><?=ini_get('post_max_size')?></td><td></td></tr>
<tr><td>memory_limit</td><td><?=ini_get('memory_limit')?></td><td></td></tr>
</table>
</div></div>

<!-- Extensiones -->
<div class="card mb-3"><div class="card-header"><i class="bi bi-puzzle"></i> Extensiones PHP</div><div class="card-body">
<table class="table table-sm mb-0">
<?php
$exts = ['pdo'=>'Conexión BD', 'pdo_mysql'=>'MySQL', 'session'=>'Sesiones', 'json'=>'JSON', 'imap'=>'IMAP (pipe)', 'openssl'=>'SSL'];
foreach ($exts as $ext => $desc):
$ok = extension_loaded($ext);
?>
<tr>
<td><?=$desc?> (<code><?=$ext?></code>)</td>
<td><?=$ok ? '<span class="badge bg-success">Instalada</span>' : '<span class="badge bg-warning">No instalada</span>'?></td>
</tr>
<?php endforeach; ?>
</table>
</div></div>

<!-- Permisos -->
<div class="card mb-3"><div class="card-header"><i class="bi bi-folder"></i> Permisos de Escritura</div><div class="card-body">
<table class="table table-sm mb-0">
<?php
$dirs = ['.'=>'Raíz', 'uploads'=>'Subida archivos', 'logs'=>'Logs'];
foreach ($dirs as $dir => $desc):
$exists = is_dir(__DIR__.'/'.$dir);
$writable = $exists && is_writable(__DIR__.'/'.$dir);
?>
<tr>
<td><code><?=$dir?>/</code> — <?=$desc?></td>
<td>
<?php if (!$exists): ?><span class="badge bg-danger">No existe</span>
<?php elseif ($writable): ?><span class="badge bg-success">Escribible</span>
<?php else: ?><span class="badge bg-danger">Solo lectura</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
</div></div>

<!-- Archivos -->
<div class="card mb-3"><div class="card-header"><i class="bi bi-file-code"></i> Archivos Clave</div><div class="card-body">
<table class="table table-sm mb-0">
<?php
$files = [
    'config.php'=>'Configuración', 'includes/database.php'=>'Base datos',
    'includes/funciones.php'=>'Funciones', 'includes/email.php'=>'Email',
    'install.php'=>'Instalador', 'pipe.php'=>'Pipe email',
    'imap_poll.php'=>'IMAP polling'
];
foreach ($files as $f => $desc):
$exists = file_exists(__DIR__.'/'.$f);
?>
<tr><td><code><?=$f?></code> — <?=$desc?></td><td><?=$exists ? '<span class="badge bg-success">Existe</span>' : '<span class="badge bg-secondary">No existe</span>'?></td></tr>
<?php endforeach; ?>
</table>
</div></div>

<!-- Conexión MySQL -->
<div class="card mb-3"><div class="card-header"><i class="bi bi-database"></i> Conexión MySQL</div><div class="card-body">
<?php
if (file_exists(__DIR__.'/config.php')) {
    try {
        require_once __DIR__.'/config.php';
        $db = getDB();
        $v = $db->query("SELECT VERSION() as v")->fetch()['v'];
        echo '<span class="badge bg-success">Conectado</span> — MySQL/MariaDB <strong>'.$v.'</strong>';
    } catch (Exception $e) {
        echo '<span class="badge bg-danger">Error</span> — '.$e->getMessage();
    }
} else {
    echo '<span class="badge bg-secondary">config.php no existe</span> — Ejecuta el instalador primero';
}
?>
</div></div>

<!-- Logs recientes -->
<div class="card"><div class="card-header"><i class="bi bi-journal-text"></i> Logs Recientes</div><div class="card-body">
<?php
$logfiles = ['logs/pipe.log', 'logs/imap_poll.log'];
foreach ($logfiles as $lf) {
    if (file_exists(__DIR__.'/'.$lf)) {
        $content = file_get_contents(__DIR__.'/'.$lf);
        $lines = explode("\n", trim($content));
        $recent = array_slice($lines, -10);
        echo "<h6><code>$lf</code></h6><pre class='bg-light p-2' style='font-size:0.8rem;'>" . htmlspecialchars(implode("\n",$recent)) . "</pre>";
    }
}
if (!file_exists(__DIR__.'/logs/pipe.log') && !file_exists(__DIR__.'/logs/imap_poll.log')) {
    echo '<p class="text-muted">Sin logs de pipe/imap aún</p>';
}
?>
</div></div>

</div><!-- /container -->
</body>
</html>
