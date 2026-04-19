<?php
/**
 * Configuració de la base de dades — CultiuConnect
 *
 * Copia aquest fitxer a config/db_config.php i ajusta els valors si cal.
 * Per defecte ja funciona amb Docker Compose sense canviar res.
 */

// Detecció d'entorn: Docker o Local (XAMPP, WAMP, etc.)
$isDocker = gethostbyname('db') !== 'db';

define('DB_HOST',    $isDocker ? 'db'          : 'localhost');
define('DB_USER',    $isDocker ? 'cultiu_user'  : 'root');
define('DB_PASS',    $isDocker ? 'cultiu_pass'  : '');
define('DB_NAME',    'cultiuconnect');
define('DB_CHARSET', 'utf8mb4');

// Fallback dinàmic: agafa el nom real de la carpeta arrel del projecte
$fallback_url = '/' . basename(realpath(__DIR__ . '/..')) . '/';

// URL base dinàmica amb fallback
if (!defined('BASE_URL')) {
    if (!isset($_SERVER['DOCUMENT_ROOT'])) {
        // Entorn CLI o sense servidor web: fallback directe
        define('BASE_URL', $fallback_url);
    } else {
        $doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
        $app_root = str_replace('\\', '/', realpath(__DIR__ . '/..'));

        if ($doc_root && $app_root && strpos($app_root, $doc_root) === 0) {
            $base_dir = substr($app_root, strlen($doc_root));
            define('BASE_URL', rtrim($base_dir, '/') . '/');
        } else {
            // Fallback segur basat en el nom real de la carpeta
            define('BASE_URL', $fallback_url);
        }
    }
}
