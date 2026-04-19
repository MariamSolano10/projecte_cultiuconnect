<?php
/**
 * modules/monitoratge/historial_monitoratge.php — Historial d'observacions de camp.
 *
 * Camps BD reals de monitoratge_plaga:
 *   id_monitoratge, id_sector, data_observacio, tipus_problema,
 *   descripcio_breu, nivell_poblacio, llindar_intervencio_assolit
 *
 * NOTA: el camp element_observat NO existeix a la BD — eliminat.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$observacions = [];
$sectors      = [];
$error_db     = null;

$filtre_sector = sanitize_int($_GET['sector_id']     ?? null);
$filtre_tipus  = sanitize($_GET['tipus_problema']    ?? '');
$filtre_alerta = sanitize_int($_GET['nomes_alertes'] ?? null);

try {
    $pdo = connectDB();

    $on     = [];
    $params = [];

    if ($filtre_sector) {
        $on[]                 = 'm.id_sector = :id_sector';
        $params[':id_sector'] = $filtre_sector;
    }
    if ($filtre_tipus) {
        $on[]             = 'm.tipus_problema = :tipus';
        $params[':tipus'] = $filtre_tipus;
    }
    if ($filtre_alerta) {
        $on[] = 'm.llindar_intervencio_assolit = 1';
    }

    $clausula_on = $on ? 'WHERE ' . implode(' AND ', $on) : '';

    $stmt = $pdo->prepare("
        SELECT
            m.id_monitoratge,
            m.data_observacio,
            m.tipus_problema,
            m.descripcio_breu,
            m.nivell_poblacio,
            m.llindar_intervencio_assolit,
            COALESCE(s.nom, '—') AS nom_sector
        FROM monitoratge_plaga m
        LEFT JOIN sector s ON s.id_sector = m.id_sector
        {$clausula_on}
        ORDER BY m.data_observacio DESC
    ");
    $stmt->execute($params);
    $observacions = $stmt->fetchAll();

    $sectors = $pdo->query(
        "SELECT id_sector, nom FROM sector ORDER BY nom ASC"
    )->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] historial_monitoratge.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les observacions.';
}

$classes_tipus = [
    'Plaga'       => 'badge--vermell',
    'Malaltia'    => 'badge--taronja',
    'Deficiencia' => 'badge--groc',
    'Mala Herba'  => 'badge--gris',
];

$titol_pagina  = 'Historial de Monitoratge';
$pagina_activa = 'monitoratge';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-historial">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
            Historial de Vigilància Sanitària
        </h1>
        <p class="descripcio-seccio">
            Registre cronològic de plagues, malalties i deficiències detectades a l'explotació.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/monitoratge/monitoratge.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i>
            Nova Observació
        </a>
    </div>

    <!-- Filtres -->
    <form method="GET"
          action="<?= BASE_URL ?>modules/monitoratge/historial_monitoratge.php"
          class="filtres-barra">

        <select name="sector_id" class="form-select form-select--inline">
            <option value="">Tots els sectors</option>
            <?php foreach ($sectors as $s): ?>
                <option value="<?= (int)$s['id_sector'] ?>"
                    <?= ($filtre_sector === (int)$s['id_sector']) ? 'selected' : '' ?>>
                    <?= e($s['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="tipus_problema" class="form-select form-select--inline">
            <option value="">Tots els tipus</option>
            <option value="Plaga"       <?= $filtre_tipus === 'Plaga'       ? 'selected' : '' ?>>Plaga</option>
            <option value="Malaltia"    <?= $filtre_tipus === 'Malaltia'    ? 'selected' : '' ?>>Malaltia</option>
            <option value="Deficiencia" <?= $filtre_tipus === 'Deficiencia' ? 'selected' : '' ?>>Deficiència</option>
            <option value="Mala Herba"  <?= $filtre_tipus === 'Mala Herba'  ? 'selected' : '' ?>>Mala Herba</option>
        </select>

        <label class="checkbox-inline">
            <input type="checkbox"
                   name="nomes_alertes"
                   value="1"
                   <?= $filtre_alerta ? 'checked' : '' ?>>
            Només alertes
        </label>

        <button type="submit" class="boto-secundari boto-secundari--petit">
            <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
        </button>

        <?php if ($filtre_sector || $filtre_tipus || $filtre_alerta): ?>
            <a href="<?= BASE_URL ?>modules/monitoratge/historial_monitoratge.php"
               class="boto-secundari boto-secundari--petit">
                <i class="fas fa-xmark" aria-hidden="true"></i> Netejar
            </a>
        <?php endif; ?>
    </form>

    <!-- Cercador en temps real -->
    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-monitoratge"
               placeholder="Cerca per sector o descripció..."
               class="input-cerca"
               aria-label="Cercar observacions">
    </div>

    <!-- Avís alertes actives -->
    <?php
    $total_alertes = count(array_filter(
        $observacions,
        fn($o) => $o['llindar_intervencio_assolit']
    ));
    ?>
    <?php if ($total_alertes > 0): ?>
        <div class="flash flash--warning" role="alert">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            Hi ha <strong><?= $total_alertes ?></strong>
            observaci<?= $total_alertes === 1 ? 'ó' : 'ons' ?>
            amb llindar d'intervenció assolit.
            <a href="?nomes_alertes=1">Veure-les totes →</a>
        </div>
    <?php endif; ?>

    <table class="taula-simple" id="taula-monitoratge">
        <thead>
            <tr>
                <th>Data i hora</th>
                <th>Sector</th>
                <th>Tipus</th>
                <th>Nivell / %</th>
                <th>Descripció</th>
                <th>Llindar</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($observacions)): ?>
                <tr>
                    <td colspan="6" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha observacions registrades.
                        <a href="<?= BASE_URL ?>modules/monitoratge/monitoratge.php">
                            Registra'n una.
                        </a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($observacions as $obs):
                    $classe_badge = $classes_tipus[$obs['tipus_problema']] ?? 'badge--gris';
                ?>
                    <tr>
                        <td><?= format_datetime($obs['data_observacio']) ?></td>
                        <td data-cerca><strong><?= e($obs['nom_sector']) ?></strong></td>
                        <td>
                            <span class="badge <?= $classe_badge ?>">
                                <?= e($obs['tipus_problema']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $obs['nivell_poblacio'] !== null
                                ? number_format((float)$obs['nivell_poblacio'], 2, ',', '.') . '%'
                                : '—' ?>
                        </td>
                        <td data-cerca class="cel-text-llarg">
                            <?= e($obs['descripcio_breu'] ?? '—') ?>
                        </td>
                        <td>
                            <?php if ($obs['llindar_intervencio_assolit']): ?>
                                <span class="badge badge--vermell">
                                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                                    Tractar
                                </span>
                            <?php else: ?>
                                <span class="badge badge--verd">
                                    <i class="fas fa-check" aria-hidden="true"></i>
                                    Sota control
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>