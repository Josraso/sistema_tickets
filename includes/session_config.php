<?php
/**
 * Configuración de sesiones largas
 *
 * Este archivo configura las sesiones para que duren 30 días por defecto.
 * Incluir ANTES de session_start() en cada página.
 *
 * O configurar en .htaccess con:
 * php_value auto_prepend_file "/ruta/completa/includes/session_config.php"
 */

// Configurar duración de sesión: 30 días = 2592000 segundos
$duracion_sesion = 2592000; // 30 días

// Configurar PHP para mantener las sesiones vivas
ini_set('session.gc_maxlifetime', $duracion_sesion);
ini_set('session.cookie_lifetime', $duracion_sesion);

// Configurar parámetros de cookie de sesión
session_set_cookie_params([
    'lifetime' => $duracion_sesion,
    'path' => '/',
    'domain' => '',
    'secure' => false,  // Cambiar a true si usas HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);
