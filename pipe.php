<?php
/**
 * PIPE DE EMAIL — Sistema de Tickets
 *
 * Este script recibe emails por pipe del servidor (Postfix/Exim)
 * y los procesa para añadir la respuesta al ticket correspondiente.
 *
 * Configuración en el servidor:
 *   En /etc/aliases (Postfix):
 *     default: |"/usr/bin/php /var/www/html/pipe.php"
 *   Luego ejecutar: newaliases
 *
 * El email llega por STDIN.
 */

// No mostrar errores al exterior (viene por cron/pipe)
error_reporting(E_ALL);
ini_set('display_errors', 0);

$base = dirname(__FILE__);
if (!file_exists($base . '/config.php')) exit(1);
require_once $base . '/config.php';

// Leer email completo de STDIN
$raw_email = file_get_contents('php://stdin');
if (empty($raw_email)) {
    log_pipe("Email vacío recibido");
    exit(1);
}

log_pipe("Email recibido (" . strlen($raw_email) . " bytes)");

// Parsear headers
$partes = explode("\r\n\r\n", $raw_email, 2);
if (count($partes) < 2) {
    // Intentar con solo \n
    $partes = explode("\n\n", $raw_email, 2);
}
$headers_raw = $partes[0] ?? '';
$body_raw = $partes[1] ?? '';

// Parsear headers a array
$headers = [];
$lines = preg_split('/\r?\n/', $headers_raw);
$current_key = '';
foreach ($lines as $line) {
    if (preg_match('/^([A-Za-z\-]+):\s*(.*)/', $line, $m)) {
        $current_key = strtolower($m[1]);
        $headers[$current_key] = trim($m[2]);
    } elseif (!empty($current_key) && preg_match('/^\s+(.*)/', $line, $m)) {
        // Header continuación
        $headers[$current_key] .= ' ' . trim($m[1]);
    }
}

$from = $headers['from'] ?? '';
$to = $headers['to'] ?? '';
$subject = decodeMIMEHeader($headers['subject'] ?? '');
$content_type = $headers['content-type'] ?? 'text/plain';

log_pipe("De: $from | Para: $to | Asunto: $subject");

// Extraer ticket ID del campo "To"
// Formato esperado: ticket+123@dominio.com
$ticket_id = null;
if (preg_match('/ticket\+(\d+)@/i', $to, $m)) {
    $ticket_id = (int)$m[1];
}

// Si no en To, buscar en Cc
if ($ticket_id === null && isset($headers['cc'])) {
    if (preg_match('/ticket\+(\d+)@/i', $headers['cc'], $m)) {
        $ticket_id = (int)$m[1];
    }
}

if ($ticket_id === null) {
    log_pipe("No se encontró ticket ID en el email");
    exit(1);
}

log_pipe("Ticket ID extraído: $ticket_id");

// Verificar que el ticket existe
try {
    $db = getDB();
    $st = $db->prepare("SELECT * FROM tickets WHERE id = ?");
    $st->execute([$ticket_id]);
    $ticket = $st->fetch();
    if (!$ticket) {
        log_pipe("Ticket #$ticket_id no existe");
        exit(1);
    }
} catch (Exception $e) {
    log_pipe("Error DB: " . $e->getMessage());
    exit(1);
}

// Extraer cuerpo (texto plano preferido)
$body_text = '';
$body_html = '';
$attachments = [];

if (str_contains($content_type, 'multipart')) {
    // Extraer boundary
    if (preg_match('/boundary="?([^";]+)"?/i', $content_type, $m)) {
        $boundary = $m[1];
        $parts = explode('--' . $boundary, $body_raw);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '--') continue;
            // Separar headers del body de la parte
            $sub = explode("\r\n\r\n", $part, 2);
            if (count($sub) < 2) $sub = explode("\n\n", $part, 2);
            $sub_headers_raw = $sub[0] ?? '';
            $sub_body = $sub[1] ?? '';

            // Parsear sub-headers
            $sh = [];
            foreach (preg_split('/\r?\n/', $sub_headers_raw) as $sl) {
                if (preg_match('/^([A-Za-z\-]+):\s*(.*)/', $sl, $m2)) {
                    $sh[strtolower($m2[1])] = trim($m2[2]);
                }
            }

            $sub_ct = $sh['content-type'] ?? 'text/plain';
            $sub_te = strtolower($sh['content-transfer-encoding'] ?? '7bit');
            $disposition = $sh['content-disposition'] ?? '';

            // Decodificar body
            if ($sub_te === 'base64') { $sub_body = base64_decode($sub_body); }
            elseif ($sub_te === 'quoted-printable') { $sub_body = quoted_printable_decode($sub_body); }

            if (str_contains($disposition, 'attachment') || str_contains($disposition, 'inline') && !str_contains($sub_ct, 'text/')) {
                // Adjunto
                $filename = '';
                if (preg_match('/filename="?([^";\r\n]+)"?/i', $disposition, $fm)) $filename = trim($fm[1]);
                elseif (preg_match('/name="?([^";\r\n]+)"?/i', $sub_ct, $fm)) $filename = trim($fm[1]);
                if (!empty($filename) && !empty($sub_body)) {
                    $attachments[] = ['name' => $filename, 'data' => $sub_body];
                }
            } elseif (str_contains($sub_ct, 'text/plain') && empty($body_text)) {
                $body_text = $sub_body;
            } elseif (str_contains($sub_ct, 'text/html') && empty($body_html)) {
                $body_html = $sub_body;
            }
        }
    }
} else {
    // Email simple
    $te = strtolower($headers['content-transfer-encoding'] ?? '7bit');
    if ($te === 'base64') { $body_text = base64_decode($body_raw); }
    elseif ($te === 'quoted-printable') { $body_text = quoted_printable_decode($body_raw); }
    else { $body_text = $body_raw; }

    // Verificar si es HTML
    if (str_contains(strtolower($content_type), 'text/html')) {
        $body_html = $body_text;
        $body_text = '';
    }
}

