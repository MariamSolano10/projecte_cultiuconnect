<?php
/**
 * modules/analisis/processar_analisi_foliar.php — Processador del formulari d'analítica foliar.
 *
 * Patró PRG: sempre redirigeix, mai mostra HTML.
 *
 * Camps rebuts:
 *   id_sector, id_plantacio (opcional), data_analisi, estat_fenologic
 *   N, P, K, Ca, Mg (macronutrients %)
 *   Fe, Mn, Zn, Cu, B (micronutrients ppm)
 *   deficiencies_detectades, recomanacions, observacions
 *
 * Mode edició: mode=editar + editar_id
 *   → UPDATE per id_analisi_foliar
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/analisis/analisis_lab.php#foliar');
    exit;
}

try {
    // ── 1. Recollida i validació ──────────────────────────────────────────────
    $id_sector       = sanitize_int($_POST['id_sector']       ?? null);
    $id_plantacio    = sanitize_int($_POST['id_plantacio']    ?? null) ?: null;
    $data_analisi    = sanitize($_POST['data_analisi']        ?? '');
    $estat_fenologic = sanitize($_POST['estat_fenologic']     ?? '');

    // Macronutrients (%)
    $N  = sanitize_decimal($_POST['N']  ?? null);
    $P  = sanitize_decimal($_POST['P']  ?? null);
    $K  = sanitize_decimal($_POST['K']  ?? null);
    $Ca = sanitize_decimal($_POST['Ca'] ?? null);
    $Mg = sanitize_decimal($_POST['Mg'] ?? null);

    // Micronutrients (ppm)
    $Fe = sanitize_decimal($_POST['Fe'] ?? null);
    $Mn = sanitize_decimal($_POST['Mn'] ?? null);
    $Zn = sanitize_decimal($_POST['Zn'] ?? null);
    $Cu = sanitize_decimal($_POST['Cu'] ?? null);
    $B  = sanitize_decimal($_POST['B']  ?? null);

    // Diagnosi
    $deficiencies = sanitize($_POST['deficiencies_detectades'] ?? '');
    $recomanacions = sanitize($_POST['recomanacions']          ?? '');
    $observacions  = sanitize($_POST['observacions']           ?? '');

    $errors = [];

    if (!$id_sector || $id_sector <= 0) {
        $errors[] = 'Cal seleccionar un sector vàlid.';
    }
    if (empty($data_analisi) || !strtotime($data_analisi)) {
        $errors[] = 'La data d\'anàlisi no és vàlida.';
    }
    $estats_valids = ['repos_hivernal','brotacio','floracio','creixement_fruit','maduresa','post_collita'];
    if (!in_array($estat_fenologic, $estats_valids, true)) {
        $errors[] = 'L\'estat fenològic seleccionat no és vàlid.';
    }

    // Validació de rangs mínims
    if ($N !== null && ($N < 0 || $N > 10)) {
        $errors[] = 'El valor de N (%) ha d\'estar entre 0 i 10.';
    }

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        $redir = ($_POST['mode'] ?? '') === 'editar'
            ? BASE_URL . 'modules/analisis/nou_analisi_foliar.php?editar=' . (int)($_POST['editar_id'] ?? 0)
            : BASE_URL . 'modules/analisis/nou_analisi_foliar.php';
        header('Location: ' . $redir);
        exit;
    }

    // ── 2. Verificar sector ───────────────────────────────────────────────────
    $pdo = connectDB();

    $check = $pdo->prepare("SELECT id_sector FROM sector WHERE id_sector = ?");
    $check->execute([$id_sector]);
    if (!$check->fetch()) {
        set_flash('error', 'El sector seleccionat no existeix.');
        header('Location: ' . BASE_URL . 'modules/analisis/nou_analisi_foliar.php');
        exit;
    }

    // Verificar plantació si s'ha indicat
    if ($id_plantacio) {
        $check2 = $pdo->prepare("SELECT id_plantacio FROM plantacio WHERE id_plantacio = ? AND id_sector = ?");
        $check2->execute([$id_plantacio, $id_sector]);
        if (!$check2->fetch()) {
            $id_plantacio = null; // Plantació no vàlida per a aquest sector → ignorar
        }
    }

    // ── 3. INSERT o UPDATE ────────────────────────────────────────────────────
    $es_edicio = ($_POST['mode'] ?? '') === 'editar';
    $editar_id = sanitize_int($_POST['editar_id'] ?? null);

    $params = [
        ':id_sector'       => $id_sector,
        ':id_plantacio'    => $id_plantacio,
        ':data_analisi'    => $data_analisi,
        ':estat_fenologic' => $estat_fenologic,
        ':N'               => $N,
        ':P'               => $P,
        ':K'               => $K,
        ':Ca'              => $Ca,
        ':Mg'              => $Mg,
        ':Fe'              => $Fe,
        ':Mn'              => $Mn,
        ':Zn'              => $Zn,
        ':Cu'              => $Cu,
        ':B'               => $B,
        ':deficiencies'    => $deficiencies  ?: null,
        ':recomanacions'   => $recomanacions ?: null,
        ':observacions'    => $observacions  ?: null,
    ];

    if ($es_edicio && $editar_id) {
        $stmt = $pdo->prepare("
            UPDATE analisi_foliar SET
                id_sector               = :id_sector,
                id_plantacio            = :id_plantacio,
                data_analisi            = :data_analisi,
                estat_fenologic         = :estat_fenologic,
                N                       = :N,
                P                       = :P,
                K                       = :K,
                Ca                      = :Ca,
                Mg                      = :Mg,
                Fe                      = :Fe,
                Mn                      = :Mn,
                Zn                      = :Zn,
                Cu                      = :Cu,
                B                       = :B,
                deficiencies_detectades = :deficiencies,
                recomanacions           = :recomanacions,
                observacions            = :observacions
            WHERE id_analisi_foliar = :id
        ");
        $params[':id'] = $editar_id;
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO analisi_foliar
                (id_sector, id_plantacio, data_analisi, estat_fenologic,
                 N, P, K, Ca, Mg,
                 Fe, Mn, Zn, Cu, B,
                 deficiencies_detectades, recomanacions, observacions)
            VALUES
                (:id_sector, :id_plantacio, :data_analisi, :estat_fenologic,
                 :N, :P, :K, :Ca, :Mg,
                 :Fe, :Mn, :Zn, :Cu, :B,
                 :deficiencies, :recomanacions, :observacions)
        ");
        $stmt->execute($params);
    }

    $mode_text = $es_edicio ? 'actualitzada' : 'registrada';
    set_flash('success', "Analítica foliar {$mode_text} correctament per a la data " . date('d/m/Y', strtotime($data_analisi)) . '.');
    header('Location: ' . BASE_URL . 'modules/analisis/analisis_lab.php#foliar');
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_analisi_foliar.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en guardar l\'analítica. Torna-ho a intentar.');
    header('Location: ' . BASE_URL . 'modules/analisis/nou_analisi_foliar.php');
    exit;
}