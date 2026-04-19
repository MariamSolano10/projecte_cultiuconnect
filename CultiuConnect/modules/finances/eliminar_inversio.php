<?php
/**
 * modules/finances/eliminar_inversio.php — Elimina un registre d'inversió/despesa.
 *
 * Fitxer de processament pur: no té vista HTML.
 * Rep la petició via GET (confirmació via data-confirma al JS del llistat),
 * processa l'eliminació i redirigeix sempre al llistat.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_inversio = sanitize_int($_GET['id'] ?? null);
$concepte    = null; // Inicialitzem per evitar variable indefinida al catch

if (!$id_inversio) {
    set_flash('error', 'ID d\'inversió invàlid o no proporcionat.');
    header('Location: ' . BASE_URL . 'modules/finances/inversions.php');
    exit;
}

try {
    $pdo = connectDB();

    // Comprovar existència i obtenir concepte per al missatge
    $check = $pdo->prepare("SELECT concepte FROM inversio WHERE id_inversio = ?");
    $check->execute([$id_inversio]);
    $inversio = $check->fetch();

    if (!$inversio) {
        set_flash('error', 'El registre d\'inversió sol·licitat no existeix.');
        header('Location: ' . BASE_URL . 'modules/finances/inversions.php');
        exit;
    }

    $concepte = $inversio['concepte'];

    $del = $pdo->prepare("DELETE FROM inversio WHERE id_inversio = ?");
    $del->execute([$id_inversio]);

    // Missatge sense e() — header.php ja escapa en mostrar el flash
    set_flash('success', 'La despesa «' . $concepte . '» s\'ha eliminat correctament.');

} catch (Exception $e) {
    error_log('[CultiuConnect] eliminar_inversio.php: ' . $e->getMessage());
    $msg = $concepte
        ? 'Error intern en eliminar «' . $concepte . '». Torna-ho a intentar.'
        : 'Error intern en eliminar el registre d\'inversió.';
    set_flash('error', $msg);
}

header('Location: ' . BASE_URL . 'modules/finances/inversions.php');
exit;