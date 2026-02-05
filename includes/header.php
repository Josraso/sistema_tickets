<?php migraciones(); $badge_resp_cli = contadorRespuestasNuevasCliente($_SESSION['usuario_id']); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=obtenerConfig('empresa_nombre','Sistema de Tickets')?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?=($_SESSION['impersonando']??false) ? '../assets/style.css' : 'assets/style.css'?>">
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="/manifest.json">
<script>
/* PWA — Service Worker */
if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/service-worker.js').catch(function(){}); }
/* Notificaciones por polling */
(function() {
    var shown = {}, ready = false;
    function poll() {
        fetch('/check_notif.php', { credentials: 'include' })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (!d || !d.notifs) { ready = true; return; }
                d.notifs.forEach(function(n) {
                    if (!ready) { shown[n.id] = true; return; } // baseline inicial sin notificar
                    if (shown[n.id]) return;
                    shown[n.id] = true;
                    if ('Notification' in window && Notification.permission === 'granted') {
                        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                            navigator.serviceWorker.controller.postMessage({ type: 'SHOW_NOTIFICATION', title: n.titulo, body: n.cuerpo, url: n.url });
                        } else {
                            var nt = new Notification(n.titulo, { body: n.cuerpo, icon: '/assets/icon-192.png' });
                            nt.onclick = function(e) { e.preventDefault(); window.open(n.url); };
                        }
                    }
                });
                ready = true;
            }).catch(function(){});
    }
    if ('Notification' in window && Notification.permission === 'default') {
        setTimeout(function() { Notification.requestPermission(); }, 5000);
    }
    setInterval(poll, 30000);
    poll();
})();
</script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
<div class="container-fluid">
<a class="navbar-brand" href="dashboard.php"><i class="bi bi-ticket"></i> <?=obtenerConfig('empresa_nombre','Sistema Tickets')?></a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navCliente"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="navCliente">
<ul class="navbar-nav me-auto">
<li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Inicio</a></li>
<li class="nav-item"><a class="nav-link" href="tickets.php"><i class="bi bi-ticket"></i> Mis Tickets <?php if($badge_resp_cli>0): ?><span class="badge-resp"><?=$badge_resp_cli?></span><?php endif; ?></a></li>
<li class="nav-item"><a class="nav-link" href="webs.php"><i class="bi bi-globe"></i> Mis Webs</a></li>
</ul>
<ul class="navbar-nav align-items-center">
<?php if ($_SESSION['impersonando'] ?? false): ?>
<li class="nav-item"><span class="badge bg-warning text-dark me-2"><i class="bi bi-person-fill-exclamation"></i> Impersonando: <?=htmlspecialchars($_SESSION['usuario_nombre'])?></span></li>
<li class="nav-item"><a class="nav-link" href="fin_impersonar.php"><i class="bi bi-arrow-bar-left"></i> Volver Admin</a></li>
<?php else: ?>
<li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <?=htmlspecialchars($_SESSION['usuario_nombre']??'')?> <span class="dropdown-toggle"></span></a>
<ul class="dropdown-menu dropdown-menu-end">
<li><span class="dropdown-header"><?=htmlspecialchars($_SESSION['usuario_nombre']??'')?></span></li>
<li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person-circle"></i> Mi Perfil</a></li>
<li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
</ul></li>
<?php endif; ?>
</ul>
</div>
</div>
</nav>
<!-- Visor imágenes -->
<div class="visor-overlay" id="visorImg" onclick="cerrarVisor()">
<span class="visor-cerrar">&times;</span>
<img src="" id="visorImgSrc" alt="Vista previa">
</div>
<div class="container-fluid mt-4">
