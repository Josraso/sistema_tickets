<?php
/**
 * Configuración de sesiones largas
 *
 * Este archivo configura las sesiones para que duren 30 días por defecto
 * y luego inicia la sesión automáticamente.
 *
 * REEMPLAZA a session_start() - usar require_once 'includes/session_config.php'
 */

// Solo configurar si la sesión NO está activa
if (session_status() === PHP_SESSION_NONE) {
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

    // Iniciar sesión con la configuración aplicada
    session_start();
}
