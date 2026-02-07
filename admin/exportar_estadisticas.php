<?php
require_once '../config.php';
session_start();
if (!estaLogueado() || !esAdmin()) redirigir('../login.php');

$db = getDB();

// Obtener todos los tickets con tiempo registrado
$tickets = $db->query("
    SELECT
        t.id,
        t.asunto,
        t.tiempo_resolucion,
        t.estado,
        u.nombre as cliente_nombre,
        w.nombre as web_nombre,
        (SELECT COUNT(*) FROM respuestas WHERE ticket_id = t.id) as num_respuestas
    FROM tickets t
    JOIN usuarios u ON t.usuario_id = u.id
    JOIN webs w ON t.web_id = w.id
    WHERE t.estado = 'terminado'
    ORDER BY t.fecha_cierre DESC
")->fetchAll();

// Configurar headers para descarga CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="estadisticas_tickets_' . date('Y-m-d_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 para Excel
echo "\xEF\xBB\xBF";

// Abrir output
$output = fopen('php://output', 'w');

// Escribir encabezados
fputcsv($output, [
    'ID Ticket',
    'Cliente',
    'Web',
    'Título',
    'Cantidad Respuestas',
    'Tiempo Empleado (min)',
    'Tiempo Empleado (horas:min)'
], ';');

// Escribir datos
foreach ($tickets as $t) {
    $tiempo_min = $t['tiempo_resolucion'] ?? 0;
    $tiempo_horas = floor($tiempo_min / 60);
    $tiempo_resto = $tiempo_min % 60;
    $tiempo_formato = $tiempo_horas . 'h ' . $tiempo_resto . 'm';

    fputcsv($output, [
        $t['id'],
        $t['cliente_nombre'],
        $t['web_nombre'],
        $t['asunto'],
        $t['num_respuestas'],
        $tiempo_min,
        $tiempo_formato
    ], ';');
}

fclose($output);
exit;
