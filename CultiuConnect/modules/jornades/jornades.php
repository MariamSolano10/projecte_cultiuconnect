<?php
/**
 * modules/jornades/jornades.php — Control d'hores treballades.
 *
 * Mostra totes les jornades amb filtres per treballador, mes i estat de validació.
 * Calcula el resum d'hores per treballador del mes seleccionat.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Control de Jornades';
$pagina_activa = 'jornades';

$jornades    = [];
$treballadors = [];
$resum       = [];
$error_db    = null;

function es_gestor(?array $usuari): bool
{
    $rol = strtolower((string)($usuari['rol'] ?? 'operari'));
    return in_array($rol, ['admin', 'tecnic', 'responsable'], true);
}

function usuari_treballador_id(PDO $pdo, int $usuari_id): ?int
{
    $stmt = $pdo->prepare("SELECT id_treballador FROM usuari WHERE id_usuari = :id LIMIT 1");
    $stmt->execute([':id' => $usuari_id]);
    $id = $stmt->fetchColumn();
    return $id !== false && $id !== null ? (int)$id : null;
}

// Filtres
$filtre_treballador = !empty($_GET['id_treballador']) && is_numeric($_GET['id_treballador'])
    ? (int)$_GET['id_treballador']
    : 0;

$filtre_mes = !empty($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes'])
    ? $_GET['mes']
    : date('Y-m');

$filtre_validada = isset($_GET['validada']) && in_array($_GET['validada'], ['0', '1', 'tots'])
    ? $_GET['validada']
    : 'tots';

try {
    $pdo = connectDB();

    $usuari = usuari_actiu();
    $gestor = es_gestor($usuari);

    // Operari: només pot veure les seves jornades (si està vinculat).
    if (!$gestor) {
        $id_self = $usuari ? usuari_treballador_id($pdo, (int)($usuari['id'] ?? 0)) : null;
        if ($id_self) {
            $filtre_treballador = $id_self;
        } else {
            $error_db = 'El teu usuari no està vinculat a cap treballador.';
            $filtre_treballador = -1; // força 0 resultats
        }
    }

    // Treballadors actius per al filtre
    if ($gestor) {
        $treballadors = $pdo->query("
            SELECT id_treballador, nom, cognoms, rol
            FROM treballador
            WHERE estat = 'actiu'
            ORDER BY cognoms, nom
        ")->fetchAll();
    } else {
        $treballadors = [];
    }

    // Condicions WHERE
    $condicions = ["DATE_FORMAT(j.data_hora_inici, '%Y-%m') = :mes"];
    $params     = [':mes' => $filtre_mes];

    if ($filtre_treballador > 0) {
        $condicions[] = 'j.id_treballador = :id_treballador';
        $params[':id_treballador'] = $filtre_treballador;
    }
    if ($filtre_validada !== 'tots') {
        $condicions[] = 'j.validada = :validada';
        $params[':validada'] = (int)$filtre_validada;
    }

    $where = 'WHERE ' . implode(' AND ', $condicions);

    // Jornades
    $sql = "
        SELECT
            j.id_jornada,
            j.data_hora_inici,
            j.data_hora_fi,
            j.pausa_minuts,
            j.ubicacio,
            j.incidencies,
            j.validada,
            t.id_treballador,
            t.nom,
            t.cognoms,
            t.rol,
            ta.tipus  AS tipus_tasca,
            ta.id_tasca,
            TIMESTAMPDIFF(MINUTE, j.data_hora_inici, j.data_hora_fi) - COALESCE(j.pausa_minuts, 0)
                AS minuts_nets
        FROM jornada j
        JOIN treballador t ON t.id_treballador = j.id_treballador
        LEFT JOIN tasca ta ON ta.id_tasca = j.id_tasca
        $where
        ORDER BY j.data_hora_inici DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jornades = $stmt->fetchAll();

    // Resum per treballador del mes
    $sql_resum = "
        SELECT
            t.id_treballador,
            t.nom,
            t.cognoms,
            t.rol,
            COUNT(j.id_jornada) AS num_jornades,
            SUM(
                TIMESTAMPDIFF(MINUTE, j.data_hora_inici, j.data_hora_fi)
                - COALESCE(j.pausa_minuts, 0)
            ) AS minuts_totals,
            SUM(CASE WHEN j.validada = 1 THEN 1 ELSE 0 END) AS jornades_validades
        FROM treballador t
        JOIN jornada j ON j.id_treballador = t.id_treballador
        WHERE DATE_FORMAT(j.data_hora_inici, '%Y-%m') = :mes
          AND j.data_hora_fi IS NOT NULL
        " . ($filtre_treballador > 0 ? "AND t.id_treballador = :id_treballador" : "") . "
        GROUP BY t.id_treballador
        ORDER BY minuts_totals DESC
    ";

    $params_resum = [':mes' => $filtre_mes];
    if ($filtre_treballador > 0) $params_resum[':id_treballador'] = $filtre_treballador;

    $stmt_resum = $pdo->prepare($sql_resum);
    $stmt_resum->execute($params_resum);
    $resum = $stmt_resum->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] jornades.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les jornades.';
}

// Genera llista de mesos (12 mesos enrere + 1 endavant)
function mesos_disponibles(): array
{
    $mesos = [];
    for ($i = -1; $i <= 12; $i++) {
        $mesos[] = date('Y-m', strtotime("-$i months"));
    }
    return array_unique($mesos);
}

function format_minuts(int|null $minuts): string
{
    if ($minuts === null || $minuts < 0) return '—';
    $h = intdiv($minuts, 60);
    $m = abs($minuts % 60);
    return sprintf('%dh %02dmin', $h, $m);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-jornades">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-clock" aria-hidden="true"></i>
            Control de Jornades
        </h1>
        <p class="descripcio-seccio">
            Registre i validació de les hores treballades per tot el personal.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/jornades/nova_jornada.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Registrar Jornada
        </a>
        <a href="<?= BASE_URL ?>modules/jornades/export_hores.php?format=csv&mes=<?= e($filtre_mes) ?>&id_treballador=<?= (int)$filtre_treballador ?>&validada=<?= e($filtre_validada) ?>"
           class="boto-secundari">
            <i class="fas fa-file-csv" aria-hidden="true"></i> Export CSV
        </a>
        <a href="<?= BASE_URL ?>modules/jornades/export_hores.php?format=pdf&mes=<?= e($filtre_mes) ?>&id_treballador=<?= (int)$filtre_treballador ?>&validada=<?= e($filtre_validada) ?>"
           class="boto-secundari">
            <i class="fas fa-file-pdf" aria-hidden="true"></i> Export PDF
        </a>
    </div>

    <!-- FILTRES -->
    <div class="filtres-avancats card">
        <form method="GET" class="form-fila">

            <div class="form-camp">
                <label for="mes">Mes</label>
                <select name="mes" id="mes">
                    <?php foreach (mesos_disponibles() as $m): ?>
                        <option value="<?= $m ?>" <?= $filtre_mes === $m ? 'selected' : '' ?>>
                            <?= date('F Y', strtotime($m . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-camp">
                <label for="id_treballador">Treballador</label>
                <select name="id_treballador" id="id_treballador">
                    <option value="0">— Tots —</option>
                    <?php foreach ($treballadors as $t): ?>
                        <option value="<?= (int)$t['id_treballador'] ?>"
                            <?= $filtre_treballador === (int)$t['id_treballador'] ? 'selected' : '' ?>>
                            <?= e($t['nom'] . ' ' . ($t['cognoms'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-camp">
                <label for="validada">Validació</label>
                <select name="validada" id="validada">
                    <option value="tots"  <?= $filtre_validada === 'tots' ? 'selected' : '' ?>>Totes</option>
                    <option value="0"     <?= $filtre_validada === '0'    ? 'selected' : '' ?>>Pendents de validar</option>
                    <option value="1"     <?= $filtre_validada === '1'    ? 'selected' : '' ?>>Validades</option>
                </select>
            </div>

            <div class="form-camp">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- RESUM DEL MES -->
    <?php if (!empty($resum)): ?>
    <div class="card">
        <div class="card__header">
            <i class="fas fa-chart-bar" aria-hidden="true"></i>
            Resum del mes: <?= date('F Y', strtotime($filtre_mes . '-01')) ?>
        </div>
        <div class="card__body">
            <table class="taula-simple">
                <thead>
                    <tr>
                        <th>Treballador</th>
                        <th>Rol</th>
                        <th>Jornades</th>
                        <th>Hores totals</th>
                        <th>Validades</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resum as $r): ?>
                        <tr>
                            <td><strong><?= e($r['nom'] . ' ' . ($r['cognoms'] ?? '')) ?></strong></td>
                            <td><?= e($r['rol']) ?></td>
                            <td><?= (int)$r['num_jornades'] ?></td>
                            <td>
                                <strong><?= format_minuts((int)$r['minuts_totals']) ?></strong>
                            </td>
                            <td>
                                <?php
                                $pct = $r['num_jornades'] > 0
                                    ? round($r['jornades_validades'] / $r['num_jornades'] * 100)
                                    : 0;
                                ?>
                                <span class="badge <?= $pct === 100 ? 'badge--verd' : ($pct > 0 ? 'badge--groc' : 'badge--vermell') ?>">
                                    <?= (int)$r['jornades_validades'] ?> / <?= (int)$r['num_jornades'] ?>
                                </span>
                            </td>
                            <td class="cel-accions">
                                <a href="?mes=<?= urlencode($filtre_mes) ?>&id_treballador=<?= (int)$r['id_treballador'] ?>"
                                   class="btn-accio btn-accio--veure"
                                   title="Veure jornades d'aquest treballador">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- LLISTAT DE JORNADES -->
    <div class="card">
        <div class="card__header">
            <i class="fas fa-list" aria-hidden="true"></i>
            Jornades — <?= date('F Y', strtotime($filtre_mes . '-01')) ?>
            <span class="badge badge--gris badge--sep">
                <?= count($jornades) ?> registres
            </span>
        </div>
        <div class="card__body">

            <div class="cerca-container">
                <input type="search"
                       data-filtre-taula="taula-jornades"
                       placeholder="Cerca per nom o ubicació..."
                       class="input-cerca"
                       aria-label="Cercar jornades">
            </div>

            <table class="taula-simple" id="taula-jornades">
                <thead>
                    <tr>
                        <th>Treballador</th>
                        <th>Inici</th>
                        <th>Fi</th>
                        <th>Pausa</th>
                        <th>Hores netes</th>
                        <th>Tasca</th>
                        <th>Ubicació</th>
                        <th>Validada</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jornades)): ?>
                        <tr>
                            <td colspan="9" class="sense-dades">
                                <i class="fas fa-info-circle" aria-hidden="true"></i>
                                No hi ha jornades registrades per als filtres seleccionats.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($jornades as $j): ?>
                            <tr>
                                <td data-cerca>
                                    <strong><?= e($j['nom'] . ' ' . ($j['cognoms'] ?? '')) ?></strong>
                                    <br><span class="text-suau"><?= e($j['rol']) ?></span>
                                </td>
                                <td><?= $j['data_hora_inici'] ? date('d/m/Y H:i', strtotime($j['data_hora_inici'])) : '—' ?></td>
                                <td>
                                    <?php if ($j['data_hora_fi']): ?>
                                        <?= date('d/m/Y H:i', strtotime($j['data_hora_fi'])) ?>
                                    <?php else: ?>
                                        <span class="badge badge--blau">En curs</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)($j['pausa_minuts'] ?? 0) ?> min</td>
                                <td>
                                    <?php if ($j['data_hora_fi']): ?>
                                        <strong><?= format_minuts((int)$j['minuts_nets']) ?></strong>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($j['tipus_tasca']): ?>
                                        <a href="<?= BASE_URL ?>modules/tasques/detall_tasca.php?id=<?= (int)$j['id_tasca'] ?>">
                                            <?= e(ucfirst($j['tipus_tasca'])) ?>
                                        </a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td data-cerca><?= $j['ubicacio'] ? e($j['ubicacio']) : '—' ?></td>
                                <td>
                                    <?php if ($j['validada']): ?>
                                        <span class="badge badge--verd">
                                            <i class="fas fa-check" aria-hidden="true"></i> Sí
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge--groc">Pendent</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cel-accions">
                                    <a href="<?= BASE_URL ?>modules/jornades/nova_jornada.php?editar=<?= (int)$j['id_jornada'] ?>"
                                       title="Editar jornada"
                                       class="btn-accio btn-accio--editar">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </a>
                                    <?php if (!$j['validada']): ?>
                                    <form method="POST"
                                          action="<?= BASE_URL ?>modules/jornades/processar_jornada.php"
                                          class="form-inline">
                                        <input type="hidden" name="accio"      value="validar">
                                        <input type="hidden" name="id_jornada" value="<?= (int)$j['id_jornada'] ?>">
                                        <input type="hidden" name="mes"        value="<?= e($filtre_mes) ?>">
                                        <input type="hidden" name="id_treballador" value="<?= $filtre_treballador ?>">
                                        <button type="submit"
                                                class="btn-accio btn-accio--veure"
                                                title="Validar jornada">
                                            <i class="fas fa-check" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST"
                                          action="<?= BASE_URL ?>modules/jornades/processar_jornada.php"
                                          class="form-inline"
                                          onsubmit="return confirm('Eliminar aquesta jornada?')">
                                        <input type="hidden" name="accio"      value="eliminar">
                                        <input type="hidden" name="id_jornada" value="<?= (int)$j['id_jornada'] ?>">
                                        <input type="hidden" name="mes"        value="<?= e($filtre_mes) ?>">
                                        <input type="hidden" name="id_treballador" value="<?= $filtre_treballador ?>">
                                        <button type="submit"
                                                class="btn-accio btn-accio--eliminar"
                                                title="Eliminar jornada">
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

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
