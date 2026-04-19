<?php
/**
 * modules/analisis/processar_analisi_aigua.php — Processador del formulari d'analítica d'aigua.
 *
 * Patró PRG: sempre redirigeix, mai mostra HTML.
 *
 * Camps rebuts:
 *   id_sector, data_analisi, origen_mostra
 *   pH, conductivitat_electrica, duresa
 *   nitrats, clorurs, sulfats, bicarbonat
 *   Na, Ca, Mg, K, SAR, observacions
 *
 * Mode edició: mode=editar + editar_id
 *   → UPDATE per id_analisi_aigua
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/analisis/analisis_lab.php#aigua');
    exit;
}

try {
    // ── 1. Recollida i validació ──────────────────────────────────────────────
    $id_sector     = sanitize_int($_POST['id_sector']    ?? null);
    $data_analisi  = sanitize($_POST['data_analisi']     ?? '');
    $origen_mostra = sanitize($_POST['origen_mostra']    ?? '');

    $pH              = sanitize_decimal($_POST['pH']                      ?? null);
    $ce              = sanitize_decimal($_POST['conductivitat_electrica'] ?? null);
    $duresa          = sanitize_decimal($_POST['duresa']                  ?? null);
    $nitrats         = sanitize_decimal($_POST['nitrats']                 ?? null);
    $clorurs         = sanitize_decimal($_POST['clorurs']                 ?? null);
    $sulfats         = sanitize_decimal($_POST['sulfats']                 ?? null);
    $bicarbonat      = sanitize_decimal($_POST['bicarbonat']              ?? null);
    $Na              = sanitize_decimal($_POST['Na']                      ?? null);
    $Ca              = sanitize_decimal($_POST['Ca']                      ?? null);
    $Mg              = sanitize_decimal($_POST['Mg']                      ?? null);
    $K               = sanitize_decimal($_POST['K']                       ?? null);
    $SAR_input       = sanitize_decimal($_POST['SAR']                     ?? null);
    $observacions    = sanitize($_POST['observacions']                    ?? '');

    $errors = [];

    if (!$id_sector || $id_sector <= 0) {
        $errors[] = 'Cal seleccionar un sector vàlid.';
    }
    if (empty($data_analisi) || !strtotime($data_analisi)) {
        $errors[] = 'La data d\'anàlisi no és vàlida.';
    }
    $origens_valids = ['pou', 'bassa', 'xarxa', 'riu', 'altres'];
    if (!in_array($origen_mostra, $origens_valids, true)) {
        $errors[] = 'L\'origen de la mostra no és vàlid.';
    }
    if ($pH !== null && ($pH < 0 || $pH > 14)) {
        $errors[] = 'El pH ha d\'estar entre 0 i 14.';
    }

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        $redir = ($_POST['mode'] ?? '') === 'editar'
            ? BASE_URL . 'modules/analisis/nou_analisi_aigua.php?editar=' . (int)($_POST['editar_id'] ?? 0)
            : BASE_URL . 'modules/analisis/nou_analisi_aigua.php';
        header('Location: ' . $redir);
        exit;
    }

    // ── 2. Càlcul automàtic del SAR si no s'ha introduït manualment ──────────
    // SAR = Na / sqrt((Ca + Mg) / 2), en meq/L.
    // Si els valors estan en ppm: Na_meq = Na/23, Ca_meq = Ca/20, Mg_meq = Mg/12.15
    $SAR = $SAR_input;
    if ($SAR === null && $Na !== null && $Ca !== null && $Mg !== null && ($Ca + $Mg) > 0) {
        $na_meq = $Na  / 23;
        $ca_meq = $Ca  / 20;
        $mg_meq = $Mg  / 12.15;
        $SAR    = $na_meq / sqrt(($ca_meq + $mg_meq) / 2);
        $SAR    = round($SAR, 2);
    }

    // ── 3. Verificar sector ───────────────────────────────────────────────────
    $pdo = connectDB();

    $check = $pdo->prepare("SELECT id_sector FROM sector WHERE id_sector = ?");
    $check->execute([$id_sector]);
    if (!$check->fetch()) {
        set_flash('error', 'El sector seleccionat no existeix.');
        header('Location: ' . BASE_URL . 'modules/analisis/nou_analisi_aigua.php');
        exit;
    }

    // ── 4. INSERT o UPDATE ────────────────────────────────────────────────────
    $es_edicio = ($_POST['mode'] ?? '') === 'editar';
    $editar_id = sanitize_int($_POST['editar_id'] ?? null);

    if ($es_edicio && $editar_id) {
        $stmt = $pdo->prepare("
            UPDATE analisi_aigua SET
                id_sector              = :id_sector,
                data_analisi           = :data_analisi,
                origen_mostra          = :origen_mostra,
                pH                     = :pH,
                conductivitat_electrica = :ce,
                duresa                 = :duresa,
                nitrats                = :nitrats,
                clorurs                = :clorurs,
                sulfats                = :sulfats,
                bicarbonat             = :bicarbonat,
                Na                     = :Na,
                Ca                     = :Ca,
                Mg                     = :Mg,
                K                      = :K,
                SAR                    = :SAR,
                observacions           = :observacions
            WHERE id_analisi_aigua = :id
        ");
        $stmt->execute([
            ':id_sector'     => $id_sector,
            ':data_analisi'  => $data_analisi,
            ':origen_mostra' => $origen_mostra,
            ':pH'            => $pH,
            ':ce'            => $ce,
            ':duresa'        => $duresa,
            ':nitrats'       => $nitrats,
            ':clorurs'       => $clorurs,
            ':sulfats'       => $sulfats,
            ':bicarbonat'    => $bicarbonat,
            ':Na'            => $Na,
            ':Ca'            => $Ca,
            ':Mg'            => $Mg,
            ':K'             => $K,
            ':SAR'           => $SAR,
            ':observacions'  => $observacions ?: null,
            ':id'            => $editar_id,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO analisi_aigua
                (id_sector, data_analisi, origen_mostra,
                 pH, conductivitat_electrica, duresa,
                 nitrats, clorurs, sulfats, bicarbonat,
                 Na, Ca, Mg, K, SAR, observacions)
            VALUES
                (:id_sector, :data_analisi, :origen_mostra,
                 :pH, :ce, :duresa,
                 :nitrats, :clorurs, :sulfats, :bicarbonat,
                 :Na, :Ca, :Mg, :K, :SAR, :observacions)
        ");
        $stmt->execute([
            ':id_sector'     => $id_sector,
            ':data_analisi'  => $data_analisi,
            ':origen_mostra' => $origen_mostra,
            ':pH'            => $pH,
            ':ce'            => $ce,
            ':duresa'        => $duresa,
            ':nitrats'       => $nitrats,
            ':clorurs'       => $clorurs,
            ':sulfats'       => $sulfats,
            ':bicarbonat'    => $bicarbonat,
            ':Na'            => $Na,
            ':Ca'            => $Ca,
            ':Mg'            => $Mg,
            ':K'             => $K,
            ':SAR'           => $SAR,
            ':observacions'  => $observacions ?: null,
        ]);
    }

    $mode_text = $es_edicio ? 'actualitzada' : 'registrada';
    set_flash('success', "Analítica d'aigua {$mode_text} correctament per a la data " . date('d/m/Y', strtotime($data_analisi)) . '.');
    header('Location: ' . BASE_URL . 'modules/analisis/analisis_lab.php#aigua');
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_analisi_aigua.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en guardar l\'analítica. Torna-ho a intentar.');
    header('Location: ' . BASE_URL . 'modules/analisis/nou_analisi_aigua.php');
    exit;
}