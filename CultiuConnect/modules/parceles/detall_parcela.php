<?php
/**
 * modules/parceles/detall_parcela.php — Fitxa agronòmica detallada d'una parcel·la.
 *
 * Accés des de parceles.php via ?id=<id_parcela>
 *
 * Seccions:
 *   1. KPIs bàsics (superfície, sectors, arbres, collita darrer any)
 *   2. Mapa Leaflet de la parcel·la
 *   3. Sectors i plantacions actives
 *   4. Anàlisi de sòl (darrera per sector)
 *   5. Historial de collites
 *   6. Infraestructures associades
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// ── Validació de l'ID ──────────────────────────────────────────────────────────
$id = sanitize_int($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Location: ' . BASE_URL . 'modules/parceles/parceles.php');
    exit;
}

// ── Variables per defecte ──────────────────────────────────────────────────────
$parcela      = null;
$sectors      = [];
$sol_darrer   = [];   // [id_sector => row]
$collites     = [];
$infra        = [];
$error_db     = null;

try {
    $pdo = connectDB();

    // ── 1. Dades bàsiques de la parcel·la ─────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT
            id_parcela,
            nom,
            superficie_ha,
            pendent,
            orientacio,
            documentacio_pdf,
            ST_AsGeoJSON(coordenades_geo) AS geojson
        FROM parcela
        WHERE id_parcela = ?
    ");
    $stmt->execute([$id]);
    $parcela = $stmt->fetch();

    if (!$parcela) {
        http_response_code(404);
        header('Location: ' . BASE_URL . 'modules/parceles/parceles.php');
        exit;
    }

    // ── 2. Sectors associats amb plantació i varietat actives ─────────────────
    $stmt2 = $pdo->prepare("
        SELECT
            s.id_sector,
            s.nom                               AS nom_sector,
            s.descripcio                        AS descripcio_sector,
            ps.superficie_m2,
            pl.id_plantacio,
            pl.data_plantacio,
            pl.marc_fila,
            pl.marc_arbre,
            pl.num_arbres_plantats,
            pl.num_falles,
            pl.sistema_formacio,
            pl.previsio_entrada_produccio,
            pl.data_arrencada,
            v.nom_varietat,
            e.nom_comu                          AS nom_especie,
            e.nom_cientific,
            v.productivitat_mitjana_esperada,
            v.cicle_vegetatiu,
            (SELECT COUNT(*) FROM fila_arbre fa WHERE fa.id_sector = s.id_sector)
                                                AS num_files,
            (SELECT SUM(fa.num_arbres)
             FROM fila_arbre fa
             WHERE fa.id_sector = s.id_sector)  AS total_arbres_files
        FROM parcela_sector ps
        JOIN sector    s  ON s.id_sector   = ps.id_sector
        LEFT JOIN plantacio pl ON pl.id_sector   = s.id_sector
                               AND pl.data_arrencada IS NULL
        LEFT JOIN varietat  v  ON v.id_varietat  = pl.id_varietat
        LEFT JOIN especie   e  ON e.id_especie   = v.id_especie
        WHERE ps.id_parcela = ?
        ORDER BY s.nom ASC
    ");
    $stmt2->execute([$id]);
    $sectors = $stmt2->fetchAll();

    // ── 3. Darrera anàlisi de sòl per cada sector d'aquesta parcel·la ─────────
    if (!empty($sectors)) {
        $ids_sectors = array_column($sectors, 'id_sector');
        $in_ph = implode(',', array_fill(0, count($ids_sectors), '?'));
        $stmt3 = $pdo->prepare("
            SELECT cs.*
            FROM caracteristiques_sol cs
            INNER JOIN (
                SELECT id_sector, MAX(data_analisi) AS max_data
                FROM caracteristiques_sol
                WHERE id_sector IN ($in_ph)
                GROUP BY id_sector
            ) ult ON ult.id_sector = cs.id_sector AND ult.max_data = cs.data_analisi
        ");
        $stmt3->execute($ids_sectors);
        foreach ($stmt3->fetchAll() as $row) {
            $sol_darrer[$row['id_sector']] = $row;
        }
    }

    // ── 4. Historial de collites (via plantació → sector → parcel·la) ─────────
    $stmt4 = $pdo->prepare("
        SELECT
            c.id_collita,
            c.data_inici,
            c.data_fi,
            c.quantitat,
            c.unitat_mesura,
            c.qualitat,
            s.nom   AS nom_sector,
            v.nom_varietat
        FROM collita c
        JOIN plantacio  pl ON pl.id_plantacio = c.id_plantacio
        JOIN sector     s  ON s.id_sector     = pl.id_sector
        JOIN parcela_sector ps ON ps.id_sector = s.id_sector
        LEFT JOIN varietat v ON v.id_varietat = pl.id_varietat
        WHERE ps.id_parcela = ?
        ORDER BY c.data_inici DESC
        LIMIT 20
    ");
    $stmt4->execute([$id]);
    $collites = $stmt4->fetchAll();

    // ── 5. Infraestructures dins la parcel·la ─────────────────────────────────
    $stmt5 = $pdo->prepare("
        SELECT
            i.id_infra,
            i.nom,
            i.tipus
        FROM infraestructura i
        JOIN parcela_infraestructura pi ON pi.id_infra = i.id_infra
        WHERE pi.id_parcela = ?
        ORDER BY i.tipus ASC, i.nom ASC
    ");
    $stmt5->execute([$id]);
    $infra = $stmt5->fetchAll();

    // ── 6. Historial de collites per any (per al gràfic) ──────────────────────
    $stmt_collites_any = $pdo->prepare("
        SELECT YEAR(c.data_inici) AS any,
               COALESCE(SUM(c.quantitat), 0) AS kg_total
        FROM collita c
        JOIN plantacio pl ON pl.id_plantacio = c.id_plantacio
        JOIN sector s ON s.id_sector = pl.id_sector
        JOIN parcela_sector ps ON ps.id_sector = s.id_sector
        WHERE ps.id_parcela = ?
          AND c.unitat_mesura IN ('kg','Kg','KG')
        GROUP BY YEAR(c.data_inici)
        ORDER BY any ASC
    ");
    $stmt_collites_any->execute([$id]);
    $collites_per_any = $stmt_collites_any->fetchAll();

    // ── 7. Previsió vs real (rendiment esperat vs collita) ────────────────────
    $stmt_prev = $pdo->prepare("
        SELECT YEAR(pc.data_previsio) AS any,
               COALESCE(SUM(pc.produccio_estimada_kg), 0) AS kg_previst
        FROM previsio_collita pc
        JOIN plantacio p ON p.id_plantacio = pc.id_plantacio
        JOIN sector s ON s.id_sector = p.id_sector
        JOIN parcela_sector ps ON ps.id_sector = s.id_sector
        WHERE ps.id_parcela = ?
        GROUP BY YEAR(pc.data_previsio)
        ORDER BY any ASC
    ");
    $stmt_prev->execute([$id]);
    $previsio_per_any = $stmt_prev->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] detall_parcela.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades de la parcel·la.';
}

// ── KPIs agregats ─────────────────────────────────────────────────────────────
$total_arbres = array_sum(array_column($sectors, 'num_arbres_plantats'))
              ?: array_sum(array_column($sectors, 'total_arbres_files'));

$kg_any_actual = array_sum(
    array_filter(
        array_map(fn($c) => (date('Y', strtotime($c['data_inici'])) == date('Y'))
            ? (float) $c['quantitat'] : 0, $collites)
    )
);

$num_sectors = count($sectors);

$superficie = (float)$parcela['superficie_ha'];
$rendiment_kg_ha = $superficie > 0 ? round($kg_any_actual / $superficie, 2) : 0;
$densitat_efectiva = $superficie > 0 ? round($total_arbres / $superficie, 0) : 0;

// Preparar dades per als gràfics
$anys_collita   = array_column($collites_per_any ?? [], 'any');
$kg_collita     = array_column($collites_per_any ?? [], 'kg_total');
$prev_map       = [];
foreach ($previsio_per_any ?? [] as $pr) {
    $prev_map[$pr['any']] = (float)$pr['kg_previst'];
}
$kg_previsio = [];
foreach ($anys_collita as $a) {
    $kg_previsio[] = $prev_map[$a] ?? null;
}

// ── Helpers de presentació ────────────────────────────────────────────────────
function badge_qualitat(string $q): string
{
    return match ($q) {
        'Extra'     => 'badge--verd',
        'Primera'   => 'badge--blau',
        'Segona'    => 'badge--groc',
        'Industrial'=> 'badge--vermell',
        default     => 'badge--gris',
    };
}

function badge_tipus_infra(string $t): string
{
    return match ($t) {
        'reg'        => 'badge--blau',
        'camin'      => 'badge--gris',
        'tanca'      => 'badge--groc',
        'edificacio' => 'badge--verd',
        default      => 'badge--gris',
    };
}

// ── Capçalera ─────────────────────────────────────────────────────────────────
$titol_pagina   = 'Detall Parcel·la — ' . ($parcela['nom'] ?? '');
$pagina_activa  = 'parceles';
$css_addicional = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-parceles">

    <!-- Capçalera de la pàgina -->
    <div class="capcalera-seccio">
        <div>
            <h1 class="titol-pagina">
                <i class="fas fa-map-location-dot" aria-hidden="true"></i>
                <?= e($parcela['nom']) ?>
            </h1>
            <p class="descripcio-seccio">
                Fitxa agronòmica completa — superfície, cultius, sòl i collites.
            </p>
        </div>
        <div class="botons-accions mt-0">
            <a href="<?= BASE_URL ?>modules/parceles/parceles.php"
               class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
            </a>
            <a href="<?= BASE_URL ?>modules/parceles/editar_parcela.php?id=<?= $id ?>"
               class="boto-principal">
                <i class="fas fa-pen" aria-hidden="true"></i> Editar
            </a>
            <?php if ($parcela['documentacio_pdf']): ?>
                <a href="<?= e($parcela['documentacio_pdf']) ?>"
                   target="_blank" rel="noopener" class="boto-secundari">
                    <i class="fas fa-file-pdf" aria-hidden="true"></i> Documentació
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- ────────────────────────────────────────────────────
         KPIs
    ──────────────────────────────────────────────────── -->
    <div class="kpi-grid">

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= format_ha((float) $parcela['superficie_ha']) ?></div>
            <div class="kpi-card__etiqueta">Superfície total</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= $num_sectors ?></div>
            <div class="kpi-card__etiqueta">Sectors de cultiu</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__valor">
                <?= $total_arbres ? number_format($total_arbres, 0, ',', '.') : '—' ?>
            </div>
            <div class="kpi-card__etiqueta">Arbres plantats</div>
        </div>

        <div class="kpi-card stat-card--verd">
            <div class="kpi-card__valor"><?= format_kg($kg_any_actual, 0) ?></div>
            <div class="kpi-card__etiqueta">Collita enguany (kg)</div>
        </div>

        <?php if ($rendiment_kg_ha > 0): ?>
        <div class="kpi-card stat-card--blau">
            <div class="kpi-card__valor"><?= number_format($rendiment_kg_ha, 0, ',', '.') ?></div>
            <div class="kpi-card__etiqueta">Rendiment (kg/ha)</div>
        </div>
        <?php endif; ?>

        <?php if ($densitat_efectiva > 0): ?>
        <div class="kpi-card stat-card--groc">
            <div class="kpi-card__valor"><?= number_format($densitat_efectiva, 0, ',', '.') ?></div>
            <div class="kpi-card__etiqueta">Arbres/ha</div>
        </div>
        <?php endif; ?>

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= $parcela['pendent'] ? e($parcela['pendent']) : '—' ?></div>
            <div class="kpi-card__etiqueta">Pendent del terreny</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-card__valor"><?= $parcela['orientacio'] ? e($parcela['orientacio']) : '—' ?></div>
            <div class="kpi-card__etiqueta">Orientació</div>
        </div>

    </div>

    <!-- ────────────────────────────────────────────────────
         Mapa Leaflet
    ──────────────────────────────────────────────────── -->
        <div class="grafic-contenidor grafic-contenidor--net">
            <div id="mapa-detall-parcela" class="mapa-embed"
             aria-label="Mapa de la parcel·la <?= e($parcela['nom']) ?>"></div>
    </div>

    <!-- ────────────────────────────────────────────────────
         Sectors i Plantacions
    ──────────────────────────────────────────────────── -->
    <div class="seguiment-bloc">
        <h2 class="seguiment-bloc__titol">
            <i class="fas fa-seedling" aria-hidden="true"></i>
            Sectors i Plantacions Actives
        </h2>

        <?php if (empty($sectors)): ?>
            <p class="sense-dades">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                No hi ha sectors associats a aquesta parcel·la.
            </p>
        <?php else: ?>
            <div class="grafics-grid">
                <?php foreach ($sectors as $s): ?>
                <div class="kpi-card kpi-card--top">
                    <div class="fila-entre-ample">
                        <strong class="titol-sector"><?= e($s['nom_sector']) ?></strong>
                            <span class="badge badge--blau">
                                <?= number_format($s['superficie_m2'] / 10000, 2, ',', '.') ?> ha
                            </span>
                        </div>

                        <?php if ($s['nom_varietat']): ?>
                            <div>
                                <span class="text-suau">Cultiu:</span>
                                <strong><?= e($s['nom_varietat']) ?></strong>
                                <?php if ($s['nom_especie']): ?>
                                    <em class="text-suau">(<?= e($s['nom_especie']) ?>)</em>
                                <?php endif; ?>
                            </div>

                            <?php if ($s['nom_cientific']): ?>
                    <div class="text-suau text-italic-soft">
                                    <?= e($s['nom_cientific']) ?>
                                </div>
                            <?php endif; ?>

                    <div class="meta-wrap">
                                <?php if ($s['data_plantacio']): ?>
                                    <span>
                                        <i class="fas fa-calendar-days text-suau" aria-hidden="true"></i>
                                        Plantació: <?= e($s['data_plantacio']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($s['marc_fila'] && $s['marc_arbre']): ?>
                                    <span>
                                        <i class="fas fa-ruler-combined text-suau" aria-hidden="true"></i>
                                        Marc: <?= e($s['marc_fila']) ?> × <?= e($s['marc_arbre']) ?> m
                                    </span>
                                <?php endif; ?>
                                <?php if ($s['num_arbres_plantats']): ?>
                                    <span>
                                        <i class="fas fa-tree text-suau" aria-hidden="true"></i>
                                        <?= (int) $s['num_arbres_plantats'] ?> arbres
                                        <?php if ($s['num_falles'] > 0): ?>
                                            <span class="text-suau">(<?= (int) $s['num_falles'] ?> falles)</span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($s['num_files']): ?>
                                    <span>
                                        <i class="fas fa-grip-lines text-suau" aria-hidden="true"></i>
                                        <?= (int) $s['num_files'] ?> files
                                    </span>
                                <?php endif; ?>
                                <?php if ($s['sistema_formacio']): ?>
                                    <span>
                                        <i class="fas fa-sitemap text-suau" aria-hidden="true"></i>
                                        <?= e($s['sistema_formacio']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($s['productivitat_mitjana_esperada']): ?>
                                    <span>
                                        <i class="fas fa-chart-bar text-suau" aria-hidden="true"></i>
                                        Rendiment esperat: <?= format_kg((float) $s['productivitat_mitjana_esperada'], 0) ?>/ha
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($s['descripcio_sector']): ?>
                                <p class="text-suau text-suau--mini">
                                    <?= e($s['descripcio_sector']) ?>
                                </p>
                            <?php endif; ?>

                        <?php else: ?>
                            <em class="text-suau">Sense plantació activa</em>
                        <?php endif; ?>

                        <!-- Accions del sector -->
                            <div class="accions-inline--tight">
                            <a href="<?= BASE_URL ?>modules/mapa/mapa_gis.php"
                               class="btn-accio btn-accio--veure"
                               title="Veure al mapa GIS">
                                <i class="fas fa-map" aria-hidden="true"></i>
                            </a>
                            <a href="<?= BASE_URL ?>modules/operacions/operacio_nova.php?id_sector=<?= (int) $s['id_sector'] ?>"
                               class="btn-accio btn-accio--editar"
                               title="Registrar tractament">
                                <i class="fas fa-spray-can-sparkles" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ────────────────────────────────────────────────────
         Anàlisi de Sòl
    ──────────────────────────────────────────────────── -->
    <?php if (!empty($sol_darrer)): ?>
        <div class="seguiment-bloc">
            <h2 class="seguiment-bloc__titol">
                <i class="fas fa-flask" aria-hidden="true"></i>
                Darrera Anàlisi de Sòl
            </h2>
            <table class="taula-simple">
                <thead>
                    <tr>
                        <th>Sector</th>
                        <th>Data</th>
                        <th>Textura</th>
                        <th class="text-dreta">pH</th>
                        <th class="text-dreta">M.O. (%)</th>
                        <th class="text-dreta">N (ppm)</th>
                        <th class="text-dreta">P (ppm)</th>
                        <th class="text-dreta">K (ppm)</th>
                        <th class="text-dreta">CE (dS/m)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sectors as $s):
                        $sol = $sol_darrer[$s['id_sector']] ?? null;
                        if (!$sol) continue;
                    ?>
                        <tr>
                            <td><?= e($s['nom_sector']) ?></td>
                            <td><?= e($sol['data_analisi']) ?></td>
                            <td><?= $sol['textura'] ? e($sol['textura']) : '—' ?></td>
                            <td class="text-dreta">
                                <?php if ($sol['pH']): ?>
                                    <?php
                                        $ph = (float) $sol['pH'];
                                        $cl = $ph < 5.5 ? 'badge--vermell' : ($ph > 7.5 ? 'badge--groc' : 'badge--verd');
                                    ?>
                                    <span class="badge <?= $cl ?>"><?= number_format($ph, 1) ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="text-dreta"><?= $sol['materia_organica'] ? number_format($sol['materia_organica'], 2) : '—' ?></td>
                            <td class="text-dreta"><?= $sol['N'] ? number_format($sol['N'], 1) : '—' ?></td>
                            <td class="text-dreta"><?= $sol['P'] ? number_format($sol['P'], 1) : '—' ?></td>
                            <td class="text-dreta"><?= $sol['K'] ? number_format($sol['K'], 1) : '—' ?></td>
                            <td class="text-dreta"><?= $sol['conductivitat_electrica'] ? number_format($sol['conductivitat_electrica'], 3) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ────────────────────────────────────────────────────
         Historial de Collites
    ──────────────────────────────────────────────────── -->
    <div class="seguiment-bloc">
        <h2 class="seguiment-bloc__titol">
            <i class="fas fa-apple-whole" aria-hidden="true"></i>
            Historial de Collites
        </h2>

        <?php if (empty($collites)): ?>
            <p class="sense-dades">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                No hi ha registres de collita per a aquesta parcel·la.
            </p>
        <?php else: ?>
            <table class="taula-simple">
                <thead>
                    <tr>
                        <th>Data inici</th>
                        <th>Data fi</th>
                        <th>Sector</th>
                        <th>Varietat</th>
                        <th class="text-dreta">Quantitat</th>
                        <th>Unitat</th>
                        <th>Qualitat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collites as $c): ?>
                        <tr>
                            <td><?= e($c['data_inici']) ?></td>
                            <td><?= $c['data_fi'] ? e($c['data_fi']) : '<em class="text-suau">En curs</em>' ?></td>
                            <td><?= e($c['nom_sector']) ?></td>
                            <td><?= $c['nom_varietat'] ? e($c['nom_varietat']) : '—' ?></td>
                            <td class="text-dreta"><strong><?= number_format((float) $c['quantitat'], 2, ',', '.') ?></strong></td>
                            <td><?= e($c['unitat_mesura']) ?></td>
                            <td>
                                <span class="badge <?= badge_qualitat($c['qualitat'] ?? '') ?>">
                                    <?= e($c['qualitat'] ?? '—') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- ────────────────────────────────────────────────────
         Infraestructures
    ──────────────────────────────────────────────────── -->
    <?php if (!empty($infra)): ?>
        <div class="seguiment-bloc">
            <h2 class="seguiment-bloc__titol">
                <i class="fas fa-trowel-bricks" aria-hidden="true"></i>
                Infraestructures
            </h2>
            <table class="taula-simple">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Tipus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($infra as $i): ?>
                        <tr>
                            <td><?= e($i['nom']) ?></td>
                            <td>
                                <span class="badge <?= badge_tipus_infra($i['tipus']) ?>">
                                    <?= e(ucfirst($i['tipus'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ────────────────────────────────────────────────────
         GRÀFIC: Historial de Collita per Any + Previsió
    ──────────────────────────────────────────────────── -->
    <?php if (!empty($collites_per_any)): ?>
    <div class="seguiment-bloc">
        <h2 class="seguiment-bloc__titol">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            Evolució del Rendiment per Any
        </h2>
        <div class="mapa-embed--small">
            <canvas id="grafic-rendiment-parcela"
                    aria-label="Gràfic d'evolució del rendiment per any"
                    role="img"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- Accions inferiors -->
    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/parceles/parceles.php"
           class="boto-secundari">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar al llistat
        </a>
        <a href="<?= BASE_URL ?>modules/seguiment/seguiment_anual.php"
           class="boto-secundari">
            <i class="fas fa-chart-line" aria-hidden="true"></i> Seguiment Anual
        </a>
        <a href="<?= BASE_URL ?>modules/mapa/mapa_gis.php"
           class="boto-secundari">
            <i class="fas fa-map" aria-hidden="true"></i> Mapa GIS
        </a>
    </div>

</div><!-- /.contingut-parceles -->

<!-- Dades per al mapa -->
<script>
    const PARCELA_GEO = <?= json_encode(
        $parcela['geojson'] ? json_decode($parcela['geojson']) : null,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    ) ?>;
    const PARCELA_NOM = <?= json_encode($parcela['nom']) ?>;
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const map = L.map('mapa-detall-parcela').setView([41.6167, 0.6222], 14);

        L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            { attribution: 'Tiles &copy; Esri', maxZoom: 20 }
        ).addTo(map);

        if (PARCELA_GEO) {
            const capa = L.geoJSON(PARCELA_GEO, {
                style: {
                    color: '#27ae60',
                    weight: 3,
                    opacity: 0.9,
                    fillOpacity: 0.3,
                    fillColor: '#27ae60',
                }
            }).addTo(map);

            capa.bindPopup(`<strong>${PARCELA_NOM}</strong>`).openPopup();
            map.fitBounds(capa.getBounds(), { padding: [30, 30] });
        }

        // ── Gràfic de rendiment per any ───────────────────────────
        const canvasRend = document.getElementById('grafic-rendiment-parcela');
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

    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
