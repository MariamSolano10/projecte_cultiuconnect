<?php
/**
 * modules/finances/inversions.php — Registre d'inversions i despeses de l'explotació.
 *
 * Taula `inversio`:
 *   id_inversio, data_inversio, concepte, categoria, import, proveidor,
 *   id_sector (FK nullable), id_maquinaria (FK nullable), observacions
 *
 * Categories esperades: 'Maquinaria', 'Fitosanitaris', 'Adobs', 'Infraestructura',
 *   'Personal', 'Serveis', 'Altres'
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Inversions i Despeses';
$pagina_activa = 'finances';

$inversions   = [];
$totals       = [];
$error_db     = null;

// Filtre de categoria
$filtre_cat = sanitize($_GET['categoria'] ?? '');

const CATEGORIES_INVERSIO = [
    'Maquinaria', 'Fitosanitaris', 'Adobs',
    'Infraestructura', 'Personal', 'Serveis', 'Altres',
];

try {
    $pdo = connectDB();

    // Totals per categoria (per al resum KPI)
    $totals = $pdo->query("
        SELECT
            categoria,
            COUNT(*)        AS num_registres,
            SUM(import)     AS total_import
        FROM inversio
        WHERE YEAR(data_inversio) = YEAR(CURDATE())
        GROUP BY categoria
        ORDER BY total_import DESC
    ")->fetchAll();

    // Llistat filtrat
    $where  = $filtre_cat ? 'WHERE i.categoria = :cat' : '';
    $params = $filtre_cat ? [':cat' => $filtre_cat] : [];

    $stmt = $pdo->prepare("
        SELECT
            i.id_inversio,
            i.data_inversio,
            i.concepte,
            i.categoria,
            i.import,
            i.proveidor,
            i.observacions,
            s.nom   AS nom_sector,
            m.nom_maquina
        FROM inversio i
        LEFT JOIN sector     s ON s.id_sector     = i.id_sector
        LEFT JOIN maquinaria m ON m.id_maquinaria = i.id_maquinaria
        $where
        ORDER BY i.data_inversio DESC
    ");
    $stmt->execute($params);
    $inversions = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] inversions.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les inversions.';
}

// Total general any actual
$total_any = array_sum(array_column($totals, 'total_import'));

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-inversions">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-euro-sign" aria-hidden="true"></i>
            Inversions i Despeses de l'Explotació
        </h1>
        <p class="descripcio-seccio">
            Registre de totes les despeses i inversions de l'any en curs, agrupades per categoria.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- KPIs per categoria -->
    <?php if (!empty($totals)): ?>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-card__valor"><?= format_euros($total_any) ?></div>
                <div class="kpi-card__etiqueta">Total despeses any actual</div>
            </div>
            <?php foreach ($totals as $t): ?>
                <div class="kpi-card kpi-card--petit">
                    <div class="kpi-card__valor"><?= format_euros((float)$t['total_import']) ?></div>
                    <div class="kpi-card__etiqueta">
                        <?= e($t['categoria']) ?>
                        <span class="text-suau">(<?= (int)$t['num_registres'] ?> reg.)</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/finances/nova_inversio.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Registrar Despesa
        </a>
    </div>

    <!-- Filtre per categoria -->
    <div class="filtres-container">
        <a href="?" class="filtre-boto <?= !$filtre_cat ? 'filtre-boto--actiu' : '' ?>">
            Totes
        </a>
        <?php foreach (CATEGORIES_INVERSIO as $cat): ?>
            <a href="?categoria=<?= urlencode($cat) ?>"
               class="filtre-boto <?= $filtre_cat === $cat ? 'filtre-boto--actiu' : '' ?>">
                <?= e($cat) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Cercador -->
    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-inversions"
               placeholder="Cerca per concepte o proveïdor..."
               class="input-cerca"
               aria-label="Cercar inversions">
    </div>

    <table class="taula-simple" id="taula-inversions">
        <thead>
            <tr>
                <th>ID</th>
                <th>Data</th>
                <th>Concepte</th>
                <th>Categoria</th>
                <th>Import</th>
                <th>Proveïdor</th>
                <th>Sector / Màquina</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($inversions)): ?>
                <tr>
                    <td colspan="8" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha inversions registrades<?= $filtre_cat ? " per a «{$filtre_cat}»" : '' ?>.
                        <a href="<?= BASE_URL ?>modules/finances/nova_inversio.php">Afegeix-ne una.</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($inversions as $inv): ?>
                    <tr>
                        <td><?= (int)$inv['id_inversio'] ?></td>
                        <td><?= format_data($inv['data_inversio'], curta: true) ?></td>
                        <td data-cerca>
                            <strong><?= e($inv['concepte']) ?></strong>
                            <?php if ($inv['observacions']): ?>
                                <br><span class="text-suau"><?= e($inv['observacions']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-cerca>
                            <span class="badge badge--blau"><?= e($inv['categoria']) ?></span>
                        </td>
                        <td class="text-dreta">
                            <strong><?= format_euros((float)$inv['import']) ?></strong>
                        </td>
                        <td data-cerca><?= $inv['proveidor'] ? e($inv['proveidor']) : '—' ?></td>
                        <td>
                            <?php if ($inv['nom_sector']): ?>
                                <i class="fas fa-map-pin text-suau" aria-hidden="true"></i>
                                <?= e($inv['nom_sector']) ?>
                            <?php elseif ($inv['nom_maquina']): ?>
                                <i class="fas fa-tractor text-suau" aria-hidden="true"></i>
                                <?= e($inv['nom_maquina']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="cel-accions">
                            <a href="<?= BASE_URL ?>modules/finances/nova_inversio.php?editar=<?= (int)$inv['id_inversio'] ?>"
                               title="Editar registre"
                               class="btn-accio btn-accio--editar">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                            </a>
                            <a href="<?= BASE_URL ?>modules/finances/eliminar_inversio.php?id=<?= (int)$inv['id_inversio'] ?>"
                               title="Eliminar registre"
                               class="btn-accio btn-accio--eliminar"
                               data-confirma="Segur que vols eliminar «<?= e($inv['concepte']) ?>»?">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>