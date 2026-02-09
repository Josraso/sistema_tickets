<?php
// ============================================================
// FUNCIONES GENERALES DEL SISTEMA
// ============================================================

function limpiar($val) {
    return htmlspecialchars(trim($val ?? ''), ENT_QUOTES, 'UTF-8');
}

function e($val) {
    echo htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function redirigir($url) {
    header('Location: ' . $url);
    exit;
}

function estaLogueado() {
    return isset($_SESSION['usuario_id']);
}

function esAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

function estaImpersonando() {
    return isset($_SESSION['impersonando']) && $_SESSION['impersonando'] === true;
}

function obtenerDuracionSesion($usuario_id) {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT sesion_duracion FROM usuarios WHERE id = ?");
        $st->execute([$usuario_id]);
        $usuario = $st->fetch();
        $minutos = $usuario['sesion_duracion'] ?? 43200; // Default 30 días
        return $minutos * 60; // Devolver segundos
    } catch (Exception $e) {
        return 2592000; // Default 30 días en segundos
    }
}

function formatearFecha($fecha) {
    if (!$fecha) return '—';
    $dt = new DateTime($fecha);
    $dt->setTimezone(new DateTimeZone('Europe/Madrid'));
    return $dt->format('d/m/Y H:i');
}

function obtenerConfig($clave, $default = '') {
    static $cache = [];
    if (isset($cache[$clave])) return $cache[$clave];
    try {
        $db = getDB();
        $st = $db->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $st->execute([$clave]);
        $row = $st->fetch();
        $val = $row ? $row['valor'] : $default;
        $cache[$clave] = $val;
        return $val;
    } catch (Exception $e) {
        return $default;
    }
}

function guardarConfig($clave, $valor) {
    $db = getDB();
    $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor), fecha_actualizacion = NOW()")
       ->execute([$clave, $valor]);
}

function registrarLog($accion, $descripcion = '') {
    try {
        $db = getDB();
        $uid = $_SESSION['usuario_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $db->prepare("INSERT INTO logs (usuario_id, accion, descripcion, ip, user_agent) VALUES (?, ?, ?, ?, ?)")
           ->execute([$uid, $accion, $descripcion, $ip, $ua]);
    } catch (Exception $e) {}
}

function registrarHistorial($ticket_id, $accion, $detalles = '') {
    $uid = $_SESSION['usuario_id'] ?? null;
    $db = getDB();
    $db->prepare("INSERT INTO historial_tickets (ticket_id, usuario_id, accion, detalles) VALUES (?, ?, ?, ?)")
       ->execute([$ticket_id, $uid, $accion, $detalles]);
}

// ============================================================
// CSRF
// ============================================================

function generarTokenCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificarTokenCSRF() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('Token CSRF inválido');
    }
}

function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generarTokenCSRF(), ENT_QUOTES) . '">';
}

// ============================================================
// BADGES
// ============================================================

function estadoBadge($estado) {
    $map = ['abierto' => 'success', 'en_proceso' => 'warning', 'terminado' => 'secondary'];
    $labels = ['abierto' => 'Abierto', 'en_proceso' => 'En Proceso', 'terminado' => 'Terminado'];
    $cls = $map[$estado] ?? 'secondary';
    $lbl = $labels[$estado] ?? $estado;
    return "<span class=\"badge bg-{$cls}\">{$lbl}</span>";
}

function prioridadBadge($prio) {
    $map = ['baja' => 'info', 'media' => 'primary', 'alta' => 'warning', 'critica' => 'danger'];
    $labels = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'];
    $cls = $map[$prio] ?? 'secondary';
    $lbl = $labels[$prio] ?? $prio;
    return "<span class=\"badge bg-{$cls}\">{$lbl}</span>";
}

// ============================================================
// TAGS
// ============================================================

function obtenerTagsTicket($ticket_id) {
    $db = getDB();
    $st = $db->prepare("SELECT t.* FROM tags t JOIN ticket_tags tt ON t.id = tt.tag_id WHERE tt.ticket_id = ?");
    $st->execute([$ticket_id]);
    return $st->fetchAll();
}

function renderTags($tags) {
    if (empty($tags)) return '';
    $html = '';
    foreach ($tags as $tag) {
        $html .= '<span class="badge tag-badge" style="background:' . htmlspecialchars($tag['color']) . '; color:' . colorContraste($tag['color']) . ';">' . htmlspecialchars($tag['nombre']) . '</span> ';
    }
    return $html;
}

function colorContraste($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $lum > 0.5 ? '#000000' : '#ffffff';
}

// ============================================================
// ARCHIVOS
// ============================================================

function obtenerMaxSubida() {
    $mb = (int) obtenerConfig('max_subida', '30');
    return $mb * 1024 * 1024;
}

