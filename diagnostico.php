<?php
// DIAGNÓSTICO DEL SERVIDOR
echo "<h1>DIAGNÓSTICO DEL SISTEMA</h1>";

echo "<h2>1. Versión PHP</h2>";
echo "Versión: " . phpversion() . "<br>";
if (version_compare(phpversion(), '7.4.0', '>=')) {
    echo "✅ PHP 7.4+ OK<br>";
} else {
    echo "❌ ERROR: Necesitas PHP 7.4 o superior<br>";
}

echo "<h2>2. Extensiones PHP</h2>";
$extensiones = ['pdo', 'pdo_mysql', 'session', 'json'];
foreach ($extensiones as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext instalado<br>";
    } else {
        echo "❌ $ext NO instalado<br>";
    }
}

echo "<h2>3. Permisos de Escritura</h2>";
$carpetas = ['.', 'uploads'];
foreach ($carpetas as $dir) {
    if (is_writable($dir)) {
        echo "✅ $dir tiene permisos de escritura<br>";
    } else {
        echo "❌ $dir NO tiene permisos de escritura<br>";
    }
}

echo "<h2>4. Archivos Requeridos</h2>";
$archivos = ['database.sql', 'includes/database.php', 'includes/funciones.php'];
foreach ($archivos as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file NO existe<br>";
    }
}

echo "<h2>5. Configuración PHP</h2>";
echo "display_errors: " . ini_get('display_errors') . "<br>";
echo "error_reporting: " . error_reporting() . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";

echo "<h2>6. Probar Conexión MySQL</h2>";
?>
<form method="post">
    <input type="text" name="host" placeholder="localhost" value="localhost"><br>
    <input type="text" name="user" placeholder="usuario"><br>
    <input type="password" name="pass" placeholder="contraseña"><br>
    <button type="submit">Probar Conexión</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=" . $_POST['host'], $_POST['user'], $_POST['pass']);
        echo "<p style='color:green'>✅ CONEXIÓN MYSQL OK!</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ ERROR: " . $e->getMessage() . "</p>";
    }
}
?>

<h2>7. Errores PHP Recientes</h2>
<?php
if (file_exists('error_log')) {
    echo "<pre>" . htmlspecialchars(file_get_contents('error_log')) . "</pre>";
} else {
    echo "No hay archivo error_log";
}
?>
