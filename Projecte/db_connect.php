<?php
// DB_Connect.php
// Funció per establir la connexió segura PDO a la Base de Dades.

/**
 * Estableix la connexió a la BD CultiuConnect.
 * @return \PDO Objecte de connexió PDO.
 * @throws \PDOException Si la connexió falla.
 */
function connectDB(): \PDO
{
    // ⚠️ CONFIGURACIÓ: MODIFICA AIXÒ AMB LES TEVES DADES REALs
    $host = 'localhost';
    $db = 'cultiuconnect_bd';  // Nom que has utilitzat per a la teva base de dades
    $user = 'root';              // Usuari de MySQL (habitualment 'root' en local)
    $pass = '';   // Contrasenya de MySQL (sovint buida ' ' en XAMPP/MAMP per defecte)
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        // Mode d'error per llançar excepcions
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Mode d'extracció per defecte (retorna arrays associatius)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Desactiva l'emulació de preparació per a una major seguretat i rendiment
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        // Intenta crear la nova connexió PDO
        return new \PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        // En cas d'error de connexió, atura l'execució i mostra un missatge d'error
        // En producció, només es registraria l'error i es mostraria un missatge genèric.
        throw new Exception("Error de connexió a la Base de Dades: " . $e->getMessage());
    }
}
?>