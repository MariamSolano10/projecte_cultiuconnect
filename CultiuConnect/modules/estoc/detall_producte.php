<?php
/**
 * modules/estoc/detall_producte.php — Fitxa completa d'un producte.
 *
 * Mostra: estat estoc, lots actius per caducitat (inventari_estoc),
 * historial de moviments (moviment_estoc) i darrers usos en camp.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_producte = sanitize_int($_GET['id'] ?? null);

if (!$id_producte) {
    set_flash('error', 'ID de producte invàlid.');
    header('Location: ' . BASE_URL . 'modules/estoc/estoc.php');
    exit;
}

try {
    $pdo = connectDB();

    // 1. Dades principals
    $stmt = $pdo->prepare("SELECT * FROM producte_quimic WHERE id_producte = ?");
    $stmt->execute([$id_producte]);
    $producte = $stmt->fetch();

    if (!$producte) {
        set_flash('error', 'El producte sol·licitat no existeix.');
        header('Location: ' . BASE_URL . 'modules/estoc/estoc.php');
        exit;
    }

    // 2. Lots actius (taula inventari_estoc, camp quantitat_disponible)
    $stmt_lots = $pdo->prepare("
        SELECT num_lot, data_caducitat, quantitat_disponible, ubicacio_magatzem
        FROM inventari_estoc
        WHERE id_producte = ?
          AND quantitat_disponible > 0
        ORDER BY data_caducitat ASC
    ");
    $stmt_lots->execute([$id_producte]);
    $lots = $stmt_lots->fetchAll();

    // 3. Historial moviments (taula moviment_estoc)
    $stmt_mov = $pdo->prepare("
        SELECT tipus_moviment, quantitat, data_moviment, motiu
        FROM moviment_estoc
        WHERE id_producte = ?
        ORDER BY data_moviment DESC
        LIMIT 20
    ");
    $stmt_mov->execute([$id_producte]);
    $moviments = $stmt_mov->fetchAll();

    // 4. Darrers usos en aplicacions al camp
    $stmt_us = $pdo->prepare("
        SELECT
            a.data_event,
            s.nom AS nom_sector,
            dap.quantitat_consumida_total
        FROM detall_aplicacio_producte dap
        JOIN inventari_estoc ie ON ie.id_estoc    = dap.id_estoc
        JOIN aplicacio       a  ON a.id_aplicacio = dap.id_aplicacio
        JOIN sector          s  ON s.id_sector    = a.id_sector
        WHERE ie.id_producte = ?
        ORDER BY a.data_event DESC
        LIMIT 5
    ");
    $stmt_us->execute([$id_producte]);
    $darrers_usos = $stmt_us->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] detall_producte.php: ' . $e->getMessage());
    set_flash('error', 'Error carregant la fitxa del producte.');
    header('Location: ' . BASE_URL . 'modules/estoc/estoc.php');
    exit;
}

$estoc_actual = (float)$producte['estoc_actual'];
$estoc_minim  = (float)$producte['estoc_minim'];
$unitat       = $producte['unitat_mesura'];

if ($estoc_actual <= 0) {
    $estat = ['text' => 'Esgotat',              'badge' => 'badge--vermell', 'kpi' => 'kpi-card--vermell', 'icona' => 'fa-circle-xmark'];
} elseif ($estoc_actual <= $estoc_minim) {
    $estat = ['text' => 'Estoc baix (alerta)',  'badge' => 'badge--groc',   'kpi' => 'kpi-card--groc',   'icona' => 'fa-triangle-exclamation'];
} else {
    $estat = ['text' => 'Estoc òptim',          'badge' => 'badge--verd',   'kpi' => 'kpi-card--verd',   'icona' => 'fa-circle-check'];
}

$titol_pagina  = 'Fitxa de producte';
$pagina_activa = 'inventari';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-estoc">

    <div class="capcalera-seccio capcalera-seccio--amb-accions">
        <div>
            <h1 class="titol-pagina">
                <i class="fas fa-box" aria-hidden="true"></i>
                <?= e($producte['nom_comercial']) ?>
            </h1>
            <p class="descripcio-seccio">
                Fitxa de traçabilitat i estat de l'inventari —
                <?= e(strtolower($producte['tipus'])) ?>
            </p>
        </div>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/estoc/estoc.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
            </a>
            <a href="<?= BASE_URL ?>modules/estoc/moviment_estoc.php?id_producte=<?= (int)$id_producte ?>"
               class="boto-secundari">
                <i class="fas fa-exchange-alt" aria-hidden="true"></i> Nou Moviment
            </a>
            <a href="<?= BASE_URL ?>modules/estoc/nou_producte.php?editar=<?= (int)$id_producte ?>"
               class="boto-principal">
                <i class="fas fa-pen" aria-hidden="true"></i> Editar
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid kpi-grid--petit">

        <div class="kpi-card <?= $estat['kpi'] ?>">
            <div class="kpi-card__icona">
                <i class="fas fa-boxes-stacked" aria-hidden="true"></i>
            </div>
            <span class="kpi-card__valor">
                <?= number_format($estoc_actual, 2, ',', '.') ?> <?= e($unitat) ?>
            </span>
            <span class="kpi-card__etiqueta">Estoc actual</span>
            <span class="badge <?= $estat['badge'] ?> badge--top">
                <i class="fas <?= $estat['icona'] ?>" aria-hidden="true"></i>
                <?= $estat['text'] ?>
            </span>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <span class="kpi-card__valor">
                <?= number_format($estoc_minim, 2, ',', '.') ?> <?= e($unitat) ?>
            </span>
            <span class="kpi-card__etiqueta">Llindar d'avís</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-tag" aria-hidden="true"></i>
            </div>
            <span class="kpi-card__valor"><?= e($producte['tipus']) ?></span>
            <span class="kpi-card__etiqueta">Categoria</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-cubes" aria-hidden="true"></i>
            </div>
            <span class="kpi-card__valor"><?= count($lots) ?></span>
            <span class="kpi-card__etiqueta">Lots actius</span>
        </div>

    </div>

    <!-- Grid: Lots + Usos -->
    <div class="detall-grid">

        <div class="detall-bloc">
            <h2 class="detall-bloc__titol detall-bloc__titol--blau">
                <i class="fas fa-barcode" aria-hidden="true"></i> Lots actius (FEFO)
            </h2>

            <?php if (empty($lots)): ?>
                <p class="sense-dades-inline">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    No hi ha lots amb estoc disponible.
                </p>
            <?php else: ?>
                <table class="taula-simple taula-simple--compacta">
                    <thead>
                        <tr>
                            <th>Lot</th>
                            <th>Caducitat</th>
                            <th class="text-dreta">Disponible</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lots as $lot):
                            $dies = dies_restants($lot['data_caducitat']);
                            // Classe per al badge de caducitat
                            $num  = (int)filter_var($dies, FILTER_SANITIZE_NUMBER_INT);
                            if (str_starts_with($dies, 'fa')) {
                                $classe_cad = 'badge--vermell';
                            } elseif ($num <= 60) {
                                $classe_cad = 'badge--groc';
                            } else {
                                $classe_cad = 'badge--verd';
                            }
                        ?>
                            <tr>
                                <td>
                                    <strong><?= e($lot['num_lot']) ?></strong>
                                    <?php if ($lot['ubicacio_magatzem']): ?>
                                        <br><span class="text-suau"><?= e($lot['ubicacio_magatzem']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= format_data($lot['data_caducitat'], curta: true) ?>
                                    <br>
                                    <span class="badge <?= $classe_cad ?> badge--mini-top">
                                        <?= e($dies) ?>
                                    </span>
                                </td>
                                <td class="text-dreta">
                                    <strong>
                                        <?= number_format((float)$lot['quantitat_disponible'], 2, ',', '.') ?>
                                        <?= e($unitat) ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="detall-bloc">
            <h2 class="detall-bloc__titol detall-bloc__titol--verd">
                <i class="fas fa-spray-can-sparkles" aria-hidden="true"></i> Darrers usos en camp
            </h2>

            <?php if (empty($darrers_usos)): ?>
                <p class="sense-dades-inline">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    Cap aplicació registrada amb aquest producte.
                </p>
            <?php else: ?>
                <table class="taula-simple taula-simple--compacta">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Sector</th>
                            <th class="text-dreta">Quantitat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($darrers_usos as $us): ?>
                            <tr>
                                <td><?= format_data($us['data_event'], curta: true) ?></td>
                                <td><?= e($us['nom_sector']) ?></td>
                                <td class="text-dreta">
                                    <?= number_format((float)$us['quantitat_consumida_total'], 2, ',', '.') ?>
                                    <?= e($unitat) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="accio-peu">
                    <a href="<?= BASE_URL ?>modules/quadern/quadern.php" class="boto-secundari boto-secundari--petit">
                        Veure quadern complet
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Historial de moviments -->
    <div class="detall-bloc detall-bloc--ample detall-bloc--mt-l">
        <h2 class="detall-bloc__titol">
            <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
            Historial de moviments (traçabilitat)
        </h2>

        <table class="taula-simple">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipus</th>
                    <th class="text-dreta">Quantitat</th>
                    <th>Motiu / Albarà</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($moviments)): ?>
                    <tr>
                        <td colspan="4" class="sense-dades">
                            Cap moviment registrat per a aquest producte.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($moviments as $mov):
                        [$badge, $signe] = match ($mov['tipus_moviment']) {
                            'Entrada'        => ['badge--verd',    '+'],
                            'Sortida'        => ['badge--vermell', '−'],
                            default          => ['badge--gris',    ''],
                        };
                        $classe_valor = match ($mov['tipus_moviment']) {
                            'Entrada' => 'estat-ok',
                            'Sortida' => 'estat-alerta',
                            default   => 'estat-normal',
                        };
                    ?>
                        <tr>
                            <td><?= format_data($mov['data_moviment'], curta: true) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= e($mov['tipus_moviment']) ?></span></td>
                            <td class="text-dreta">
                                <strong class="<?= $classe_valor ?>">
                                    <?= $signe ?><?= number_format((float)$mov['quantitat'], 2, ',', '.') ?>
                                    <?= e($unitat) ?>
                                </strong>
                            </td>
                            <td class="cel-text-llarg text-suau">
                                <?php 
                                $es_automatic = str_starts_with($mov['motiu'] ?? '', 'Aplicació #');
                                if ($es_automatic): ?>
                                    <span class="badge badge--blau badge--mini-right">
                                        <i class="fas fa-link"></i> Auto
                                    </span>
                                <?php endif; ?>
                                <?= e($mov['motiu'] ?: '---') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
