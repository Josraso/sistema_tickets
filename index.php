<?php
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require_once 'config.php';
session_start();

if (estaLogueado()) {
    header('Location: ' . (esAdmin() ? 'admin/' : 'dashboard.php'));
} else {
    header('Location: login.php');
}
exit;
