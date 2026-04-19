<?php
/**
 * modules/quadern/quadern.php √¢‚,¨‚?ù Quadern de Camp Oficial.
 *
 * Agrega les dades registrades als altres m√f¬≤duls per generar el registre
 * legal d'activitats de l'explotaci√f¬≥ (RD 1702/2011 i normativa auton√f¬≤mica).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Quadern de Camp';
$pagina_activa = 'quadern';

// Filtres
$any_sel    = sanitize_int($_GET['any'] ?? date('Y'));
$sector_sel = sanitize_int($_GET['id_sector'] ?? null);

$any_sel = $any_sel ?: (int)date('Y');

$tractaments  = [];
$monitoratges = [];
$collites     = [];
$analisis     = [];
$sectors      = [];
$error_db     = null;

try {
    $pdo = connectDB();

    // Selector de sectors
    $sectors = $pdo->query("SELECT id_sector, nom FROM sector ORDER BY nom ASC")->fetchAll();

    $param_any    = [':any' => $any_sel];
    $param_sector = $sector_sel ? [':sector' => $sector_sel] : [];

    // --- 1. Tractaments / Aplicacions ---
    // Connexi√f¬≥ via inventari_estoc i √f¬∫s correcte de data_event
    // Paginaci√f¬≥n para tratamientos
    $pagina_t = sanitize_int($_GET['pagina_t'] ?? 1);
    $per_pagina = 20;
    
    $sql_t = "
        SELECT
            a.id_aplicacio,
            a.data_event AS data_aplicacio,
            s.nom AS sector,
            a.tipus_event AS tipus_aplicacio,
            a.metode_aplicacio,
            a.descripcio AS observacions,
            GROUP_CONCAT(
                CONCAT(pq.nom_comercial, ' (', dap.quantitat_consumida_total, ' ', pq.unitat_mesura, ')')
                ORDER BY pq.nom_comercial SEPARATOR ' | '
            ) AS productes
        FROM aplicacio a
        JOIN sector s ON s.id_sector = a.id_sector
        LEFT JOIN detall_aplicacio_producte dap ON dap.id_aplicacio = a.id_aplicacio
        LEFT JOIN inventari_estoc ie ON ie.id_estoc = dap.id_estoc
        LEFT JOIN producte_quimic pq ON pq.id_producte = ie.id_producte
        WHERE YEAR(a.data_event) = :any
        " . ($sector_sel ? 'AND a.id_sector = :sector' : '') . "
        GROUP BY a.id_aplicacio, a.data_event, s.nom, a.tipus_event, a.metode_aplicacio, a.descripcio
        ORDER BY a.data_event DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql_t);
    $stmt->execute(array_merge($param_any, $param_sector, [
        ':limit' => $per_pagina,
        ':offset' => ($pagina_t - 1) * $per_pagina
    ]));
    $tractaments = $stmt->fetchAll();

    // --- 2. Monitoratges de plagues ---
    // √f≈°s correcte de data_observacio i sense inventar taules innecess√f¬†ries
    $sql_m = "
        SELECT
            mp.id_monitoratge,
            mp.data_observacio AS data_monitoratge,
            s.nom AS sector,
            mp.tipus_problema AS nom_plaga,
            mp.nivell_poblacio,
            mp.llindar_intervencio_assolit,
            mp.descripcio_breu
        FROM monitoratge_plaga mp
        JOIN sector s ON s.id_sector = mp.id_sector
        WHERE YEAR(mp.data_observacio) = :any
        " . ($sector_sel ? 'AND mp.id_sector = :sector' : '') . "
        ORDER BY mp.data_observacio DESC
    ";
    $stmt = $pdo->prepare($sql_m);
    $stmt->execute(array_merge($param_any, $param_sector));
    $monitoratges = $stmt->fetchAll();

    // --- 3. Collites ---
    // Connexi√f¬≥ del sector mitjan√f¬ßant la taula plantacio
    $sql_c = "
        SELECT
            c.id_collita,
            c.data_inici,
            c.data_fi,
            s.nom AS sector,
            c.quantitat,
            c.unitat_mesura,
            c.qualitat,
            c.observacions,
            GROUP_CONCAT(
                CONCAT(t.nom, ' ', COALESCE(t.cognoms,''))
                ORDER BY t.nom SEPARATOR ', '
            ) AS treballadors
        FROM collita c
        JOIN plantacio p ON p.id_plantacio = c.id_plantacio
        JOIN sector s ON s.id_sector = p.id_sector
        LEFT JOIN collita_treballador ct ON ct.id_collita = c.id_collita
        LEFT JOIN treballador t ON t.id_treballador = ct.id_treballador
        WHERE YEAR(c.data_inici) = :any
        " . ($sector_sel ? 'AND s.id_sector = :sector' : '') . "
        GROUP BY c.id_collita, c.data_inici, c.data_fi, s.nom, c.quantitat, c.unitat_mesura, c.qualitat, c.observacions
        ORDER BY c.data_inici DESC
    ";
    $stmt = $pdo->prepare($sql_c);
    $stmt->execute(array_merge($param_any, $param_sector));
    $collites = $stmt->fetchAll();

    // --- 4. An√f¬†lisis de s√f¬≤l ---
    $sql_a = "
        SELECT
            cs.id_sector,
            cs.data_analisi,
            s.nom AS sector,
            cs.pH,
            cs.materia_organica,
            cs.N, cs.P, cs.K,
            cs.conductivitat_electrica
        FROM caracteristiques_sol cs
        JOIN sector s ON s.id_sector = cs.id_sector
        WHERE YEAR(cs.data_analisi) = :any
        " . ($sector_sel ? 'AND cs.id_sector = :sector' : '') . "
        ORDER BY cs.data_analisi DESC
    ";
    $stmt = $pdo->prepare($sql_a);
    $stmt->execute(array_merge($param_any, $param_sector));
    $analisis = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] quadern.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del quadern.';
}

$anys_disponibles = range((int)date('Y'), (int)date('Y') - 5);

require_once __DIR__ . '/../../includes/header.php';
?>


<div class="contingut-quadern">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-book-open" aria-hidden="true"></i>
            Quadern de Camp Oficial
        </h1>
        <p class="descripcio-seccio">
            Registre legal d'activitats de l'explotaci√f¬≥ (tractaments fitosanitaris,
            monitoratge, collites i an√f¬†lisis de s√f¬≤l). Normativa: RD 1702/2011.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="formulari-filtres">
        <div class="form-fila-3">
            <div class="form-grup">
                <label for="any" class="form-label">Any</label>
                <select id="any" name="any" class="form-select">
                    <?php foreach ($anys_disponibles as $a): ?>
                        <option value="<?= $a ?>" <?= $a === $any_sel ? 'selected' : '' ?>>
                            <?= $a ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup">
                <label for="id_sector" class="form-label">Sector (opcional)</label>
                <select id="id_sector" name="id_sector" class="form-select">
                    <option value="">√¢‚,¨‚?ù Tots els sectors √¢‚,¨‚?ù</option>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?= (int)$s['id_sector'] ?>"
                            <?= $sector_sel === (int)$s['id_sector'] ? 'selected' : '' ?>>
                            <?= e($s['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup form-grup--accio">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
                </button>
                <a href="<?= BASE_URL ?>modules/quadern/exportar_quadern_pdf.php?any=<?= $any_sel ?><?= $sector_sel ? '&id_sector=' . $sector_sel : '' ?>" 
                   class="boto-secundari" 
                   title="Exportar a PDF"
                   target="_blank">
                    <i class="fas fa-file-pdf" aria-hidden="true"></i> Exportar PDF
                </a>
                <a href="<?= BASE_URL ?>modules/quadern/quadern_normativa.php" class="boto-secundari">
                    <i class="fas fa-scale-balanced" aria-hidden="true"></i> Normativa
                </a>
            </div>
        </div>
    </form>

    <!-- Navegaci√f¬≥ de Pestanyes -->
    <nav class="quadern-tabs" aria-label="Navegaci√f¬≥ de categories">
        <button class="quadern-tab-btn activa" onclick="obrirPestanya(event, 'tractaments')">
            <i class="fas fa-spray-can-sparkles"></i> Tractaments <span class="badge badge--gris badge-etiqueta-fixa"><?= count($tractaments) ?></span>
        </button>
        <button class="quadern-tab-btn" onclick="obrirPestanya(event, 'monitoratge')">
            <i class="fas fa-bug"></i> Monitoratge <span class="badge badge--gris badge-etiqueta-fixa"><?= count($monitoratges) ?></span>
        </button>
        <button class="quadern-tab-btn" onclick="obrirPestanya(event, 'collites')">
            <i class="fas fa-apple-whole"></i> Collites <span class="badge badge--gris badge-etiqueta-fixa"><?= count($collites) ?></span>
        </button>
        <button class="quadern-tab-btn" onclick="obrirPestanya(event, 'analisis')">
            <i class="fas fa-flask"></i> An√f¬†lisis de S√f¬≤l <span class="badge badge--gris badge-etiqueta-fixa"><?= count($analisis) ?></span>
        </button>
    </nav>


    <section class="quadern-seccio quadern-pane activa" id="tractaments">
        <h2 class="quadern-seccio__titol">
            <i class="fas fa-spray-can-sparkles" aria-hidden="true"></i>
            Tractaments Fitosanitaris
            <span class="badge badge--gris"><?= count($tractaments) ?></span>
        </h2>
        <?php if (empty($tractaments)): ?>
            <p class="sense-dades-inline">Cap tractament registrat per a aquest per√f¬≠ode.</p>
        <?php else: ?>
            <table class="taula-simple taula-quadern">
                <thead>
                    <tr><th>Data</th><th>Sector</th><th>Tipus</th><th>M√f¬®tode</th><th>Productes (dosi)</th><th>Observacions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($tractaments as $t): ?>
                        <tr>
                            <td><?= format_data($t['data_aplicacio'], curta: true) ?></td>
                            <td><?= e($t['sector']) ?></td>
                            <td><?= e($t['tipus_aplicacio'] ?? '√¢‚,¨‚?ù') ?></td>
                            <td><?= e($t['metode_aplicacio'] ?? '√¢‚,¨‚?ù') ?></td>
                            <td class="cel-text-llarg"><?= e($t['productes'] ?? '√¢‚,¨‚?ù') ?></td>
                            <td class="cel-text-llarg"><?= e($t['observacions'] ?? '√¢‚,¨‚?ù') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="quadern-seccio quadern-pane" id="monitoratge">
        <h2 class="quadern-seccio__titol">
            <i class="fas fa-bug" aria-hidden="true"></i>
            Monitoratge de Plagues i Malalties
            <span class="badge badge--gris"><?= count($monitoratges) ?></span>
        </h2>
        <?php if (empty($monitoratges)): ?>
            <p class="sense-dades-inline">Cap monitoratge registrat per a aquest per√f¬≠ode.</p>
        <?php else: ?>
            <table class="taula-simple taula-quadern">
                <thead>
                    <tr><th>Data</th><th>Sector</th><th>Plaga / Malaltia</th><th>Nivell poblaci√f¬≥</th><th>Llindar assolit</th><th>Descripci√f¬≥</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($monitoratges as $m): ?>
                        <tr>
                            <td><?= format_data($m['data_monitoratge'], curta: true) ?></td>
                            <td><?= e($m['sector']) ?></td>
                            <td><?= e($m['nom_plaga'] ?? '√¢‚,¨‚?ù') ?></td>
                            <td><?= e($m['nivell_poblacio'] ?? '√¢‚,¨‚?ù') ?></td>
                            <td>
                                <?php if ($m['llindar_intervencio_assolit']): ?>
                                    <span class="badge badge--vermell">S√f¬≠</span>
                                <?php else: ?>
                                    <span class="badge badge--verd">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="cel-text-llarg"><?= e($m['descripcio_breu'] ?? '√¢‚,¨‚?ù') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="quadern-seccio quadern-pane" id="collites">
        <h2 class="quadern-seccio__titol">
            <i class="fas fa-apple-whole" aria-hidden="true"></i>
            Collites i Producci√f¬≥
            <span class="badge badge--gris"><?= count($collites) ?></span>
        </h2>
        <?php if (empty($collites)): ?>
            <p class="sense-dades-inline">Cap collita registrada per a aquest per√f¬≠ode.</p>
        <?php else: ?>
            <table class="taula-simple taula-quadern">
                <thead>
                    <tr><th>Inici</th><th>Fi</th><th>Sector</th><th>Quantitat</th><th>Qualitat</th><th>Treballadors</th><th>Observacions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($collites as $c): ?>
                        <tr>
                            <td><?= format_data($c['data_inici'], curta: true) ?></td>
                            <td><?= $c['data_fi'] ? format_data($c['data_fi'], curta: true) : '√¢‚,¨‚?ù' ?></td>
                            <td><?= e($c['sector']) ?></td>
                            <td><?= format_kg((float)$c['quantitat']) ?> <?= e($c['unitat_mesura'] ?? '') ?></td>
                            <td><?= e($c['qualitat'] ?? '√¢‚,¨‚?ù') ?></td>
                            <td class="cel-text-llarg"><?= e($c['treballadors'] ?? '√¢‚,¨‚?ù') ?></td>
                            <td class="cel-text-llarg"><?= e($c['observacions'] ?? '√¢‚,¨‚?ù') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="quadern-seccio quadern-pane" id="analisis">
        <h2 class="quadern-seccio__titol">
            <i class="fas fa-flask" aria-hidden="true"></i>
            An√f¬†lisis de S√f¬≤l
            <span class="badge badge--gris"><?= count($analisis) ?></span>
        </h2>
        <?php if (empty($analisis)): ?>
            <p class="sense-dades-inline">Cap an√f¬†lisi registrada per a aquest per√f¬≠ode.</p>
        <?php else: ?>
            <table class="taula-simple taula-quadern">
                <thead>
                    <tr><th>Data</th><th>Sector</th><th>pH</th><th>M.O. (%)</th><th>CE (dS/m)</th><th>N</th><th>P</th><th>K</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($analisis as $a): ?>
                        <tr>
                            <td><?= format_data($a['data_analisi'], curta: true) ?></td>
                            <td><?= e($a['sector']) ?></td>
                            <td><?= $a['pH'] !== null ? number_format((float)$a['pH'], 1, ',', '.') : '√¢‚,¨‚?ù' ?></td>
                            <td><?= $a['materia_organica'] !== null ? number_format((float)$a['materia_organica'], 2, ',', '.') : '√¢‚,¨‚?ù' ?></td>
                            <td><?= $a['conductivitat_electrica'] !== null ? number_format((float)$a['conductivitat_electrica'], 3, ',', '.') : '√¢‚,¨‚?ù' ?></td>
                            <td><?= $a['N'] !== null ? number_format((float)$a['N'], 1, ',', '.') : '√¢‚,¨‚?ù' ?></td>
                            <td><?= $a['P'] !== null ? number_format((float)$a['P'], 1, ',', '.') : '√¢‚,¨‚?ù' ?></td>
                            <td><?= $a['K'] !== null ? number_format((float)$a['K'], 1, ',', '.') : '√¢‚,¨‚?ù' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <p class="nota-peu">
        * Document generat autom√f¬†ticament a partir dels registres introdu√f¬Øts a CultiuConnect.
    </p>

</div>

<script>
function obrirPestanya(evt, idSeccio) {
    // Amagar totes les seccions (panes)
    const panells = document.querySelectorAll('.quadern-pane');
    panells.forEach(panell => panell.classList.remove('activa'));

    // Desactivar tots els botons
    const botons = document.querySelectorAll('.quadern-tab-btn');
    botons.forEach(boto => boto.classList.remove('activa'));

    // Activar el panell sol√,¬∑licitat i el bot√f¬≥ clicat
    document.getElementById(idSeccio).classList.add('activa');
    evt.currentTarget.classList.add('activa');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
