# Sistema de Tickets de Soporte Técnico

Sistema web de tickets multi-cliente y multi-web en PHP + MySQL. Orientado a dar soporte técnico a páginas web de distintos clientes.

## Requisitos

- PHP 7.4+
- MySQL / MariaDB
- Extensiones PHP: `pdo`, `pdo_mysql`, `session`, `json`, `openssl`
- Extensión `imap` (opcional, solo si usas polling IMAP)

## Instalación

1. Sube todos los archivos al servidor (la raíz del proyecto va directamente a `public_html` o equivalente, **sin carpeta `public`**)
2. Asegúrate de que la carpeta `uploads/` y `logs/` tienen permisos de escritura (775)
3. Abre en el navegador la URL raíz. Si `config.php` no existe, aparece automáticamente el instalador
4. Sigue los pasos del instalador:
   - **Paso 1:** Datos de conexión MySQL (host, nombre BD, usuario, contraseña)
   - **Paso 2:** Creación automática de tablas
   - **Paso 3:** Configuración: admin, empresa, dominio, SMTP (opcional durante la instalación, se puede completar después)
5. El instalador genera `config.php` automáticamente

## Estructura de carpetas

```
/
├── admin/                  Panel de administración
│   ├── includes/
│   │   ├── header.php
│   │   └── footer.php
│   ├── index.php           Dashboard admin
│   ├── tickets.php         Listado tickets (con filtros + export CSV)
│   ├── ver_ticket.php      Ver/gestionar ticket
│   ├── clientes.php        Gestión clientes
│   ├── webs.php            Gestión webs
│   ├── tags.php            Gestión tags
│   ├── configuracion.php   Panel configuración (SMTP, plantillas, etc)
│   ├── estadisticas.php    Estadísticas y datos
│   └── impersonar.php      Entrar como cliente
├── includes/
│   ├── database.php        Singleton PDO
│   ├── funciones.php       Funciones globales (CSRF, captcha, archivos, tags, logs...)
│   ├── email.php           Sistema de envío email + plantillas
│   ├── header.php          Header HTML cliente
│   └── footer.php          Footer HTML cliente
├── assets/
│   └── style.css           Estilos custom
├── uploads/                Archivos subidos (jpg, png, pdf, zip, rar)
├── logs/                   Logs del sistema (pipe, imap)
├── index.php               Router principal
├── login.php               Inicio de sesión (con captcha + CSRF)
├── registro.php            Registro clientes (con captcha + CSRF)
├── logout.php              Cierre sesión
├── dashboard.php           Dashboard cliente
├── tickets.php             Mis tickets (con filtros)
├── nuevo_ticket.php        Crear ticket (tags, archivos, CSRF)
├── ver_ticket.php          Ver ticket + responder + incidencias + historial
├── webs.php                Gestión webs del cliente
├── fin_impersonar.php      Fin impersonación (volver a admin)
├── install.php             Instalador
├── diagnostico.php         Diagnóstico del servidor
├── pipe.php                Script PIPE para recepción de emails por servidor
├── imap_poll.php           Polling IMAP (alternativa al pipe)
├── config.php              Generado por instalador (no subir al git)
└── README.md               Este archivo
```

## Funcionalidades

### Usuarios y Roles
- **Admin:** gestión completa, responder tickets, notas internas, impersonar clientes, configuración
- **Cliente:** crear webs, abrir tickets, responder, reportar incidencias
- Registro libre (activable/desactivable desde admin)
- Aprobación manual por admin (pendiente → activo / bloqueado)
- Impersonación: el admin puede entrar como cualquier cliente sin contraseña

### Webs
- Cada cliente puede añadir múltiples webs (nombre, dominio, notas)
- Todos los tickets deben estar asociados a una web

### Tickets
- **Estados:** Abierto → En Proceso → Terminado
- **Prioridades:** Baja, Media, Alta, Crítica
- **Tags:** asignables al crear, gestionables desde admin
- **Archivos:** subida de jpg, png, gif, zip, rar, pdf (máximo configurable, default 30 MB)
- **Filtros:** estado, web, prioridad, tag, texto libre, cliente (admin)
- **Historial automático:** registra cada cambio de estado, respuesta, nota interna, incidencia
- **Exportar CSV:** desde el listado de admin con los filtros activos

### Incidencias
- Cuando un ticket está en "Terminado", el cliente solo ve un botón: **"Tengo una Incidencia"**
- Ese botón **no reabre** el ticket. Solo marca un flag
- Se genera automáticamente:
  - Email de aviso al admin
  - Alerta visual (fila roja) en el listado admin
  - Badge con contador en el navbar admin
  - Etiqueta visible en el ticket

### Notas Internas
- El admin puede añadir notas invisibles al cliente al responder un ticket
- Se muestran diferenciadas visualmente (estilo gris con etiqueta "Nota interna")

### Sistema de Correo (SMTP)
- Envío automático de emails en:
  - Nuevo ticket creado (al admin y al cliente)
  - Respuesta del admin (al cliente)
  - Ticket cerrado (al cliente)
  - Incidencia reportada (al admin)
- **Plantillas editables** desde Admin → Configuración → Plantillas Email
- Variables dinámicas: `{{id}}`, `{{asunto}}`, `{{mensaje}}`, `{{web}}`, `{{prioridad}}`, `{{cliente}}`, `{{respuesta}}`, `{{url}}`, `{{url_admin}}`, `{{empresa}}`

### Sistema PIPE — Responder tickets por email

Cada ticket tiene una dirección de respuesta automática:

```
ticket+123@tudominio.com
```

Responder a ese email añade automáticamente la respuesta al ticket (incluyendo adjuntos).

#### Método 1: Pipe del servidor (recomendado)

Para servidores con Postfix o Exim:

```bash
# /etc/aliases (Postfix)
default: |"/usr/bin/php /var/www/html/pipe.php"

# Recargar aliases
newaliases
```

Para Exim:
```
# /etc/exim3/conf.d/main/00_exim_daemon_options (o equivalente)
# Añadir un router que pipe todos los emails a:
/usr/bin/php /var/www/html/pipe.php
```

#### Método 2: IMAP Polling (cron)

Si no tienes acceso al MTA del servidor:

1. Crea una cuenta email dedicada (ej: `soporte@empresa.com`)
2. Edita `imap_poll.php` con las credenciales IMAP de esa cuenta
3. Añade al cron:
```
* * * * * php /var/www/html/imap_poll.php >> /var/log/imap_poll.log 2>&1
```

El script se ejecuta cada minuto, comprueba emails nuevos, extrae el ID del ticket del destinatario y añade la respuesta.

### Seguridad
- Contraseñas hasheadas con `password_hash` (bcrypt)
- **CSRF** en todos los formularios
- **Captcha matemático** en login y registro
- Validación de inputs (sanitización HTML)
- Sesiones protegidas con control de rol
- Extensiones de archivo permitidas whitelist (jpg, png, gif, zip, rar, pdf)
- Logs de todas las acciones

### Configuración (Admin → Configuración)
- Nombre empresa
- Dominio base y dominio mail
- Máximo de subida de archivos
- Activar/desactivar registro libre
- SMTP completo
- Plantillas email editables
- Información sobre configuración PIPE

## .gitignore recomendado

Añadir al `.gitignore`:
```
config.php
uploads/*
!uploads/.gitkeep
logs/*
!logs/.gitkeep
```

## Licencia

Uso interno.
