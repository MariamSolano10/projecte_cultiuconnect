<?php
/**
 * config/db.php — Connexió PDO a la Base de Dades CultiuConnect.
 *
 * Ús:
 *   require_once __DIR__ . '/../config/db.php';
 *   $pdo = connectDB();
 *
 * Retorna sempre la mateixa instència PDO (patró Singleton):
 * no importa quantes vegades es cridi connectDB() en una mateixa
 * petició, només s'obre una connexió a la base de dades.
 */

require_once __DIR__ . '/db_config.php';

function connectDB(): PDO
{
    // Variable estàtica: es crea només en la primera crida
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Llança excepcions en cas d'error SQL
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Resultats com a arrays associatius
        PDO::ATTR_EMULATE_PREPARES   => false,                    // Sentències preparades natives (més segures)
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci", // Forçar charset
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // En producció: registra l'error en un log intern, no el mostris mai a l'usuari
        error_log('[CultiuConnect] Error de connexió BD: ' . $e->getMessage());
        throw new RuntimeException('No s\'ha pogut connectar a la base de dades. Contacta amb l\'administrador.');
    }
}