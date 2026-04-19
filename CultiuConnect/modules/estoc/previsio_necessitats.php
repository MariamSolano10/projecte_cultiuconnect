<?php
/**
 * modules/estoc/previsio_necessitats.php
 *
 * Sistema d'intel·ligència d'estoc que prediu les necessitats futures
 * basant-se en els tractaments programats dels propers 15 dies.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina = 'Previsió de Necessitats d\'Estoc';
$pagina_activa = 'estoc';

$necessitats = [];
$alerts_estoc = [];
$error_db = null;

try {
    $pdo = connectDB();

    // 1. Obtenir tractaments programats dels propers 15 dies
    $data_inici = date('Y-m-d');
    $data_fi = date('Y-m-d', strtotime('+15 days'));

    $stmt = $pdo->prepare("
        SELECT 
            tp.id_programat,
            tp.data_prevista,
            tp.id_protocol,
            tp.motiu,
            s.nom AS nom_sector,
            pr.productes_json
        FROM tractament_programat tp
        LEFT JOIN sector s ON tp.id_sector = s.id_sector
        LEFT JOIN protocol_tractament pr ON tp.id_protocol = pr.id_protocol
        WHERE tp.data_prevista BETWEEN :data_inici AND :data_fi
          AND tp.estat = 'pendent'
        ORDER BY tp.data_prevista ASC
    ");
    $stmt->execute([
        ':data_inici' => $data_inici,
        ':data_fi' => $data_fi
    ]);
    $tractaments = $stmt->fetchAll();

    // 2. Calcular necessitats totals per producte
    $necessitats_totals = [];
    foreach ($tractaments as $tractament) {
        if (!$tractament['productes_json']) continue;
        
        $productes = json_decode($tractament['productes_json'], true);
        if (!is_array($productes)) continue;

        // Obtenir superfície del sector
        $stmtSup = $pdo->prepare("
            SELECT COALESCE(SUM(ps.superficie_m2) /10000, 0) AS superficie_ha
            FROM parcela_sector ps
            WHERE ps.id_sector = :id_sector
        ");
        $stmtSup->execute([':id_sector' => $tractament['id_sector']]);
        $superficie_ha = (float) $stmtSup->fetchColumn();

        foreach ($productes as $producte) {
            $id_producte = (int) ($producte['id_producte'] ?? 0);
            $dosi_ha = (float) ($producte['dosi_ha'] ?? 0);
            
            if (!$id_producte || $dosi_ha <= 0 || $superficie_ha <= 0) continue;
            
            $quantitat_necessaria = round($dosi_ha * $superficie_ha, 4);
            
            if (!isset($necessitats_totals[$id_producte])) {
                $necessitats_totals[$id_producte] = [
                    'id_producte' => $id_producte,
                    'quantitat_necessaria' => 0,
                    'tractaments' => []
                ];
            }
            
            $necessitats_totals[$id_producte]['quantitat_necessaria'] += $quantitat_necessaria;
            $necessitats_totals[$id_producte]['tractaments'][] = [
                'data' => $tractament['data_prevista'],
                'sector' => $tractament['nom_sector'],
                'motiu' => $tractament['motiu'],
                'quantitat' => $quantitat_necessaria
            ];
        }
    }

    // 3. Comparar amb estoc actual i generar alertes
    if (!empty($necessitats_totals)) {
        $ids_productes = array_keys($necessitats_totals);
        
        $stmt = $pdo->prepare("
            SELECT id_producte, nom_comercial, estoc_actual, estoc_minim, unitat_mesura
            FROM producte_quimic
            WHERE id_producte IN (" . implode(',', $ids_productes) . ")
        ");
        $stmt->execute();
        $productes_info = $stmt->fetchAll(PDO::FETCH_KEY_PAIR | PDO::FETCH_GROUP);
        
        foreach ($necessitats_totals as $id_producte => $necessitat) {
            $info_producte = $productes_info[$id_producte][0] ?? null;
            if (!$info_producte) continue;
            
            $estoc_actual = (float) $info_producte['estoc_actual'];
            $diferencia = $estoc_actual - $necessitat['quantitat_necessaria'];
            $percentatge_disponible = $estoc_actual > 0 ? ($diferencia / $estoc_actual) * 100 : -100;
            
            $necessitats[] = [
                'id_producte' => $id_producte,
                'nom_producte' => $info_producte['nom_comercial'],
                'estoc_actual' => $estoc_actual,
                'estoc_minim' => (float) $info_producte['estoc_minim'],
                'necessitat_total' => $necessitat['quantitat_necessaria'],
                'diferencia' => $diferencia,
                'percentatge_disponible' => $percentatge_disponible,
                'unitat_mesura' => $info_producte['unitat_mesura'],
                'tractaments' => $necessitat['tractaments'],
                'estat' => $diferencia >= 0 ? 'suficient' : 'insuficient'
            ];
            
            // Generar alerta si estoc insuficient o per sota del mínim
            if ($diferencia < 0 || $estoc_actual < $info_producte['estoc_minim']) {
                $alerts_estoc[] = [
                    'id_producte' => $id_producte,
                    'nom_producte' => $info_producte['nom_comercial'],
                    'tipus_alerta' => $diferencia < 0 ? 'ESTOC_INSUFICIENT' : 'ESTOC_MINIM',
                    'missatge' => $diferencia < 0 
                        ? sprintf(
                            'Estoc insuficient per %s. Necessari: %.2f %s, Disponible: %.2f %s',
                            $info_producte['nom_comercial'],
                            $necessitat['quantitat_necessaria'],
                            $info_producte['unitat_mesura'],
                            $estoc_actual,
                            $info_producte['unitat_mesura']
                        )
                        : sprintf(
                            'Estoc baix per %s. Actual: %.2f %s, Mínim: %.2f %s',
                            $info_producte['nom_comercial'],
                            $estoc_actual,
                            $info_producte['unitat_mesura'],
                            $info_producte['estoc_minim'],
                            $info_producte['unitat_mesura']
                        )
                ];
            }
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] previsio_necessitats.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les previsions de necessitats.';
}

function getClasseEstat($estat): string {
    return match($estat) {
        'suficient' => 'badge--verd',
        'insuficient' => 'badge--vermell',
        default => 'badge--groc'
    };
}

function getIconaEstat($estat): string {
    return match($estat) {
        'suficient' => 'fa-check-circle',
        'insuficient' => 'fa-exclamation-triangle',
        default => 'fa-question-circle'
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-estoc">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            Previsió de Necessitats d'Estoc
        </h1>
        <p class="descripcio-seccio">
            Calcula la demanda futura de productes basada en tractaments programats.
            Planifica les compres segons les necessitats previstes per als propers mesos.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="form-filtres">
        <form method="GET" class="form-filtres__form">
            <div class="form-grup">
                <label for="mesos" class="form-label">Període de previsió</label>
                <select id="mesos" name="mesos" class="form-select">
                    <option value="1" <?= $mesos_previsio === 1 ? 'selected' : '' ?>>Pròxim mes</option>
                    <option value="3" <?= $mesos_previsio === 3 ? 'selected' : '' ?>>Pròxims 3 mesos</option>
                    <option value="6" <?= $mesos_previsio === 6 ? 'selected' : '' ?>>Pròxims 6 mesos</option>
                    <option value="12" <?= $mesos_previsio === 12 ? 'selected' : '' ?>>Pròxim any</option>
                </select>
            </div>

            <div class="form-grup">
                <label for="id_producte" class="form-label">Producte (opcional)</label>
                <select id="id_producte" name="id_producte" class="form-select">
                    <option value="0">Tots els productes</option>
                    <?php foreach ($productes as $prod): ?>
                        <option value="<?= (int)$prod['id_producte'] ?>" 
                                <?= $id_producte_filtrar === (int)$prod['id_producte'] ? 'selected' : '' ?>>
                            <?= e($prod['nom_comercial']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="boto-principal">
                <i class="fas fa-filter" aria-hidden="true"></i> Aplicar filtres
            </button>
        </form>
    </div>

    <!-- Resum -->
    <div class="kpi-grid kpi-grid--petit kpi-grid--spaced">
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-flask" aria-hidden="true"></i>
            </div>
            <span class="kpi-card__valor"><?= count($previsions) ?></span>
            <span class="kpi-card__etiqueta">Productes amb demanda</span>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            </div>
            <span class="kpi-card__valor"><?= $mesos_previsio ?></span>
            <span class="kpi-card__etiqueta">Mesos de previsió</span>
        </div>

        <?php 
        $total_necessitat = array_sum(array_column($previsions, 'quantitat_necessaria'));
        $productes_critics = 0;
        foreach ($previsions as $prev) {
            [$classe] = estatPrevisio((float)$prev['estoc_actual'], (float)$prev['quantitat_necessaria']);
            if ($classe === 'badge--vermell') $productes_critics++;
        }
        ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            </div>
            <span class="kpi-card__valor"><?= $productes_critics ?></span>
            <span class="kpi-card__etiqueta">Productes crítics</span>
        </div>
    </div>

    <!-- Taula de previsions -->
    <div class="taula-container">
        <table class="taula-simple">
            <thead>
                <tr>
                    <th>Producte</th>
                    <th>Unitat</th>
                    <th>Estoc actual</th>
                    <th>Necessitat prevista</th>
                    <th>Balanç</th>
                    <th>Estat</th>
                    <th>Tractaments</th>
                    <th>Període</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($previsions)): ?>
                    <tr>
                        <td colspan="9" class="sense-dades">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            No hi ha tractaments programats que requereixin productes en aquest període.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($previsions as $prev): 
                        $unitat = $prev['unitat_mesura'] ?? '';
                        $estoc_actual = (float)$prev['estoc_actual'];
                        $necessitat = (float)$prev['quantitat_necessaria'];
                        
                        [$classe_estat, $text_estat, $diferencia] = estatPrevisio($estoc_actual, $necessitat);
                        
                        $periode = '';
                        if ($prev['data_propera'] && $prev['data_ultima']) {
                            if ($prev['data_propera'] === $prev['data_ultima']) {
                                $periode = format_data($prev['data_propera'], curta: true);
                            } else {
                                $periode = format_data($prev['data_propera'], curta: true) . ' - ' . format_data($prev['data_ultima'], curta: true);
                            }
                        }
                    ?>
                        <tr>
                            <td><strong><?= e($prev['nom_comercial']) ?></strong></td>
                            <td><?= e($unitat) ?></td>
                            <td class="text-dreta">
                                <strong><?= number_format($estoc_actual, 2, ',', '.') ?></strong>
                            </td>
                            <td class="text-dreta estat-alerta">
                                <strong><?= number_format($necessitat, 2, ',', '.') ?></strong>
                            </td>
                            <td class="text-dreta">
                                <span class="<?= $diferencia >= 0 ? 'estat-ok' : 'estat-alerta' ?>">
                                    <?= $diferencia >= 0 ? '+' : '-' ?>
                                    <?= number_format(abs($estoc_actual - $necessitat), 2, ',', '.') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $classe_estat ?>">
                                    <?= $text_estat ?>
                                </span>
                            </td>
                            <td class="text-dreta"><?= (int)$prev['num_tractaments'] ?></td>
                            <td><?= e($periode) ?></td>
                            <td class="cel-accions">
                                <a href="<?= BASE_URL ?>modules/estoc/detall_producte.php?id=<?= (int)$prev['id_producte'] ?>"
                                   title="Veure detall del producte"
                                   class="btn-accio btn-accio--veure">
                                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                </a>
                                <a href="<?= BASE_URL ?>modules/tractaments/tractaments_programats.php?id_producte=<?= (int)$prev['id_producte'] ?>"
                                   title="Veure tractaments d'aquest producte"
                                   class="btn-accio">
                                    <i class="fas fa-spray-can" aria-hidden="true"></i>
                                </a>
                                <?php if ($classe_estat === 'badge--vermell'): ?>
                                    <a href="<?= BASE_URL ?>modules/estoc/moviment_estoc.php?id_producte=<?= (int)$prev['id_producte'] ?>"
                                       title="Comprar producte (entrada d'estoc)"
                                       class="btn-accio btn-accio--important">
                                        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Accions -->
    <div class="accio-peu accio-peu--mt">
        <a href="<?= BASE_URL ?>modules/estoc/estoc.php" class="boto-secundari">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar a l'inventari
        </a>
        <a href="<?= BASE_URL ?>modules/tractaments/tractaments_programats.php" class="boto-secundari">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i> Veure tractaments programats
        </a>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
