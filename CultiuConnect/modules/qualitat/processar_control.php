<?php
/**
 * modules/qualitat/processar_control.php
 *
 * Backend per a les operacions CRUD del mòdul de qualitat:
 *   POST accio=crear   → Insereix un nou control_qualitat
 *   POST accio=editar  → Actualitza un control_qualitat existent
 *   GET  accio=eliminar&id=X → Elimina el registre (amb confirmació JS al llistat)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// ── Helper: redirigeix amb missatge flash ────────────────────────────────────
function redir(string $url, string $tipus, string $msg): never
{
    set_flash($tipus, $msg);
    header('Location: ' . $url);
    exit;
}

$url_llista = BASE_URL . 'modules/qualitat/qualitat_lots.php';
$url_form   = BASE_URL . 'modules/qualitat/nou_control.php';

// ── Determina l'acció ────────────────────────────────────────────────────────
$accio = $_POST['accio'] ?? $_GET['accio'] ?? '';

try {
    $pdo = connectDB();

    // ════════════════════════════════════════════════════════════════════════
    // CREAR
    // ════════════════════════════════════════════════════════════════════════
    if ($accio === 'crear') {

        // Validació mínima
        $id_lot       = filter_input(INPUT_POST, 'id_lot',      FILTER_VALIDATE_INT);
        $data_control = trim($_POST['data_control'] ?? '');
        $resultat     = trim($_POST['resultat']     ?? '');

        $resultats_valids = ['Acceptat', 'Condicional', 'Rebutjat'];

        if (!$id_lot || $id_lot <= 0) {
            redir($url_form, 'error', 'Has de seleccionar un lot de producció.');
        }
        if (!$data_control || !strtotime($data_control)) {
            redir($url_form, 'error', 'La data del control no és vàlida.');
        }
        if (!in_array($resultat, $resultats_valids, true)) {
            redir($url_form, 'error', 'El resultat seleccionat no és vàlid.');
        }

        // Recull i neteja els camps opcionals
        $id_inspector   = filter_input(INPUT_POST, 'id_inspector',   FILTER_VALIDATE_INT) ?: null;
        $calibre_mm     = filter_input(INPUT_POST, 'calibre_mm',     FILTER_VALIDATE_FLOAT) ?: null;
        $fermesa        = filter_input(INPUT_POST, 'fermesa_kg_cm2', FILTER_VALIDATE_FLOAT) ?: null;
        $color          = trim($_POST['color']            ?? '') ?: null;
        $defectes       = trim($_POST['defectes_visibles']?? '') ?: null;
        $sabor          = trim($_POST['sabor']            ?? '') ?: null;
        $aroma          = trim($_POST['aroma']            ?? '') ?: null;
        $textura        = trim($_POST['textura']          ?? '') ?: null;
        $comentaris     = trim($_POST['comentaris']       ?? '') ?: null;

        // Comprova que el lot existeix
        $chk = $pdo->prepare("SELECT id_lot FROM lot_produccio WHERE id_lot = :id");
        $chk->execute([':id' => $id_lot]);
        if (!$chk->fetch()) {
            redir($url_form, 'error', 'El lot seleccionat no existeix a la base de dades.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO control_qualitat
                (id_lot, id_inspector, data_control,
                 calibre_mm, color, fermesa_kg_cm2, defectes_visibles,
                 sabor, aroma, textura, resultat, comentaris)
            VALUES
                (:id_lot, :id_inspector, :data_control,
                 :calibre_mm, :color, :fermesa_kg_cm2, :defectes_visibles,
                 :sabor, :aroma, :textura, :resultat, :comentaris)
        ");
        $stmt->execute([
            ':id_lot'           => $id_lot,
            ':id_inspector'     => $id_inspector,
            ':data_control'     => $data_control,
            ':calibre_mm'       => $calibre_mm,
            ':color'            => $color,
            ':fermesa_kg_cm2'   => $fermesa,
            ':defectes_visibles'=> $defectes,
            ':sabor'            => $sabor,
            ':aroma'            => $aroma,
            ':textura'          => $textura,
            ':resultat'         => $resultat,
            ':comentaris'       => $comentaris,
        ]);

        redir($url_llista, 'success', 'Control de qualitat registrat correctament.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // EDITAR
    // ════════════════════════════════════════════════════════════════════════
    if ($accio === 'editar') {

        $id_control   = filter_input(INPUT_POST, 'id_control', FILTER_VALIDATE_INT);
        $id_lot       = filter_input(INPUT_POST, 'id_lot',     FILTER_VALIDATE_INT);
        $data_control = trim($_POST['data_control'] ?? '');
        $resultat     = trim($_POST['resultat']     ?? '');

        $resultats_valids = ['Acceptat', 'Condicional', 'Rebutjat'];

        if (!$id_control || $id_control <= 0) {
            redir($url_llista, 'error', 'Identificador de control invàlid.');
        }
        if (!$id_lot || $id_lot <= 0) {
            redir($url_form . '?editar=' . $id_control, 'error', 'Has de seleccionar un lot de producció.');
        }
        if (!$data_control || !strtotime($data_control)) {
            redir($url_form . '?editar=' . $id_control, 'error', 'La data del control no és vàlida.');
        }
        if (!in_array($resultat, $resultats_valids, true)) {
            redir($url_form . '?editar=' . $id_control, 'error', 'El resultat seleccionat no és vàlid.');
        }

        // Comprova que el registre existeix
        $chk = $pdo->prepare("SELECT id_control FROM control_qualitat WHERE id_control = :id");
        $chk->execute([':id' => $id_control]);
        if (!$chk->fetch()) {
            redir($url_llista, 'error', 'Control de qualitat no trobat.');
        }

        $id_inspector   = filter_input(INPUT_POST, 'id_inspector',   FILTER_VALIDATE_INT) ?: null;
        $calibre_mm     = filter_input(INPUT_POST, 'calibre_mm',     FILTER_VALIDATE_FLOAT) ?: null;
        $fermesa        = filter_input(INPUT_POST, 'fermesa_kg_cm2', FILTER_VALIDATE_FLOAT) ?: null;
        $color          = trim($_POST['color']            ?? '') ?: null;
        $defectes       = trim($_POST['defectes_visibles']?? '') ?: null;
        $sabor          = trim($_POST['sabor']            ?? '') ?: null;
        $aroma          = trim($_POST['aroma']            ?? '') ?: null;
        $textura        = trim($_POST['textura']          ?? '') ?: null;
        $comentaris     = trim($_POST['comentaris']       ?? '') ?: null;

        $stmt = $pdo->prepare("
            UPDATE control_qualitat SET
                id_lot             = :id_lot,
                id_inspector       = :id_inspector,
                data_control       = :data_control,
                calibre_mm         = :calibre_mm,
                color              = :color,
                fermesa_kg_cm2     = :fermesa_kg_cm2,
                defectes_visibles  = :defectes_visibles,
                sabor              = :sabor,
                aroma              = :aroma,
                textura            = :textura,
                resultat           = :resultat,
                comentaris         = :comentaris
            WHERE id_control = :id_control
        ");
        $stmt->execute([
            ':id_lot'           => $id_lot,
            ':id_inspector'     => $id_inspector,
            ':data_control'     => $data_control,
            ':calibre_mm'       => $calibre_mm,
            ':color'            => $color,
            ':fermesa_kg_cm2'   => $fermesa,
            ':defectes_visibles'=> $defectes,
            ':sabor'            => $sabor,
            ':aroma'            => $aroma,
            ':textura'          => $textura,
            ':resultat'         => $resultat,
            ':comentaris'       => $comentaris,
            ':id_control'       => $id_control,
        ]);

        redir($url_llista, 'success', 'Control de qualitat actualitzat correctament.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // ELIMINAR
    // ════════════════════════════════════════════════════════════════════════
    if ($accio === 'eliminar') {

        $id_control = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$id_control || $id_control <= 0) {
            redir($url_llista, 'error', 'Identificador de control invàlid.');
        }

        // Comprova que existeix
        $chk = $pdo->prepare("SELECT id_control FROM control_qualitat WHERE id_control = :id");
        $chk->execute([':id' => $id_control]);
        if (!$chk->fetch()) {
            redir($url_llista, 'error', 'Control de qualitat no trobat.');
        }

        $stmt = $pdo->prepare("DELETE FROM control_qualitat WHERE id_control = :id");
        $stmt->execute([':id' => $id_control]);

        redir($url_llista, 'success', 'Control de qualitat eliminat correctament.');
    }

    // Acció desconeguda
    redir($url_llista, 'error', 'Acció no reconeguda.');

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_control.php: ' . $e->getMessage());
    redir($url_llista, 'error', 'Error intern en processar el control: ' . $e->getMessage());
}