<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists(__DIR__ . '/config.php')) {
    die('Ya está instalado. Borra config.php para reinstalar');
}

session_start();

if (isset($_POST['paso1'])) {
    try {
        $host = $_POST['db_host'];
        $name = $_POST['db_name'];
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];
        
        $pdo = new PDO("mysql:host=$host", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name`");
        $pdo->exec("USE `$name`");
        
        // SQL directo aquí
        $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100),
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            telefono VARCHAR(20),
            rol ENUM('admin','cliente') DEFAULT 'cliente',
            estado ENUM('pendiente','activo','bloqueado') DEFAULT 'pendiente',
            token_recordar VARCHAR(255),
            fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
            ultimo_acceso DATETIME
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS webs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            nombre VARCHAR(150),
            dominio VARCHAR(255),
            notas TEXT,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            web_id INT,
            asunto VARCHAR(255),
            mensaje TEXT,
            estado ENUM('abierto','en_proceso','terminado') DEFAULT 'abierto',
            prioridad ENUM('baja','media','alta','critica') DEFAULT 'media',
            tiene_incidencia TINYINT(1) DEFAULT 0,
            asignado_a INT,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_cierre DATETIME
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS respuestas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT,
            usuario_id INT,
            mensaje TEXT,
            es_nota_interna TINYINT(1) DEFAULT 0,
            es_email TINYINT(1) DEFAULT 0,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS archivos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT,
            respuesta_id INT,
            nombre_original VARCHAR(255),
            nombre_guardado VARCHAR(255),
            extension VARCHAR(10),
            tamanio INT,
            ruta VARCHAR(500),
            fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) UNIQUE,
            color VARCHAR(7) DEFAULT '#6c757d',
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_tags (
            ticket_id INT,
            tag_id INT,
            PRIMARY KEY (ticket_id, tag_id)
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS historial_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT,
            usuario_id INT,
            accion VARCHAR(100),
            detalles TEXT,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS configuracion (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clave VARCHAR(100) UNIQUE,
            valor TEXT,
            tipo ENUM('texto','numero','boolean','json') DEFAULT 'texto',
            fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            accion VARCHAR(100),
            descripcion TEXT,
            ip VARCHAR(45),
            user_agent VARCHAR(255),
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Configuración
        $pdo->exec("INSERT INTO configuracion (clave, valor) VALUES ('sitio_nombre', 'Sistema de Tickets')");
        $pdo->exec("INSERT INTO configuracion (clave, valor) VALUES ('registro_activo', '1')");
        
        // Tags
        $pdo->exec("INSERT INTO tags (nombre, color) VALUES ('Urgente', '#dc3545')");
        $pdo->exec("INSERT INTO tags (nombre, color) VALUES ('Duda', '#0dcaf0')");
        
        $_SESSION['db'] = compact('host','name','user','pass');
        header('Location: install.php?paso=2');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (isset($_POST['paso2'])) {
    $db = $_SESSION['db'];
    $nombre = $_POST['admin_nombre'];
    $email = $_POST['admin_email'];
    $password = $_POST['admin_password'];
    
    try {
        $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']}", $db['user'], $db['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, 'admin', 'activo')");
        $stmt->execute([$nombre, $email, $hash]);
        
        $config = "<?php\ndefine('DB_HOST', '{$db['host']}');\ndefine('DB_NAME', '{$db['name']}');\ndefine('DB_USER', '{$db['user']}');\ndefine('DB_PASS', '{$db['pass']}');\ndefine('DB_CHARSET', 'utf8mb4');\ndate_default_timezone_set('Europe/Madrid');\nerror_reporting(E_ALL);\nini_set('display_errors', 1);\ndefine('BASE_PATH', __DIR__);\nrequire_once BASE_PATH . '/includes/funciones.php';\nrequire_once BASE_PATH . '/includes/database.php';\n";
        
        file_put_contents(__DIR__ . '/config.php', $config);
        
        $instalado = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$paso = $_GET['paso'] ?? 1;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Instalador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#667eea;padding:50px 0;}</style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white text-center">
                    <h2>Instalador Sistema de Tickets</h2>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">ERROR: <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($instalado)): ?>
                        <div class="alert alert-success">
                            <h4>✅ ¡INSTALACIÓN COMPLETADA!</h4>
                            <p>Usuario: <?php echo htmlspecialchars($email); ?></p>
                            <p>Contraseña: (la que pusiste)</p>
                        </div>
                        <a href="index.php" class="btn btn-primary btn-lg w-100">IR AL SISTEMA</a>
                        
                    <?php elseif ($paso == 1): ?>
                        <h4>Paso 1: Base de Datos MySQL</h4>
                        <form method="post">
                            <div class="mb-3">
                                <label>Servidor MySQL:</label>
                                <input type="text" name="db_host" class="form-control" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label>Nombre Base de Datos:</label>
                                <input type="text" name="db_name" class="form-control" value="tickets_db" required>
                                <small>Se creará automáticamente</small>
                            </div>
                            <div class="mb-3">
                                <label>Usuario MySQL:</label>
                                <input type="text" name="db_user" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Contraseña MySQL:</label>
                                <input type="password" name="db_pass" class="form-control">
                            </div>
                            <button type="submit" name="paso1" class="btn btn-primary w-100">SIGUIENTE</button>
                        </form>
                        
                    <?php else: ?>
                        <h4>Paso 2: Crear Administrador</h4>
                        <form method="post">
                            <div class="mb-3">
                                <label>Nombre:</label>
                                <input type="text" name="admin_nombre" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Email:</label>
                                <input type="email" name="admin_email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Contraseña:</label>
                                <input type="password" name="admin_password" class="form-control" required>
                            </div>
                            <button type="submit" name="paso2" class="btn btn-success w-100">INSTALAR</button>
                        </form>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
