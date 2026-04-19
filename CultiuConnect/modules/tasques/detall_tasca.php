<?php
/**
 * modules/tasques/detall_tasca.php — Detall complet d'una tasca.
 *
 * Mostra la info de la tasca, els treballadors assignats i permet:
 *  - Assignar nous treballadors
 *  - Treure assignacions
 *  - Canviar l'estat de la tasca
 *  - Registrar inici/fi real d'una assignació
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Detall de Tasca';
$pagina_activa = 'tasques';

$id_tasca = !empty($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_tasca) {
    set_flash('error', 'Tasca no especificada.');
    header('Location: ' . BASE_URL . 'modules/tasques/tasques.php');
    exit;
}

$tasca         = null;
$assignacions  = [];
$disponibles   = [];
$sector        = null;
$error_db      = null;

// --- Accions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accio = $_POST['accio'] ?? '';

    try {
        $pdo = connectDB();

        // Assignar treballador
        if ($accio === 'assignar' && !empty($_POST['id_treballador'])) {
            $id_treb = (int)$_POST['id_treballador'];
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO assignacio_tasca (id_tasca, id_treballador, estat)
                VALUES (:id_tasca, :id_treballador, 'assignat')
            ");
            $stmt->execute([':id_tasca' => $id_tasca, ':id_treballador' => $id_treb]);
            set_flash('success', 'Treballador assignat correctament.');

        // Treure assignació
        } elseif ($accio === 'desassignar' && !empty($_POST['id_treballador'])) {
            $id_treb = (int)$_POST['id_treballador'];
            $stmt = $pdo->prepare("
                DELETE FROM assignacio_tasca
                WHERE id_tasca = :id_tasca AND id_treballador = :id_treballador
            ");
            $stmt->execute([':id_tasca' => $id_tasca, ':id_treballador' => $id_treb]);
            set_flash('success', 'Assignació eliminada.');

        // Canviar estat de la tasca
        } elseif ($accio === 'canviar_estat' && !empty($_POST['nou_estat'])) {
            $estats_valids = ['pendent', 'en_proces', 'completada', 'cancel·lada'];
            $nou_estat = $_POST['nou_estat'];
            if (in_array($nou_estat, $estats_valids)) {
                $stmt = $pdo->prepare("UPDATE tasca SET estat = :estat WHERE id_tasca = :id");
                $stmt->execute([':estat' => $nou_estat, ':id' => $id_tasca]);
                set_flash('success', 'Estat actualitzat a: ' . ucfirst($nou_estat));
            }

        // Actualitzar dates reals d'una assignació
        } elseif ($accio === 'actualitzar_dates' && !empty($_POST['id_treballador'])) {
            $id_treb        = (int)$_POST['id_treballador'];
            $data_inici_real = trim($_POST['data_inici_real'] ?? '') ?: null;
            $data_fi_real    = trim($_POST['data_fi_real'] ?? '') ?: null;
            $estat_assig     = in_array($_POST['estat_assig'] ?? '', ['assignat','en_proces','completat'])
                                ? $_POST['estat_assig'] : 'assignat';
            $notes           = trim($_POST['notes'] ?? '') ?: null;

            $stmt = $pdo->prepare("
                UPDATE assignacio_tasca
                SET data_inici_real = :inici,
                    data_fi_real    = :fi,
                    estat           = :estat,
                    notes           = :notes
                WHERE id_tasca = :id_tasca AND id_treballador = :id_treballador
            ");
            $stmt->execute([
                ':inici'          => $data_inici_real,
                ':fi'             => $data_fi_real,
                ':estat'          => $estat_assig,
                ':notes'          => $notes,
                ':id_tasca'       => $id_tasca,
                ':id_treballador' => $id_treb,
            ]);
            set_flash('success', 'Assignació actualitzada correctament.');
        }

    } catch (Exception $e) {
        error_log('[CultiuConnect] detall_tasca.php POST: ' . $e->getMessage());
        set_flash('error', 'Error intern en processar l\'acció.');
    }

    header('Location: ' . BASE_URL . 'modules/tasques/detall_tasca.php?id=' . $id_tasca);
    exit;
}

// --- Carregar dades ---
try {
    $pdo = connectDB();

    // Tasca
    $stmt = $pdo->prepare("
        SELECT t.*, s.nom AS nom_sector,
               tp.descripcio AS precedent_desc, tp.tipus AS precedent_tipus
        FROM tasca t
        LEFT JOIN sector s ON s.id_sector = t.id_sector
        LEFT JOIN tasca tp ON tp.id_tasca = t.tasca_precedent
        WHERE t.id_tasca = :id
    ");
    $stmt->execute([':id' => $id_tasca]);
    $tasca = $stmt->fetch();

    if (!$tasca) {
        set_flash('error', 'Tasca no trobada.');
        header('Location: ' . BASE_URL . 'modules/tasques/tasques.php');
        exit;
    }

    $titol_pagina = 'Tasca: ' . ucfirst($tasca['tipus']);

    // Treballadors assignats
    $stmt = $pdo->prepare("
        SELECT
            at.id_treballador,
            at.data_inici_real,
            at.data_fi_real,
            at.estat,
            at.notes,
            t.nom,
            t.cognoms,
            t.rol,
            TIMESTAMPDIFF(MINUTE, at.data_inici_real, at.data_fi_real) AS minuts_treballats
        FROM assignacio_tasca at
        JOIN treballador t ON t.id_treballador = at.id_treballador
        WHERE at.id_tasca = :id
        ORDER BY t.cognoms, t.nom
    ");
    $stmt->execute([':id' => $id_tasca]);
    $assignacions = $stmt->fetchAll();

    $ids_assignats = array_column($assignacions, 'id_treballador');

    // Treballadors disponibles per assignar (actius i no assignats)
    $stmt = $pdo->prepare("
        SELECT id_treballador, nom, cognoms, rol
        FROM treballador
        WHERE estat = 'actiu'
        " . ($ids_assignats ? "AND id_treballador NOT IN (" . implode(',', array_map('intval', $ids_assignats)) . ")" : "") . "
        ORDER BY cognoms, nom
    ");
    $stmt->execute();
    $disponibles = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] detall_tasca.php GET: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar el detall de la tasca.';
}

function classeEstatTasca(string $estat): string
{
    return match ($estat) {
        'pendent'     => 'badge--groc',
        'en_proces'   => 'badge--blau',
        'completada'  => 'badge--verd',
        'cancel·lada' => 'badge--vermell',
        default       => 'badge--gris',
    };
}

function classeEstatAssig(string $estat): string
{
    return match ($estat) {
        'assignat'   => 'badge--groc',
        'en_proces'  => 'badge--blau',
        'completat'  => 'badge--verd',
        default      => 'badge--gris',
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-detall">

    <!-- CAPÇALERA -->
    <div class="capcalera-seccio">
        <div>
            <h1 class="titol-pagina">
                <i class="fas fa-list-check" aria-hidden="true"></i>
                Tasca #<?= $id_tasca ?>
                <?php if ($tasca): ?>
                    — <?= e(ucfirst($tasca['tipus'])) ?>
                <?php endif; ?>
            </h1>
        </div>
        <div class="flex-inline-actions">
            <a href="<?= BASE_URL ?>modules/tasques/nova_tasca.php?editar=<?= $id_tasca ?>"
               class="boto-secundari">
                <i class="fas fa-pen" aria-hidden="true"></i> Editar
            </a>
            <a href="<?= BASE_URL ?>modules/tasques/tasques.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <?php if ($tasca): ?>

    <div class="detall-grid">

        <!-- INFORMACIÓ DE LA TASCA -->
        <div class="card">
            <div class="card__header">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                Informació general
            </div>
            <div class="card__body">
                <dl class="llista-detalls">
                    <dt>Estat</dt>
                    <dd>
                        <span class="badge <?= classeEstatTasca($tasca['estat']) ?>">
                            <?= e(ucfirst($tasca['estat'])) ?>
                        </span>
                    </dd>

                    <dt>Sector</dt>
                    <dd><?= $tasca['nom_sector'] ? e($tasca['nom_sector']) : '—' ?></dd>

                    <dt>Descripció</dt>
                    <dd><?= $tasca['descripcio'] ? e($tasca['descripcio']) : '—' ?></dd>

                    <dt>Inici previst</dt>
                    <dd><?= format_data($tasca['data_inici_prevista'], curta: true) ?></dd>

                    <dt>Fi prevista</dt>
                    <dd><?= $tasca['data_fi_prevista'] ? format_data($tasca['data_fi_prevista'], curta: true) : '—' ?></dd>

                    <dt>Durada estimada</dt>
                    <dd><?= $tasca['duracio_estimada_h'] ? number_format((float)$tasca['duracio_estimada_h'], 1) . ' h' : '—' ?></dd>

                    <dt>Treballadors necessaris</dt>
                    <dd><?= $tasca['num_treballadors_necessaris'] ?? '—' ?></dd>

                    <?php if ($tasca['qualificacions_necessaries']): ?>
                    <dt>Qualificacions</dt>
                    <dd><?= e($tasca['qualificacions_necessaries']) ?></dd>
                    <?php endif; ?>

                    <?php if ($tasca['equipament_necessari']): ?>
                    <dt>Equipament</dt>
                    <dd><?= e($tasca['equipament_necessari']) ?></dd>
                    <?php endif; ?>

                    <?php if ($tasca['precedent_tipus']): ?>
                    <dt>Tasca precedent</dt>
                    <dd>
                        <a href="<?= BASE_URL ?>modules/tasques/detall_tasca.php?id=<?= (int)$tasca['tasca_precedent'] ?>">
                            <?= e(ucfirst($tasca['precedent_tipus'])) ?>
                            <?= $tasca['precedent_desc'] ? ': ' . e(mb_substr($tasca['precedent_desc'], 0, 50)) : '' ?>
                        </a>
                    </dd>
                    <?php endif; ?>

                    <?php if ($tasca['instruccions']): ?>
                    <dt>Instruccions</dt>
                    <dd class="instruccions-text"><?= nl2br(e($tasca['instruccions'])) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- CANVIAR ESTAT -->
        <div class="card">
            <div class="card__header">
                <i class="fas fa-arrow-right-arrow-left" aria-hidden="true"></i>
                Canviar estat
            </div>
            <div class="card__body">
                <form method="POST">
                    <input type="hidden" name="accio" value="canviar_estat">
                    <div class="form-fila form-fila--end-gap">
                        <div class="form-camp form-camp--grow">
                            <label for="nou_estat">Nou estat</label>
                            <select name="nou_estat" id="nou_estat">
                                <?php foreach ([
                                    'pendent'     => 'Pendent',
                                    'en_proces'   => 'En procés',
                                    'completada'  => 'Completada',
                                    'cancel·lada' => 'Cancel·lada',
                                ] as $val => $lbl): ?>
                                    <option value="<?= $val ?>"
                                        <?= $tasca['estat'] === $val ? 'selected' : '' ?>>
                                        <?= $lbl ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="boto-principal">
                            <i class="fas fa-check" aria-hidden="true"></i> Aplicar
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- fi detall-grid -->

    <!-- TREBALLADORS ASSIGNATS -->
    <div class="card form-accions--mt-l">
        <div class="card__header">
            <i class="fas fa-users" aria-hidden="true"></i>
            Treballadors assignats
            <span class="badge badge--gris badge--space-left">
                <?= count($assignacions) ?> / <?= $tasca['num_treballadors_necessaris'] ?? '?' ?>
            </span>
        </div>
        <div class="card__body">

            <!-- Formulari per assignar un treballador nou -->
            <?php if (!empty($disponibles)): ?>
            <form method="POST" class="form-inline form-inline--mb-l">
                <input type="hidden" name="accio" value="assignar">
                <div class="form-fila form-fila--end-gap">
                    <div class="form-camp form-camp--grow">
                        <label for="id_treballador_nou">Assignar treballador</label>
                        <select name="id_treballador" id="id_treballador_nou">
                            <option value="">— Selecciona un treballador —</option>
                            <?php foreach ($disponibles as $d): ?>
                                <option value="<?= (int)$d['id_treballador'] ?>">
                                    <?= e($d['nom'] . ' ' . ($d['cognoms'] ?? '')) ?>
                                    (<?= e($d['rol']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="boto-principal">
                        <i class="fas fa-user-plus" aria-hidden="true"></i> Assignar
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <!-- Taula d'assignats -->
            <?php if (empty($assignacions)): ?>
                <p class="sense-dades">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    Encara no hi ha cap treballador assignat a aquesta tasca.
                </p>
            <?php else: ?>
                <table class="taula-simple">
                    <thead>
                        <tr>
                            <th>Treballador</th>
                            <th>Rol</th>
                            <th>Inici real</th>
                            <th>Fi real</th>
                            <th>Temps treballat</th>
                            <th>Estat</th>
                            <th>Notes</th>
                            <th>Accions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignacions as $a): ?>
                            <tr>
                                <td><strong><?= e($a['nom'] . ' ' . ($a['cognoms'] ?? '')) ?></strong></td>
                                <td><?= e($a['rol']) ?></td>
                                <td><?= $a['data_inici_real'] ? format_data($a['data_inici_real']) : '—' ?></td>
                                <td><?= $a['data_fi_real']    ? format_data($a['data_fi_real'])    : '—' ?></td>
                                <td>
                                    <?php if ($a['minuts_treballats'] !== null && $a['minuts_treballats'] >= 0): ?>
                                        <?= floor($a['minuts_treballats'] / 60) ?>h
                                        <?= ($a['minuts_treballats'] % 60) ?>min
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= classeEstatAssig($a['estat']) ?>">
                                        <?= e(ucfirst($a['estat'])) ?>
                                    </span>
                                </td>
                                <td><?= $a['notes'] ? e(mb_substr($a['notes'], 0, 40)) . '...' : '—' ?></td>
                                <td class="cel-accions">
                                    <!-- Botó editar inline -->
                                    <button type="button"
                                            class="btn-accio btn-accio--editar"
                                            title="Actualitzar assignació"
                                            onclick="toggleFormAssig(<?= (int)$a['id_treballador'] ?>)">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </button>
                                    <!-- Botó desassignar -->
                                    <form method="POST" class="form-inline-display"
                                          onsubmit="return confirm('Treure aquesta assignació?')">
                                        <input type="hidden" name="accio" value="desassignar">
                                        <input type="hidden" name="id_treballador" value="<?= (int)$a['id_treballador'] ?>">
                                        <button type="submit"
                                                class="btn-accio btn-accio--eliminar"
                                                title="Treure assignació">
                                            <i class="fas fa-user-xmark" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <!-- Formulari ocult per editar dates i estat -->
                            <tr id="form-assig-<?= (int)$a['id_treballador'] ?>"
                                class="bloc-amagat-suau">
                                <td colspan="8">
                                    <form method="POST" class="py-s">
                                        <input type="hidden" name="accio" value="actualitzar_dates">
                                        <input type="hidden" name="id_treballador" value="<?= (int)$a['id_treballador'] ?>">
                                        <div class="form-fila form-fila--wrap-gap">
                                            <div class="form-camp">
                                                <label>Inici real</label>
                                                <input type="datetime-local" name="data_inici_real"
                                                       value="<?= $a['data_inici_real'] ? date('Y-m-d\TH:i', strtotime($a['data_inici_real'])) : '' ?>">
                                            </div>
                                            <div class="form-camp">
                                                <label>Fi real</label>
                                                <input type="datetime-local" name="data_fi_real"
                                                       value="<?= $a['data_fi_real'] ? date('Y-m-d\TH:i', strtotime($a['data_fi_real'])) : '' ?>">
                                            </div>
                                            <div class="form-camp">
                                                <label>Estat</label>
                                                <select name="estat_assig">
                                                    <?php foreach (['assignat' => 'Assignat', 'en_proces' => 'En procés', 'completat' => 'Completat'] as $v => $l): ?>
                                                        <option value="<?= $v ?>" <?= $a['estat'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-camp form-camp--min-200">
                                                <label>Notes</label>
                                                <input type="text" name="notes"
                                                       value="<?= e($a['notes'] ?? '') ?>"
                                                       placeholder="Observacions...">
                                            </div>
                                            <div class="form-camp form-camp--bottom">
                                                <button type="submit" class="boto-principal">
                                                    <i class="fas fa-save"></i> Desar
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>

</div>

<script>
function toggleFormAssig(idTreballador) {
    const fila = document.getElementById('form-assig-' + idTreballador);
    if (fila) {
        const visible = window.getComputedStyle(fila).display !== 'none';
        fila.style.display = visible ? 'none' : 'table-row';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
