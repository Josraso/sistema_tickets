<?php
function limpiar($data) { return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8'); }
function redirigir($url) { header("Location: $url"); exit; }
function estaLogueado() { return isset($_SESSION['usuario_id']); }
function esAdmin() { return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'; }
function generarTokenCSRF() { if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verificarTokenCSRF($token) { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token); }
function formatearFecha($fecha) { if (empty($fecha)) return '-'; return date('d/m/Y H:i', strtotime($fecha)); }
function e($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
function obtenerConfig($clave, $default = null) {
    static $cache = [];
    if (isset($cache[$clave])) return $cache[$clave];
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $stmt->execute([$clave]);
        $result = $stmt->fetch();
        $cache[$clave] = $result ? $result['valor'] : $default;
        return $cache[$clave];
    } catch (Exception $e) {
        return $default;
    }
}
function registrarLog($accion, $desc = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO logs (usuario_id, accion, descripcion, ip) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['usuario_id'] ?? null, $accion, $desc, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) {}
}
function estadoBadge($estado) {
    $badges = [
        'abierto' => '<span class="badge bg-primary">Abierto</span>',
        'en_proceso' => '<span class="badge bg-warning">En Proceso</span>',
        'terminado' => '<span class="badge bg-success">Terminado</span>',
        'pendiente' => '<span class="badge bg-secondary">Pendiente</span>',
        'activo' => '<span class="badge bg-success">Activo</span>',
        'bloqueado' => '<span class="badge bg-danger">Bloqueado</span>'
    ];
    return $badges[$estado] ?? '<span class="badge bg-secondary">'.$estado.'</span>';
}
function prioridadBadge($prioridad) {
    $badges = [
        'baja' => '<span class="badge bg-secondary">Baja</span>',
        'media' => '<span class="badge bg-info">Media</span>',
        'alta' => '<span class="badge bg-warning">Alta</span>',
        'critica' => '<span class="badge bg-danger">Crítica</span>'
    ];
    return $badges[$prioridad] ?? '<span class="badge bg-secondary">'.$prioridad.'</span>';
}
