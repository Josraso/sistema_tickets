<?php
/**
 * IMAP POLLING — Sistema de Tickets
 *
 * Alternativa al pipe: este script se ejecuta por CRON cada minuto
 * y comprueba una bandeja IMAP dedicada buscando emails tipo ticket+ID@dominio
 *
 * Cron:
 *   * * * * * php /var/www/html/imap_poll.php >> /var/log/imap_poll.log 2>&1
 *
 * CONFIGURACIÓN: Edita las variables justo debajo de este comentario.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$base = dirname(__FILE__);
if (!file_exists($base . '/config.php')) exit(1);
require_once $base . '/config.php';

// ============================================================
// CONFIGURACIÓN IMAP — desde base de datos
// ============================================================
$IMAP_HOST    = obtenerConfig('imap_host', '');
$IMAP_PORT    = (int)obtenerConfig('imap_port', 993);
$IMAP_USER    = obtenerConfig('imap_user', '');
$IMAP_PASS    = obtenerConfig('imap_pass', '');
$IMAP_MAILBOX = obtenerConfig('imap_mailbox', 'INBOX');
$IMAP_SSL     = obtenerConfig('imap_security', 'ssl') === 'ssl';

if (empty($IMAP_HOST) || empty($IMAP_USER)) {
    log_imap("ERROR: IMAP no configurado. Ve a Admin → Configuración → PIPE");
    exit(1);
}
// ============================================================

function log_imap($msg) {
    $base = dirname(__FILE__);
    file_put_contents($base . '/logs/imap_poll.log', date('Y-m-d H:i:s') . " | $msg\n", FILE_APPEND);
}

// Verificar que IMAP extension existe
if (!extension_loaded('imap')) {
    log_imap("ERROR: Extensión PHP IMAP no cargada");
    exit(1);
}

// Conectar
$ssl_flag = $IMAP_SSL ? '/ssl/novalidate-cert' : '';
$connection_string = "{" . $IMAP_HOST . ":" . $IMAP_PORT . $ssl_flag . "}" . $IMAP_MAILBOX;

$imap = @imap_open($connection_string, $IMAP_USER, $IMAP_PASS);
if (!$imap) {
    log_imap("ERROR: No se pudo conectar al IMAP — " . imap_last_error());
    exit(1);
}

log_imap("Conectado. Buscando emails...");

$emails = imap_search($imap, 'UNSEEN');
if (!$emails) {
    log_imap("Sin emails nuevos");
    imap_close($imap);
    exit(0);
}

sort($emails);
$db = getDB();
$exts_permitidas = ['jpg','jpeg','png','gif','zip','rar','pdf'];

foreach ($emails as $num) {
    $header = imap_header($imap, $num);
    $to = $header->toinsession ?? $header->tostrs ?? '';
    // imap_header devuelve objeto, usar campos estándar
    $to_raw = '';
    if (isset($header->to) && is_array($header->to) && isset($header->to[0])) {
        $to_raw = $header->to[0]->mailbox . '@' . $header->to[0]->host;
    }
    $from_raw = '';
    if (isset($header->from) && is_array($header->from) && isset($header->from[0])) {
        $from_raw = $header->from[0]->mailbox . '@' . $header->from[0]->host;
    }
    $subject = imap_utf8($header->subject ?? '');

    log_imap("Email #$num | De: $from_raw | Para: $to_raw | Asunto: $subject");

    // Extraer ticket ID
    $ticket_id = null;
    if (preg_match('/ticket\+(\d+)@/i', $to_raw, $m)) {
        $ticket_id = (int)$m[1];
    }
    // Si no en To, buscar en todos los destinatarios originales
    if ($ticket_id === null) {
        $all_to = imap_utf8(implode(', ', array_map(function($r) {
            return ($r->mailbox ?? '') . '@' . ($r->host ?? '');
        }, $header->to ?? [])));
        if (preg_match('/ticket\+(\d+)@/i', $all_to, $m)) {
            $ticket_id = (int)$m[1];
        }
    }

    if ($ticket_id === null) {
        log_imap("  → No se encontró ticket ID, ignorando");
        // Marcar como leído para no procesar de nuevo
        imap_setflag_full($imap, $num, "\\Seen");
        continue;
    }

    // Verificar ticket existe
    $st = $db->prepare("SELECT * FROM tickets WHERE id = ?");
    $st->execute([$ticket_id]);
    $ticket = $st->fetch();
    if (!$ticket) {
        log_imap("  → Ticket #$ticket_id no existe");
        imap_setflag_full($imap, $num, "\\Seen");
        continue;
    }

    // Obtener cuerpo
    $structure = imap_fetchstructure($imap, $num);
    $body_text = '';
    $body_html = '';
    $attachments = [];

    if ($structure->type === 0) {
        // Simple text
        $body_raw = imap_fetchbody($imap, $num, 1);
        $decoded = decodeBody($body_raw, $structure);
        // Verificar si es HTML o texto plano
        if (isset($structure->subtype) && strtoupper($structure->subtype) === 'HTML') {
            $body_html = $decoded;
        } else {
            $body_text = $decoded;
        }
    } elseif ($structure->type === 1 && isset($structure->parts)) {
        // Multipart - buscar text/plain primero, luego text/html
        foreach ($structure->parts as $i => $part) {
            $idx = $i + 1;
            $content = imap_fetchbody($imap, $num, (string)$idx);
            $decoded = decodeBody($content, $part);
            $filename = getFilename($part);

            if (!empty($filename)) {
                $attachments[] = ['name' => $filename, 'data' => $decoded];
            } elseif ($part->type === 0) {
                // Es texto
                $subtype = strtoupper($part->subtype ?? 'PLAIN');
                if ($subtype === 'PLAIN' && empty($body_text)) {
                    $body_text = $decoded;
                } elseif ($subtype === 'HTML' && empty($body_html)) {
                    $body_html = $decoded;
                }
            }
        }
    }

    // Preferir texto plano, si no hay convertir HTML a texto
    if (empty($body_text) && !empty($body_html)) {
        $body_text = htmlToPlainText($body_html);
    }

    // Limpiar quoted replies
    $lines = explode("\n", $body_text);
    $clean = [];
    foreach ($lines as $line) {
        $t = trim($line);
        if (strpos($t, '>') === 0) continue;
        if (strpos($t, '--') === 0 && strlen($t) < 10) break;
        // Encabezado de cita Gmail (español / inglés) y Outlook
        if (preg_match('/^(El\s+\w+.*escribi|On\s+\w+.*wrote\s*:|-{3,}\s*(Original Message|Mensaje original))/i', $t)) break;
        if (preg_match('/(escribi[oó]|wrote)\s*:\s*$/i', $t)) break;
        $clean[] = $line;
    }
    $body_text = trim(implode("\n", $clean));

    // Buscar usuario por email
    $usuario_id = $ticket['usuario_id'];
    if (!empty($from_raw)) {
        $st = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $st->execute([$from_raw]);
        $u = $st->fetch();
        if ($u) $usuario_id = $u['id'];
    }

    // Insertar respuesta
    $respuesta_id = null;
    if (!empty($body_text)) {
        $db->prepare("INSERT INTO respuestas (ticket_id, usuario_id, mensaje, es_email) VALUES (?, ?, ?, 1)")
           ->execute([$ticket_id, $usuario_id, $body_text]);
        $respuesta_id = $db->lastInsertId();
        log_imap("  → Respuesta insertada (ID: $respuesta_id)");
    }

    // Adjuntos
    foreach ($attachments as $att) {
        $ext = strtolower(pathinfo($att['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $exts_permitidas)) { log_imap("  → Adjunto rechazado: " . $att['name']); continue; }
        $nombre_guardado = uniqid('imap_') . '_' . time() . '.' . $ext;
        $ruta = BASE_PATH . '/uploads/' . $nombre_guardado;
        if (file_put_contents($ruta, $att['data'])) {
            $db->prepare("INSERT INTO archivos (ticket_id, respuesta_id, nombre_original, nombre_guardado, extension, tamanio, ruta) VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([$ticket_id, $respuesta_id, $att['name'], $nombre_guardado, $ext, strlen($att['data']), $ruta]);
            log_imap("  → Adjunto guardado: " . $att['name']);
        }
    }

    // Historial
    $db->prepare("UPDATE tickets SET fecha_actualizacion = NOW() WHERE id = ?")->execute([$ticket_id]);
    $db->prepare("INSERT INTO historial_tickets (ticket_id, usuario_id, accion, detalles) VALUES (?, ?, 'Respuesta por email (IMAP)', ?)")
       ->execute([$ticket_id, $usuario_id, "De: $from_raw"]);

    // Marcar como leído
    imap_setflag_full($imap, $num, "\\Seen");
    log_imap("  → Procesado OK ticket #$ticket_id");
}

imap_close($imap);
log_imap("Polling finalizado");

// ============ HELPERS ============
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

function decodeBody($content, $structure) {
    switch ($structure->encoding) {
        case ENC7BIT:    return $content;
        case ENC8BIT:    return $content;
        case ENCBINARY:  return $content;
        case ENCBASE64:  return base64_decode($content);
        case ENCQUOTEDP: return quoted_printable_decode($content);
        default:         return $content;
    }
}

function getFilename($part) {
    $filename = '';
    if ($part->parameters) {
        foreach ($part->parameters as $p) {
            if (strtolower($p->attribute) === 'name') $filename = $p->value;
        }
    }
    if (empty($filename) && $part->disposition_parameters) {
        foreach ($part->disposition_parameters as $p) {
            if (strtolower($p->attribute) === 'filename') $filename = $p->value;
        }
    }
    return imap_utf8($filename);
}
