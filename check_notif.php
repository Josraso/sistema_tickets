<?php
/* Endpoint de polling de notificaciones — devuelve JSON con eventos no leídos */
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!estaLogueado()) {
    echo json_encode(['notifs' => []]);
    exit;
}

$db   = getDB();
$uid  = $_SESSION['usuario_id'];
$rol  = $_SESSION['rol'] ?? 'cliente';
$base = rtrim(obtenerConfig('dominio_base', ''), '/');
$notifs = [];

if ($rol === 'admin') {
    /* Respuestas no leídas por admin (de clientes) */
    $stmt = $db->prepare(
        "SELECT r.id, r.ticket_id, t.asunto, r.mensaje
         FROM respuestas r
         JOIN tickets t ON r.ticket_id = t.id
         WHERE r.leido_admin = 0 AND r.usuario_id != ?
         ORDER BY r.fecha_creacion DESC
         LIMIT 5"
    );
    $stmt->execute([$uid]);
    foreach ($stmt->fetchAll() as $r) {
        $notifs[] = [
            'id'     => 'resp_' . $r['id'],
            'titulo' => 'Respuesta nueva — #' . $r['ticket_id'] . ' ' . $r['asunto'],
            'cuerpo' => mb_substr(strip_tags($r['mensaje']), 0, 80),
            'url'    => $base . '/admin/ver_ticket.php?id=' . $r['ticket_id']
        ];
    }
} else {
    /* Respuestas no leídas por cliente (del admin) */
    $stmt = $db->prepare(
        "SELECT r.id, r.ticket_id, t.asunto, r.mensaje
         FROM respuestas r
         JOIN tickets t ON r.ticket_id = t.id
         WHERE t.usuario_id = ? AND r.leido_cliente = 0 AND r.usuario_id != ?
         ORDER BY r.fecha_creacion DESC
         LIMIT 5"
    );
    $stmt->execute([$uid, $uid]);
    foreach ($stmt->fetchAll() as $r) {
        $notifs[] = [
            'id'     => 'resp_' . $r['id'],
            'titulo' => 'Respuesta nueva — #' . $r['ticket_id'],
            'cuerpo' => mb_substr(strip_tags($r['mensaje']), 0, 80),
            'url'    => $base . '/ver_ticket.php?id=' . $r['ticket_id']
        ];
    }
}

echo json_encode(['notifs' => $notifs]);
