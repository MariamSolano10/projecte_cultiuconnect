<?php
/**
 * modules/estoc/detall_inventari.php
 *
 * Mostra el detall d'un inventari físic específic amb totes les regularitzacions
 * realitzades en una data determinada.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$data_inventari = sanitize($_GET['data'] ?? '');

if (empty($data_inventari) || !strtotime($data_inventari)) {
    set_flash('error', 'Data d\'inventari invàlida.');
    header('Location: ' . BASE_URL . 'modules/estoc/inventari_fisic.php');
    exit;
}

$titol_pagina  = 'Detall Inventari Físic - ' . format_data($data_inventari);
$pagina_activa = 'inventari';

$detall_inventari = [];
$resum = null;
$error_db = null;

try {
    $pdo = connectDB();

    // 1. Detall de totes les regularitzacions d'aquesta data
    $stmt = $pdo->prepare("
        SELECT 
            ifr.id_registre,
            ifr.id_producte,
            ifr.estoc_teoric,
            ifr.estoc_real,
            ifr.diferencia,
            ifr.observacions,
            p.nom_comercial,
            p.tipus,
            p.unitat_mesura,
            p.estoc_actual AS estoc_actualitzat
        FROM inventari_fisic_registre ifr
        JOIN producte_quimic p ON p.id_producte = ifr.id_producte
        WHERE ifr.data_inventari = ?
        ORDER BY ABS(ifr.diferencia) DESC, p.nom_comercial ASC
    ");
    $stmt->execute([$data_inventari]);
    $detall_inventari = $stmt->fetchAll();

    // 2. Resum estadístic
    $stmt_resum = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_productes,
            SUM(CASE WHEN diferencia != 0 THEN 1 ELSE 0 END) AS discreancies,
            SUM(CASE WHEN diferencia > 0 THEN 1 ELSE 0 END) AS sobrants,
            SUM(CASE WHEN diferencia < 0 THEN 1 ELSE 0 END) AS faltants,
            SUM(ABS(diferencia)) AS diferencia_total_abs,
            SUM(diferencia) AS diferencia_total_neta,
            MAX(observacions) AS observacions_generals
        FROM inventari_fisic_registre
        WHERE data_inventari = ?
    ");
    $stmt_resum->execute([$data_inventari]);
    $resum = $stmt_resum->fetch();

} catch (Exception $e) {
    error_log('[CultiuConnect] detall_inventari.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els detalls de l\'inventari.';
}

function classeDiferencia(float $diferencia): array
{
    $abs_diff = abs($diferencia);
    
    if ($abs_diff <= 0.01) {
        return ['badge--verd', 'Correcte', 'estat-ok'];
    } elseif ($abs_diff <= 0.1) {
        return ['badge--groc', 'Petita discrepància', 'estat-normal'];
    } elseif ($abs_diff <= 1.0) {
        return ['badge--taronja', 'Discrepància moderada', 'estat-alerta'];
    } else {
        return ['badge--vermell', 'Discrepància important', 'estat-error'];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-estoc">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-clipboard-check" aria-hidden="true"></i>
            Detall Inventari Físic
        </h1>
        <p class="descripcio-seccio">
            Regularitzacions realitzades el <strong><?= format_data($data_inventari) ?></strong>.
        </p>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/estoc/inventari_fisic.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar a inventari
            </a>
            <a href="<?= BASE_URL ?>modules/estoc/estoc.php" class="boto-secundari">
                <i class="fas fa-boxes-stacked" aria-hidden="true"></i> Veure estoc actual
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <?php if ($resum): ?>
        <!-- Resum estadístic -->
        <div class="kpi-grid kpi-grid--petit">
            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-boxes" aria-hidden="true"></i>
                </div>
                <span class="kpi-card__valor"><?= (int)$resum['total_productes'] ?></span>
                <span class="kpi-card__etiqueta">Productes comptats</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                </div>
                <span class="kpi-card__valor"><?= (int)$resum['discreancies'] ?></span>
                <span class="kpi-card__etiqueta">Discrepàncies</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-arrow-up" aria-hidden="true"></i>
                </div>
                <span class="kpi-card__valor"><?= (int)$resum['sobrants'] ?></span>
                <span class="kpi-card__etiqueta">Sobrants</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-arrow-down" aria-hidden="true"></i>
                </div>
                <span class="kpi-card__valor"><?= (int)$resum['faltants'] ?></span>
                <span class="kpi-card__etiqueta">Faltants</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-calculator" aria-hidden="true"></i>
                </div>
                <span class="kpi-card__valor"><?= number_format((float)$resum['diferencia_total_abs'], 2, ',', '.') ?></span>
                <span class="kpi-card__etiqueta">Diferència total</span>
            </div>

            <div class="kpi-card">
                <div class="kpi-card__icona">
                    <i class="fas fa-balance-scale" aria-hidden="true"></i>
                </div>
                <span class="kpi-card__valor <?= (float)$resum['diferencia_total_neta'] >= 0 ? 'estat-ok' : 'estat-alerta' ?>">
                    <?= (float)$resum['diferencia_total_neta'] >= 0 ? '+' : '' ?>
                    <?= number_format((float)$resum['diferencia_total_neta'], 2, ',', '.') ?>
                </span>
                <span class="kpi-card__etiqueta">Balanç net</span>
            </div>
        </div>

        <?php if ($resum['observacions_generals']): ?>
            <div class="info-box info-box--separat">
                <h4 class="titol-bloc--xs">
                    <i class="fas fa-sticky-note" aria-hidden="true"></i> Observacions generals
                </h4>
                <p class="paragraf--sense-marge"><?= e($resum['observacions_generals']) ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Taula detallada -->
    <div class="taula-container">
        <table class="taula-simple">
            <thead>
                <tr>
                    <th>Producte</th>
                    <th>Tipus</th>
                    <th class="text-dreta">Estoc teòric</th>
                    <th class="text-dreta">Estoc real</th>
                    <th class="text-dreta">Diferència</th>
                    <th>Estat</th>
                    <th class="text-dreta">Estoc actual</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($detall_inventari)): ?>
                    <tr>
                        <td colspan="7" class="sense-dades">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            No es troben registres d'inventari per aquesta data.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($detall_inventari as $detall): 
                        $unitat = $detall['unitat_mesura'] ?? '';
                        $diferencia = (float)$detall['diferencia'];
                        
                        [$classe_badge, $text_estat, $classe_valor] = classeDiferencia($diferencia);
                    ?>
                        <tr>
                            <td><strong><?= e($detall['nom_comercial']) ?></strong></td>
                            <td>
                                <span class="badge <?= match($detall['tipus']) {
                                    'Fitosanitari' => 'badge--vermell',
                                    'Fertilitzant' => 'badge--verd',
                                    'Herbicida'    => 'badge--taronja',
                                    default        => 'badge--gris',
                                } ?>">
                                    <?= e($detall['tipus']) ?>
                                </span>
                            </td>
                            <td class="text-dreta">
                                <?= number_format((float)$detall['estoc_teoric'], 2, ',', '.') ?>
                                <br><small><?= e($unitat) ?></small>
                            </td>
                            <td class="text-dreta">
                                <strong><?= number_format((float)$detall['estoc_real'], 2, ',', '.') ?></strong>
                                <br><small><?= e($unitat) ?></small>
                            </td>
                            <td class="text-dreta">
                                <span class="<?= $classe_valor ?>">
                                    <?= $diferencia >= 0 ? '+' : '' ?>
                                    <?= number_format($diferencia, 2, ',', '.') ?>
                                </span>
                                <br><small><?= e($unitat) ?></small>
                            </td>
                            <td>
                                <span class="badge <?= $classe_badge ?>">
                                    <?= $text_estat ?>
                                </span>
                            </td>
                            <td class="text-dreta">
                                <?= number_format((float)$detall['estoc_actualitzat'], 2, ',', '.') ?>
                                <br><small><?= e($unitat) ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Gràfic de distribució de discrepàncies (si n'hi ha) -->
    <?php if (!empty($detall_inventari) && $resum && (int)$resum['discreancies'] > 0): ?>
        <div class="detall-bloc detall-bloc--mt-l">
            <h2 class="detall-bloc__titol">
                <i class="fas fa-chart-pie" aria-hidden="true"></i>
                Anàlisi de discrepàncies
            </h2>

            <div class="kpi-grid kpi-grid--petit">
                <div class="kpi-card">
                    <div class="kpi-card__icona">
                        <i class="fas fa-percentage" aria-hidden="true"></i>
                    </div>
                    <span class="kpi-card__valor">
                        <?= number_format(((int)$resum['discreancies'] / (int)$resum['total_productes']) * 100, 1, ',', '.') ?>%
                    </span>
                    <span class="kpi-card__etiqueta">% amb discrepància</span>
                </div>

                <div class="kpi-card">
                    <div class="kpi-card__icona">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                    </div>
                    <span class="kpi-card__valor">
                        <?= number_format((float)$resum['diferencia_total_abs'] / (int)$resum['discreancies'], 2, ',', '.') ?>
                    </span>
                    <span class="kpi-card__etiqueta">Mitjana per discrepància</span>
                </div>

                <div class="kpi-card">
                    <div class="kpi-card__icona">
                        <i class="fas fa-trophy" aria-hidden="true"></i>
                    </div>
                    <span class="kpi-card__valor">
                        <?= number_format(((int)$resum['total_productes'] - (int)$resum['discreancies']) / (int)$resum['total_productes'] * 100, 1, ',', '.') ?>%
                    </span>
                    <span class="kpi-card__etiqueta">Precisió global</span>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
