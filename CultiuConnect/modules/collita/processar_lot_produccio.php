<?php
/**
 * modules/collita/processar_lot_produccio.php — PRG crear lot_produccio.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/collita/lot_produccio.php');
    exit;
}

$id_collita     = sanitize_int($_POST['id_collita'] ?? null);
$identificador  = sanitize($_POST['identificador'] ?? '');
$data_processat = sanitize($_POST['data_processat'] ?? '');
$pes_kg         = sanitize_decimal($_POST['pes_kg'] ?? null);
$qualitat       = sanitize($_POST['qualitat'] ?? 'Primera');
$desti          = sanitize($_POST['desti'] ?? '');
$client_final   = sanitize($_POST['client_final'] ?? '');

$errors = [];
if (!$id_collita) $errors[] = 'Cal seleccionar una collita.';
if ($identificador === '') $errors[] = 'L\'identificador del lot és obligatori.';
if ($data_processat !== '' && !strtotime($data_processat)) $errors[] = 'La data de processat no és vàlida.';
if ($pes_kg !== null && $pes_kg < 0) $errors[] = 'El pes no pot ser negatiu.';
if (!in_array($qualitat, ['Extra','Primera','Segona','Industrial'], true)) $errors[] = 'La qualitat no és vàlida.';

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    header('Location: ' . BASE_URL . 'modules/collita/nou_lot_produccio.php?collita_id=' . (int)$id_collita);
    exit;
}

try {
    $pdo = connectDB();

    // Verificar collita existent
    $chk = $pdo->prepare("SELECT id_collita FROM collita WHERE id_collita = :id AND data_fi IS NOT NULL");
    $chk->execute([':id' => $id_collita]);
    if (!$chk->fetch()) {
        throw new RuntimeException('La collita seleccionada no existeix o no està finalitzada.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO lot_produccio
            (id_collita, identificador, data_processat, pes_kg, qualitat, desti, client_final)
        VALUES
            (:id_collita, :identificador, :data_processat, :pes_kg, :qualitat, :desti, :client_final)
    ");
    $stmt->execute([
        ':id_collita'     => $id_collita,
        ':identificador'  => $identificador,
        ':data_processat' => $data_processat !== '' ? $data_processat : null,
        ':pes_kg'         => $pes_kg,
        ':qualitat'       => $qualitat,
        ':desti'          => $desti !== '' ? $desti : null,
        ':client_final'   => $client_final !== '' ? $client_final : null,
    ]);

    set_flash('success', 'Lot de producció creat. Ja pots generar el QR de traçabilitat.');
    header('Location: ' . BASE_URL . 'modules/collita/lot_produccio.php?collita_id=' . (int)$id_collita);
    exit;

} catch (RuntimeException $e) {
    error_log('[CultiuConnect] processar_lot_produccio.php runtime: ' . $e->getMessage());
    set_flash('error', 'No s\'ha pogut crear el lot de producció.');
    header('Location: ' . BASE_URL . 'modules/collita/nou_lot_produccio.php?collita_id=' . (int)$id_collita);
    exit;
} catch (Exception $e) {
    if ($e->getCode() === '23000') {
        set_flash('error', 'Ja existeix un lot amb aquest identificador.');
    } else {
        error_log('[CultiuConnect] processar_lot_produccio.php: ' . $e->getMessage());
        set_flash('error', 'Error intern en crear el lot.');
    }
    header('Location: ' . BASE_URL . 'modules/collita/nou_lot_produccio.php?collita_id=' . (int)$id_collita);
    exit;
}

