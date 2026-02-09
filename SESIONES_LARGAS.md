# Configuración de Sesiones Largas (30 días)

## Problema
Las sesiones se cierran muy rápido y los usuarios pierden las notificaciones tanto en la app como en la versión web.

## Solución Aplicada

### 1. Default cambiado a 30 días
El archivo `includes/funciones.php` ahora tiene un default de **30 días** en vez de 30 minutos:
```php
$minutos = $usuario['sesion_duracion'] ?? 43200; // 30 días
```

### 2. Archivo .htaccess creado
Se ha creado `.htaccess` en la raíz con configuración para sesiones de 30 días.

### 3. Archivo session_config.php
Se ha creado `includes/session_config.php` para configurar sesiones largas.

## Cómo Aplicar en Producción

### Opción 1: Usar .htaccess (RECOMENDADO)
El archivo `.htaccess` ya está configurado. Solo asegúrate de que:
1. El módulo `mod_php` esté habilitado
2. `AllowOverride All` esté configurado en Apache

### Opción 2: Configurar en php.ini
Agregar al archivo `php.ini`:
```ini
session.gc_maxlifetime = 2592000
session.cookie_lifetime = 2592000
```

### Opción 3: Auto-prepend (AVANZADO)
Editar `.htaccess` y descomentar la línea:
```apache
php_value auto_prepend_file "/var/www/vhosts/pequemovil.es/httpdocs/includes/session_config.php"
```
Cambiar la ruta por la ruta completa real del servidor.

## Verificar que Funciona

### 1. Verificar cookies en el navegador
1. Abre DevTools (F12)
2. Ve a Application → Cookies
3. Busca la cookie `PHPSESSID`
4. Verifica que `Expires` sea dentro de 30 días

### 2. Verificar desde PHP
Crear un archivo `test_sesion.php`:
```php
<?php
session_start();
echo "Cookie lifetime: " . ini_get('session.cookie_lifetime') . " segundos<br>";
echo "GC maxlifetime: " . ini_get('session.gc_maxlifetime') . " segundos<br>";
echo "30 días = 2592000 segundos";
```

## Notas Importantes

- Los usuarios que YA tienen sesión activa necesitan **cerrar sesión y volver a entrar** para que se aplique la nueva duración
- Si estás usando HTTPS, cambiar `'secure' => true` en `includes/session_config.php`
- La sesión se renovará automáticamente cada vez que el usuario haga una petición

## Troubleshooting

### Las sesiones aún se cierran
1. Verificar que `.htaccess` se está leyendo: `phpinfo()` y buscar "session.cookie_lifetime"
2. Verificar permisos de carpeta `/tmp` o la carpeta de sesiones de PHP
3. Verificar que no haya otros `.htaccess` que sobrescriban la configuración
4. Contactar con hosting para verificar configuración del servidor
