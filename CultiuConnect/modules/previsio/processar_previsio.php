<?php
/**
 * modules/previsio/processar_previsio.php
 *
 * Accions POST:
 *  - crear   → INSERT nova previsió (constraint UNIQUE id_plantacio + temporada)
 *  - editar  → UPDATE previsió existent
 *  - eliminar → DELETE
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/previsio/previsio.php');
    exit;
}

$accio = $_POST['accio'] ?? '';

$temporada  = is_numeric($_POST['temporada'] ?? '') ? (int)$_POST['temporada'] : (int)date('Y');
$url_retorn = BASE_URL . 'modules/previsio/previsio.php?temporada=' . $temporada;

try {
    $pdo = connectDB();

    // ----------------------------------------------------------------
    // CREAR
    // ----------------------------------------------------------------
    if ($accio === 'crear') {

        $id_plantacio                = !empty($_POST['id_plantacio']) ? (int)$_POST['id_plantacio'] : null;
        $data_previsio               = trim($_POST['data_previsio'] ?? '');
        $produccio_estimada_kg       = is_numeric($_POST['produccio_estimada_kg'] ?? '')  ? (float)$_POST['produccio_estimada_kg']  : null;
        $produccio_per_arbre_kg      = is_numeric($_POST['produccio_per_arbre_kg'] ?? '') ? (float)$_POST['produccio_per_arbre_kg'] : null;
        $data_inici_collita_estimada = trim($_POST['data_inici_collita_estimada'] ?? '') ?: null;
        $data_fi_collita_estimada    = trim($_POST['data_fi_collita_estimada']    ?? '') ?: null;
        $calibre_previst             = trim($_POST['calibre_previst']   ?? '') ?: null;
        $qualitat_prevista           = in_array($_POST['qualitat_prevista'] ?? '', ['Extra','Primera','Segona','Industrial'])
                                       ? $_POST['qualitat_prevista'] : null;
        $mo_necessaria_jornal        = is_numeric($_POST['mo_necessaria_jornal'] ?? '') ? (int)$_POST['mo_necessaria_jornal'] : null;
        $factors_considerats         = trim($_POST['factors_considerats'] ?? '') ?: null;

        if (!$id_plantacio || !$data_previsio) {
            set_flash('error', 'La plantació i la data de previsió són obligatòries.');
            header('Location: ' . BASE_URL . 'modules/previsio/nova_previsio.php?temporada=' . $temporada);
            exit;
        }

        if ($data_inici_collita_estimada && $data_fi_collita_estimada
            && $data_fi_collita_estimada < $data_inici_collita_estimada) {
            set_flash('error', 'La data de fi de collita no pot ser anterior a l\'inici.');
            header('Location: ' . BASE_URL . 'modules/previsio/nova_previsio.php?temporada=' . $temporada);
            exit;
        }

        // Comprovem que no existeixi ja una previsió per a aquesta plantació i temporada
        $stmtCheck = $pdo->prepare("
            SELECT id_previsio FROM previsio_collita
            WHERE id_plantacio = :id_plantacio AND temporada = :temporada
        ");
        $stmtCheck->execute([':id_plantacio' => $id_plantacio, ':temporada' => $temporada]);
        if ($stmtCheck->fetch()) {
            set_flash('error', 'Ja existeix una previsió per a aquesta plantació i temporada. Edita-la en lloc de crear-ne una de nova.');
            header('Location: ' . BASE_URL . 'modules/previsio/nova_previsio.php?temporada=' . $temporada);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO previsio_collita (
                id_plantacio, temporada, data_previsio,
                produccio_estimada_kg, produccio_per_arbre_kg,
                data_inici_collita_estimada, data_fi_collita_estimada,
                calibre_previst, qualitat_prevista,
                mo_necessaria_jornal, factors_considerats
            ) VALUES (
                :id_plantacio, :temporada, :data_previsio,
                :produccio_estimada_kg, :produccio_per_arbre_kg,
                :data_inici_collita_estimada, :data_fi_collita_estimada,
                :calibre_previst, :qualitat_prevista,
                :mo_necessaria_jornal, :factors_considerats
            )
        ");
        $stmt->execute([
            ':id_plantacio'                => $id_plantacio,
            ':temporada'                   => $temporada,
            ':data_previsio'               => $data_previsio,
            ':produccio_estimada_kg'       => $produccio_estimada_kg,
            ':produccio_per_arbre_kg'      => $produccio_per_arbre_kg,
            ':data_inici_collita_estimada' => $data_inici_collita_estimada,
            ':data_fi_collita_estimada'    => $data_fi_collita_estimada,
            ':calibre_previst'             => $calibre_previst,
            ':qualitat_prevista'           => $qualitat_prevista,
            ':mo_necessaria_jornal'        => $mo_necessaria_jornal,
            ':factors_considerats'         => $factors_considerats,
        ]);

        set_flash('success', 'Previsió de collita creada correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // EDITAR
    // ----------------------------------------------------------------
    if ($accio === 'editar') {

        $id_previsio = !empty($_POST['id_previsio']) ? (int)$_POST['id_previsio'] : 0;
        if (!$id_previsio) {
            set_flash('error', 'Previsió no identificada.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $id_plantacio                = !empty($_POST['id_plantacio']) ? (int)$_POST['id_plantacio'] : null;
        $data_previsio               = trim($_POST['data_previsio'] ?? '');
        $produccio_estimada_kg       = is_numeric($_POST['produccio_estimada_kg'] ?? '')  ? (float)$_POST['produccio_estimada_kg']  : null;
        $produccio_per_arbre_kg      = is_numeric($_POST['produccio_per_arbre_kg'] ?? '') ? (float)$_POST['produccio_per_arbre_kg'] : null;
        $data_inici_collita_estimada = trim($_POST['data_inici_collita_estimada'] ?? '') ?: null;
        $data_fi_collita_estimada    = trim($_POST['data_fi_collita_estimada']    ?? '') ?: null;
        $calibre_previst             = trim($_POST['calibre_previst']   ?? '') ?: null;
        $qualitat_prevista           = in_array($_POST['qualitat_prevista'] ?? '', ['Extra','Primera','Segona','Industrial'])
                                       ? $_POST['qualitat_prevista'] : null;
        $mo_necessaria_jornal        = is_numeric($_POST['mo_necessaria_jornal'] ?? '') ? (int)$_POST['mo_necessaria_jornal'] : null;
        $factors_considerats         = trim($_POST['factors_considerats'] ?? '') ?: null;

        if (!$id_plantacio || !$data_previsio) {
            set_flash('error', 'La plantació i la data de previsió són obligatòries.');
            header('Location: ' . BASE_URL . 'modules/previsio/nova_previsio.php?editar=' . $id_previsio);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE previsio_collita SET
                id_plantacio                = :id_plantacio,
                temporada                   = :temporada,
                data_previsio               = :data_previsio,
                produccio_estimada_kg       = :produccio_estimada_kg,
                produccio_per_arbre_kg      = :produccio_per_arbre_kg,
                data_inici_collita_estimada = :data_inici_collita_estimada,
                data_fi_collita_estimada    = :data_fi_collita_estimada,
                calibre_previst             = :calibre_previst,
                qualitat_prevista           = :qualitat_prevista,
                mo_necessaria_jornal        = :mo_necessaria_jornal,
                factors_considerats         = :factors_considerats
            WHERE id_previsio = :id_previsio
        ");
        $stmt->execute([
            ':id_plantacio'                => $id_plantacio,
            ':temporada'                   => $temporada,
            ':data_previsio'               => $data_previsio,
            ':produccio_estimada_kg'       => $produccio_estimada_kg,
            ':produccio_per_arbre_kg'      => $produccio_per_arbre_kg,
            ':data_inici_collita_estimada' => $data_inici_collita_estimada,
            ':data_fi_collita_estimada'    => $data_fi_collita_estimada,
            ':calibre_previst'             => $calibre_previst,
            ':qualitat_prevista'           => $qualitat_prevista,
            ':mo_necessaria_jornal'        => $mo_necessaria_jornal,
            ':factors_considerats'         => $factors_considerats,
            ':id_previsio'                 => $id_previsio,
        ]);

        set_flash('success', 'Previsió actualitzada correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // ELIMINAR
    // ----------------------------------------------------------------
    if ($accio === 'eliminar') {

        $id_previsio = !empty($_POST['id_previsio']) ? (int)$_POST['id_previsio'] : 0;
        if (!$id_previsio) {
            set_flash('error', 'Previsió no identificada.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM previsio_collita WHERE id_previsio = :id");
        $stmt->execute([':id' => $id_previsio]);

        set_flash('success', 'Previsió eliminada correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    set_flash('error', 'Acció no reconeguda.');
    header('Location: ' . $url_retorn);
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_previsio.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en processar la previsió.');
    header('Location: ' . $url_retorn);
    exit;
}
