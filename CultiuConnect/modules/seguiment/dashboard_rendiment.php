<?php
/**
 * modules/seguiment/dashboard_rendiment.php
 *
 * Dashboard d'Analisi de Rendiment Historic.
 * Permet comparar la productivitat entre sectors, parceles i varietats
 * mitjancant grafics interactius al llarg dels anys.
 *
 * Grafics inclosos:
 *   1. Evolucio plurianual de produccio total (linia, ultims N anys)
 *   2. Produccio per sector comparada any a any (barres agrupades)
 *   3. Rendiment kg/ha per varietat (barres horitzontals, any seleccionat)
 *   4. Evolucio del rendiment d'una varietat concreta al llarg dels anys (linia)
 *
 * Filtres disponibles:
 *   - Nombre d'anys a mostrar (5, 10, tots)
 *   - Any de referencia per a l'analisi de varietats
 *   - Sector concret (opcional)
 *   - Varietat concreta (opcional, per al grafic 4)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Analisi de Rendiment Historic';
$pagina_activa = 'seguiment';

// -----------------------------------------------------------
// Filtres GET
// -----------------------------------------------------------
$any_ref      = sanitize_int($_GET['any']        ?? date('Y')) ?: (int)date('Y');
$num_anys     = sanitize_int($_GET['num_anys']   ?? 5)         ?: 5;
$num_anys     = in_array($num_anys, [5, 10, 20], true) ? $num_anys : 5;
$id_sector    = sanitize_int($_GET['id_sector']  ?? null);
$id_parcela   = sanitize_int($_GET['id_parcela'] ?? null);
$id_varietat  = sanitize_int($_GET['id_varietat'] ?? null);

$any_inici    = $any_ref - $num_anys + 1;

// -----------------------------------------------------------
// Dades
// -----------------------------------------------------------
$sectors       = [];
$varietats     = [];

// Grafic 1: produccio total per any (linia plurianual)
$prod_per_any  = [];

// Grafic 2: produccio per sector i any (barres agrupades)
$prod_sector_any = [];   // [nom_sector][any] = kg
$sectors_noms    = [];

// Grafic 3: rendiment kg/ha per varietat (any de referencia)
$rend_varietat   = [];

// Grafic 4: evolucio d'una varietat concreta
$evolucio_var    = [];

$error_db = null;

try {
    $pdo = connectDB();

    // Llistes per als selectors
    $sectors  = $pdo->query("SELECT id_sector, nom FROM sector ORDER BY nom ASC")->fetchAll();
    $parceles = $pdo->query("SELECT id_parcela, nom FROM parcela ORDER BY nom ASC")->fetchAll();

    $varietats = $pdo->query("
        SELECT v.id_varietat,
               CONCAT(v.nom_varietat, ' (', e.nom_comu, ')') AS nom_complet
        FROM   varietat v
        JOIN   especie  e ON e.id_especie = v.id_especie
        ORDER BY e.nom_comu ASC, v.nom_varietat ASC
    ")->fetchAll();

    // ============================================================
    // GRAFIC 1: Produccio total per any (plurianual)
    // ============================================================
    $where_espai = '';
    $params_espai = [];

    if ($id_sector) {
        $where_espai = 'AND p.id_sector = :id_sector';
        $params_espai[':id_sector'] = $id_sector;
    } elseif ($id_parcela) {
        $where_espai = 'AND p.id_sector IN (SELECT id_sector FROM parcela_sector WHERE id_parcela = :id_parcela)';
        $params_espai[':id_parcela'] = $id_parcela;
    }

    $sql_g1 = "
        SELECT YEAR(c.data_inici)       AS any,
               COALESCE(SUM(c.quantitat), 0) AS kg_total
        FROM   collita c
        JOIN   plantacio p ON p.id_plantacio = c.id_plantacio
        WHERE  c.unitat_mesura IN ('kg','Kg','KG')
          AND  YEAR(c.data_inici) BETWEEN :any_inici AND :any_ref
          {$where_espai}
        GROUP BY YEAR(c.data_inici)
        ORDER BY any ASC
    ";
    $params_g1 = array_merge([':any_inici' => $any_inici, ':any_ref' => $any_ref], $params_espai);

    $stmt = $pdo->prepare($sql_g1);
    $stmt->execute($params_g1);

    // Omplir tots els anys del rang (0 si no hi ha dades)
    $prod_per_any = array_fill_keys(range($any_inici, $any_ref), 0.0);
    foreach ($stmt->fetchAll() as $r) {
        $prod_per_any[(int)$r['any']] = (float)$r['kg_total'];
    }

    // ============================================================
    // GRAFIC 2: Produccio per sector i any (barres agrupades)
    // Limitem als 6 sectors mes productius del periode
    // ============================================================
    $stmt_top = $pdo->prepare("
        SELECT p.id_sector, s.nom,
               COALESCE(SUM(c.quantitat), 0) AS kg_total
        FROM   collita c
        JOIN   plantacio p ON p.id_plantacio = c.id_plantacio
        JOIN   sector    s ON s.id_sector    = p.id_sector
        WHERE  c.unitat_mesura IN ('kg','Kg','KG')
          AND  YEAR(c.data_inici) BETWEEN :any_inici AND :any_ref
          {$where_espai}
        GROUP BY p.id_sector, s.nom
        ORDER BY kg_total DESC
        LIMIT 6
    ");
    $stmt_top->execute(array_merge([':any_inici' => $any_inici, ':any_ref' => $any_ref], $params_espai));
    $top_sectors = $stmt_top->fetchAll();

    if (!empty($top_sectors)) {
        $ids_top      = array_column($top_sectors, 'id_sector');
        $sectors_noms = array_column($top_sectors, 'nom');

        // Inicialitzar estructura buida
        foreach ($sectors_noms as $nom) {
            $prod_sector_any[$nom] = array_fill_keys(range($any_inici, $any_ref), 0.0);
        }

        // Mapa id -> nom
        $mapa_sector = array_combine(
            array_column($top_sectors, 'id_sector'),
            array_column($top_sectors, 'nom')
        );

        $in_placeholders = implode(',', array_fill(0, count($ids_top), '?'));
        $stmt_g2 = $pdo->prepare("
            SELECT p.id_sector,
                   YEAR(c.data_inici)            AS any,
                   COALESCE(SUM(c.quantitat), 0) AS kg
            FROM   collita c
            JOIN   plantacio p ON p.id_plantacio = c.id_plantacio
            WHERE  c.unitat_mesura IN ('kg','Kg','KG')
              AND  YEAR(c.data_inici) BETWEEN ? AND ?
              AND  p.id_sector IN ({$in_placeholders})
            GROUP BY p.id_sector, YEAR(c.data_inici)
            ORDER BY any ASC
        ");
        $stmt_g2->execute(array_merge([$any_inici, $any_ref], $ids_top));

        foreach ($stmt_g2->fetchAll() as $r) {
            $nom = $mapa_sector[(int)$r['id_sector']] ?? null;
            if ($nom) {
                $prod_sector_any[$nom][(int)$r['any']] = (float)$r['kg'];
            }
        }
    }

    // ============================================================
    // GRAFIC 3: Rendiment kg/ha per varietat (any de referencia)
    // ============================================================
    $sql_g3 = "
        SELECT CONCAT(v.nom_varietat, ' (', e.nom_comu, ')') AS varietat,
               ROUND(AVG(sa.rendiment_kg_ha), 1)              AS rend_mig
        FROM   seguiment_anual sa
        JOIN   plantacio p  ON p.id_plantacio = sa.id_plantacio
        JOIN   varietat  v  ON v.id_varietat  = p.id_varietat
        JOIN   especie   e  ON e.id_especie   = v.id_especie
        WHERE  sa.any = :any_ref
          AND  sa.rendiment_kg_ha IS NOT NULL
          {$where_espai}
        GROUP BY v.id_varietat, v.nom_varietat, e.nom_comu
        ORDER BY rend_mig DESC
        LIMIT 12
    ";
    $params_g3 = array_merge([':any_ref' => $any_ref], $params_espai);

    $stmt_g3 = $pdo->prepare($sql_g3);
    $stmt_g3->execute($params_g3);
    $rend_varietat = $stmt_g3->fetchAll();

    // ============================================================
    // GRAFIC 5: Previsió vs Collita
    // ============================================================
    $sql_g5 = "
        SELECT YEAR(pc.data_previsio) AS any,
               COALESCE(SUM(pc.produccio_estimada_kg), 0) AS kg_previst
        FROM   previsio_collita pc
        JOIN   plantacio p ON p.id_plantacio = pc.id_plantacio
        WHERE  YEAR(pc.data_previsio) BETWEEN :any_inici AND :any_ref
          {$where_espai}
        GROUP BY YEAR(pc.data_previsio)
        ORDER BY any ASC
    ";
    $stmt_g5 = $pdo->prepare($sql_g5);
    $stmt_g5->execute(array_merge([':any_inici' => $any_inici, ':any_ref' => $any_ref], $params_espai));

    $prev_per_any = array_fill_keys(range($any_inici, $any_ref), null);
    foreach ($stmt_g5->fetchAll() as $r) {
        $prev_per_any[(int)$r['any']] = (float)$r['kg_previst'];
    }

    // ============================================================
    // GRAFIC 4: Evolucio d'una varietat concreta al llarg dels anys
    // ============================================================
    if ($id_varietat) {
        $stmt_g4 = $pdo->prepare("
            SELECT sa.any,
                   ROUND(AVG(sa.rendiment_kg_ha), 1) AS rend_mig
            FROM   seguiment_anual sa
            JOIN   plantacio p ON p.id_plantacio = sa.id_plantacio
            WHERE  p.id_varietat = :id_varietat
              AND  sa.any BETWEEN :any_inici AND :any_ref
              AND  sa.rendiment_kg_ha IS NOT NULL
            GROUP BY sa.any
            ORDER BY sa.any ASC
        ");
        $stmt_g4->execute([
            ':id_varietat' => $id_varietat,
            ':any_inici'   => $any_inici,
            ':any_ref'     => $any_ref,
        ]);

        $evolucio_raw = $stmt_g4->fetchAll();
        $evolucio_var = array_fill_keys(range($any_inici, $any_ref), null);
        foreach ($evolucio_raw as $r) {
            $evolucio_var[(int)$r['any']] = (float)$r['rend_mig'];
        }
    }

    // ============================================================
    // GRAFIC 6: Heatmap Geoespacial (kg/ha per sector)
    // ============================================================
    $stmt_heatmap = $pdo->prepare("
        SELECT s.id_sector, s.nom,
               ST_AsGeoJSON(s.coordenades_geo) as geojson,
               COALESCE((
                   SELECT SUM(c.quantitat) / NULLIF((SELECT SUM(ps.superficie_m2)/10000 FROM parcela_sector ps WHERE ps.id_sector = s.id_sector), 0)
                   FROM collita c
                   JOIN plantacio pl ON pl.id_plantacio = c.id_plantacio
                   WHERE pl.id_sector = s.id_sector
                     AND YEAR(c.data_inici) = :any_ref
               ), 0) as kg_ha
        FROM sector s
        WHERE s.coordenades_geo IS NOT NULL
          {$where_espai}
    ");
    $stmt_heatmap->execute(array_merge([':any_ref' => $any_ref], $params_espai));
    $heatmap_data = $stmt_heatmap->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] dashboard_rendiment.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del dashboard.';
}

// -----------------------------------------------------------
// Preparar dades JSON per a Chart.js
// -----------------------------------------------------------
$anys_labels = array_keys($prod_per_any);

// Paleta de colors per als sectors (CSS vars amb fallback)
$paleta_js = json_encode([
    '#2e7d32', '#1565c0', '#e65100', '#6a1b9a',
    '#00838f', '#ad1457', '#558b2f', '#4527a0',
]);

// Noms i valors del grafic 3
$g3_labels = json_encode(array_column($rend_varietat, 'varietat'));
$g3_data   = json_encode(array_column($rend_varietat, 'rend_mig'));

// Nom de la varietat seleccionada per al titol del grafic 4
$nom_varietat_sel = '';
if ($id_varietat) {
    foreach ($varietats as $v) {
        if ((int)$v['id_varietat'] === $id_varietat) {
            $nom_varietat_sel = $v['nom_complet'];
            break;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-seguiment">

    <!-- Capcalera -->
    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-chart-bar" aria-hidden="true"></i>
            Analisi de Rendiment Historic
        </h1>
        <p class="descripcio-seccio">
            Comparativa de productivitat entre sectors, parcel&middot;les i varietats al llarg dels anys.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- ================================================
         Filtres globals
    ================================================ -->
    <form method="GET"
          class="formulari-filtres formulari-filtres--dashboard"
          id="form-filtres">

        <div class="form-fila-inline form-fila-inline--wrap">

            <!-- Any de referencia -->
            <div class="form-grup form-grup--compacte">
                <label for="any" class="form-label">Any de referencia:</label>
                <select id="any" name="any" class="form-select form-select--compact">
                    <?php for ($a = (int)date('Y'); $a >= (int)date('Y') - 20; $a--): ?>
                        <option value="<?= $a ?>" <?= $a === $any_ref ? 'selected' : '' ?>>
                            <?= $a ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Finestra temporal -->
            <div class="form-grup form-grup--compacte">
                <label for="num_anys" class="form-label">Periode:</label>
                <select id="num_anys" name="num_anys" class="form-select form-select--compact">
                    <option value="5"  <?= $num_anys ===  5 ? 'selected' : '' ?>>Ultims 5 anys</option>
                    <option value="10" <?= $num_anys === 10 ? 'selected' : '' ?>>Ultims 10 anys</option>
                    <option value="20" <?= $num_anys === 20 ? 'selected' : '' ?>>Ultims 20 anys</option>
                </select>
            </div>

            <!-- Parcel·la -->
            <div class="form-grup form-grup--compacte">
                <label for="id_parcela" class="form-label">Parcel·la:</label>
                <select id="id_parcela" name="id_parcela" class="form-select form-select--compact">
                    <option value="">Totes</option>
                    <?php foreach ($parceles as $pa): ?>
                        <option value="<?= (int)$pa['id_parcela'] ?>"
                            <?= (int)($id_parcela) === (int)$pa['id_parcela'] ? 'selected' : '' ?>>
                            <?= e($pa['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sector (opcional) -->
            <div class="form-grup form-grup--compacte">
                <label for="id_sector" class="form-label">Sector:</label>
                <select id="id_sector" name="id_sector" class="form-select form-select--compact">
                    <option value="">Tots</option>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?= (int)$s['id_sector'] ?>"
                            <?= (int)($id_sector) === (int)$s['id_sector'] ? 'selected' : '' ?>>
                            <?= e($s['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Varietat (per al grafic 4) -->
            <div class="form-grup form-grup--compacte">
                <label for="id_varietat" class="form-label">Varietat (grafic 4):</label>
                <select id="id_varietat" name="id_varietat" class="form-select form-select--compact">
                    <option value="">-- Selecciona varietat --</option>
                    <?php foreach ($varietats as $v): ?>
                        <option value="<?= (int)$v['id_varietat'] ?>"
                            <?= (int)($id_varietat) === (int)$v['id_varietat'] ? 'selected' : '' ?>>
                            <?= e($v['nom_complet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="boto-principal boto-principal--petit">
                <i class="fas fa-filter" aria-hidden="true"></i>
                Aplicar filtres
            </button>

        </div>
    </form>

    <!-- ================================================
         GRAFIC 1: Evolucio plurianual de produccio total
    ================================================ -->
    <div class="grafics-grid grafics-grid--1col">
        <div class="grafic-contenidor">
            <h2 class="grafic-titol">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                Evolucio de la produccio total
                <?= $id_sector ? '— ' . e(array_column($sectors, 'nom', 'id_sector')[$id_sector] ?? '') : '' ?>
                (<?= $any_inici ?> – <?= $any_ref ?>)
            </h2>
            <canvas id="g1-plurianual"
                    height="100"
                    aria-label="Grafic d'evolucio plurianual de produccio"
                    role="img"></canvas>
        </div>
    </div>

    <!-- ================================================
         GRAFIC 5: Real vs Previsió
    ================================================ -->
    <div class="grafics-grid grafics-grid--1col">
                    <div class="grafic-contenidor grafic-contenidor--alt">
            <h2 class="grafic-titol">
                <i class="fas fa-balance-scale" aria-hidden="true"></i>
                Rendiment Real vs Estimat
                <?= $id_sector ? '— Sector: ' . e(array_column($sectors, 'nom', 'id_sector')[$id_sector] ?? '') : '' ?>
                <?= $id_parcela ? '— Parcel·la: ' . e(array_column($parceles, 'nom', 'id_parcela')[$id_parcela] ?? '') : '' ?>
            </h2>
            <canvas id="g5-prev-real"
                    aria-label="Grafic evolució de la produccio total vs previst"
                    role="img"></canvas>
        </div>
    </div>

    <!-- ================================================
         GRAFIC 2: Produccio per sector i any
    ================================================ -->
    <?php if (!empty($sectors_noms)): ?>
    <div class="grafics-grid grafics-grid--1col">
        <div class="grafic-contenidor">
            <h2 class="grafic-titol">
                <i class="fas fa-map-pin" aria-hidden="true"></i>
                Produccio per sector any a any
                (<?= $any_inici ?> – <?= $any_ref ?>)
            </h2>
            <canvas id="g2-sectors"
                    height="110"
                    aria-label="Grafic de produccio per sector i any"
                    role="img"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================
         GRAFIC 3 i 4: Costat a costat
    ================================================ -->
    <div class="grafics-grid">

        <!-- GRAFIC 3: Rendiment per varietat -->
        <div class="grafic-contenidor">
            <h2 class="grafic-titol">
                <i class="fas fa-seedling" aria-hidden="true"></i>
                Rendiment per varietat (kg/ha) — <?= $any_ref ?>
            </h2>
            <?php if (empty($rend_varietat)): ?>
                <div class="estat-buit estat-buit--petit">
                    <i class="fas fa-circle-info estat-buit__icona" aria-hidden="true"></i>
                    <p class="estat-buit__text">
                        Sense dades de seguiment anual per a <?= $any_ref ?>.
                        <a href="<?= BASE_URL ?>modules/seguiment/seguiment_anual.php?any=<?= (int)$any_ref ?>">
                            Veure seguiment anual
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <canvas id="g3-varietats"
                        height="<?= min(count($rend_varietat) * 28 + 40, 380) ?>"
                        aria-label="Grafic de rendiment per varietat"
                        role="img"></canvas>
            <?php endif; ?>
        </div>

        <!-- GRAFIC 4: Evolucio d'una varietat concreta -->
        <div class="grafic-contenidor">
            <h2 class="grafic-titol">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                Evolucio de varietat
                <?= $nom_varietat_sel ? '— ' . e($nom_varietat_sel) : '' ?>
            </h2>
            <?php if (!$id_varietat): ?>
                <div class="estat-buit estat-buit--petit">
                    <i class="fas fa-hand-pointer estat-buit__icona" aria-hidden="true"></i>
                    <p class="estat-buit__text">
                        Selecciona una varietat al filtre superior per veure la seva evolucio historica.
                    </p>
                </div>
            <?php elseif (empty(array_filter($evolucio_var, fn($v) => $v !== null))): ?>
                <div class="estat-buit estat-buit--petit">
                    <i class="fas fa-circle-info estat-buit__icona" aria-hidden="true"></i>
                    <p class="estat-buit__text">Sense dades de seguiment per a aquesta varietat.</p>
                </div>
            <?php else: ?>
                <canvas id="g4-evolucio-var"
                        height="180"
                        aria-label="Grafic d'evolucio de la varietat seleccionada"
                        role="img"></canvas>
            <?php endif; ?>
        </div>

    </div>

    <!-- ================================================
         GRAFIC 6: Heatmap Rendiment
    ================================================ -->
    <div class="grafics-grid grafics-grid--1col">
        <div class="grafic-contenidor">
            <h2 class="grafic-titol">
                <i class="fas fa-map" aria-hidden="true"></i>
                Mapa de Rendiment Agronòmic (kg/ha) — <?= $any_ref ?>
            </h2>
                    <div id="heatmap-map" class="mapa-embed--heat"></div>
        </div>
    </div>

    <!-- ================================================
         Taula de resum comparatiu
    ================================================ -->
    <?php if (!empty($prod_per_any)): ?>
    <div class="seguiment-taules">
        <div class="seguiment-bloc seguiment-bloc--ample">
            <h2 class="seguiment-bloc__titol">
                <i class="fas fa-table" aria-hidden="true"></i>
                Resum numeric per any
            </h2>
            <div class="taula-responsive">
                <table class="taula-simple" aria-label="Resum numeric de produccio per any">
                    <thead>
                        <tr>
                            <th scope="col">Any</th>
                            <th scope="col" class="text-dreta">Produccio (kg)</th>
                            <th scope="col" class="text-dreta">Variacio vs any anterior</th>
                            <th scope="col">Tendencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $anys_taula = array_reverse($anys_labels);
                        $prev_kg    = null;
                        foreach ($anys_taula as $a):
                            $kg  = $prod_per_any[$a] ?? 0;
                            $var = null;
                            if ($prev_kg !== null && $prev_kg > 0) {
                                $var = round((($kg - $prev_kg) / $prev_kg) * 100, 1);
                            }
                            $prev_kg = $kg;
                        ?>
                        <tr>
                            <td><strong><?= $a ?></strong></td>
                            <td class="text-dreta"><?= format_kg($kg, 0) ?></td>
                            <td class="text-dreta">
                                <?php if ($var !== null): ?>
                                    <span class="<?= $var >= 0 ? 'text-exit' : 'text-perill' ?>">
                                        <i class="fas fa-arrow-<?= $var >= 0 ? 'up' : 'down' ?>"></i>
                                        <?= abs($var) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($var === null): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($var > 5): ?>
                                    <span class="badge badge--success">Pujada</span>
                                <?php elseif ($var < -5): ?>
                                    <span class="badge badge--error">Baixada</span>
                                <?php else: ?>
                                    <span class="badge badge--neutral">Estable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Accio: tornar al seguiment anual -->
    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/seguiment/seguiment_anual.php"
           class="boto-secundari">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            Tornar al Seguiment Anual
        </a>
    </div>

</div><!-- /.contingut-seguiment -->

<!-- Leaflet JS o CSS pel mapa -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    // --------------------------------------------------------
    // RENDER: Heatmap Leaflet (Geoespacial)
    // --------------------------------------------------------
    const elMap = document.getElementById('heatmap-map');
    if (elMap) {
        // Inicialitzar mapa central general
        const map = L.map('heatmap-map').setView([41.5, 1.5], 13);
        // Capa satèl·lit (per veure la finca clarament)
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        }).addTo(map);

        const dadesHeatmap = <?= json_encode($heatmap_data ?? []) ?>;
        const bounds = new L.LatLngBounds();
        
        // Determinar max kg_ha auto-relatiu
        let maxValue = 1; // Evitar div 0
        dadesHeatmap.forEach(d => {
            const v = parseFloat(d.kg_ha);
            if (v > maxValue) maxValue = v;
        });

        dadesHeatmap.forEach(d => {
            if (!d.geojson) return;
            const geo = JSON.parse(d.geojson);
            const rendiment = parseFloat(d.kg_ha);
            
            // Relatiu a maxValue de l'any. (0=vermell, maxValue=verd)
            const ratio = maxValue > 0 ? (rendiment / maxValue) : 0;
            // Evitem valors negatius. HSV/HSL Hue: 0 = vermell, 120 = verd.
            const hue = Math.max(0, Math.min(ratio * 120, 120));
            // Saturation 100%, Light 45% (vibrant opacity)
            const colorPolicia = rendiment > 0 ? `hsl(${hue}, 100%, 45%)` : '#999999'; 

            const geoLayer = L.geoJSON(geo, {
                style: {
                    color: colorPolicia,
                    weight: 3,
                    fillColor: colorPolicia,
                    fillOpacity: rendiment > 0 ? 0.6 : 0.2
                }
            }).bindPopup(`<strong>${d.nom}</strong><br>Rendiment Anual: ${rendiment.toFixed(1)} kg/ha`);
            
            geoLayer.addTo(map);
            bounds.extend(geoLayer.getBounds());
        });

        if (bounds.isValid()) {
            map.fitBounds(bounds);
        }
    }

    // --------------------------------------------------------
    // Colors base
    // --------------------------------------------------------
    const cs = getComputedStyle(document.documentElement);
    const cPrincipal  = cs.getPropertyValue('--color-principal').trim()    || '#2e7d32';
    const cBlau       = cs.getPropertyValue('--color-accent-blau').trim()  || '#1565c0';
    const cGris       = cs.getPropertyValue('--color-text-suau').trim()    || '#757575';

    const paleta = <?= $paleta_js ?>;

    function hexAlfa(hex, alfa) {
        // Afegeix transparencia a un color hex
        const r = parseInt(hex.slice(1,3),16);
        const g = parseInt(hex.slice(3,5),16);
        const b = parseInt(hex.slice(5,7),16);
        return `rgba(${r},${g},${b},${alfa})`;
    }

    const optsComuns = {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } },
            tooltip: { mode: 'index', intersect: false }
        }
    };

    // --------------------------------------------------------
    // GRAFIC 1: Evolucio plurianual (linia)
    // --------------------------------------------------------
    const g1 = document.getElementById('g1-plurianual');
    if (g1) {
        const anys   = <?= json_encode(array_map('strval', $anys_labels)) ?>;
        const valors = <?= json_encode(array_values($prod_per_any)) ?>;

        new Chart(g1, {
            type: 'line',
            data: {
                labels: anys,
                datasets: [{
                    label: 'Produccio total (kg)',
                    data:  valors,
                    borderColor:     cPrincipal,
                    backgroundColor: hexAlfa(cPrincipal, 0.12),
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                }]
            },
            options: {
                ...optsComuns,
                plugins: { ...optsComuns.plugins, legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => v.toLocaleString('ca-ES') + ' kg' }
                    }
                }
            }
        });
    }

    // --------------------------------------------------------
    // GRAFIC 2: Sectors i anys (barres agrupades)
    // --------------------------------------------------------
    const g2 = document.getElementById('g2-sectors');
    if (g2) {
        const sectorsNoms = <?= json_encode(array_values($sectors_noms)) ?>;
        const anys        = <?= json_encode(array_map('strval', $anys_labels)) ?>;
        const dadesSector = <?= json_encode(
            array_map('array_values', $prod_sector_any),
            JSON_PRETTY_PRINT
        ) ?>;

        const datasets = sectorsNoms.map((nom, i) => ({
            label: nom,
            data:  dadesSector[nom] ?? [],
            backgroundColor: hexAlfa(paleta[i % paleta.length], 0.75),
            borderColor:     paleta[i % paleta.length],
            borderWidth: 1,
            borderRadius: 3,
        }));

        new Chart(g2, {
            type: 'bar',
            data: { labels: anys, datasets },
            options: {
                ...optsComuns,
                scales: {
                    x: { stacked: false },
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => v.toLocaleString('ca-ES') + ' kg' }
                    }
                }
            }
        });
    }

    // --------------------------------------------------------
    // GRAFIC 3: Rendiment per varietat (barres horitzontals)
    // --------------------------------------------------------
    const g3 = document.getElementById('g3-varietats');
    if (g3) {
        const etiquetes = <?= $g3_labels ?>;
        const valors    = <?= $g3_data ?>;

        new Chart(g3, {
            type: 'bar',
            data: {
                labels: etiquetes,
                datasets: [{
                    label: 'kg/ha',
                    data:  valors,
                    backgroundColor: etiquetes.map((_, i) =>
                        hexAlfa(paleta[i % paleta.length], 0.75)
                    ),
                    borderColor: etiquetes.map((_, i) => paleta[i % paleta.length]),
                    borderWidth: 1,
                    borderRadius: 3,
                }]
            },
            options: {
                ...optsComuns,
                indexAxis: 'y',
                plugins: { ...optsComuns.plugins, legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { callback: v => v.toLocaleString('ca-ES') + ' kg/ha' }
                    }
                }
            }
        });
    }

    // --------------------------------------------------------
    // GRAFIC 4: Evolucio d'una varietat (linia)
    // --------------------------------------------------------
    const g4 = document.getElementById('g4-evolucio-var');
    if (g4) {
        const anys   = <?= json_encode(array_map('strval', $anys_labels)) ?>;
        const valors = <?= json_encode(array_values($evolucio_var ?: [])) ?>;
        const nomVar = <?= json_encode($nom_varietat_sel) ?>;

        new Chart(g4, {
            type: 'line',
            data: {
                labels: anys,
                datasets: [{
                    label: nomVar || 'Rendiment (kg/ha)',
                    data:  valors,
                    borderColor:     cBlau,
                    backgroundColor: hexAlfa(cBlau, 0.10),
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    spanGaps: true,   // connecta punts on manquen dades
                }]
            },
            options: {
                ...optsComuns,
                plugins: { ...optsComuns.plugins, legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: v => v.toLocaleString('ca-ES') + ' kg/ha' }
                    }
                }
            }
        });
    }

    // --------------------------------------------------------
    // GRAFIC 5: Real vs Previsió
    // --------------------------------------------------------
    const g5 = document.getElementById('g5-prev-real');
    if (g5) {
        const anys   = <?= json_encode(array_map('strval', $anys_labels)) ?>;
        const vReal = <?= json_encode(array_values($prod_per_any)) ?>;
        const vPrev = <?= json_encode(array_values($prev_per_any)) ?>;

        const datasets = [{
            label: 'Collita real (kg)',
            data:  vReal,
            borderColor:     '#2e7d32',
            backgroundColor: hexAlfa('#2e7d32', 0.12),
            borderWidth: 2.5,
            fill: true,
            tension: 0.35,
            pointRadius: 5,
            pointHoverRadius: 7,
        }];

        if (vPrev.some(v => v !== null && v > 0)) {
            datasets.push({
                label: 'Previsió (kg)',
                data:  vPrev,
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

        new Chart(g5, {
            type: 'line',
            data: {
                labels: anys,
                datasets: datasets
            },
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
