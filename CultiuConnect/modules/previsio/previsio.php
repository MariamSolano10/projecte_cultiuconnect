<?php
/**
 * modules/previsio/previsio.php — Previsió de collita per temporada.
 *
 * Mostra totes les previsions agrupades per temporada amb:
 *  - Resum de producció estimada total
 *  - Comparativa estimació vs real (si ja hi ha collites registrades)
 *  - Filtres per temporada i plantació
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Previsió de Collita';
$pagina_activa = 'previsio';

$previsions   = [];
$plantacions  = [];
$temporades   = [];
$resum        = [];
$error_db     = null;

$temporada_actual = (int)date('Y');

$filtre_temporada = is_numeric($_GET['temporada'] ?? '') ? (int)$_GET['temporada'] : $temporada_actual;
$filtre_plantacio = is_numeric($_GET['id_plantacio'] ?? '') ? (int)$_GET['id_plantacio'] : 0;

try {
    $pdo = connectDB();

    // Temporades disponibles
    $temporades = $pdo->query("
        SELECT DISTINCT temporada FROM previsio_collita ORDER BY temporada DESC
    ")->fetchAll(PDO::FETCH_COLUMN);

    // Afegim any actual i el vinent si no hi són
    foreach ([$temporada_actual, $temporada_actual + 1] as $t) {
        if (!in_array($t, $temporades)) $temporades[] = $t;
    }
    rsort($temporades);

    // Plantacions actives per al filtre
    $plantacions = $pdo->query("
        SELECT p.id_plantacio, v.nom_varietat, e.nom_comu, s.nom AS nom_sector
        FROM plantacio p
        JOIN varietat v ON v.id_varietat = p.id_varietat
        JOIN especie  e ON e.id_especie  = v.id_especie
        JOIN sector   s ON s.id_sector   = p.id_sector
        WHERE p.data_arrencada IS NULL
        ORDER BY s.nom, v.nom_varietat
    ")->fetchAll();

    // Condicions
    $condicions = ['pc.temporada = :temporada'];
    $params     = [':temporada' => $filtre_temporada];

    if ($filtre_plantacio > 0) {
        $condicions[] = 'pc.id_plantacio = :id_plantacio';
        $params[':id_plantacio'] = $filtre_plantacio;
    }

    $where = 'WHERE ' . implode(' AND ', $condicions);

    // Previsions amb dades reals de collita per comparar
    $sql = "
        SELECT
            pc.id_previsio,
            pc.id_plantacio,
            pc.temporada,
            pc.data_previsio,
            pc.produccio_estimada_kg,
            pc.produccio_per_arbre_kg,
            pc.data_inici_collita_estimada,
            pc.data_fi_collita_estimada,
            pc.calibre_previst,
            pc.qualitat_prevista,
            pc.mo_necessaria_jornal,
            pc.factors_considerats,
            v.nom_varietat,
            e.nom_comu      AS nom_especie,
            s.nom           AS nom_sector,
            pl.num_arbres_plantats,
            pl.num_falles,
            -- Producció real registrada per a aquesta plantació i any
            COALESCE(SUM(c.quantitat), 0) AS produccio_real_kg,
            COUNT(c.id_collita)           AS num_collites
        FROM previsio_collita pc
        JOIN plantacio pl ON pl.id_plantacio = pc.id_plantacio
        JOIN varietat  v  ON v.id_varietat   = pl.id_varietat
        JOIN especie   e  ON e.id_especie    = v.id_especie
        JOIN sector    s  ON s.id_sector     = pl.id_sector
        LEFT JOIN collita c
            ON  c.id_plantacio = pc.id_plantacio
            AND YEAR(c.data_inici) = pc.temporada
            AND c.data_fi IS NOT NULL
        $where
        GROUP BY pc.id_previsio
        ORDER BY s.nom, v.nom_varietat
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $previsions = $stmt->fetchAll();

    // Resum de la temporada
    $stmtResum = $pdo->prepare("
        SELECT
            COUNT(*)                            AS num_plantacions,
            COALESCE(SUM(produccio_estimada_kg), 0) AS total_estimat_kg,
            COALESCE(SUM(mo_necessaria_jornal), 0)  AS total_jornals
        FROM previsio_collita
        WHERE temporada = :temporada
    ");
    $stmtResum->execute([':temporada' => $filtre_temporada]);
    $resum = $stmtResum->fetch();

    // Total real de la temporada
    $stmtReal = $pdo->prepare("
        SELECT COALESCE(SUM(c.quantitat), 0) AS total_real_kg
        FROM collita c
        WHERE YEAR(c.data_inici) = :temporada AND c.data_fi IS NOT NULL
    ");
    $stmtReal->execute([':temporada' => $filtre_temporada]);
    $resum['total_real_kg'] = $stmtReal->fetchColumn();

} catch (Exception $e) {
    error_log('[CultiuConnect] previsio.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les previsions.';
}

function classePrecisio(float $estimat, float $real): string
{
    if ($estimat <= 0 || $real <= 0) return '';
    $desviacio = abs($real - $estimat) / $estimat * 100;
    if ($desviacio <= 10) return 'badge--verd';
    if ($desviacio <= 25) return 'badge--groc';
    return 'badge--vermell';
}

function textPrecisio(float $estimat, float $real): string
{
    if ($estimat <= 0 || $real <= 0) return '—';
    $pct = ($real - $estimat) / $estimat * 100;
    $signe = $pct >= 0 ? '+' : '';
    return $signe . number_format($pct, 1) . '%';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-previsio">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            Previsió de Collita
        </h1>
        <p class="descripcio-seccio">
            Estimacions de producció per temporada i comparativa amb la collita real registrada.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/previsio/nova_previsio.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Nova Previsió
        </a>
    </div>

    <!-- FILTRES -->
    <div class="filtres-avancats card">
        <form method="GET" class="form-fila">

            <div class="form-camp">
                <label for="temporada">Temporada</label>
                <select name="temporada" id="temporada">
                    <?php foreach ($temporades as $t): ?>
                        <option value="<?= (int)$t ?>"
                            <?= $filtre_temporada === (int)$t ? 'selected' : '' ?>>
                            <?= (int)$t ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-camp">
                <label for="id_plantacio">Plantació</label>
                <select name="id_plantacio" id="id_plantacio">
                    <option value="0">— Totes —</option>
                    <?php foreach ($plantacions as $pl): ?>
                        <option value="<?= (int)$pl['id_plantacio'] ?>"
                            <?= $filtre_plantacio === (int)$pl['id_plantacio'] ? 'selected' : '' ?>>
                            <?= e($pl['nom_sector'] . ' — ' . $pl['nom_comu'] . ' ' . $pl['nom_varietat']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-camp">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- RESUM DE LA TEMPORADA -->
    <?php if ($resum && !$error_db): ?>
    <div class="resum-cards">

        <div class="stat-card stat-card--blau">
            <div class="stat-card__icon"><i class="fas fa-seedling"></i></div>
            <div class="stat-card__valor"><?= (int)$resum['num_plantacions'] ?></div>
            <div class="stat-card__label">Plantacions amb previsió</div>
        </div>

        <div class="stat-card stat-card--groc">
            <div class="stat-card__icon"><i class="fas fa-scale-balanced"></i></div>
            <div class="stat-card__valor">
                <?= number_format((float)$resum['total_estimat_kg'] / 1000, 1) ?> t
            </div>
            <div class="stat-card__label">Producció estimada</div>
        </div>

        <div class="stat-card stat-card--verd">
            <div class="stat-card__icon"><i class="fas fa-apple-whole"></i></div>
            <div class="stat-card__valor">
                <?= number_format((float)$resum['total_real_kg'] / 1000, 1) ?> t
            </div>
            <div class="stat-card__label">Producció real registrada</div>
        </div>

        <div class="stat-card stat-card--gris">
            <div class="stat-card__icon"><i class="fas fa-person-digging"></i></div>
            <div class="stat-card__valor"><?= (int)$resum['total_jornals'] ?></div>
            <div class="stat-card__label">Jornals estimats</div>
        </div>

        <?php if ((float)$resum['total_estimat_kg'] > 0 && (float)$resum['total_real_kg'] > 0): ?>
        <div class="stat-card <?= (float)$resum['total_real_kg'] >= (float)$resum['total_estimat_kg'] ? 'stat-card--verd' : 'stat-card--vermell' ?>">
            <div class="stat-card__icon"><i class="fas fa-percent"></i></div>
            <div class="stat-card__valor">
                <?= textPrecisio((float)$resum['total_estimat_kg'], (float)$resum['total_real_kg']) ?>
            </div>
            <div class="stat-card__label">Desviació estimació/real</div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- TAULA DE PREVISIONS -->
    <div class="card">
        <div class="card__header">
            <i class="fas fa-table" aria-hidden="true"></i>
            Previsions — Temporada <?= $filtre_temporada ?>
            <span class="badge badge--gris badge--sep">
                <?= count($previsions) ?> plantacions
            </span>
        </div>
        <div class="card__body">

            <div class="cerca-container">
                <input type="search"
                       data-filtre-taula="taula-previsio"
                       placeholder="Cerca per sector o varietat..."
                       class="input-cerca"
                       aria-label="Cercar previsions">
            </div>

            <table class="taula-simple" id="taula-previsio">
                <thead>
                    <tr>
                        <th>Sector / Varietat</th>
                        <th>Arbres</th>
                        <th>Estimació (kg)</th>
                        <th>kg/arbre</th>
                        <th>Inici collita estimat</th>
                        <th>Fi collita estimada</th>
                        <th>Qualitat prevista</th>
                        <th>Jornals</th>
                        <th>Real (kg)</th>
                        <th>Desviació</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($previsions)): ?>
                        <tr>
                            <td colspan="11" class="sense-dades">
                                <i class="fas fa-info-circle" aria-hidden="true"></i>
                                No hi ha previsions per a la temporada <?= $filtre_temporada ?>.
                                <a href="<?= BASE_URL ?>modules/previsio/nova_previsio.php?temporada=<?= $filtre_temporada ?>">
                                    Crear-ne una
                                </a>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($previsions as $p):
                            $arbres_efectius = max(0, (int)($p['num_arbres_plantats'] ?? 0) - (int)($p['num_falles'] ?? 0));
                            $real = (float)$p['produccio_real_kg'];
                            $est  = (float)$p['produccio_estimada_kg'];
                        ?>
                        <tr>
                            <td data-cerca>
                                <strong><?= e($p['nom_sector']) ?></strong><br>
                                <span class="text-suau"><?= e($p['nom_especie'] . ' — ' . $p['nom_varietat']) ?></span>
                            </td>
                            <td>
                                <?= $arbres_efectius ?>
                                <?php if ((int)$p['num_falles'] > 0): ?>
                                    <span class="text-suau" title="Falles">
                                        (-<?= (int)$p['num_falles'] ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= $est > 0 ? number_format($est, 0, ',', '.') : '—' ?></strong>
                            </td>
                            <td>
                                <?= $p['produccio_per_arbre_kg']
                                    ? number_format((float)$p['produccio_per_arbre_kg'], 2, ',', '.') . ' kg'
                                    : '—' ?>
                            </td>
                            <td>
                                <?= $p['data_inici_collita_estimada']
                                    ? format_data($p['data_inici_collita_estimada'], curta: true)
                                    : '—' ?>
                            </td>
                            <td>
                                <?= $p['data_fi_collita_estimada']
                                    ? format_data($p['data_fi_collita_estimada'], curta: true)
                                    : '—' ?>
                            </td>
                            <td>
                                <?php if ($p['qualitat_prevista']): ?>
                                    <?php
                                    $q_class = match($p['qualitat_prevista']) {
                                        'Extra'     => 'badge--verd',
                                        'Primera'   => 'badge--blau',
                                        'Segona'    => 'badge--groc',
                                        'Industrial'=> 'badge--gris',
                                        default     => 'badge--gris',
                                    };
                                    ?>
                                    <span class="badge <?= $q_class ?>">
                                        <?= e($p['qualitat_prevista']) ?>
                                    </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?= $p['mo_necessaria_jornal'] ? (int)$p['mo_necessaria_jornal'] : '—' ?></td>
                            <td>
                                <?php if ($real > 0): ?>
                                    <strong><?= number_format($real, 0, ',', '.') ?></strong>
                                    <span class="text-suau">
                                        (<?= (int)$p['num_collites'] ?> reg.)
                                    </span>
                                <?php else: ?>
                                    <span class="text-suau">Sense dades</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($est > 0 && $real > 0): ?>
                                    <span class="badge <?= classePrecisio($est, $real) ?>">
                                        <?= textPrecisio($est, $real) ?>
                                    </span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="cel-accions">
                                <a href="<?= BASE_URL ?>modules/previsio/nova_previsio.php?editar=<?= (int)$p['id_previsio'] ?>"
                                   title="Editar previsió"
                                   class="btn-accio btn-accio--editar">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                </a>
                                <form method="POST"
                                      action="<?= BASE_URL ?>modules/previsio/processar_previsio.php"
                                      class="form-inline"
                                      onsubmit="return confirm('Eliminar aquesta previsió?')">
                                    <input type="hidden" name="accio"       value="eliminar">
                                    <input type="hidden" name="id_previsio" value="<?= (int)$p['id_previsio'] ?>">
                                    <input type="hidden" name="temporada"   value="<?= $filtre_temporada ?>">
                                    <button type="submit"
                                            class="btn-accio btn-accio--eliminar"
                                            title="Eliminar previsió">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FACTORS CONSIDERATS (detall expandible) -->
    <?php $amb_factors = array_filter($previsions, fn($p) => !empty($p['factors_considerats'])); ?>
    <?php if (!empty($amb_factors)): ?>
    <div class="card card--separador">
        <div class="card__header">
            <i class="fas fa-magnifying-glass-chart" aria-hidden="true"></i>
            Factors considerats en les estimacions
        </div>
        <div class="card__body">
            <?php foreach ($amb_factors as $p): ?>
            <div class="factors-item">
                <strong><?= e($p['nom_sector'] . ' — ' . $p['nom_varietat']) ?></strong>
                <span class="text-suau factors-meta">
                    (Previsió del <?= format_data($p['data_previsio'], curta: true) ?>)
                </span>
                <p class="factors-text"><?= nl2br(e($p['factors_considerats'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
