<?php
/**
 * modules/collita/processar_nova_collita.php — Processador del formulari de collites.
 *
 * Rep les dades via POST des de nova_collita.php, les valida i les insereix
 * a la BD amb una transacció segura:
 *   1. INSERT a `collita`
 *   2. INSERT a `collita_treballador` per a cada treballador seleccionat
 *
 * Patró PRG: sempre redirigeix, mai mostra HTML.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/collita/collita.php');
    exit;
}

// -----------------------------------------------------------
// 1. Recollida i sanejament
// -----------------------------------------------------------
$id_plantacio  = sanitize_int($_POST['id_plantacio']   ?? null);
$data_inici    = sanitize($_POST['data_inici']         ?? '');
$data_fi       = sanitize($_POST['data_fi']            ?? '');
$quantitat     = sanitize_decimal($_POST['quantitat']  ?? null);
$unitat_mesura = sanitize($_POST['unitat_mesura']      ?? 'kg');
$qualitat      = sanitize($_POST['qualitat']           ?? '');
$observacions  = sanitize($_POST['observacions']       ?? '');

// Treballadors:
// - Format nou (UI actual): id_treballador + hores_treballades (un sol responsable)
// - Format antic: arrays 'treballadors' i 'hores_treballades'
$id_treballador_single = sanitize_int($_POST['id_treballador'] ?? null);
$hores_single          = sanitize_decimal($_POST['hores_treballades'] ?? null);

$treballadors_raw = $_POST['treballadors']      ?? [];
$hores_raw        = $_POST['hores_treballades'] ?? [];

if ($id_treballador_single) {
    $treballadors_raw = [$id_treballador_single];
    $hores_raw        = [$hores_single];
}

// Neteja d'IDs de treballadors
$treballadors = array_filter(
    array_map(fn($id) => sanitize_int($id), $treballadors_raw)
);

// -----------------------------------------------------------
// 2. Validació
// -----------------------------------------------------------
$errors = [];

$qualitats_valides   = ['Extra', 'Primera', 'Segona', 'Industrial', 'Excel·lent'];
$unitats_valides     = ['kg', 't', 'caixes', 'Kg', 'T'];

if (!$id_plantacio) {
    $errors[] = 'Has de seleccionar una plantació (sector/varietat) vàlida.';
}
if (empty($data_inici) || !strtotime($data_inici)) {
    $errors[] = 'La data d\'inici de la collita és obligatòria i ha de ser vàlida.';
}
if (!empty($data_fi) && strtotime($data_fi) < strtotime($data_inici)) {
    $errors[] = 'La data de fi no pot ser anterior a la data d\'inici.';
}
if ($quantitat === null || $quantitat <= 0) {
    $errors[] = 'La quantitat collida ha de ser un número superior a 0.';
}
if (!in_array($unitat_mesura, $unitats_valides)) {
    $errors[] = 'La unitat de mesura no és vàlida.';
}
if (!in_array($qualitat, $qualitats_valides)) {
    $errors[] = 'La qualitat seleccionada no és vàlida.';
}

// Validar hores si hi ha treballadors seleccionats
foreach ($treballadors as $idx => $id_t) {
    $hores = sanitize_decimal($hores_raw[$idx] ?? null);
    if ($hores === null || $hores <= 0) {
        $errors[] = 'Tots els treballadors assignats han de tenir hores treballades vàlides.';
        break;
    }
}

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    header('Location: ' . BASE_URL . 'modules/collita/nova_collita.php');
    exit;
}

// -----------------------------------------------------------
// 3. Transacció: collita + collita_treballador
// -----------------------------------------------------------
try {
    $pdo = connectDB();
    $pdo->beginTransaction();

    // A. Inserir la collita
    $stmt = $pdo->prepare("
        INSERT INTO collita
            (id_plantacio, data_inici, data_fi, quantitat,
             unitat_mesura, qualitat, observacions)
        VALUES
            (:id_plantacio, :data_inici, :data_fi, :quantitat,
             :unitat_mesura, :qualitat, :observacions)
    ");
    $stmt->execute([
        ':id_plantacio'  => $id_plantacio,
        ':data_inici'    => $data_inici,
        ':data_fi'       => !empty($data_fi) ? $data_fi : null,
        ':quantitat'     => $quantitat,
        ':unitat_mesura' => $unitat_mesura,
        ':qualitat'      => $qualitat,
        ':observacions'  => $observacions ?: null,
    ]);

    $id_collita = (int)$pdo->lastInsertId();

    // B. Vincular treballadors (si n'hi ha)
    if (!empty($treballadors)) {
        $stmt_t = $pdo->prepare("
            INSERT INTO collita_treballador
                (id_collita, id_treballador, hores_treballades)
            VALUES
                (:id_collita, :id_treballador, :hores)
        ");

        foreach ($treballadors as $idx => $id_t) {
            $hores = (float)sanitize_decimal($hores_raw[$idx] ?? null);
            $stmt_t->execute([
                ':id_collita'     => $id_collita,
                ':id_treballador' => $id_t,
                ':hores'          => $hores,
            ]);
        }
    }

    $pdo->commit();

    // Missatge sense e() — header.php escapa en mostrar el flash
    set_flash('success',
        'La collita s\'ha registrat correctament: ' .
        number_format($quantitat, 2, ',', '.') . ' ' . $unitat_mesura .
        ' · Qualitat: ' . $qualitat . '.'
    );
    header('Location: ' . BASE_URL . 'modules/collita/collita.php');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[CultiuConnect] processar_nova_collita.php: ' . $e->getMessage());
    set_flash('error', 'Error crític en desar la collita. Cap dada ha estat alterada.');
    header('Location: ' . BASE_URL . 'modules/collita/nova_collita.php');
    exit;
}