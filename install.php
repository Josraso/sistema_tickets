<?php
/**
 * INSTALADOR — Sistema de Tickets
 * Pantalla única multi-paso sin recarga de página.
 */

session_start();

// Si ya existe config.php, ir a login (ya instalado)
if (file_exists(__DIR__ . '/config.php') && !isset($_GET['reinstalar'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$paso = $_SESSION['install_paso'] ?? 1;

// ============ PASO 1: Datos MySQL ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['paso'] ?? '') === '1') {
    $host = $_POST['db_host'] ?? 'localhost';
    $dbname = $_POST['db_nombre'] ?? 'sistema_tickets';
    $user = $_POST['db_usuario'] ?? '';
    $pass = $_POST['db_pass'] ?? '';

    // Validar nombre DB (solo alfanuméricos y guiones bajos)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbname)) {
        $error = 'El nombre de la base de datos solo puede contener letras, números y guiones bajos';
    } else {
        try {
            // Conectar sin seleccionar BD
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Crear BD si no existe
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
            // Guardar en sesión
            $_SESSION['install_db'] = compact('host', 'dbname', 'user', 'pass');
            $_SESSION['install_paso'] = 2;
            header('Location: install.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Error de conexión MySQL: ' . $e->getMessage();
        }
    }
}

// ============ PASO 2: Crear tablas ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['paso'] ?? '') === '2') {
    if (!isset($_SESSION['install_db'])) { $_SESSION['install_paso'] = 1; header('Location: install.php'); exit; }
    $d = $_SESSION['install_db'];
    try {
        $pdo = new PDO("mysql:host={$d['host']};dbname={$d['dbname']};charset=utf8mb4", $d['user'], $d['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                telefono VARCHAR(20),
                rol ENUM('admin','cliente') DEFAULT 'cliente',
                estado ENUM('pendiente','activo','bloqueado') DEFAULT 'pendiente',
                token_recordar VARCHAR(255),
                fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
                ultimo_acceso DATETIME
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS webs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                dominio VARCHAR(255) NOT NULL,
                notas TEXT,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                web_id INT NOT NULL,
                asunto VARCHAR(255) NOT NULL,
                mensaje TEXT NOT NULL,
                estado ENUM('abierto','en_proceso','terminado') DEFAULT 'abierto',
                prioridad ENUM('baja','media','alta','critica') DEFAULT 'media',
                tiene_incidencia TINYINT(1) DEFAULT 0,
                asignado_a INT,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                fecha_cierre DATETIME,
                tiempo_resolucion INT DEFAULT NULL,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
                FOREIGN KEY (web_id) REFERENCES webs(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS respuestas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT NOT NULL,
                usuario_id INT NOT NULL,
                mensaje TEXT NOT NULL,
                es_nota_interna TINYINT(1) DEFAULT 0,
                es_email TINYINT(1) DEFAULT 0,
                leido_admin TINYINT(1) DEFAULT 0,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ticket_id) REFERENCES tickets(id),
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS archivos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT NOT NULL,
                respuesta_id INT,
                nombre_original VARCHAR(255) NOT NULL,
                nombre_guardado VARCHAR(255) NOT NULL,
                extension VARCHAR(10),
                tamanio INT,
                ruta VARCHAR(500),
                fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ticket_id) REFERENCES tickets(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(50) NOT NULL UNIQUE,
                color VARCHAR(7) DEFAULT '#6c757d',
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ticket_tags (
                ticket_id INT NOT NULL,
                tag_id INT NOT NULL,
                PRIMARY KEY (ticket_id, tag_id),
                FOREIGN KEY (ticket_id) REFERENCES tickets(id),
                FOREIGN KEY (tag_id) REFERENCES tags(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS historial_tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT NOT NULL,
                usuario_id INT,
                accion VARCHAR(100) NOT NULL,
                detalles TEXT,
                fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ticket_id) REFERENCES tickets(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS configuracion (
                id INT AUTO_INCREMENT PRIMARY KEY,
                clave VARCHAR(100) NOT NULL UNIQUE,
                valor TEXT,
                tipo ENUM('texto','numero','boolean','json') DEFAULT 'texto',
                fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT,
                accion VARCHAR(100) NOT NULL,
                descripcion TEXT,
                ip VARCHAR(45),
                user_agent VARCHAR(255),
                fecha DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Tags por defecto
        $pdo->exec("INSERT IGNORE INTO tags (nombre, color) VALUES ('Urgente', '#dc3545'), ('Duda', '#0dcaf0'), ('Bug', '#fd7e14'), ('Mejora', '#198754')");

        $_SESSION['install_paso'] = 3;
        header('Location: install.php');
        exit;
    } catch (PDOException $e) {
        $error = 'Error creando tablas: ' . $e->getMessage();
    }
}

// ============ PASO 3: Admin + Configuración ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['paso'] ?? '') === '3') {
    if (!isset($_SESSION['install_db'])) { $_SESSION['install_paso'] = 1; header('Location: install.php'); exit; }
    $d = $_SESSION['install_db'];

    $admin_nombre = $_POST['admin_nombre'] ?? 'Admin';
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_pass = $_POST['admin_pass'] ?? '';
    $empresa = $_POST['empresa_nombre'] ?? 'Sistema de Tickets';
    $dominio_base = $_POST['dominio_base'] ?? '';
    $dominio_mail = $_POST['dominio_mail'] ?? '';
    $max_subida = (int)($_POST['max_subida'] ?? 30);
    $smtp_host = $_POST['smtp_host'] ?? '';
    $smtp_port = (int)($_POST['smtp_port'] ?? 587);
    $smtp_user = $_POST['smtp_user'] ?? '';
    $smtp_pass = $_POST['smtp_pass'] ?? '';
    $smtp_from = $_POST['smtp_from'] ?? '';

    if (empty($admin_email) || empty($admin_pass)) {
        $error = 'Email y contraseña del admin son obligatorios';
    } else {
        try {
            $pdo = new PDO("mysql:host={$d['host']};dbname={$d['dbname']};charset=utf8mb4", $d['user'], $d['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Crear admin
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT IGNORE INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, 'admin', 'activo')")
                ->execute([$admin_nombre, $admin_email, $hash]);

            // Insertar configuración
            $configs = [
                ['empresa_nombre', $empresa],
                ['dominio_base', $dominio_base],
                ['dominio_mail', $dominio_mail],
                ['max_subida', (string)$max_subida],
                ['registro_activo', '1'],
                ['smtp_host', $smtp_host],
                ['smtp_port', (string)$smtp_port],
                ['smtp_user', $smtp_user],
                ['smtp_pass', $smtp_pass],
                ['smtp_from', $smtp_from],
            ];
            foreach ($configs as $c) {
                $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
                    ->execute($c);
            }

            // Generar config.php
            $config_content = '<?php
// ============================================================
// CONFIGURACIÓN DEL SISTEMA — Generado por instalador
// ============================================================

define(\'DB_HOST\', \'' . addslashes($d['host']) . '\');
define(\'DB_NAME\', \'' . addslashes($d['dbname']) . '\');
define(\'DB_USER\', \'' . addslashes($d['user']) . '\');
define(\'DB_PASS\', \'' . addslashes($d['pass']) . '\');
define(\'DB_CHARSET\', \'utf8mb4\');

date_default_timezone_set(\'Europe/Madrid\');
error_reporting(E_ALL);
ini_set(\'display_errors\', 1);

define(\'BASE_PATH\', __DIR__);

require_once BASE_PATH . \'/includes/database.php\';
require_once BASE_PATH . \'/includes/funciones.php\';
require_once BASE_PATH . \'/includes/email.php\';
';
            file_put_contents(__DIR__ . '/config.php', $config_content);

            // Crear carpetas
            @mkdir(__DIR__ . '/uploads', 0775, true);
            @mkdir(__DIR__ . '/logs', 0775, true);

            unset($_SESSION['install_db']);
            $_SESSION['install_paso'] = 99; // finalizado
            header('Location: install.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Si paso 99: mostrar pantalla de fin
if ($paso === 99 || isset($_SESSION['install_paso']) && $_SESSION['install_paso'] === 99) {
    unset($_SESSION['install_paso']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalador — Sistema de Tickets</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.install-card { width: 100%; max-width: 640px; border-radius: 14px; box-shadow: 0 8px 30px rgba(0,0,0,0.2); }
.install-logo { font-size: 2.8rem; color: #fff; margin-bottom: 0.2rem; }
.install-title { color: rgba(255,255,255,0.9); font-weight: 300; font-size: 1rem; margin-bottom: 1.5rem; }
.paso-bar { display: flex; justify-content: center; gap: 8px; margin-bottom: 1.5rem; }
.paso-dot { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.85rem; font-weight: 700; }
.paso-dot.activo { background: #fff; color: #667eea; }
.paso-dot.done { background: #28a745; }
.paso-line { width: 40px; height: 3px; background: rgba(255,255,255,0.3); align-self: center; border-radius: 2px; }
.nav-tabs .nav-link { color: #6c757d; border: none; padding: 0.6rem 1rem; }
.nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; background: transparent; font-weight: 600; }
</style>
</head>
<body>
<div class="container"><div class="row justify-content-center"><div class="col-lg-7">
<div class="text-center mb-3">
<div class="install-logo"><i class="bi bi-ticket-fill"></i></div>
<p class="install-title">Sistema de Tickets — Instalación</p>
<!-- Pasos visuales -->
<div class="paso-bar">
<?php
$p = $_SESSION['install_paso'] ?? 1;
$pn = [1=>'MySQL', 2=>'Tablas', 3=>'Configuración', 99=>'¡Listo!'];
foreach ($pn as $num => $lbl):
    if ($num === 99 && $p < 99) continue;
    $cls = '';
    if ($num < $p) $cls = 'done';
    elseif ($num === $p) $cls = 'activo';
?>
<?php if ($num !== 1 && $num !== 99 && $p >= $num): ?><div class="paso-line"></div><?php endif; ?>
<div class="paso-dot <?=$cls?>"><i class="bi <?=$cls==='done'?'bi-check':'bi-circle'?>"></i></div>
<?php endforeach; ?>
</div>
</div>

<div class="card install-card">
<div class="card-body p-4">
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=$error?></div><?php endif; ?>

<?php
$p = $_SESSION['install_paso'] ?? 1;
if ($p === 99):
?>
<!-- ===== PASO FINAL ===== -->
<div class="text-center">
<div style="font-size:4rem; color:#28a745;"><i class="bi bi-check-circle-fill"></i></div>
<h3 class="text-success">¡Instalación completada!</h3>
<p class="text-muted">El sistema está listo para usar. Ahora:</p>
<ol class="text-start" style="max-width:380px; margin:0 auto;">
<li>Inicia sesión con las credenciales del admin que creaste</li>
<li>Configura el SMTP desde Admin → Configuración → SMTP</li>
<li>Si quieres PIPE de email, revisa Admin → Configuración → PIPE</li>
<li>¡Crea clientes y tickets!</li>
</ol>
<a href="login.php" class="btn btn-primary btn-lg mt-4"><i class="bi bi-box-arrow-in-right"></i> Ir al Login</a>
</div>

<?php elseif ($p === 1): ?>
<!-- ===== PASO 1: MySQL ===== -->
<h5><i class="bi bi-database"></i> Paso 1 — Conexión MySQL</h5>
<form method="post">
<input type="hidden" name="paso" value="1">
<div class="mb-3"><label class="form-label">Host</label><input type="text" name="db_host" class="form-control" value="localhost"></div>
<div class="mb-3"><label class="form-label">Nombre de la base de datos</label><input type="text" name="db_nombre" class="form-control" value="sistema_tickets"><div class="form-text">Se creará automáticamente si no existe</div></div>
<div class="mb-3"><label class="form-label">Usuario MySQL</label><input type="text" name="db_usuario" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Contraseña MySQL</label><input type="password" name="db_pass" class="form-control"></div>
<button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-arrow-right"></i> Siguiente</button>
</form>

<?php elseif ($p === 2): ?>
<!-- ===== PASO 2: Crear tablas ===== -->
<h5 class="text-center"><i class="bi bi-table"></i> Paso 2 — Crear tablas</h5>
<p class="text-center text-muted">Se crearán todas las tablas necesarias en la base de datos.</p>
<ul>
<li>usuarios, webs, tickets, respuestas</li>
<li>archivos, tags, ticket_tags</li>
<li>historial_tickets, configuracion, logs</li>
</ul>
<form method="post"><input type="hidden" name="paso" value="2">
<button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-play-fill"></i> Crear Tablas</button>
</form>

<?php elseif ($p === 3): ?>
<!-- ===== PASO 3: Config ===== -->
<h5><i class="bi bi-gear"></i> Paso 3 — Configuración</h5>

<!-- Tabs de configuración -->
<ul class="nav nav-tabs mb-3">
<li class="nav-item"><a class="nav-link active" id="tAdmin" data-bs-toggle="tab" href="#panAdmin">Admin</a></li>
<li class="nav-item"><a class="nav-link" id="tGeneral" data-bs-toggle="tab" href="#panGeneral">General</a></li>
<li class="nav-item"><a class="nav-link" id="tSMTP" data-bs-toggle="tab" href="#panSMTP">SMTP</a></li>
</ul>
<form method="post"><input type="hidden" name="paso" value="3">
<div class="tab-content">
<!-- Admin -->
<div class="tab-pane fade show active" id="panAdmin">
<div class="mb-3"><label class="form-label"><i class="bi bi-person-circle"></i> Nombre del Admin</label><input type="text" name="admin_nombre" class="form-control" value="Administrador"></div>
<div class="mb-3"><label class="form-label"><i class="bi bi-envelope"></i> Email del Admin *</label><input type="email" name="admin_email" class="form-control" required></div>
<div class="mb-3"><label class="form-label"><i class="bi bi-lock"></i> Contraseña del Admin *</label><input type="password" name="admin_pass" class="form-control" required minlength="6"></div>
</div>
<!-- General -->
<div class="tab-pane fade" id="panGeneral">
<div class="mb-3"><label class="form-label"><i class="bi bi-building"></i> Nombre empresa</label><input type="text" name="empresa_nombre" class="form-control" value="Sistema de Tickets"></div>
<div class="mb-3"><label class="form-label"><i class="bi bi-globe"></i> Dominio base (URL completa)</label><input type="text" name="dominio_base" class="form-control" placeholder="https://tudominio.com"><div class="form-text">Se usa para generar enlaces en emails</div></div>
<div class="mb-3"><label class="form-label"><i class="bi bi-envelope"></i> Dominio mail (para PIPE)</label><input type="text" name="dominio_mail" class="form-control" placeholder="tudominio.com"><div class="form-text">Los emails serán <strong>ticket+ID@aquí</strong></div></div>
<div class="mb-3"><label class="form-label"><i class="bi bi-hdd"></i> Máximo subida archivos (MB)</label><input type="number" name="max_subida" class="form-control" value="30" min="1" max="500"></div>
</div>
<!-- SMTP -->
<div class="tab-pane fade" id="panSMTP">
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> Puedes dejarlo en blanco y configurarlo después desde Admin → Configuración</div>
<div class="mb-3"><label class="form-label">Host SMTP</label><input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com"></div>
<div class="mb-3"><label class="form-label">Puerto</label><input type="number" name="smtp_port" class="form-control" value="587"></div>
<div class="mb-3"><label class="form-label">Usuario SMTP</label><input type="email" name="smtp_user" class="form-control"></div>
<div class="mb-3"><label class="form-label">Contraseña SMTP</label><input type="password" name="smtp_pass" class="form-control"></div>
<div class="mb-3"><label class="form-label">Email remitente (From)</label><input type="email" name="smtp_from" class="form-control"></div>
</div>
</div>
<button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-check-lg"></i> Finalizar Instalación</button>
</form>
<?php endif; ?>
</div>
</div>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
