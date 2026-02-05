<?php
// ============================================================
// SISTEMA DE CORREO ELECTRÓNICO
// ============================================================

function obtenerSMTP() {
    return [
        'host'     => obtenerConfig('smtp_host', ''),
        'port'     => (int)obtenerConfig('smtp_port', '587'),
        'security' => obtenerConfig('smtp_security', 'starttls'),
        'user'     => obtenerConfig('smtp_user', ''),
        'pass'     => obtenerConfig('smtp_pass', ''),
        'from'     => obtenerConfig('smtp_from', ''),
        'from_name'=> obtenerConfig('empresa_nombre', 'Sistema de Tickets'),
    ];
}

// Lee una respuesta SMTP completa (maneja multilinea 250-xxx / 250 xxx)
function smtpRead($fp) {
    $last = '';
    do {
        $line = fgets($fp, 4096);
        if ($line === false) break;
        $last = $line;
    } while (strlen($line) >= 4 && $line[3] === '-');
    return trim($last);
}

// Envía un comando SMTP y lee la respuesta
function smtpCmd($fp, $cmd) {
    fputs($fp, $cmd . "\r\n");
    return smtpRead($fp);
}

function enviarEmail($para, $asunto, $cuerpo_html, $reply_to = '', &$error_msg = null) {
    $smtp = obtenerSMTP();
    if (empty($smtp['host']) || empty($smtp['user'])) {
        $error_msg = 'SMTP no configurado (host o usuario vacío)';
        registrarLog('email_error', "SMTP no configurado. Para: $para");
        return false;
    }

    $error_msg = '';
    try {
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);

        $port     = $smtp['port'];
        $security = $smtp['security'];

        // Conectar: SSL desde el inicio si es 'ssl', en otro caso sin cifrado aún
        $prefix = ($security === 'ssl') ? 'ssl://' : '';
        $fp = stream_socket_client($prefix . $smtp['host'] . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            $error_msg = "Conexión fallida al servidor {$smtp['host']}:{$port} — $errstr ($errno)";
            registrarLog('email_error', $error_msg);
            return false;
        }
        stream_set_timeout($fp, 10);

        // Greeting 220
        $read = smtpRead($fp);
        if (substr($read, 0, 3) !== '220') {
            $error_msg = "Greeting SMTP inesperado: $read";
            fclose($fp); return false;
        }

        // EHLO
        $read = smtpCmd($fp, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if (substr($read, 0, 3) !== '250') {
            $error_msg = "EHLO rechazado: $read";
            fclose($fp); return false;
        }

        // STARTTLS si la seguridad es starttls
        if ($security === 'starttls') {
            $read = smtpCmd($fp, 'STARTTLS');
            if (substr($read, 0, 3) !== '220') {
                $error_msg = "STARTTLS no aceptado por el servidor: $read";
                fclose($fp); return false;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_SSL_CLIENT_ALL)) {
                $error_msg = "Fallo al activar cifrado TLS en la conexión";
                fclose($fp); return false;
            }
            // Re-EHLO obligatorio tras STARTTLS (RFC 3207)
            $read = smtpCmd($fp, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            if (substr($read, 0, 3) !== '250') {
                $error_msg = "EHLO tras STARTTLS rechazado: $read";
                fclose($fp); return false;
            }
        }

        // AUTH LOGIN
        $read = smtpCmd($fp, 'AUTH LOGIN');
        if (substr($read, 0, 3) !== '334') {
            $error_msg = "AUTH LOGIN no aceptado: $read";
            fclose($fp); return false;
        }
        $read = smtpCmd($fp, base64_encode($smtp['user']));
        if (substr($read, 0, 3) !== '334') {
            $error_msg = "Usuario SMTP rechazado: $read";
            fclose($fp); return false;
        }
        $read = smtpCmd($fp, base64_encode($smtp['pass']));
        if (substr($read, 0, 3) !== '235') {
            $error_msg = "Autenticación SMTP fallida: $read";
            fclose($fp); return false;
        }

        // MAIL FROM
        $read = smtpCmd($fp, "MAIL FROM:<{$smtp['from']}>");
        if (substr($read, 0, 3) !== '250') {
            $error_msg = "MAIL FROM rechazado: $read";
            fclose($fp); return false;
        }

        // RCPT TO
        $paras = is_array($para) ? $para : [$para];
        foreach ($paras as $p) {
            $read = smtpCmd($fp, "RCPT TO:<$p>");
            if (substr($read, 0, 3) !== '250') {
                $error_msg = "RCPT TO rechazado ($p): $read";
                fclose($fp); return false;
            }
        }

        // DATA
        $read = smtpCmd($fp, 'DATA');
        if (substr($read, 0, 3) !== '354') {
            $error_msg = "DATA no aceptado: $read";
            fclose($fp); return false;
        }

        $headers = "From: {$smtp['from_name']} <{$smtp['from']}>\r\n"
                 . "To: " . implode(', ', $paras) . "\r\n"
                 . "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n";
        if (!empty($reply_to)) {
            $headers .= "Reply-To: <$reply_to>\r\n";
        }
        $headers .= "\r\n";
        fputs($fp, $headers . $cuerpo_html . "\r\n.\r\n");
        $read = smtpRead($fp);
        if (substr($read, 0, 3) !== '250') {
            $error_msg = "Mensaje no aceptado por el servidor: $read";
            fclose($fp); return false;
        }

        smtpCmd($fp, 'QUIT');
        fclose($fp);
        registrarLog('email_enviado', "Para: " . implode(', ', $paras) . " | Asunto: $asunto");
        return true;
    } catch (Exception $e) {
        $error_msg = "Excepción: " . $e->getMessage();
        registrarLog('email_error', $error_msg . " | Para: $para");
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
        'ticket_en_proceso' => '<h2>Tu Ticket está en Proceso</h2><p>El ticket <strong>#{{id}} — {{asunto}}</strong> (<strong>{{web}}</strong>) está siendo revisado por nuestro equipo.</p><p>Si necesitas añadir información, puedes responder directamente en la plataforma.</p><hr><p><em>Sistema de Tickets — {{empresa}}</em></p>',
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
        $reply_to = 'ticket+' . $ticket['id'] . '@' . obtenerConfig('dominio_mail', '');
        enviarEmail($cliente['email'], 'Ticket creado: #' . $ticket['id'] . ' — ' . $ticket['asunto'], $html2, $reply_to);
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
        $reply_to = 'ticket+' . $ticket['id'] . '@' . obtenerConfig('dominio_mail', '');
        enviarEmail($cliente['email'], 'Respuesta en ticket #' . $ticket['id'], $html, $reply_to);
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
        $reply_to = 'ticket+' . $ticket['id'] . '@' . obtenerConfig('dominio_mail', '');
        enviarEmail($cliente['email'], 'Ticket cerrado: #' . $ticket['id'], $html, $reply_to);
    }
}

function emailTicketEnProceso($ticket) {
    $db = getDB();
    $st = $db->prepare("SELECT email FROM usuarios WHERE id=?"); $st->execute([$ticket['usuario_id']]);
    $cliente = $st->fetch();
    if ($cliente) {
        $html = renderPlantilla('ticket_en_proceso', [
            'id' => $ticket['id'],
            'asunto' => $ticket['asunto'],
            'web' => getWebNombre($ticket['web_id']),
        ]);
        $reply_to = 'ticket+' . $ticket['id'] . '@' . obtenerConfig('dominio_mail', '');
        enviarEmail($cliente['email'], 'Ticket en proceso: #' . $ticket['id'], $html, $reply_to);
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
