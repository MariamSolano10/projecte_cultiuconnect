<?php
/**
 * modules/qualitat/qualitat_lots.php
 *
 * Llistat de controls de qualitat. Permet filtrar per lot, resultat i dates.
 * Des d'aquí es pot accedir a nou_control.php i a la fitxa de cada lot.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Controls de Qualitat';
$pagina_activa = 'qualitat';

$controls  = [];
$lots      = [];
$error_db  = null;

// Filtres GET
$filtre_lot     = isset($_GET['id_lot'])    && is_numeric($_GET['id_lot'])  ? (int)$_GET['id_lot']   : null;
$filtre_resultat= isset($_GET['resultat'])  && $_GET['resultat'] !== ''     ? trim($_GET['resultat']) : null;
$filtre_des     = isset($_GET['data_des'])  && $_GET['data_des']  !== ''    ? trim($_GET['data_des']) : null;
$filtre_fins    = isset($_GET['data_fins']) && $_GET['data_fins'] !== ''    ? trim($_GET['data_fins']): null;

try {
    $pdo = connectDB();

    // Lots per al filtre
    $lots = $pdo->query("
        SELECT lp.id_lot, lp.identificador, s.nom AS nom_sector
        FROM lot_produccio lp
        LEFT JOIN sector s ON s.id_sector = lp.id_sector
        ORDER BY lp.identificador
    ")->fetchAll();

    // Query principal amb filtres dinàmics
    $where  = ['1=1'];
    $params = [];

    if ($filtre_lot !== null) {
        $where[]            = 'cq.id_lot = :id_lot';
        $params[':id_lot']  = $filtre_lot;
    }
    if ($filtre_resultat !== null) {
        $where[]               = 'cq.resultat = :resultat';
        $params[':resultat']   = $filtre_resultat;
    }
    if ($filtre_des !== null) {
        $where[]               = 'cq.data_control >= :data_des';
        $params[':data_des']   = $filtre_des;
    }
    if ($filtre_fins !== null) {
        $where[]               = 'cq.data_control <= :data_fins';
        $params[':data_fins']  = $filtre_fins;
    }

    $sql = "
        SELECT
            cq.id_control,
            cq.data_control,
            cq.calibre_mm,
            cq.fermesa_kg_cm2,
            cq.color,
            cq.sabor,
            cq.resultat,
            cq.comentaris,
            lp.identificador    AS lot_identificador,
            lp.pes_kg           AS lot_pes_kg,
            lp.qualitat         AS lot_qualitat,
            s.nom               AS nom_sector,
            p.nom               AS nom_parcela,
            t.nom               AS inspector_nom,
            t.cognoms           AS inspector_cognoms
        FROM control_qualitat cq
        JOIN lot_produccio lp   ON lp.id_lot          = cq.id_lot
        LEFT JOIN sector s      ON s.id_sector         = lp.id_sector
        LEFT JOIN parcela p     ON p.id_parcela        = lp.id_parcela
        LEFT JOIN treballador t ON t.id_treballador    = cq.id_inspector
        WHERE " . implode(' AND ', $where) . "
        ORDER BY cq.data_control DESC, cq.id_control DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $controls = $stmt->fetchAll();

    // Estadístiques globals (independents dels filtres)
    $stats = $pdo->query("
        SELECT
            COUNT(*)                                                     AS total,
            SUM(resultat = 'Acceptat')                                   AS acceptats,
            SUM(resultat = 'Condicional')                                AS condicionals,
            SUM(resultat = 'Rebutjat')                                   AS rebutjats,
            ROUND(AVG(calibre_mm),    2)                                 AS calibre_mig,
            ROUND(AVG(fermesa_kg_cm2),2)                                 AS fermesa_mitja
        FROM control_qualitat
    ")->fetch();

} catch (Exception $e) {
    error_log('[CultiuConnect] qualitat_lots.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els controls de qualitat.';
}

// Etiquetes i classes CSS per al resultat
function badge_resultat(string $r): string {
    $map = [
        'Acceptat'    => ['cls' => 'badge--ok',   'ico' => 'fa-circle-check'],
        'Condicional' => ['cls' => 'badge--avis',  'ico' => 'fa-triangle-exclamation'],
        'Rebutjat'    => ['cls' => 'badge--error', 'ico' => 'fa-circle-xmark'],
    ];
    $d = $map[$r] ?? ['cls' => 'badge--info', 'ico' => 'fa-circle-info'];
    return "<span class=\"badge {$d['cls']}\"><i class=\"fas {$d['ico']}\" aria-hidden=\"true\"></i> " . e($r) . "</span>";
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-llista">

    <!-- ── CAP�?ALERA ──────────────────────────────────────────────────────── -->
    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-medal" aria-hidden="true"></i>
            Controls de Qualitat
        </h1>
        <a href="<?= BASE_URL ?>modules/qualitat/nou_control.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Nou control
        </a>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- ── TARGETES DE RESUM ──────────────────────────────────────────────── -->
    <?php if ($stats && $stats['total'] > 0): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-clipboard-list"></i>
            <div class="stat-val"><?= (int) $stats['total'] ?></div>
            <div class="stat-label">Controls totals</div>
        </div>
        <div class="stat-card stat-card--ok">
            <i class="fas fa-circle-check"></i>
            <div class="stat-val"><?= (int) $stats['acceptats'] ?></div>
            <div class="stat-label">Acceptats</div>
        </div>
        <div class="stat-card stat-card--avis">
            <i class="fas fa-triangle-exclamation"></i>
            <div class="stat-val"><?= (int) $stats['condicionals'] ?></div>
            <div class="stat-label">Condicionals</div>
        </div>
        <div class="stat-card stat-card--error">
            <i class="fas fa-circle-xmark"></i>
            <div class="stat-val"><?= (int) $stats['rebutjats'] ?></div>
            <div class="stat-label">Rebutjats</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-ruler-horizontal"></i>
            <div class="stat-val"><?= $stats['calibre_mig'] ?? '—' ?> <small>mm</small></div>
            <div class="stat-label">Calibre mitjà</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-weight-hanging"></i>
            <div class="stat-val"><?= $stats['fermesa_mitja'] ?? '—' ?> <small>kg/cm²</small></div>
            <div class="stat-label">Fermesa mitjana</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── FILTRES ────────────────────────────────────────────────────────── -->
    <form method="GET" class="filtres-barra" id="form-filtres">
        <div class="filtre-grup">
            <label for="id_lot">Lot</label>
            <select name="id_lot" id="id_lot">
                <option value="">Tots els lots</option>
                <?php foreach ($lots as $lot): ?>
                    <option value="<?= (int) $lot['id_lot'] ?>"
                        <?= $filtre_lot === (int) $lot['id_lot'] ? 'selected' : '' ?>>
                        <?= e($lot['identificador']) ?>
                        <?= $lot['nom_sector'] ? '— ' . e($lot['nom_sector']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtre-grup">
            <label for="resultat">Resultat</label>
            <select name="resultat" id="resultat">
                <option value="">Tots</option>
                <?php foreach (['Acceptat', 'Condicional', 'Rebutjat'] as $r): ?>
                    <option value="<?= $r ?>" <?= $filtre_resultat === $r ? 'selected' : '' ?>><?= $r ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filtre-grup">
            <label for="data_des">Des de</label>
            <input type="date" name="data_des" id="data_des" value="<?= e($filtre_des ?? '') ?>">
        </div>

        <div class="filtre-grup">
            <label for="data_fins">Fins a</label>
            <input type="date" name="data_fins" id="data_fins" value="<?= e($filtre_fins ?? '') ?>">
        </div>

        <div class="filtre-accions">
            <button type="submit" class="boto-secundari">
                <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
            </button>
            <a href="<?= BASE_URL ?>modules/qualitat/qualitat_lots.php" class="boto-neutre">
                <i class="fas fa-xmark" aria-hidden="true"></i> Netejar
            </a>
        </div>
    </form>

    <!-- ── TAULA DE RESULTATS ─────────────────────────────────────────────── -->
    <?php if (empty($controls)): ?>
        <div class="buit-resultat">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <p>No s'han trobat controls de qualitat amb els filtres seleccionats.</p>
            <a href="<?= BASE_URL ?>modules/qualitat/nou_control.php" class="boto-principal">
                <i class="fas fa-plus"></i> Registrar el primer control
            </a>
        </div>
    <?php else: ?>
        <p class="text-suau">
            <?= count($controls) ?> registre<?= count($controls) !== 1 ? 's' : '' ?> trobat<?= count($controls) !== 1 ? 's' : '' ?>.
        </p>

        <div class="taula-responsive">
            <table class="taula-dades" id="taula-controls">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Lot</th>
                        <th>Sector / Parcel·la</th>
                        <th>Calibre (mm)</th>
                        <th>Fermesa (kg/cm²)</th>
                        <th>Sabor</th>
                        <th>Inspector</th>
                        <th>Resultat</th>
                        <th class="col-accions">Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($controls as $c): ?>
                        <tr>
                            <td><?= $c['data_control'] ? date('d/m/Y', strtotime($c['data_control'])) : '—' ?></td>
                            <td>
                                <strong><?= e($c['lot_identificador']) ?></strong>
                                <?php if ($c['lot_pes_kg']): ?>
                                    <br><span class="text-suau"><?= e($c['lot_pes_kg']) ?> kg</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($c['nom_sector'] ?? '—') ?>
                                <?php if ($c['nom_parcela']): ?>
                                    <br><span class="text-suau"><?= e($c['nom_parcela']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= $c['calibre_mm'] !== null ? e($c['calibre_mm']) : '—' ?></td>
                            <td><?= $c['fermesa_kg_cm2'] !== null ? e($c['fermesa_kg_cm2']) : '—' ?></td>
                            <td><?= $c['sabor'] ? e($c['sabor']) : '—' ?></td>
                            <td>
                                <?php if ($c['inspector_nom']): ?>
                                    <?= e($c['inspector_cognoms'] . ', ' . $c['inspector_nom']) ?>
                                <?php else: ?>
                                    <span class="text-suau">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= badge_resultat($c['resultat'] ?? '') ?></td>
                            <td class="col-accions">
                                <a href="<?= BASE_URL ?>modules/qualitat/nou_control.php?editar=<?= (int)$c['id_control'] ?>"
                                   class="boto-taula" title="Editar">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                </a>
                                <a href="<?= BASE_URL ?>modules/qualitat/processar_control.php?accio=eliminar&id=<?= (int)$c['id_control'] ?>"
                                   class="boto-taula boto-taula--perill"
                                   title="Eliminar"
                                   data-confirma="Segur que vols eliminar aquest control de qualitat?">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- ── ESTILS LOCALS ───────────────────────────────────────────────────────── -->
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
