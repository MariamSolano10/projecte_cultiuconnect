<?php
/**
 * modules/sectors/detall_sector.php — Fitxa completa d'un sector.
 *
 * Mostra: dades de la plantació activa, mapa del sector, parcel·les
 * associades, última anàlisi de sòl i historial d'aplicacions recents.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();
require __DIR__ . '/partials/historial_plantacions.php';

$id_sector = sanitize_int($_GET['id'] ?? null);

if (!$id_sector) {
    set_flash('error', 'ID de sector invàlid.');
    header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
    exit;
}

try {
    $pdo = connectDB();

    // --------------------------------------------------------
    // 1. Dades del sector i la plantació activa
    // --------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT
            s.id_sector,
            s.nom           AS nom_sector,
            s.descripcio,
            ST_AsGeoJSON(s.coordenades_geo) AS geojson,
            pl.id_plantacio,
            pl.data_plantacio,
            pl.marc_fila,
            pl.marc_arbre,
            pl.num_arbres_plantats,
            pl.num_falles,
            pl.sistema_formacio,
            v.nom_varietat,
            e.nom_comu      AS especie
        FROM sector s
        LEFT JOIN plantacio pl ON pl.id_sector    = s.id_sector
                               AND pl.data_arrencada IS NULL
        LEFT JOIN varietat  v  ON v.id_varietat   = pl.id_varietat
        LEFT JOIN especie   e  ON e.id_especie     = v.id_especie
        WHERE s.id_sector = ?
    ");
    $stmt->execute([$id_sector]);
    $sector = $stmt->fetch();

    if (!$sector) {
        set_flash('error', 'El sector sol·licitat no existeix.');
        header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
        exit;
    }

    // --------------------------------------------------------
    // 2. Parcel·les associades (superfície des de parcela.superficie_ha)
    //    parcela_sector NO té superficie_m2 — usem parcela.superficie_ha
    // --------------------------------------------------------
    $stmt_p = $pdo->prepare("
        SELECT
            pa.nom      AS nom_parcela,
            pa.superficie_ha
        FROM parcela_sector ps
        JOIN parcela pa ON pa.id_parcela = ps.id_parcela
        WHERE ps.id_sector = ?
        ORDER BY pa.nom ASC
    ");
    $stmt_p->execute([$id_sector]);
    $parceles_sector = $stmt_p->fetchAll();

    // --------------------------------------------------------
    // 3. Última anàlisi de sòl
    // --------------------------------------------------------
    $stmt_sol = $pdo->prepare("
        SELECT data_analisi, textura, pH, materia_organica,
               N, P, K, Ca, Mg, Na, conductivitat_electrica
        FROM caracteristiques_sol
        WHERE id_sector = ?
        ORDER BY data_analisi DESC
        LIMIT 1
    ");
    $stmt_sol->execute([$id_sector]);
    $analisi_sol = $stmt_sol->fetch();

    // --------------------------------------------------------
    // 4. Últimes 10 aplicacions amb productes
    //    aplicacio NO té: descripcio, volum_caldo, id_treballador
    //    Els productes venen de detall_aplicacio_producte + producte_quimic
    // --------------------------------------------------------
    $stmt_ap = $pdo->prepare("
        SELECT
            a.id_aplicacio,
            a.data_event,
            a.tipus_event,
            a.condicions_ambientals,
            GROUP_CONCAT(
                CONCAT(pq.nom_comercial, ' (', dap.quantitat_consumida_total, ' ', pq.unitat_mesura, ')')
                ORDER BY pq.nom_comercial SEPARATOR ' · '
            ) AS productes
        FROM aplicacio a
        LEFT JOIN detall_aplicacio_producte dap ON dap.id_aplicacio = a.id_aplicacio
        LEFT JOIN inventari_estoc           ie  ON ie.id_estoc      = dap.id_estoc
        LEFT JOIN producte_quimic           pq  ON pq.id_producte   = ie.id_producte
        WHERE a.id_sector = ?
        GROUP BY a.id_aplicacio
        ORDER BY a.data_event DESC
        LIMIT 10
    ");
    $stmt_ap->execute([$id_sector]);
    $aplicacions = $stmt_ap->fetchAll();

    // --------------------------------------------------------
    // 5. Resum de files d'arbres
    // --------------------------------------------------------
    $stmt_files = $pdo->prepare("
        SELECT COUNT(*)                     AS total_files,
               COALESCE(SUM(num_arbres), 0) AS total_arbres
        FROM fila_arbre
        WHERE id_sector = ?
    ");
    $stmt_files->execute([$id_sector]);
    $resum_files = $stmt_files->fetch();

    // --------------------------------------------------------
    // 6. Historial de collites per any (per al gràfic)
    // --------------------------------------------------------
    $stmt_collites = $pdo->prepare("
        SELECT YEAR(c.data_inici) AS any,
               COALESCE(SUM(c.quantitat), 0) AS kg_total
        FROM   collita c
        JOIN   plantacio p ON p.id_plantacio = c.id_plantacio
        WHERE  p.id_sector = ?
          AND  c.unitat_mesura IN ('kg','Kg','KG')
        GROUP BY YEAR(c.data_inici)
        ORDER BY any ASC
    ");
    $stmt_collites->execute([$id_sector]);
    $collites_per_any = $stmt_collites->fetchAll();

    // --------------------------------------------------------
    // 7. Previsió vs real (rendiment esperat vs collita)
    // --------------------------------------------------------
    $stmt_prev = $pdo->prepare("
        SELECT YEAR(pc.data_previsio) AS any,
               COALESCE(SUM(pc.produccio_estimada_kg), 0) AS kg_previst
        FROM   previsio_collita pc
        JOIN   plantacio p ON p.id_plantacio = pc.id_plantacio
        WHERE  p.id_sector = ?
        GROUP BY YEAR(pc.data_previsio)
        ORDER BY any ASC
    ");
    $stmt_prev->execute([$id_sector]);
    $previsio_per_any = $stmt_prev->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] detall_sector.php: ' . $e->getMessage());
    set_flash('error', 'Error carregant les dades del sector.');
    header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
    exit;
}

// Càlculs per a la vista
$nom_cultiu        = '—';
if ($sector['nom_varietat']) {
    $nom_cultiu = $sector['nom_varietat'] . ' (' . $sector['especie'] . ')';
}
$superficie_ha = array_sum(array_column($parceles_sector, 'superficie_ha'));

// ── KPIs Agronòmics Calculats ────────────────────────────
$arbres_plantats  = (int)($sector['num_arbres_plantats'] ?? 0);
$arbres_falles    = (int)($sector['num_falles'] ?? 0);
$arbres_efectius  = $arbres_plantats - $arbres_falles;
$marc_fila        = (float)($sector['marc_fila'] ?? 0);
$marc_arbre       = (float)($sector['marc_arbre'] ?? 0);
$arbres_teorics   = 0;
$densitat_efectiva = 0;

if ($marc_fila > 0 && $marc_arbre > 0 && $superficie_ha > 0) {
    $superficie_m2    = $superficie_ha * 10000;
    $arbres_teorics   = (int)floor($superficie_m2 / ($marc_fila * $marc_arbre));
    $densitat_efectiva = $superficie_ha > 0 ? round($arbres_efectius / $superficie_ha, 0) : 0;
}

// Edat de la plantació i vida útil
$edat_plantacio    = null;
$vida_util_estimada = 25; // anys per defecte per arbres fruiters
$percentatge_vida  = 0;
if ($sector['data_plantacio']) {
    $data_pl = new DateTime($sector['data_plantacio']);
    $avui    = new DateTime();
    $edat_plantacio = (int)$data_pl->diff($avui)->y;
    $percentatge_vida = min(100, round(($edat_plantacio / $vida_util_estimada) * 100));
}

// Preparar dades per als gràfics
$anys_collita   = array_column($collites_per_any, 'any');
$kg_collita     = array_column($collites_per_any, 'kg_total');
$prev_map       = [];
foreach ($previsio_per_any as $pr) {
    $prev_map[$pr['any']] = (float)$pr['kg_previst'];
}
// Alinear previsió amb els anys de collita
$kg_previsio = [];
foreach ($anys_collita as $a) {
    $kg_previsio[] = $prev_map[$a] ?? null;
}

// -----------------------------------------------------------
// Capçalera
// -----------------------------------------------------------
$titol_pagina  = 'Detall de sector';   // Sense dades de BD
$pagina_activa = 'sectors';
$css_addicional = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-detall-sector">

    <!-- Capçalera de secció amb botons d'acció -->
    <div class="capcalera-seccio capcalera-seccio--amb-accions">
        <div>
            <h1 class="titol-pagina">
                <i class="fas fa-layer-group" aria-hidden="true"></i>
                <?= e($sector['nom_sector']) ?>
            </h1>
            <p class="descripcio-seccio">
                <?= $sector['descripcio'] ? e($sector['descripcio']) : 'Sense descripció addicional.' ?>
            </p>
        </div>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/sectors/sectors.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
            </a>
            <a href="<?= BASE_URL ?>modules/sectors/renovar_plantacio.php?id_sector=<?= (int)$id_sector ?>"
               class="boto-secundari">
                <i class="fas fa-rotate" aria-hidden="true"></i> Renovar Plantació
            </a>
            <a href="<?= BASE_URL ?>modules/sectors/nou_sector.php?editar=<?= (int)$id_sector ?>"
               class="boto-principal">
                <i class="fas fa-pen" aria-hidden="true"></i> Editar Sector
            </a>
        </div>
    </div>

    <!-- ================================================
         GRID SUPERIOR: Plantació + Mapa
    ================================================ -->
    <div class="detall-grid">

        <!-- Bloc esquerre: Plantació + Sòl -->
        <div class="detall-bloc">

            <h2 class="detall-bloc__titol detall-bloc__titol--verd">
                <i class="fas fa-leaf" aria-hidden="true"></i> Dades de la Plantació
            </h2>

            <dl class="dades-list">
                <dt>Cultiu</dt>
                <dd><?= e($nom_cultiu) ?></dd>

                <dt>Superfície total</dt>
                <dd><?= format_ha($superficie_ha) ?></dd>

                <?php if ($sector['data_plantacio']): ?>
                    <dt>Data de plantació</dt>
                    <dd><?= format_data($sector['data_plantacio'], curta: true) ?></dd>

                    <dt>Marc de plantació</dt>
                    <dd>
                        <?= $sector['marc_fila'] && $sector['marc_arbre']
                            ? e($sector['marc_fila']) . ' × ' . e($sector['marc_arbre']) . ' m'
                            : '—' ?>
                    </dd>

                    <dt>Sistema de formació</dt>
                    <dd><?= e($sector['sistema_formacio'] ?? '—') ?></dd>

                    <dt>Arbres plantats</dt>
                    <dd>
                        <?= (int)$sector['num_arbres_plantats'] ?>
                        <?php if ((int)$sector['num_falles'] > 0): ?>
                            <span class="text-alert">
                                (<?= (int)$sector['num_falles'] ?> falles)
                            </span>
                        <?php endif; ?>
                    </dd>
                <?php else: ?>
                    <dt>Plantació</dt>
                    <dd><em class="text-suau">Sense plantació activa registrada.</em></dd>
                <?php endif; ?>
            </dl>

            <h2 class="detall-bloc__titol detall-bloc__titol--lila">
                <i class="fas fa-flask" aria-hidden="true"></i> Última Anàlisi de Sòl
            </h2>

            <?php if ($analisi_sol): ?>
                <dl class="dades-list">
                    <dt>Data</dt>
                    <dd><?= format_data($analisi_sol['data_analisi'], curta: true) ?></dd>

                    <dt>Textura</dt>
                    <dd><?= e($analisi_sol['textura'] ?? '—') ?></dd>

                    <dt>pH</dt>
                    <dd>
                        <?php if ($analisi_sol['pH'] !== null):
                            $ph = (float)$analisi_sol['pH'];
                            $classe_ph = ($ph < 5.5 || $ph > 8.0) ? 'text-alert' : '';
                        ?>
                            <span class="<?= $classe_ph ?>">
                                <?= number_format($ph, 1, ',', '.') ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </dd>

                    <dt>Matèria orgànica</dt>
                    <dd>
                        <?= $analisi_sol['materia_organica'] !== null
                            ? number_format((float)$analisi_sol['materia_organica'], 2, ',', '.') . ' %'
                            : '—' ?>
                    </dd>

                    <dt>N — P — K (ppm)</dt>
                    <dd>
                        <?= $analisi_sol['N'] !== null ? number_format((float)$analisi_sol['N'], 1, ',', '.') : '—' ?>
                        —
                        <?= $analisi_sol['P'] !== null ? number_format((float)$analisi_sol['P'], 1, ',', '.') : '—' ?>
                        —
                        <?= $analisi_sol['K'] !== null ? number_format((float)$analisi_sol['K'], 1, ',', '.') : '—' ?>
                    </dd>

                    <?php if ($analisi_sol['conductivitat_electrica'] !== null): ?>
                        <dt>Conductivitat elèctrica</dt>
                        <dd><?= number_format((float)$analisi_sol['conductivitat_electrica'], 3, ',', '.') ?> dS/m</dd>
                    <?php endif; ?>
                </dl>
                <a href="<?= BASE_URL ?>modules/analisis/analisis_lab.php" class="boto-secundari boto-secundari--petit">
                    <i class="fas fa-flask" aria-hidden="true"></i> Veure totes les anàlisis
                </a>
            <?php else: ?>
                <p class="sense-dades-inline">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    No hi ha anàlisis de sòl registrades per a aquest sector.
                    <a href="<?= BASE_URL ?>modules/analisis/nou_analisi.php?editar_sector=<?= (int)$id_sector ?>">
                        Registra'n una.
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <!-- Bloc dret: Mapa + Parcel·les -->
        <div class="detall-bloc">

            <div id="mapa-sector"
                 class="mapa-detall"
                 role="application"
                 aria-label="Mapa del sector <?= e($sector['nom_sector']) ?>">
            </div>
            <input type="hidden" id="geojson_sector"
                   value="<?= e($sector['geojson'] ?? '') ?>">

            <h2 class="detall-bloc__titol">
                <i class="fas fa-map-marked-alt" aria-hidden="true"></i> Parcel·les del sector
            </h2>

            <?php if (empty($parceles_sector)): ?>
                <p class="sense-dades-inline">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    Aquest sector no està vinculat a cap parcel·la.
                </p>
            <?php else: ?>
                <table class="taula-simple taula-simple--compacta">
                    <thead>
                        <tr>
                            <th>Parcel·la cadastral</th>
                            <th class="text-dreta">Superfície</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parceles_sector as $ps): ?>
                            <tr>
                                <td><?= e($ps['nom_parcela']) ?></td>
                                <td class="text-dreta"><?= format_ha((float)$ps['superficie_ha']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="taula-total">
                            <td><strong>Total</strong></td>
                            <td class="text-dreta"><strong><?= format_ha($superficie_ha) ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
    </div>

    <!-- ================================================
         HISTORIAL D'APLICACIONS
    ================================================ -->
    <div class="detall-bloc detall-bloc--ample">
        <h2 class="detall-bloc__titol detall-bloc__titol--blau">
            <i class="fas fa-spray-can-sparkles" aria-hidden="true"></i>
            Últimes operacions i tractaments
        </h2>

        <table class="taula-simple">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipus</th>
                    <th>Productes (dosi)</th>
                    <th>Condicions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($aplicacions)): ?>
                    <tr>
                        <td colspan="4" class="sense-dades">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            No hi ha operacions registrades per a aquest sector.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($aplicacions as $ap): ?>
                        <tr>
                            <td><?= format_data($ap['data_event'], curta: true) ?></td>
                            <td>
                                <span class="badge badge--blau">
                                    <?= e($ap['tipus_event'] ?? 'General') ?>
                                </span>
                            </td>
                            <td class="cel-text-llarg">
                                <?= $ap['productes'] ? e($ap['productes']) : '—' ?>
                            </td>
                            <td class="cel-text-llarg">
                                <?= $ap['condicions_ambientals'] ? e($ap['condicions_ambientals']) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="accio-peu">
            <a href="<?= BASE_URL ?>modules/quadern/quadern.php?id_sector=<?= (int)$id_sector ?>"
               class="boto-secundari">
                Veure tot l'historial al quadern
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>

    <!-- ================================================
         FILES D'ARBRES
    ================================================ -->
    <div class="detall-bloc detall-bloc--ample">
        <div class="capcalera-bloque">
            <h2 class="detall-bloc__titol detall-bloc__titol--verd detall-bloc__titol--sense-marge">
                <i class="fas fa-grip-lines" aria-hidden="true"></i>
                Files d'Arbres
            </h2>
            <a href="<?= BASE_URL ?>modules/sectors/files_arbre.php?id_sector=<?= (int)$id_sector ?>"
               class="boto-secundari boto-secundari--petit">
                <i class="fas fa-eye" aria-hidden="true"></i>
                Gestionar files
            </a>
        </div>

        <?php if (!empty($resum_files) && (int)$resum_files['total_files'] > 0): ?>
            <div class="resum-cards resum-cards--grid">
                <div class="stat-card stat-card--verd">
                    <div class="stat-card__icon"><i class="fas fa-grip-lines"></i></div>
                    <div class="stat-card__valor"><?= (int)$resum_files['total_files'] ?></div>
                    <div class="stat-card__label">Files registrades</div>
                </div>
                <?php if ((int)$resum_files['total_arbres'] > 0): ?>
                <div class="stat-card stat-card--blau">
                    <div class="stat-card__icon"><i class="fas fa-tree"></i></div>
                    <div class="stat-card__valor"><?= (int)$resum_files['total_arbres'] ?></div>
                    <div class="stat-card__label">Arbres en files</div>
                </div>
                <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>modules/sectors/files_arbre.php?id_sector=<?= (int)$id_sector ?>"
               class="boto-secundari">
                Veure totes les files
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        <?php else: ?>
            <p class="sense-dades-inline">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                No hi ha files d'arbres registrades per a aquest sector.
                <a href="<?= BASE_URL ?>modules/sectors/nou_fila_arbre.php?id_sector=<?= (int)$id_sector ?>">
                    Afegeix la primera fila.
                </a>
            </p>
        <?php endif; ?>
    </div>

    <!-- ================================================
         GRÀFIC: Historial de Collita per Any + Previsió
    ================================================ -->
    <?php if (!empty($collites_per_any)): ?>
    <div class="detall-bloc detall-bloc--ample">
        <h2 class="detall-bloc__titol detall-bloc__titol--blau">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            Evolució del Rendiment per Any
        </h2>
        <div class="mapa-caixa">
            <canvas id="grafic-rendiment-sector"
                    aria-label="Gràfic d'evolució del rendiment per any"
                    role="img"></canvas>
        </div>
        <div class="accio-peu accio-peu--mt">
            <a href="<?= BASE_URL ?>modules/seguiment/dashboard_rendiment.php?id_sector=<?= (int)$id_sector ?>"
               class="boto-secundari">
                Veure anàlisi de rendiment complet
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Leaflet (lectura, sense Leaflet.draw) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
(function () {
'use strict';

// ── Mapa Leaflet ──────────────────────────────────────────
const map = L.map('mapa-sector').setView([41.6167, 0.6222], 13);

L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: '© Esri', maxZoom: 20 }
).addTo(map);

const geojsonRaw = document.getElementById('geojson_sector').value;

if (geojsonRaw) {
    try {
        const capa = L.geoJSON(JSON.parse(geojsonRaw), {
            style: { color: '#2980b9', weight: 3, fillOpacity: 0.35 }
        }).addTo(map);
        map.fitBounds(capa.getBounds(), { padding: [20, 20] });
    } catch (err) {
        console.warn('[CultiuConnect] GeoJSON sector no vàlid:', err);
    }
}

// ── Gràfic de rendiment per any ───────────────────────────
const canvasRend = document.getElementById('grafic-rendiment-sector');
if (canvasRend) {
    const anysR    = <?= json_encode(array_map('strval', $anys_collita)) ?>;
    const kgR      = <?= json_encode(array_map('floatval', $kg_collita)) ?>;
    const kgPrev   = <?= json_encode($kg_previsio) ?>;

    const datasets = [{
        label: 'Collita real (kg)',
        data:  kgR,
        borderColor:     '#2e7d32',
        backgroundColor: 'rgba(46,125,50,0.12)',
        borderWidth: 2.5,
        fill: true,
        tension: 0.35,
        pointRadius: 5,
        pointHoverRadius: 7,
    }];

    // Si hi ha previsions, afegir línia
    if (kgPrev.some(v => v !== null)) {
        datasets.push({
            label: 'Previsió (kg)',
            data:  kgPrev,
            borderColor:     '#e65100',
            backgroundColor: 'rgba(230,81,0,0.08)',
            borderWidth: 2,
            borderDash: [6, 3],
            fill: false,
            tension: 0.3,
            pointRadius: 4,
            pointHoverRadius: 6,
            spanGaps: true,
        });
    }

    new Chart(canvasRend, {
        type: 'line',
        data: { labels: anysR, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => v.toLocaleString('ca-ES') + ' kg' }
                }
            }
        }
    });
}

})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
