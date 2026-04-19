<?php

/**
 * modules/monitoratge/processar_monitoratge.php — Processador del formulari de monitoratge.
 *
 * PRG: Èxit → historial_monitoratge.php | Error → monitoratge.php
 *
 * Camps inserits a monitoratge_plaga:
 *   id_sector, data_observacio, tipus_problema,
 *   descripcio_breu, nivell_poblacio, llindar_intervencio_assolit
 *
 * NOTA: element_observat NO existeix a BD — eliminat de l'INSERT.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/monitoratge/monitoratge.php');
    exit;
}

// -----------------------------------------------------------
// 1. Recollida i sanejament
// -----------------------------------------------------------
$id_sector = sanitize_int($_POST['id_sector'] ?? null);
$data_obs = sanitize($_POST['data_observacio'] ?? '');
$hora_obs = sanitize($_POST['hora_observacio'] ?? '');
$tipus = sanitize($_POST['tipus_problema'] ?? '');
$nivell = sanitize_decimal($_POST['nivell_poblacio'] ?? null);
$descripcio = sanitize($_POST['descripcio_breu'] ?? '');
$llindar = isset($_POST['llindar_intervencio_assolit']) ? 1 : 0;

// -----------------------------------------------------------
// 2. Validació
// -----------------------------------------------------------
$errors = [];

if (!$id_sector || $id_sector <= 0) {
    $errors[] = 'Cal seleccionar un sector d\'observació.';
}
if (empty($data_obs) || !strtotime($data_obs)) {
    $errors[] = 'La data d\'observació no és vàlida.';
}
$tipus_valids = ['Plaga', 'Malaltia', 'Deficiencia', 'Mala Herba'];
if (empty($tipus) || !in_array($tipus, $tipus_valids)) {
    $errors[] = 'Cal seleccionar un tipus de problema vàlid.';
}
if (empty($descripcio)) {
    $errors[] = 'La descripció de l\'observació és obligatòria.';
}

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    header('Location: ' . BASE_URL . 'modules/monitoratge/monitoratge.php');
    exit;
}

// -----------------------------------------------------------
// 3. Construir DATETIME combinant data + hora
// -----------------------------------------------------------
$hora_neta = !empty($hora_obs) ? $hora_obs . ':00' : '00:00:00';
$datetime_obs = $data_obs . ' ' . $hora_neta;

// -----------------------------------------------------------
// 4. Inserció — ÚNICAMENT els camps que existeixen a BD
// -----------------------------------------------------------
try {
    $pdo = connectDB();

    $stmt = $pdo->prepare("
        INSERT INTO monitoratge_plaga
            (id_sector, data_observacio, tipus_problema,
             descripcio_breu, nivell_poblacio, llindar_intervencio_assolit)
        VALUES
            (:id_sector, :data_observacio, :tipus_problema,
             :descripcio_breu, :nivell_poblacio, :llindar)
    ");

    $stmt->execute([
        ':id_sector' => $id_sector,
        ':data_observacio' => $datetime_obs,
        ':tipus_problema' => $tipus,
        ':descripcio_breu' => substr($descripcio, 0, 255),
        ':nivell_poblacio' => $nivell,   // NULL si no s'ha introduït
        ':llindar' => $llindar,
    ]);

    $msg = $llindar
        ? 'Observació registrada. S\'ha activat una alerta de llindar d\'intervenció.'
        : 'Observació de monitoratge registrada correctament.';

    set_flash('success', $msg);
    header('Location: ' . BASE_URL . 'modules/monitoratge/historial_monitoratge.php');
    exit;

} catch (Exception $e) {
    // Registrem l'error de forma invisible per a l'usuari
    error_log('[CultiuConnect] processar_monitoratge.php: ' . $e->getMessage());

    // Mostrem un missatge amigable
    set_flash('error', 'Error intern en registrar l\'observació. Torna-ho a intentar.');
    header('Location: ' . BASE_URL . 'modules/monitoratge/monitoratge.php');
    exit;
}