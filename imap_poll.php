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
// CONFIGURACIÓN IMAP — Editar aquí
// ============================================================
$IMAP_HOST    = 'imap.gmail.com';          // Servidor IMAP
$IMAP_PORT    = 993;                        // Puerto (993 = SSL)
$IMAP_USER    = 'soporte@tuempresa.com';    // Email de la bandeja
$IMAP_PASS    = 'tu_contraseña_app';        // Contraseña / app password
$IMAP_MAILBOX = 'INBOX';                    // Bandeja
$IMAP_SSL     = true;                       // SSL sí/no
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
$ssl_flag = $IMAP_SSL ? '/ssl' : '';
$connection_string = "{imap.$IMAP_HOST:$IMAP_PORT/ssl}$IMAP_MAILBOX";
// Formato correcto para IMAP
$connection_string = "{" . ($IMAP_SSL ? "imaps:" : "imap:") . "//$IMAP_HOST:$IMAP_PORT}$IMAP_MAILBOX";

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
    $attachments = [];

    if ($structure->type === 0) {
        // Simple text
        $body_raw = imap_fetchbody($imap, $num, 1);
        $body_text = decodeBody($body_raw, $structure);
    } elseif ($structure->type === 1 && isset($structure->parts)) {
        // Multipart
        foreach ($structure->parts as $i => $part) {
            $idx = $i + 1;
            $content = imap_fetchbody($imap, $num, (string)$idx);
            $decoded = decodeBody($content, $part);
            $filename = getFilename($part);

            if (!empty($filename)) {
                $attachments[] = ['name' => $filename, 'data' => $decoded];
            } elseif ($part->type === 0 && empty($body_text)) {
                // Texto
                $body_text = $decoded;
            }
        }
    }

    // Limpiar quoted replies
    $lines = explode("\n", $body_text);
    $clean = [];
    foreach ($lines as $line) {
        $t = trim($line);
        if (str_starts_with($t, '>')) continue;
        if (str_starts_with($t, '--') && strlen($t) < 10) break;
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
           ->execute([$ticket_id, $usuario_id, htmlspecialchars($body_text, ENT_QUOTES)]);
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
