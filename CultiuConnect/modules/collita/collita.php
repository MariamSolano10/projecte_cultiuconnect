<?php
/**
 * modules/collita/collita.php — Llistat de collites registrades.
 *
 * Mostra totes les entrades de producció amb sector, varietat,
 * quantitat, qualitat i estat (en curs / finalitzada).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$collites  = [];
$resum     = [];
$error_db  = null;

try {
    $pdo = connectDB();

    // KPIs del mes actual
    $resum = $pdo->query("
        SELECT
            COUNT(*)                                        AS total_collites,
            COALESCE(SUM(CASE WHEN unitat_mesura='kg' THEN quantitat ELSE 0 END), 0) AS kg_totals,
            COALESCE(SUM(CASE WHEN unitat_mesura='t'  THEN quantitat * 1000 ELSE 0 END), 0) AS kg_tones,
            SUM(CASE WHEN data_fi IS NULL THEN 1 ELSE 0 END) AS en_curs
        FROM collita
        WHERE MONTH(data_inici) = MONTH(CURRENT_DATE())
          AND YEAR(data_inici)  = YEAR(CURRENT_DATE())
    ")->fetch();

    $avan = $pdo->query("
        SELECT
            (
                SELECT COALESCE(SUM(ps.superficie_m2)/10000, 1)
                FROM parcela_sector ps
            ) AS ha_actives,
            (
                SELECT COALESCE(SUM(quantitat), 0)
                FROM collita
                WHERE YEAR(data_inici) = YEAR(CURRENT_DATE()) AND unitat_mesura = 'kg'
            ) AS kg_any_total,
            (
                SELECT COALESCE(SUM(hores_treballades), 0.001)
                FROM collita_treballador ct
                JOIN collita c ON c.id_collita = ct.id_collita
                WHERE YEAR(c.data_inici) = YEAR(CURRENT_DATE())
            ) AS hores_recol_any
    ")->fetch();

    $collites = $pdo->query("
        SELECT
            c.id_collita,
            c.data_inici,
            c.data_fi,
            c.quantitat,
            c.unitat_mesura,
            c.qualitat,
            c.observacions,
            s.nom        AS nom_sector,
            v.nom_varietat
        FROM collita c
        JOIN plantacio pl ON c.id_plantacio = pl.id_plantacio
        JOIN sector    s  ON pl.id_sector   = s.id_sector
        LEFT JOIN varietat v ON pl.id_varietat = v.id_varietat
        ORDER BY c.data_inici DESC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] collita.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les collites.';
}

$classes_qualitat = [
    'Extra'      => 'badge--verd',
    'Primera'    => 'badge--blau',
    'Segona'     => 'badge--groc',
    'Industrial' => 'badge--gris',
];

$titol_pagina  = 'Collites';
$pagina_activa = 'collita';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-collita">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-apple-whole" aria-hidden="true"></i>
            Gestió de Collites
        </h1>
        <p class="descripcio-seccio">
            Registre de totes les entrades de producció. Cada collita queda vinculada
            a la plantació i als treballadors que hi han participat.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Panell Principal Collita (Layout) -->
    <?php if (!$error_db): ?>
    <div class="dashboard-layout dashboard-layout--separat">
        
        <!-- COLUMNA PRINCIPAL -->
        <div class="dashboard-column dashboard-column--main">
            
            <div class="kpi-grid">
                <div class="kpi-card kpi-card--verd">
                    <div class="kpi-card__icona"><i class="fas fa-boxes-stacked"></i></div>
                    <div class="kpi-card__contingut">
                        <span class="kpi-card__valor"><?= (int)$resum['total_collites'] ?></span>
                        <span class="kpi-card__etiqueta">Collites aquest mes</span>
                    </div>
                </div>
                <div class="kpi-card kpi-card--blau">
                    <div class="kpi-card__icona"><i class="fas fa-weight-hanging"></i></div>
                    <div class="kpi-card__contingut">
                        <span class="kpi-card__valor">
                            <?= format_kg((float)$resum['kg_totals'] + (float)$resum['kg_tones'], 0) ?>
                        </span>
                        <span class="kpi-card__etiqueta">Total produït (kg)</span>
                    </div>
                </div>
                <div class="kpi-card <?= $resum['en_curs'] > 0 ? 'kpi-card--taronja' : 'kpi-card--gris' ?>">
                    <div class="kpi-card__icona"><i class="fas fa-tractor"></i></div>
                    <div class="kpi-card__contingut">
                        <span class="kpi-card__valor"><?= (int)$resum['en_curs'] ?></span>
                        <span class="kpi-card__etiqueta">Collites en curs</span>
                    </div>
                </div>
            </div>

            <!-- Simulador Econòmic Estilitzat -->
            <div class="feed-widget feed-widget--pad-l">
               <div class="flex-between-start-wrap">
                  <div>
                      <h3 class="panel-titol">
                          <i class="fas fa-euro-sign" class="panel-titol__icona--alerta"></i> Retorn Econòmic Estimat
                      </h3>
                      <p class="text-suau--petit">
                          Simulació predictiva basada en els rendiments d'aquest any natural.
                      </p>
                      <div id="valor-retorn-ha" class="metric-hero">
                          0,00 �,�/ha
                      </div>
                  </div>
                  <div class="simulador-caixa">
                      <label for="preu-simul" class="form-label form-label--verd">
                          Preu de Mercat (�,�/kg):
                      </label>
                      <input type="number" id="preu-simul" value="0.85" step="0.05" min="0" class="form-input input-simulador">
                  </div>
               </div>
            </div>
            
        </div>

        <!-- COLUMNA LATERAL (KPIs Avançats) -->
        <div class="dashboard-column dashboard-column--side">
            <div class="feed-widget feed-widget--columna">
                <header class="feed-header feed-header--blanc">
                    <h3><i class="fas fa-chart-area"></i> Rendiment Actiu</h3>
                </header>
                <div class="stats-grid stats-grid--single">
                    <div class="stat-card stat-card--llista-separada">
                        <i class="fas fa-seedling icona-verd"></i>
                        <div class="stat-val stat-val--verd">
                            <?= number_format(((float)$avan['kg_any_total'] / (float)$avan['ha_actives']), 1, ',', '.') ?> <span class="unitat-subtil">kg/ha</span>
                        </div>
                        <div class="stat-label">Rendiment Mitjà</div>
                    </div>
                    <div class="stat-card stat-card--llista">
                        <i class="fas fa-stopwatch icona-taronja"></i>
                        <div class="stat-val">
                            <?= number_format(((float)$avan['kg_any_total'] / (float)$avan['hores_recol_any']), 1, ',', '.') ?> <span class="unitat-subtil">kg/h</span>
                        </div>
                        <div class="stat-label">Eficiència Personal (Hora)</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const inputPreu = document.getElementById('preu-simul');
        const retornU   = document.getElementById('valor-retorn-ha');
        // Ràtio kg/ha (fixa backend pre-calculada)
        const ratioKgHa = <?= (float)$avan['kg_any_total'] / (float)$avan['ha_actives'] ?>;

        function updateRetorn() {
            const val = parseFloat(inputPreu.value) || 0;
            const res = ratioKgHa * val;
            retornU.textContent = res.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' �,�/ha';
        }

        inputPreu.addEventListener('input', updateRetorn);
        updateRetorn(); // Execució inicial
    });
    </script>
    <?php endif; ?>

    <!-- Toolbar de la Taula (Unida) -->
    <div class="feed-header feed-header--toolbar">
        <div class="flex-inline-actions">
            <a href="<?= BASE_URL ?>modules/collita/nova_collita.php" class="boto-principal boto-secundari--petit">
                <i class="fas fa-plus"></i> Registrar Collita
            </a>
            <a href="<?= BASE_URL ?>modules/collita/lot_produccio.php" class="boto-secundari boto-secundari--petit boto-secundari--blanc">
                <i class="fas fa-boxes-packing"></i> Gestió de Lots
            </a>
        </div>
        <div class="toolbar-search">
            <div class="input-with-icon input-with-icon--compact">
                <i class="fas fa-search input-icon"></i>
                <input type="search"
                       data-filtre-taula="taula-collites"
                       placeholder="Cerca per sector, varietat o qualitat..."
                       class="form-input"
                       aria-label="Cercar collites">
            </div>
        </div>
    </div>

    <!-- Taula Principal -->
    <table class="taula-simple" id="taula-collites">
        <thead>
            <tr>
                <th>ID</th>
                <th>Inici</th>
                <th>Fi</th>
                <th>Sector</th>
                <th>Varietat</th>
                <th>Quantitat</th>
                <th>Qualitat</th>
                <th>Estat</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($collites)): ?>
                <tr>
                    <td colspan="9" class="sense-dades">
                        <i class="fas fa-info-circle"></i>
                        No hi ha collites registrades.
                        <a href="<?= BASE_URL ?>modules/collita/nova_collita.php">Registra'n una.</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($collites as $c):
                    $classe_q = $classes_qualitat[$c['qualitat']] ?? 'badge--gris';
                    $en_curs  = empty($c['data_fi']);
                ?>
                    <tr>
                        <td><?= (int)$c['id_collita'] ?></td>
                        <td><?= format_data($c['data_inici'] ? substr($c['data_inici'], 0, 10) : null, curta: true) ?></td>
                        <td><?= $c['data_fi'] ? format_data(substr($c['data_fi'], 0, 10), curta: true) : '<em class="text-suau">En curs</em>' ?></td>
                        <td data-cerca><strong><?= e($c['nom_sector']) ?></strong></td>
                        <td data-cerca><?= e($c['nom_varietat'] ?? '—') ?></td>
                        <td><?= number_format((float)$c['quantitat'], 2, ',', '.') . ' ' . e($c['unitat_mesura']) ?></td>
                        <td data-cerca>
                            <span class="badge <?= $classe_q ?>"><?= e($c['qualitat']) ?></span>
                        </td>
                        <td>
                            <?php if ($en_curs): ?>
                                <span class="badge badge--taronja">En curs</span>
                            <?php else: ?>
                                <span class="badge badge--verd">Finalitzada</span>
                            <?php endif; ?>
                        </td>
                        <td class="cel-accions">
                            <a href="<?= BASE_URL ?>modules/collita/lot_produccio.php?collita_id=<?= (int)$c['id_collita'] ?>"
                               title="Veure lots associats"
                               class="btn-accio btn-accio--veure">
                                <i class="fas fa-boxes-packing"></i>
                            </a>
                            <a href="<?= BASE_URL ?>modules/collita/nou_lot_produccio.php?collita_id=<?= (int)$c['id_collita'] ?>"
                               title="Crear lot d'envasat"
                               class="btn-accio btn-accio--editar">
                                <i class="fas fa-plus"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

