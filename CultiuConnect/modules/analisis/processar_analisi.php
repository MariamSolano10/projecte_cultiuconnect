<?php
/**
 * modules/analisis/processar_analisi.php — Processador del formulari d'analítica de sòl.
 *
 * Patró PRG: sempre redirigeix, mai mostra HTML.
 *
 * Camps rebuts del formulari (nou_analisi.php):
 *   id_sector, data_analisi, textura
 *   pH, materia_organica, conductivitat_electrica
 *   N, P, K, Ca, Mg, Na
 *
 * Mode edició: mode=editar + editar_sector + editar_data
 *   → fa UPSERT (ON DUPLICATE KEY UPDATE)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/analisis/analisis_lab.php');
    exit;
}

try {
    // -----------------------------------------------------------
    // 1. Recollida i validació
    // -----------------------------------------------------------
    $id_sector    = sanitize_int($_POST['id_sector']    ?? null);
    $data_analisi = sanitize($_POST['data_analisi']     ?? '');
    $textura      = sanitize($_POST['textura']          ?? '');

    // Numèrics opcionals — sanitize_decimal retorna null si el camp és buit o invàlid
    $pH              = sanitize_decimal($_POST['pH']                    ?? null);
    $mo              = sanitize_decimal($_POST['materia_organica']      ?? null);
    $ce              = sanitize_decimal($_POST['conductivitat_electrica'] ?? null);
    $N               = sanitize_decimal($_POST['N']                    ?? null);
    $P               = sanitize_decimal($_POST['P']                    ?? null);
    $K               = sanitize_decimal($_POST['K']                    ?? null);
    $Ca              = sanitize_decimal($_POST['Ca']                   ?? null);
    $Mg              = sanitize_decimal($_POST['Mg']                   ?? null);
    $Na              = sanitize_decimal($_POST['Na']                   ?? null);

    $errors = [];

    if (!$id_sector || $id_sector <= 0) {
        $errors[] = 'Cal seleccionar un sector vàlid.';
    }
    if (empty($data_analisi) || !strtotime($data_analisi)) {
        $errors[] = 'La data d\'anàlisi no és vàlida.';
    }
    if ($pH !== null && ($pH < 0 || $pH > 14)) {
        $errors[] = 'El pH ha d\'estar entre 0 i 14.';
    }

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        header('Location: ' . BASE_URL . 'modules/analisis/nou_analisi.php');
        exit;
    }

    // -----------------------------------------------------------
    // 2. Verificar que el sector existeix
    // -----------------------------------------------------------
    $pdo = connectDB();

    $check = $pdo->prepare("SELECT id_sector FROM sector WHERE id_sector = ?");
    $check->execute([$id_sector]);
    if (!$check->fetch()) {
        set_flash('error', 'El sector seleccionat no existeix.');
        header('Location: ' . BASE_URL . 'modules/analisis/nou_analisi.php');
        exit;
    }

    // -----------------------------------------------------------
    // 3. UPSERT a `caracteristiques_sol`
    //    PK composta (id_sector, data_analisi):
    //    si ja existeix → actualitza; si no → insereix
    // -----------------------------------------------------------
    $stmt = $pdo->prepare("
        INSERT INTO caracteristiques_sol
            (id_sector, data_analisi, textura,
             pH, materia_organica, conductivitat_electrica,
             N, P, K, Ca, Mg, Na)
        VALUES
            (:id_sector, :data_analisi, :textura,
             :pH, :mo, :ce,
             :N, :P, :K, :Ca, :Mg, :Na)
        ON DUPLICATE KEY UPDATE
            textura                  = VALUES(textura),
            pH                       = VALUES(pH),
            materia_organica         = VALUES(materia_organica),
            conductivitat_electrica  = VALUES(conductivitat_electrica),
            N                        = VALUES(N),
            P                        = VALUES(P),
            K                        = VALUES(K),
            Ca                       = VALUES(Ca),
            Mg                       = VALUES(Mg),
            Na                       = VALUES(Na)
    ");

    $stmt->execute([
        ':id_sector'   => $id_sector,
        ':data_analisi' => $data_analisi,
        ':textura'     => $textura   ?: null,
        ':pH'          => $pH,
        ':mo'          => $mo,
        ':ce'          => $ce,
        ':N'           => $N,
        ':P'           => $P,
        ':K'           => $K,
        ':Ca'          => $Ca,
        ':Mg'          => $Mg,
        ':Na'          => $Na,
    ]);

    $mode = ($_POST['mode'] ?? '') === 'editar' ? 'actualitzada' : 'registrada';
    set_flash('success', "Analítica {$mode} correctament per a la data " . date('d/m/Y', strtotime($data_analisi)) . '.');
    header('Location: ' . BASE_URL . 'modules/analisis/analisis_lab.php');
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_analisi.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en guardar l\'analítica. Torna-ho a intentar.');
    header('Location: ' . BASE_URL . 'modules/analisis/nou_analisi.php');
    exit;
}
