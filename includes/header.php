<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=obtenerConfig('empresa_nombre','Sistema de Tickets')?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?=($_SESSION['impersonando']??false) ? '../assets/style.css' : 'assets/style.css'?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
<div class="container-fluid">
<a class="navbar-brand" href="dashboard.php"><i class="bi bi-ticket"></i> <?=obtenerConfig('empresa_nombre','Sistema Tickets')?></a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navCliente"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="navCliente">
<ul class="navbar-nav me-auto">
<li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Inicio</a></li>
<li class="nav-item"><a class="nav-link" href="tickets.php"><i class="bi bi-ticket"></i> Mis Tickets</a></li>
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
