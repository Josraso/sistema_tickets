<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — <?=obtenerConfig('empresa_nombre','Sistema de Tickets')?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php $inc = contadorIncidencias(); ?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#343a40;">
<div class="container-fluid">
<a class="navbar-brand" href="index.php"><i class="bi bi-shield-fill"></i> ADMIN</a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navAdmin"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="navAdmin">
<ul class="navbar-nav me-auto">
<li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
<li class="nav-item"><a class="nav-link" href="tickets.php">
<i class="bi bi-ticket"></i> Tickets <?php if($inc>0): ?><span class="badge-inc"><?=$inc?></span><?php endif; ?>
</a></li>
<li class="nav-item"><a class="nav-link" href="clientes.php"><i class="bi bi-people"></i> Clientes</a></li>
<li class="nav-item"><a class="nav-link" href="webs.php"><i class="bi bi-globe"></i> Webs</a></li>
<li class="nav-item"><a class="nav-link" href="tags.php"><i class="bi bi-tag"></i> Tags</a></li>
<li class="nav-item"><a class="nav-link" href="estadisticas.php"><i class="bi bi-bar-chart"></i> Estadísticas</a></li>
<li class="nav-item"><a class="nav-link" href="configuracion.php"><i class="bi bi-gear"></i> Configuración</a></li>
</ul>
<ul class="navbar-nav align-items-center">
<li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <?=htmlspecialchars($_SESSION['usuario_nombre']??'')?> <span class="dropdown-toggle"></span></a>
<ul class="dropdown-menu dropdown-menu-end">
<li><span class="dropdown-header">Admin</span></li>
<li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
</ul></li>
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
