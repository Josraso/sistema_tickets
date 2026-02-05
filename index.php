<?php
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}
require_once 'config.php';
session_start();
if (!estaLogueado()) {
    redirigir('login.php');
}
if (esAdmin() && !estaImpersonando()) {
    redirigir('admin/');
} else {
    redirigir('dashboard.php');
}
