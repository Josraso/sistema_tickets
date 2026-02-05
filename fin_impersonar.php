<?php
require_once 'config.php';
session_start();
if (!estaLogueado() || !isset($_SESSION['impersonando'])) redirigir('index.php');

// Restaurar sesión admin
$_SESSION['usuario_id'] = $_SESSION['admin_original_id'];
$_SESSION['usuario_nombre'] = $_SESSION['admin_original_nombre'];
$_SESSION['rol'] = $_SESSION['admin_original_rol'];
unset($_SESSION['impersonando']);
unset($_SESSION['admin_original_id']);
unset($_SESSION['admin_original_nombre']);
unset($_SESSION['admin_original_rol']);

registrarLog('fin_impersonar', 'Admin volvió a su sesión');

redirigir('admin/');
