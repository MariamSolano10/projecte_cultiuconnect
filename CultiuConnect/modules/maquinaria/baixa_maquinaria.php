<?php
/**
 * modules/maquinaria/baixa_maquinaria.php — Elimina (hard delete) una màquina.
 *
 * Fitxer de processament pur: no té vista HTML.
 * Rep la petició via GET (amb confirmació data-confirma al JS del llistat),
 * processa l'eliminació i redirigeix sempre al llistat.
 *
 * Si la màquina té registres associats a altres taules (FK),
 * MySQL llançarà un error 23000 que capturem per mostrar un missatge clar.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_maquina  = sanitize_int($_GET['id'] ?? null);
$nom_maquina = null; // Inicialitzem per evitar variable indefinida al catch

if (!$id_maquina) {
    set_flash('error', 'ID de maquinària invàlid o no proporcionat.');
    header('Location: ' . BASE_URL . 'modules/maquinaria/maquinaria.php');
    exit;
}

try {
    $pdo = connectDB();

    // Comprovar existència i obtenir nom per als missatges
    $check = $pdo->prepare("SELECT nom_maquina FROM maquinaria WHERE id_maquinaria = ?");
    $check->execute([$id_maquina]);
    $maquina = $check->fetch();

    if (!$maquina) {
        set_flash('error', 'La màquina sol·licitada no existeix.');
        header('Location: ' . BASE_URL . 'modules/maquinaria/maquinaria.php');
        exit;
    }

    $nom_maquina = $maquina['nom_maquina'];

    // Eliminació (hard delete)
    $del = $pdo->prepare("DELETE FROM maquinaria WHERE id_maquinaria = ?");
    $del->execute([$id_maquina]);

    // Missatge sense e() — set_flash guarda raw, header.php aplica e() en mostrar-lo
    set_flash('success', 'La màquina «' . $nom_maquina . '» s\'ha donat de baixa correctament.');

} catch (Exception $e) {
    error_log('[CultiuConnect] baixa_maquinaria.php: ' . $e->getMessage());

    // Error de clau forana (23000): la màquina té registres associats
    if ($e->getCode() === '23000') {
        $msg = $nom_maquina
            ? 'No es pot eliminar «' . $nom_maquina . '» perquè té registres associats al quadern de camp o a tasques. Edita-la i marca-la com a inactiva.'
            : 'No es pot eliminar la màquina perquè té registres associats.';
        set_flash('error', $msg);
    } else {
        set_flash('error', 'Error intern en donar de baixa la màquina. Torna-ho a intentar.');
    }
}

header('Location: ' . BASE_URL . 'modules/maquinaria/maquinaria.php');
exit;