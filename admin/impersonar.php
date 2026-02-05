<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();
$cid = (int)($_GET['id'] ?? 0);

$st = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'cliente' AND estado = 'activo'");
$st->execute([$cid]); $cliente = $st->fetch();

if (!$cliente) { redirigir('clientes.php'); }

// Guardar sesión admin original
$_SESSION['admin_original_id'] = $_SESSION['usuario_id'];
$_SESSION['admin_original_nombre'] = $_SESSION['usuario_nombre'];
$_SESSION['admin_original_rol'] = $_SESSION['rol'];

// Cambiar a sesión del cliente
$_SESSION['usuario_id'] = $cliente['id'];
$_SESSION['usuario_nombre'] = $cliente['nombre'];
$_SESSION['rol'] = 'cliente';
$_SESSION['impersonando'] = true;

registrarLog('impersonar', "Admin impersona cliente #{$cliente['id']}: {$cliente['nombre']}");

redirigir('../dashboard.php');
