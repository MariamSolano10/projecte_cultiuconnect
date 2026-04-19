<?php
/**
 * modules/seguiment/seguiment_anual.php — Dashboard de seguiment anual.
 *
 * Consolida KPIs de tots els mòduls per a la presa de decisions de direcció.
 * Filtre per any (per defecte any actual).
 *
 * Taules consultades:
 *   collita, aplicacio, detall_aplicacio_producte, producte_quimic,
 *   monitoratge_plaga, treballador, registre_hores, inversio,
 *   sector, plantacio, varietat, especie
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina = 'Seguiment Anual';
$pagina_activa = 'seguiment';

$any_sel = sanitize_int($_GET['any'] ?? date('Y')) ?: (int) date('Y');
$any_ant = $any_sel - 1;

// Dades que omplirem
$kpi = [];
$prod_sector = [];
$cost_cat = [];
$tractaments_mes = [];
$collita_mes = [];
$error_db = null;

try {
    $pdo = connectDB();

    // ============================================================
    // KPIs principals
    // ============================================================

    // 1. Producció total (kg) any sel i any anterior + # collites
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN YEAR(data_inici) = :any  THEN quantitat ELSE 0 END), 0) AS kg_any,
            COALESCE(SUM(CASE WHEN YEAR(data_inici) = :any1 THEN quantitat ELSE 0 END), 0) AS kg_ant,
            COUNT(CASE  WHEN YEAR(data_inici) = :any2 THEN 1 END)                          AS num_collites
        FROM collita
        WHERE unitat_mesura IN ('kg','Kg','KG')
    ");
    $stmt->execute([':any' => $any_sel, ':any1' => $any_ant, ':any2' => $any_sel]);
    $row = $stmt->fetch();
    $kpi['kg_any'] = (float) ($row['kg_any'] ?? 0);
    $kpi['kg_ant'] = (float) ($row['kg_ant'] ?? 0);
    $kpi['num_collites'] = (int) ($row['num_collites'] ?? 0);
    $kpi['variacio_kg'] = $kpi['kg_ant'] > 0
        ? round((($kpi['kg_any'] - $kpi['kg_ant']) / $kpi['kg_ant']) * 100, 1)
        : null;

    // 2. Tractaments i productes consumits
    $stmt2 = $pdo->prepare("
    SELECT
        COUNT(DISTINCT a.id_aplicacio)              AS num_tractaments,
        COALESCE(SUM(dap.quantitat_consumida_total), 0)  AS kg_producte
    FROM aplicacio a
    LEFT JOIN detall_aplicacio_producte dap ON dap.id_aplicacio = a.id_aplicacio
    WHERE YEAR(a.data_event) = ?
");
    $stmt2->execute([$any_sel]);
    $res2 = $stmt2->fetch();
    $kpi['num_tractaments'] = $res2['num_tractaments'];
    $kpi['kg_producte'] = $res2['kg_producte'];

    // 3. Monitoratges i alertes
    $stmt3 = $pdo->prepare("
    SELECT
        COUNT(*)                                          AS total,
        COALESCE(SUM(llindar_intervencio_assolit = 1), 0)  AS alertes
    FROM monitoratge_plaga
    WHERE YEAR(data_observacio) = ?
");
    $stmt3->execute([$any_sel]);
    $res3 = $stmt3->fetch();
    $kpi['total_monitoratges'] = $res3['total'];
    $kpi['alertes_fitos'] = $res3['alertes'];

    // 4. Personal actiu i hores treballades (extretes de collita_treballador)
    $kpi['treballadors_actius'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM treballador WHERE estat = 'actiu'"
    )->fetchColumn();

    $stmt4 = $pdo->prepare("
    SELECT COALESCE(SUM(ct.hores_treballades), 0)
    FROM collita_treballador ct
    JOIN collita c ON ct.id_collita = c.id_collita
    WHERE YEAR(c.data_inici) = ?
");
    $stmt4->execute([$any_sel]);
    $kpi['num_hores'] = (float) $stmt4->fetchColumn();

    // 5. Inversions / despeses totals
    $stmt5 = $pdo->prepare("
        SELECT COALESCE(SUM(import), 0)
        FROM inversio
        WHERE YEAR(data_inversio) = ?
    ");
    $stmt5->execute([$any_sel]);
    $kpi['despeses_any'] = (float) $stmt5->fetchColumn();

    // ============================================================
    // Producció per sector (per al gràfic de barres)
    // ============================================================
    $stmt6 = $pdo->prepare("
    SELECT
        s.nom   AS sector,
        COALESCE(SUM(c.quantitat), 0) AS kg_total
    FROM collita c
    JOIN plantacio p ON p.id_plantacio = c.id_plantacio
    JOIN sector s ON s.id_sector = p.id_sector
    WHERE YEAR(c.data_inici) = ?
      AND c.unitat_mesura = 'kg'
    GROUP BY s.id_sector, s.nom
    ORDER BY kg_total DESC
    LIMIT 10
");
    $stmt6->execute([$any_sel]);
    $prod_sector = $stmt6->fetchAll();

    // ============================================================
    // Despeses per categoria
    // ============================================================
    $stmt7 = $pdo->prepare("
        SELECT categoria, COALESCE(SUM(import), 0) AS total
        FROM inversio
        WHERE YEAR(data_inversio) = ?
        GROUP BY categoria
        ORDER BY total DESC
    ");
    $stmt7->execute([$any_sel]);
    $cost_cat = $stmt7->fetchAll();

    // ============================================================
    // Collita mensual (per al gràfic de línia)
    // ============================================================
    $stmt8 = $pdo->prepare("
        SELECT
            MONTH(data_inici)           AS mes,
            COALESCE(SUM(quantitat), 0) AS kg
        FROM collita
        WHERE YEAR(data_inici) = ?
          AND unitat_mesura IN ('kg','Kg','KG')
        GROUP BY MONTH(data_inici)
        ORDER BY mes ASC
    ");
    $stmt8->execute([$any_sel]);
    $collita_mes_raw = $stmt8->fetchAll();

    // Omplir tots els 12 mesos (0 si no hi ha dades)
    $collita_mes = array_fill(1, 12, 0.0);
    foreach ($collita_mes_raw as $r) {
        $collita_mes[(int) $r['mes']] = (float) $r['kg'];
    }

    // ============================================================
    // Tractaments mensuals
    // ============================================================
    $stmt9 = $pdo->prepare("
    SELECT MONTH(data_event) AS mes, COUNT(*) AS n
    FROM aplicacio
    WHERE YEAR(data_event) = ?
    GROUP BY MONTH(data_event)
    ORDER BY mes ASC
");
    $stmt9->execute([$any_sel]);
    $tractaments_mes = $stmt9->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (Exception $e) {
    error_log('[CultiuConnect] seguiment_anual.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del seguiment anual.';
}

$anys = range((int) date('Y'), (int) date('Y') - 5);

// Noms dels mesos en català
$noms_mesos = ['Gen', 'Feb', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Des'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-seguiment">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            Seguiment Anual de l'Explotació
        </h1>
        <p class="descripcio-seccio">
            Resum executiu de producció, tractaments, personal i inversions per a la presa de decisions.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Selector d'any -->
    <form method="GET" class="formulari-filtres">
        <div class="form-fila-inline">
            <label for="any" class="form-label">Any d'anàlisi:</label>
            <select id="any" name="any" class="form-select form-select--compact" onchange="this.form.submit()">
                <?php foreach ($anys as $a): ?>
                    <option value="<?= $a ?>" <?= $a === $any_sel ? 'selected' : '' ?>>
                        <?= $a ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <!-- ================================================
         KPIs principals
    ================================================ -->
    <div class="kpi-grid">

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= format_kg($kpi['kg_any'] ?? 0, 0) ?></div>
            <div class="kpi-card__etiqueta">Producció total (kg)</div>
            <?php if (isset($kpi['variacio_kg'])): ?>
                <div class="kpi-card__variacio <?= $kpi['variacio_kg'] >= 0 ? 'kpi-up' : 'kpi-down' ?>">
                    <i class="fas fa-arrow-<?= $kpi['variacio_kg'] >= 0 ? 'up' : 'down' ?>"></i>
                    <?= abs($kpi['variacio_kg']) ?>% vs <?= $any_ant ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= (int) ($kpi['num_collites'] ?? 0) ?></div>
            <div class="kpi-card__etiqueta">Registres de collita</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= (int) ($kpi['num_tractaments'] ?? 0) ?></div>
            <div class="kpi-card__etiqueta">Tractaments fitosanitaris</div>
        </div>

        <div class="kpi-card <?= ($kpi['alertes_fitos'] ?? 0) > 0 ? 'kpi-card--alerta' : '' ?>">
            <div class="kpi-card__valor"><?= (int) ($kpi['alertes_fitos'] ?? 0) ?></div>
            <div class="kpi-card__etiqueta">Alertes de plaga (llindar assolit)</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= (int) ($kpi['treballadors_actius'] ?? 0) ?></div>
            <div class="kpi-card__etiqueta">Treballadors actius</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= format_hores($kpi['num_hores'] ?? 0) ?></div>
            <div class="kpi-card__etiqueta">Hores treballades</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= format_euros($kpi['despeses_any'] ?? 0) ?></div>
            <div class="kpi-card__etiqueta">Despeses / inversions</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= format_kg($kpi['kg_producte'] ?? 0, 1) ?></div>
            <div class="kpi-card__etiqueta">Producte fitosanitari aplicat (kg)</div>
        </div>

    </div>

    <!-- ================================================
         Gràfics mensuals
    ================================================ -->
    <div class="grafics-grid">

        <!-- Collita mensual -->
        <div class="grafic-contenidor">
            <h2 class="grafic-titol">
                <i class="fas fa-apple-whole" aria-hidden="true"></i>
                Producció mensual (kg) — <?= $any_sel ?>
            </h2>
            <canvas id="grafic-collita" height="120" aria-label="Gràfic de producció mensual" role="img"></canvas>
        </div>

        <!-- Tractaments mensuals -->
        <div class="grafic-contenidor">
            <h2 class="grafic-titol">
                <i class="fas fa-spray-can-sparkles" aria-hidden="true"></i>
                Tractaments fitosanitaris per mes — <?= $any_sel ?>
            </h2>
            <canvas id="grafic-tractaments" height="120" aria-label="Gràfic de tractaments mensuals"
                role="img"></canvas>
        </div>

    </div>

    <!-- ================================================
         Taules de resum
    ================================================ -->
    <div class="seguiment-taules">

        <!-- Producció per sector -->
        <?php if (!empty($prod_sector)): ?>
            <div class="seguiment-bloc">
                <h2 class="seguiment-bloc__titol">
                    <i class="fas fa-map-pin" aria-hidden="true"></i>
                    Producció per sector
                </h2>
                <table class="taula-simple">
                    <thead>
                        <tr>
                            <th>Sector</th>
                            <th class="text-dreta">Kg collits</th>
                            <th>% sobre total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prod_sector as $ps):
                            $pct = $kpi['kg_any'] > 0
                                ? round(((float) $ps['kg_total'] / $kpi['kg_any']) * 100, 1)
                                : 0;
                            ?>
                            <tr>
                                <td><?= e($ps['sector']) ?></td>
                                <td class="text-dreta"><strong><?= format_kg((float) $ps['kg_total'], 0) ?></strong></td>
                                <td>
                                    <div class="barra-progres">
                                        <div class="barra-progres__farcit" data-width="<?= min($pct, 100) ?>"
                                            aria-label="<?= $pct ?>%"></div>
                                    </div>
                                    <span class="text-suau"><?= $pct ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Despeses per categoria -->
        <?php if (!empty($cost_cat)): ?>
            <div class="seguiment-bloc">
                <h2 class="seguiment-bloc__titol">
                    <i class="fas fa-euro-sign" aria-hidden="true"></i>
                    Despeses per categoria
                </h2>
                <table class="taula-simple">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th class="text-dreta">Import</th>
                            <th>% sobre total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cost_cat as $cc):
                            $pct = $kpi['despeses_any'] > 0
                                ? round(((float) $cc['total'] / $kpi['despeses_any']) * 100, 1)
                                : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge badge--blau"><?= e($cc['categoria']) ?></span>
                                </td>
                                <td class="text-dreta"><strong><?= format_euros((float) $cc['total']) ?></strong></td>
                                <td>
                                    <div class="barra-progres">
                                        <div class="barra-progres__farcit" data-width="<?= min($pct, 100) ?>"></div>
                                    </div>
                                    <span class="text-suau"><?= $pct ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

    <!-- Accions -->
    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/seguiment/dashboard_rendiment.php?any=<?= $any_sel ?>"
           class="boto-principal">
            <i class="fas fa-chart-bar" aria-hidden="true"></i>
            Analisi de Rendiment Historic
        </a>
        <a href="<?= BASE_URL ?>modules/quadern/quadern.php?any=<?= $any_sel ?>" class="boto-secundari">
            <i class="fas fa-book-open" aria-hidden="true"></i> Quadern de Camp
        </a>
        <a href="<?= BASE_URL ?>modules/quadern/quadern_normativa.php" class="boto-secundari">
            <i class="fas fa-scale-balanced" aria-hidden="true"></i> Normativa
        </a>
    </div>

</div>

<!-- Chart.js per als gràfics mensuals -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    (function () {
        'use strict';

        const mesos = <?= json_encode(array_values($noms_mesos)) ?>;
        const collita = <?= json_encode(array_values($collita_mes)) ?>;
        const tractaments = <?= json_encode(array_values($tractaments_mes)) ?>;

        // Colors CSS del projecte
        const colorPrincipal = getComputedStyle(document.documentElement)
            .getPropertyValue('--color-principal').trim() || '#2e7d32';
        const colorAccentBlau = getComputedStyle(document.documentElement)
            .getPropertyValue('--color-accent-blau').trim() || '#1565c0';

        document.querySelectorAll('.barra-progres__farcit[data-width]').forEach(el => {
            el.style.width = `${el.dataset.width}%`;
        });

        // Gràfic collita mensual
        new Chart(document.getElementById('grafic-collita'), {
            type: 'bar',
            data: {
                labels: mesos,
                datasets: [{
                    label: 'Kg collits',
                    data: collita,
                    backgroundColor: colorPrincipal + 'cc',
                    borderColor: colorPrincipal,
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: v => v.toLocaleString('ca-ES') + ' kg'
                        }
                    }
                }
            }
        });

        // Gràfic tractaments mensuals
        new Chart(document.getElementById('grafic-tractaments'), {
            type: 'line',
            data: {
                labels: mesos,
                datasets: [{
                    label: 'Tractaments',
                    data: tractaments,
                    backgroundColor: colorAccentBlau + '33',
                    borderColor: colorAccentBlau,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    })();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

