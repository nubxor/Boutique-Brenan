<?php
/**
 * GALLETITAS - CONFIGURACIÓN GENERAL
 * Edita estos datos con la información de tu hosting.
 */

declare(strict_types=1);

// Datos de conexión MySQL.
define('DB_HOST', 'localhost');
define('DB_NAME', 'galletitas_catalogo');
define('DB_USER', 'TU_USUARIO_MYSQL');
define('DB_PASS', 'TU_PASSWORD_MYSQL');
define('DB_CHARSET', 'utf8mb4');

// URL base del catálogo.
// Si lo instalas en la raíz del dominio, deja: ''
// Si lo instalas en una carpeta, por ejemplo /catalogo, escribe: '/catalogo'
define('BASE_URL', '');

// Carpeta donde se guardan las imágenes.
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

// Tamaño máximo de imagen en bytes. 5 MB.
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);

// Usuario inicial del panel.
// IMPORTANTE: cambia esta contraseña después de instalar.
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');

// Seguridad de sesión.
define('SESSION_NAME', 'galletitas_admin_session');