// Preferir texto plano, si no convertir HTML a texto
if (empty($body_text) && !empty($body_html)) {
    $body_text = htmlToPlainText($body_html);
}

// Limpiar body: eliminar quoted replies (líneas que empiezan con >)
$body_lines = explode("\n", $body_text);
$clean_lines = [];
foreach ($body_lines as $line) {
    $trimmed = trim($line);
    if (str_starts_with($trimmed, '>')) continue;
    if (str_starts_with($trimmed, '--') && strlen($trimmed) < 10) break; // firma
    $clean_lines[] = $line;
}
$body_text = trim(implode("\n", $clean_lines));

if (empty($body_text) && empty($attachments)) {
    log_pipe("Body y adjuntos vacíos para ticket #$ticket_id");
    exit(1);
}

// Extraer email del remitente
$from_email = '';
if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $from, $em)) {
    $from_email = $em[1];
}

// Buscar usuario por email
$usuario_id = null;
if (!empty($from_email)) {
    $st = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
    $st->execute([$from_email]);
    $usu = $st->fetch();
    if ($usu) $usuario_id = $usu['id'];
}
// Si no se encuentra, usar el usuario del ticket (respuesta sin autenticación)
if ($usuario_id === null) $usuario_id = $ticket['usuario_id'];

// Insertar respuesta
if (!empty($body_text)) {
    $db->prepare("INSERT INTO respuestas (ticket_id, usuario_id, mensaje, es_email) VALUES (?, ?, ?, 1)")
       ->execute([$ticket_id, $usuario_id, $body_text]);
    $respuesta_id = $db->lastInsertId();
    log_pipe("Respuesta insertada (ID: $respuesta_id) en ticket #$ticket_id");
} else {
    $respuesta_id = null;
}

// Guardar adjuntos permitidos
$exts_permitidas = ['jpg','jpeg','png','gif','zip','rar','pdf'];
foreach ($attachments as $att) {
    $ext = strtolower(pathinfo($att['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts_permitidas)) { log_pipe("Adjunto rechazado (extensión): " . $att['name']); continue; }
    $nombre_guardado = uniqid('pipe_') . '_' . time() . '.' . $ext;
    $ruta = BASE_PATH . '/uploads/' . $nombre_guardado;
    if (file_put_contents($ruta, $att['data'])) {
        $db->prepare("INSERT INTO archivos (ticket_id, respuesta_id, nombre_original, nombre_guardado, extension, tamanio, ruta) VALUES (?, ?, ?, ?, ?, ?, ?)")
           ->execute([$ticket_id, $respuesta_id, $att['name'], $nombre_guardado, $ext, strlen($att['data']), $ruta]);
        log_pipe("Adjunto guardado: " . $att['name']);
    }
}

// Actualizar fecha
$db->prepare("UPDATE tickets SET fecha_actualizacion = NOW() WHERE id = ?")->execute([$ticket_id]);

// Registrar en historial
$_SESSION = []; // simular sesión para registrarHistorial
$_SESSION['usuario_id'] = $usuario_id;
registrarHistorial($ticket_id, 'Respuesta por email', "De: $from_email");

log_pipe("Proceso completado para ticket #$ticket_id");

// ============ HELPERS ============

function log_pipe($msg) {
    $base = dirname(__FILE__);
    file_put_contents($base . '/logs/pipe.log', date('Y-m-d H:i:s') . " | $msg\n", FILE_APPEND);
}

function htmlToPlainText($html) {
    // Convertir HTML a texto plano legible

    // Primero decodificar entidades HTML comunes
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Reemplazar saltos de línea HTML por \n
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<\/p>/i', "\n\n", $html);
    $html = preg_replace('/<\/div>/i', "\n", $html);
    $html = preg_replace('/<\/h[1-6]>/i', "\n\n", $html);
    $html = preg_replace('/<\/li>/i', "\n", $html);
    $html = preg_replace('/<li[^>]*>/i', "• ", $html);

    // Eliminar scripts y styles
    $html = preg_replace('/<script[^>]*?>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style[^>]*?>.*?<\/style>/is', '', $html);

    // Eliminar todos los tags HTML restantes
    $text = strip_tags($html);

    // Limpiar espacios en blanco excesivos
    $text = preg_replace('/[ \t]+/', ' ', $text); // Múltiples espacios a uno
    $text = preg_replace('/\n[ \t]+/', "\n", $text); // Espacios al inicio de línea
    $text = preg_replace('/[ \t]+\n/', "\n", $text); // Espacios al final de línea
    $text = preg_replace('/\n{3,}/', "\n\n", $text); // Máximo 2 saltos de línea seguidos

    return trim($text);
}

function decodeMIMEHeader($str) {
    if (preg_match_all('/=\?([^?]+)\?([bBqQ])\?([^?]+)\?=/', $str, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $charset = $m[1];
            $encoding = strtoupper($m[2]);
            $encoded = $m[3];
            $decoded = ($encoding === 'B') ? base64_decode($encoded) : quoted_printable_decode(str_replace('_', ' ', $encoded));
            $decoded = mb_convert_encoding($decoded, 'UTF-8', $charset);
            $str = str_replace($m[0], $decoded, $str);
        }
    }
    return $str;
}