function extensionesPermitidas() {
    return [
        'jpg', 'jpeg', 'png', 'gif', 'webp',  // Imágenes
        'pdf',                                  // PDF
        'zip', 'rar', '7z',                    // Comprimidos
        'doc', 'docx',                         // Word
        'xls', 'xlsx',                         // Excel
        'csv',                                  // CSV
        'txt',                                  // Texto plano
        'ppt', 'pptx'                          // PowerPoint
    ];
}

function uploadArchivo($file, $ticket_id, $respuesta_id = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > obtenerMaxSubida()) return false;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, extensionesPermitidas())) return false;

    $nombre_guardado = uniqid('arch_') . '_' . time() . '.' . $ext;
    $ruta = BASE_PATH . '/uploads/' . $nombre_guardado;

    if (!move_uploaded_file($file['tmp_name'], $ruta)) return false;

    $db = getDB();
    $db->prepare("INSERT INTO archivos (ticket_id, respuesta_id, nombre_original, nombre_guardado, extension, tamanio, ruta) VALUES (?, ?, ?, ?, ?, ?, ?)")
       ->execute([$ticket_id, $respuesta_id, $file['name'], $nombre_guardado, $ext, $file['size'], $ruta]);
    return true;
}

function obtenerArchivos($ticket_id, $respuesta_id = null) {
    $db = getDB();
    if ($respuesta_id !== null) {
        $st = $db->prepare("SELECT * FROM archivos WHERE ticket_id = ? AND respuesta_id = ? ORDER BY fecha_subida");
        $st->execute([$ticket_id, $respuesta_id]);
    } else {
        $st = $db->prepare("SELECT * FROM archivos WHERE ticket_id = ? AND respuesta_id IS NULL ORDER BY fecha_subida");
        $st->execute([$ticket_id]);
    }
    return $st->fetchAll();
}

function renderArchivos($archivos) {
    if (empty($archivos)) return '';
    $html = '<div class="archivos-container">';
    foreach ($archivos as $a) {
        $url = '/uploads/' . htmlspecialchars($a['nombre_guardado']);
        $ext = strtolower($a['extension']);
        $icono = 'bi-paperclip';

        // Asignar iconos según tipo de archivo
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) $icono = 'bi-image';
        elseif ($ext === 'pdf') $icono = 'bi-filetype-pdf';
        elseif (in_array($ext, ['zip','rar','7z'])) $icono = 'bi-file-zip';
        elseif (in_array($ext, ['doc','docx'])) $icono = 'bi-file-word';
        elseif (in_array($ext, ['xls','xlsx'])) $icono = 'bi-file-excel';
        elseif ($ext === 'csv') $icono = 'bi-filetype-csv';
        elseif ($ext === 'txt') $icono = 'bi-file-text';
        elseif (in_array($ext, ['ppt','pptx'])) $icono = 'bi-file-ppt';

        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $html .= '<div class="archivo-item archivo-img" onclick="abrirVisor(\'' . $url . '\')">'
                   . '<img src="' . $url . '" alt="' . htmlspecialchars($a['nombre_original']) . '" class="archivo-thumb">'
                   . '<span class="archivo-nombre">' . htmlspecialchars($a['nombre_original']) . '</span>'
                   . '</div>';
        } elseif ($ext === 'pdf') {
            $html .= '<a href="' . $url . '" target="_blank" class="archivo-item archivo-pdf">'
                   . '<i class="bi ' . $icono . '"></i>'
                   . '<span class="archivo-nombre">' . htmlspecialchars($a['nombre_original']) . '</span>'
                   . '</a>';
        } else {
            $html .= '<a href="' . $url . '" download class="archivo-item">'
                   . '<i class="bi ' . $icono . '"></i>'
                   . '<span class="archivo-nombre">' . htmlspecialchars($a['nombre_original']) . '</span>'
                   . '</a>';
        }
    }
    $html .= '</div>';
    return $html;
}

function renderArchivoUpload($name = 'archivos', $multiple = true) {
    $maxMB  = obtenerConfig('max_subida', '30');
    $exts   = implode(', ', extensionesPermitidas());
    $mult   = $multiple ? 'multiple' : '';
    $onchg  = $multiple ? ' onchange="if(this.files.length>5){alert(\'Máximo 5 archivos\');this.value=\'\'}"' : '';
    $limite = $multiple ? ' | Hasta 5 archivos' : '';
    return '<div class="mb-3"><label class="form-label"><i class="bi bi-paperclip"></i> Archivos (opcionales)</label>'
         . '<input type="file" name="' . $name . '[]" class="form-control form-control-sm" ' . $mult . $onchg . ' accept=".jpg,.jpeg,.png,.gif,.zip,.rar,.pdf">'
         . '<div class="form-text">Permitidos: ' . $exts . ' | Máximo: ' . $maxMB . ' MB por archivo' . $limite . '</div></div>';
}

// ============================================================
// CAPTCHA SIMPLE
// ============================================================

