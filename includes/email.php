<?php
// ============================================================
// SISTEMA DE CORREO ELECTRÓNICO
// ============================================================

function obtenerSMTP() {
    return [
        'host'    => obtenerConfig('smtp_host', ''),
        'port'    => (int)obtenerConfig('smtp_port', '587'),
        'user'    => obtenerConfig('smtp_user', ''),
        'pass'    => obtenerConfig('smtp_pass', ''),
        'from'    => obtenerConfig('smtp_from', ''),
        'from_name' => obtenerConfig('empresa_nombre', 'Sistema de Tickets'),
    ];
}

function enviarEmail($para, $asunto, $cuerpo_html) {
    $smtp = obtenerSMTP();
    if (empty($smtp['host']) || empty($smtp['user'])) {
        registrarLog('email_error', "SMTP no configurado. Para: $para, Asunto: $asunto");
        return false;
    }
    try {
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $port = $smtp['port'];
        if ($port == 465) {
            $fp = stream_socket_client("ssl://{$smtp['host']}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        } else {
            $fp = stream_socket_client("{$smtp['host']}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        }
        if (!$fp) {
            registrarLog('email_error', "No se pudo conectar al SMTP: $errstr ($errno)");
            return false;
        }
        $read = fgets($fp);
        if (substr($read,0,3) !== '220') { fclose($fp); return false; }

        // EHLO
        fputs($fp, "EHLO " . ($smtp['host'] ?? 'localhost') . "\r\n"); $read = fgets($fp);
        // Leer lineas multi-linea EHLO
        while (strpos($read, ' ') === 3) { $read = fgets($fp); if (substr($read,0,3) !== '250') break; }

        // STARTTLS si no es 465
        if ($port !== 465) {
            fputs($fp, "STARTTLS\r\n"); $read = fgets($fp);
            $fp2 = stream_socket_client("ssl://{$smtp['host']}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
            if ($fp2) {
                // Re-intentar con ssl:// directo si STARTTLS falla
            }
            // Intentar con ssl stream wrapper
            stream_socket_enable_crypto($fp, true, STREAM_SSL_SERVER_ALL);
            fputs($fp, "EHLO " . ($smtp['host'] ?? 'localhost') . "\r\n"); $read = fgets($fp);
            while (strpos($read, ' ') === 3) { $read = fgets($fp); }
        }

        // AUTH
        fputs($fp, "AUTH LOGIN\r\n"); $read = fgets($fp);
        fputs($fp, base64_encode($smtp['user']) . "\r\n"); $read = fgets($fp);
        fputs($fp, base64_encode($smtp['pass']) . "\r\n"); $read = fgets($fp);
        if (substr($read,0,3) !== '235') {
            registrarLog('email_error', "AUTH fallo: $read");
            fclose($fp); return false;
        }

        // MAIL FROM
        fputs($fp, "MAIL FROM:<{$smtp['from']}>\r\n"); $read = fgets($fp);
        // RCPT TO
        $paras = is_array($para) ? $para : [$para];
        foreach ($paras as $p) { fputs($fp, "RCPT TO:<$p>\r\n"); $read = fgets($fp); }
        // DATA
        fputs($fp, "DATA\r\n"); $read = fgets($fp);

        $headers = "From: {$smtp['from_name']} <{$smtp['from']}>\r\n"
                 . "To: " . implode(', ', $paras) . "\r\n"
                 . "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n"
                 . "\r\n";
        fputs($fp, $headers . $cuerpo_html . "\r\n.\r\n");
        $read = fgets($fp);
        fputs($fp, "QUIT\r\n");
        fclose($fp);
        registrarLog('email_enviado', "Para: " . implode(', ',$paras) . " | Asunto: $asunto");
        return true;
    } catch (Exception $e) {
        registrarLog('email_error', "Excepción: " . $e->getMessage() . " | Para: $para");
        return false;
    }
}

// ============================================================
// PLANTILLAS EMAIL
// ============================================================

function obtenerPlantilla($tipo) {
    $db = getDB();
    $clave = 'plantilla_' . $tipo;
    $val = obtenerConfig($clave, '');
    if (!empty($val)) return $val;
    // Defaults
    $defaults = [
        'ticket_creado' => '<h2>Nuevo Ticket Recibido</h2><p><strong>Asunto:</strong> {{asunto}}</p><p><strong>Web:</strong> {{web}}</p><p><strong>Prioridad:</strong> {{prioridad}}</p><p><strong>Mensaje:</strong></p><p>{{mensaje}}</p><hr><p><em>Sistema de Tickets — {{empresa}}</em></p>',
        'respuesta_admin' => '<h2>Nueva Respuesta en tu Ticket</h2><p><strong>Ticket:</strong> #{{id}} — {{asunto}}</p><hr><p>{{respuesta}}</p><hr><p><a href="{{url}}">Ver ticket completo</a></p><p><em>Sistema de Tickets — {{empresa}}</em></p>',
        'ticket_cerrado' => '<h2>Ticket Cerrado</h2><p>El ticket <strong>#{{id}} — {{asunto}}</strong> ha sido marcado como <strong>Terminado</strong>.</p><p>Si tienes una incidencia, puedes reportarla desde la plataforma.</p><hr><p><em>Sistema de Tickets — {{empresa}}</em></p>',
        'incidencia' => '<h2>⚠ Nueva Incidencia Reportada</h2><p><strong>Ticket:</strong> #{{id}} — {{asunto}}</p><p><strong>Cliente:</strong> {{cliente}}</p><p><strong>Web:</strong> {{web}}</p><p>El cliente ha reportado una incidencia en un ticket cerrado. Revísalo.</p><hr><p><a href="{{url_admin}}">Ver ticket en Admin</a></p><p><em>Sistema de Tickets — {{empresa}}</em></p>',
        'nuevo_ticket_admin' => '<h2>Nuevo Ticket</h2><p><strong>Cliente:</strong> {{cliente}}</p><p><strong>Asunto:</strong> {{asunto}}</p><p><strong>Web:</strong> {{web}}</p><p><strong>Prioridad:</strong> {{prioridad}}</p><p><strong>Mensaje:</strong></p><p>{{mensaje}}</p><hr><p><a href="{{url_admin}}">Ver ticket en Admin</a></p><p><em>Sistema de Tickets — {{empresa}}</em></p>',
    ];
    return $defaults[$tipo] ?? '';
}

function renderPlantilla($tipo, $vars = []) {
    $tpl = obtenerPlantilla($tipo);
    $empresa = obtenerConfig('empresa_nombre', 'Sistema de Tickets');
    $vars['empresa'] = $empresa;
    foreach ($vars as $k => $v) {
        $tpl = str_replace('{{' . $k . '}}', htmlspecialchars((string)$v, ENT_QUOTES), $tpl);
    }
    return $tpl;
}

// ============================================================
// EMAILS AUTOMÁTICOS
// ============================================================

function emailTicketCreado($ticket) {
    $db = getDB();
    // Email al admin
    $admins = $db->query("SELECT email FROM usuarios WHERE rol='admin'")->fetchAll();
    $html = renderPlantilla('nuevo_ticket_admin', [
        'id' => $ticket['id'],
        'asunto' => $ticket['asunto'],
        'mensaje' => $ticket['mensaje'],
        'web' => getWebNombre($ticket['web_id']),
        'prioridad' => ucfirst($ticket['prioridad']),
        'cliente' => getUsuarioNombre($ticket['usuario_id']),
        'url_admin' => obtenerConfig('dominio_base', '') . '/admin/ver_ticket.php?id=' . $ticket['id'],
    ]);
    foreach ($admins as $admin) {
        enviarEmail($admin['email'], '[Ticket] Nuevo ticket #' . $ticket['id'], $html);
    }
    // Email al cliente
    $st = $db->prepare("SELECT email FROM usuarios WHERE id=?"); $st->execute([$ticket['usuario_id']]);
    $cliente = $st->fetch();
    if ($cliente) {
        $html2 = renderPlantilla('ticket_creado', [
            'id' => $ticket['id'],
            'asunto' => $ticket['asunto'],
            'mensaje' => $ticket['mensaje'],
            'web' => getWebNombre($ticket['web_id']),
            'prioridad' => ucfirst($ticket['prioridad']),
        ]);
        enviarEmail($cliente['email'], 'Ticket creado: #' . $ticket['id'] . ' — ' . $ticket['asunto'], $html2);
    }
}

function emailRespuestaAdmin($ticket, $respuesta_texto) {
    $db = getDB();
    $st = $db->prepare("SELECT email FROM usuarios WHERE id=?"); $st->execute([$ticket['usuario_id']]);
    $cliente = $st->fetch();
    if ($cliente) {
        $html = renderPlantilla('respuesta_admin', [
            'id' => $ticket['id'],
            'asunto' => $ticket['asunto'],
            'respuesta' => $respuesta_texto,
            'url' => obtenerConfig('dominio_base', '') . '/ver_ticket.php?id=' . $ticket['id'],
        ]);
        enviarEmail($cliente['email'], 'Respuesta en ticket #' . $ticket['id'], $html);
    }
}

function emailTicketCerrado($ticket) {
    $db = getDB();
    $st = $db->prepare("SELECT email FROM usuarios WHERE id=?"); $st->execute([$ticket['usuario_id']]);
    $cliente = $st->fetch();
    if ($cliente) {
        $html = renderPlantilla('ticket_cerrado', [
            'id' => $ticket['id'],
            'asunto' => $ticket['asunto'],
        ]);
        enviarEmail($cliente['email'], 'Ticket cerrado: #' . $ticket['id'], $html);
    }
}

function emailIncidencia($ticket_id) {
    $db = getDB();
    $st = $db->prepare("SELECT t.*,w.nombre as web_nombre FROM tickets t JOIN webs w ON t.web_id=w.id WHERE t.id=?");
    $st->execute([$ticket_id]); $ticket = $st->fetch();
    if (!$ticket) return;
    $admins = $db->query("SELECT email FROM usuarios WHERE rol='admin'")->fetchAll();
    $html = renderPlantilla('incidencia', [
        'id' => $ticket['id'],
        'asunto' => $ticket['asunto'],
        'cliente' => getUsuarioNombre($ticket['usuario_id']),
        'web' => $ticket['web_nombre'],
        'url_admin' => obtenerConfig('dominio_base', '') . '/admin/ver_ticket.php?id=' . $ticket['id'],
    ]);
    foreach ($admins as $admin) {
        enviarEmail($admin['email'], '⚠ Incidencia en ticket #' . $ticket['id'], $html);
    }
}

function emailRespuestaCliente($ticket, $respuesta_texto) {
    $db = getDB();
    $admins = $db->query("SELECT email FROM usuarios WHERE rol='admin'")->fetchAll();
    $html = renderPlantilla('respuesta_admin', [
        'id' => $ticket['id'],
        'asunto' => $ticket['asunto'],
        'respuesta' => $respuesta_texto,
        'url' => obtenerConfig('dominio_base', '') . '/admin/ver_ticket.php?id=' . $ticket['id'],
    ]);
    foreach ($admins as $admin) {
        enviarEmail($admin['email'], 'Respuesta cliente ticket #' . $ticket['id'], $html);
    }
}

// Helpers
function getWebNombre($web_id) {
    $db = getDB();
    $st = $db->prepare("SELECT nombre FROM webs WHERE id=?"); $st->execute([$web_id]);
    $r = $st->fetch();
    return $r ? $r['nombre'] : 'Desconocida';
}

function getUsuarioNombre($uid) {
    $db = getDB();
    $st = $db->prepare("SELECT nombre FROM usuarios WHERE id=?"); $st->execute([$uid]);
    $r = $st->fetch();
    return $r ? $r['nombre'] : 'Desconocido';
}
