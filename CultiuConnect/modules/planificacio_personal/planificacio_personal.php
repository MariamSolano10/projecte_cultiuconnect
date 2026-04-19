<?php
/**
 * modules/planificacio_personal/planificacio_personal.php
 *
 * Gestiona la planificació estacional de necessitats de mà d'obra:
 * poda, aclarida, collita, etc. Amb CRUD complet i vista de calendari.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Planificació de Personal';
$pagina_activa = 'planificacio_personal';

$planificacions = [];
$error_db       = null;

$filtre_temporada = is_numeric($_GET['temporada'] ?? '') ? (int)$_GET['temporada'] : (int)date('Y');

// ── POST inline: eliminar ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accio'] ?? '') === 'eliminar') {
    try {
        $pdo = connectDB();
        $pdo->prepare("DELETE FROM planificacio_personal WHERE id_planificacio = :id")
            ->execute([':id' => (int)($_POST['id_planificacio'] ?? 0)]);
        set_flash('success', 'Planificació eliminada.');
    } catch (Exception $e) {
        error_log('[CultiuConnect] planificacio_personal eliminar: ' . $e->getMessage());
        set_flash('error', 'Error en eliminar la planificació.');
    }
    header('Location: ' . BASE_URL . 'modules/planificacio_personal/planificacio_personal.php?temporada=' . $filtre_temporada);
    exit;
}

try {
    $pdo = connectDB();

    $temporades = $pdo->query("
        SELECT DISTINCT temporada FROM planificacio_personal ORDER BY temporada DESC
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ([(int)date('Y'), (int)date('Y') + 1] as $t) {
        if (!in_array($t, $temporades)) $temporades[] = $t;
    }
    rsort($temporades);

    $planificacions = $pdo->prepare("
        SELECT * FROM planificacio_personal
        WHERE temporada = :temporada
        ORDER BY data_inici ASC
    ");
    $planificacions->execute([':temporada' => $filtre_temporada]);
    $planificacions = $planificacions->fetchAll();

    // Resum de la temporada
    $resum = $pdo->prepare("
        SELECT
            SUM(DATEDIFF(data_fi, data_inici) + 1)          AS dies_totals,
            SUM(num_treballadors_necessaris)                  AS total_treb_previst,
            MAX(num_treballadors_necessaris)                  AS pic_max
        FROM planificacio_personal
        WHERE temporada = :temporada
    ");
    $resum->execute([':temporada' => $filtre_temporada]);
    $resum = $resum->fetch();

} catch (Exception $e) {
    error_log('[CultiuConnect] planificacio_personal.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar la planificació.';
}

// Colors per periode (visual)
function colorPeriode(string $periode): string
{
    $mapa = [
        'poda'          => '#3b82f6',
        'aclarida'      => '#a855f7',
        'collita'       => '#22c55e',
        'tractament'    => '#f59e0b',
        'fertilitzacio' => '#06b6d4',
        'reg'           => '#0ea5e9',
        'manteniment'   => '#6b7280',
    ];
    foreach ($mapa as $clau => $color) {
        if (stripos($periode, $clau) !== false) return $color;
    }
    return '#6b7280';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-planificacio-personal">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-calendar-days" aria-hidden="true"></i>
            Planificació de Personal
        </h1>
        <p class="descripcio-seccio">
            Estimació de necessitats de mà d'obra per temporada: poda, aclarida, collita i altres períodes intensius.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error"><i class="fas fa-circle-xmark"></i> <?= e($error_db) ?></div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/planificacio_personal/nova_planificacio.php?temporada=<?= $filtre_temporada ?>"
           class="boto-principal">
            <i class="fas fa-plus"></i> Nova Planificació
        </a>
    </div>

    <!-- FILTRE TEMPORADA -->
    <div class="filtres-container">
        <?php foreach ($temporades as $t): ?>
            <a href="?temporada=<?= (int)$t ?>"
               class="filtre-boto <?= $filtre_temporada === (int)$t ? 'filtre-boto--actiu' : '' ?>">
                <?= (int)$t ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- RESUM -->
    <?php if ($resum && !$error_db): ?>
    <div class="resum-cards">
        <div class="stat-card stat-card--blau">
            <div class="stat-card__icon"><i class="fas fa-calendar"></i></div>
            <div class="stat-card__valor"><?= count($planificacions) ?></div>
            <div class="stat-card__label">Períodes planificats</div>
        </div>
        <div class="stat-card stat-card--groc">
            <div class="stat-card__icon"><i class="fas fa-sun"></i></div>
            <div class="stat-card__valor"><?= (int)($resum['dies_totals'] ?? 0) ?></div>
            <div class="stat-card__label">Dies de necessitat</div>
        </div>
        <div class="stat-card stat-card--verd">
            <div class="stat-card__icon"><i class="fas fa-users"></i></div>
            <div class="stat-card__valor"><?= (int)($resum['total_treb_previst'] ?? 0) ?></div>
            <div class="stat-card__label">Treballadors·dia previstos</div>
        </div>
        <div class="stat-card stat-card--vermell">
            <div class="stat-card__icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-card__valor"><?= (int)($resum['pic_max'] ?? 0) ?></div>
            <div class="stat-card__label">Pic màxim simultani</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- VISTA DE CALENDARI (Gantt simplificat) -->
    <?php if (!empty($planificacions)): ?>
    <div class="card">
        <div class="card__header">
            <i class="fas fa-chart-gantt"></i>
            Diagrama de períodes — <?= $filtre_temporada ?>
        </div>
        <div class="card__body">
            <div class="gantt">
                <?php
                // Calculem rang de dates
                $data_min = min(array_column($planificacions, 'data_inici'));
                $data_max = max(array_column($planificacions, 'data_fi'));
                $dies_total = (new DateTime($data_max))->diff(new DateTime($data_min))->days + 1;

                foreach ($planificacions as $pl):
                    $dies_offset = (new DateTime($pl['data_inici']))->diff(new DateTime($data_min))->days;
                    $dies_durada = (new DateTime($pl['data_fi']))->diff(new DateTime($pl['data_inici']))->days + 1;
                    $pct_inici  = $dies_total > 0 ? round($dies_offset  / $dies_total * 100, 1) : 0;
                    $pct_amplada = $dies_total > 0 ? round($dies_durada / $dies_total * 100, 1) : 5;
                    $color       = colorPeriode($pl['periode']);
                ?>
                <div class="gantt__row">
                    <div class="gantt__label">
                        <?= e(mb_substr($pl['periode'], 0, 18)) ?>
                    </div>
                    <div class="gantt__track">
                        <div class="gantt__bar"
                             data-x="<?= $pct_inici ?>"
                             data-w="<?= $pct_amplada ?>"
                             data-bg="<?= e($color) ?>"
                             title="<?= e($pl['periode']) ?>: <?= format_data($pl['data_inici'], curta:true) ?> - <?= format_data($pl['data_fi'], curta:true) ?>">
                            <?= (int)$pl['num_treballadors_necessaris'] ?> p.
                        </div>
                    </div>
                    <div class="gantt__date">
                        <?= format_data($pl['data_inici'], curta: true) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAULA -->
    <div class="card">
        <div class="card__header">
            <i class="fas fa-table"></i>
            Períodes de necessitat — <?= $filtre_temporada ?>
        </div>
        <div class="card__body">
            <table class="taula-simple">
                <thead>
                    <tr>
                        <th>Període</th>
                        <th>Inici</th>
                        <th>Fi</th>
                        <th>Dies</th>
                        <th>Treballadors necessaris</th>
                        <th>Perfil</th>
                        <th>Observacions</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($planificacions)): ?>
                        <tr>
                            <td colspan="8" class="sense-dades">
                                <i class="fas fa-info-circle"></i>
                                No hi ha períodes planificats per a la temporada <?= $filtre_temporada ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($planificacions as $pl):
                            $dies = (new DateTime($pl['data_fi']))->diff(new DateTime($pl['data_inici']))->days + 1;
                            $color = colorPeriode($pl['periode']);
                        ?>
                        <tr>
                            <td>
                                <span class="periode-pill">
                                    <span class="periode-dot" data-dot="<?= e($color) ?>"></span>
                                    <strong><?= e($pl['periode']) ?></strong>
                                </span>
                            </td>
                            <td><?= format_data($pl['data_inici'], curta: true) ?></td>
                            <td><?= format_data($pl['data_fi'],    curta: true) ?></td>
                            <td><?= $dies ?> dies</td>
                            <td>
                                <span class="badge badge--blau">
                                    <i class="fas fa-users"></i>
                                    <?= (int)$pl['num_treballadors_necessaris'] ?>
                                </span>
                            </td>
                            <td><?= $pl['perfil_necessari'] ? e($pl['perfil_necessari']) : '—' ?></td>
                            <td>
                                <?= $pl['observacions']
                                    ? e(mb_substr($pl['observacions'], 0, 50)) . (mb_strlen($pl['observacions']) > 50 ? '…' : '')
                                    : '—' ?>
                            </td>
                            <td class="cel-accions">
                                <a href="<?= BASE_URL ?>modules/planificacio_personal/nova_planificacio.php?editar=<?= (int)$pl['id_planificacio'] ?>"
                                   class="btn-accio btn-accio--editar" title="Editar">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form method="POST" class="form-inline"
                                      onsubmit="return confirm('Eliminar aquest període?')">
                                    <input type="hidden" name="accio"           value="eliminar">
                                    <input type="hidden" name="id_planificacio" value="<?= (int)$pl['id_planificacio'] ?>">
                                    <button type="submit" class="btn-accio btn-accio--eliminar" title="Eliminar">
                                        <i class="fas fa-trash"></i>
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

</div>

<script>
document.querySelectorAll('.gantt__bar[data-x][data-w][data-bg]').forEach(el => {
    el.style.setProperty('--x', `${el.dataset.x}%`);
    el.style.setProperty('--w', `${el.dataset.w}%`);
    el.style.setProperty('--bg', el.dataset.bg);
});

document.querySelectorAll('.periode-dot[data-dot]').forEach(el => {
    el.style.setProperty('--dot', el.dataset.dot);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