function generarCaptcha() {
    $a = rand(2, 9);
    $b = rand(2, 9);
    $_SESSION['captcha'] = $a + $b;
    return '<div class="captcha-box"><span class="captcha-num">' . $a . '</span> + <span class="captcha-num">' . $b . '</span> = <input type="text" name="captcha" class="form-control captcha-input" required autocomplete="off"></div>';
}

function verifyCaptcha() {
    if (!isset($_SESSION['captcha']) || !isset($_POST['captcha'])) return false;
    $ok = (int)$_POST['captcha'] === $_SESSION['captcha'];
    unset($_SESSION['captcha']);
    return $ok;
}

// ============================================================
// INCIDENCIAS (contador para admin)
// ============================================================

function contadorIncidencias() {
    try {
        $db = getDB();
        $st = $db->query("SELECT COUNT(*) as t FROM tickets WHERE tiene_incidencia = 1 AND estado = 'terminado'");
        return $st->fetch()['t'];
    } catch (Exception $e) {
        return 0;
    }
}

// ============================================================
// MIGRACIONES (se ejecutan una sola vez por petición)
// ============================================================

function migraciones() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        // tickets: tiempo_resolucion
        $cols = $db->query("SHOW COLUMNS FROM tickets")->fetchAll();
        $campos = array_column($cols, 'Field');
        if (!in_array('tiempo_resolucion', $campos)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN tiempo_resolucion INT DEFAULT NULL AFTER fecha_cierre");
        }
        // respuestas: leido_admin
        $cols2 = $db->query("SHOW COLUMNS FROM respuestas")->fetchAll();
        $campos2 = array_column($cols2, 'Field');
        if (!in_array('leido_admin', $campos2)) {
            $db->exec("ALTER TABLE respuestas ADD COLUMN leido_admin TINYINT(1) DEFAULT 0 AFTER es_email");
        }
        if (!in_array('leido_cliente', $campos2)) {
            $db->exec("ALTER TABLE respuestas ADD COLUMN leido_cliente TINYINT(1) DEFAULT 0 AFTER leido_admin");
        }
        // usuarios: firma
        $cols3 = $db->query("SHOW COLUMNS FROM usuarios")->fetchAll();
        $campos3 = array_column($cols3, 'Field');
        if (!in_array('firma', $campos3)) {
            $db->exec("ALTER TABLE usuarios ADD COLUMN firma TEXT DEFAULT NULL AFTER telefono");
        }
        // usuarios: sesion_duracion (minutos, NULL = default 30min)
        if (!in_array('sesion_duracion', $campos3)) {
            $db->exec("ALTER TABLE usuarios ADD COLUMN sesion_duracion INT DEFAULT NULL AFTER firma");
        }
        // respuestas_rapidas: tabla
        $tables = $db->query("SHOW TABLES LIKE 'respuestas_rapidas'")->fetchAll();
        if (empty($tables)) {
            $db->exec("CREATE TABLE respuestas_rapidas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                titulo VARCHAR(100) NOT NULL,
                contenido TEXT NOT NULL,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (Exception $e) { /* silencio */ }
}

// ============================================================
// TIEMPO
// ============================================================

function formatMinutos($min) {
    if ($min === null || $min === 0) return '—';
    $h = (int)($min / 60);
    $m = $min % 60;
    if ($h > 0 && $m > 0) return "{$h}h {$m}m";
    if ($h > 0) return "{$h}h";
    return "{$m}m";
}

// ============================================================
// RESPUESTAS NUEVAS (badge admin)
// ============================================================

function contadorRespuestasNuevas() {
    try {
        $db = getDB();
        $st = $db->query("SELECT COUNT(DISTINCT r.ticket_id) as t FROM respuestas r WHERE r.leido_admin = 0 AND r.es_nota_interna = 0");
        return $st->fetch()['t'];
    } catch (Exception $e) {
        return 0;
    }
}

function marcarRespuestaLeidas($ticket_id) {
    try {
        $db = getDB();
        $db->prepare("UPDATE respuestas SET leido_admin = 1 WHERE ticket_id = ? AND es_nota_interna = 0")->execute([$ticket_id]);
    } catch (Exception $e) {}
}

// ============================================================
// RESPUESTAS NUEVAS (badge cliente)
// ============================================================

function contadorRespuestasNuevasCliente($usuario_id) {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT COUNT(DISTINCT r.ticket_id) as t FROM respuestas r JOIN tickets tk ON r.ticket_id = tk.id WHERE tk.usuario_id = ? AND r.leido_cliente = 0 AND r.es_nota_interna = 0 AND r.usuario_id != ?");
        $st->execute([$usuario_id, $usuario_id]);
        return $st->fetch()['t'];
    } catch (Exception $e) {
        return 0;
    }
}

function marcarRespuestasLeidasCliente($ticket_id) {
    try {
        $db = getDB();
        $db->prepare("UPDATE respuestas SET leido_cliente = 1 WHERE ticket_id = ? AND es_nota_interna = 0")->execute([$ticket_id]);
    } catch (Exception $e) {}
}
