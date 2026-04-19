<?php
/**
 * modules/admin/logs_accions.php
 *
 * Vista d'administració de logs d'accions del sistema.
 * Només accessible per a administradors per auditar activitats.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// Protecció: només administradors
$usuari = $_SESSION['usuari'] ?? null;
if (!$usuari || $usuari['rol'] !== 'admin') {
    set_flash('error', 'No tens permisos per accedir a aquesta pàgina.');
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$titol_pagina  = 'Logs d\'Accions del Sistema';
$pagina_activa = 'logs';

$logs     = [];
$error_db = null;
$filters  = [
    'data_inici' => sanitize($_GET['data_inici'] ?? date('Y-m-d', strtotime('-7 days'))),
    'data_fi'    => sanitize($_GET['data_fi'] ?? date('Y-m-d')),
    'id_treballador' => sanitize_int($_GET['id_treballador'] ?? null),
    'taula'      => sanitize($_GET['taula'] ?? null),
    'cerca'      => sanitize($_GET['cerca'] ?? '')
];

try {
    $pdo = connectDB();

    // Carregar treballadors per filtre
    $treballadors = $pdo->query("
        SELECT t.id_treballador, t.nom, t.cognoms, u.nom_usuari
        FROM treballador t
        LEFT JOIN usuari u ON u.id_usuari = t.id_usuari
        ORDER BY t.nom ASC
    ")->fetchAll();

    // Carregar taules afectades úniques
    $taules = $pdo->query("
        SELECT DISTINCT taula_afectada 
        FROM log_accions 
        WHERE taula_afectada IS NOT NULL 
        ORDER BY taula_afectada ASC
    ")->fetchAll();

    // Consulta principal de logs
    $sql = "
        SELECT 
            la.id_log,
            la.data_hora,
            la.accio,
            la.taula_afectada,
            la.id_registre_afectat,
            la.comentaris,
            t.nom AS treballador_nom,
            t.cognoms AS treballador_cognoms,
            u.nom_usuari
        FROM log_accions la
        LEFT JOIN treballador t ON t.id_treballador = la.id_treballador
        LEFT JOIN usuari u ON u.id_usuari = t.id_usuari
        WHERE DATE(la.data_hora) BETWEEN :data_inici AND :data_fi
    ";
    
    $params = [
        ':data_inici' => $filters['data_inici'],
        ':data_fi' => $filters['data_fi']
    ];

    if ($filters['id_treballador']) {
        $sql .= " AND la.id_treballador = :id_treballador";
        $params[':id_treballador'] = $filters['id_treballador'];
    }

    if ($filters['taula']) {
        $sql .= " AND la.taula_afectada = :taula";
        $params[':taula'] = $filters['taula'];
    }

    if ($filters['cerca']) {
        $sql .= " AND (la.accio LIKE :cerca OR la.comentaris LIKE :cerca)";
        $params[':cerca'] = '%' . $filters['cerca'] . '%';
    }

    $sql .= " ORDER BY la.data_hora DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] logs_accions.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els logs d\'accions.';
}

function classeTaula($taula): array
{
    return match($taula) {
        'usuari' => ['badge--blau', 'fa-user'],
        'aplicacio' => ['badge--vermell', 'fa-spray-can'],
        'moviment_estoc' => ['badge--verd', 'fa-boxes-stacked'],
        'proveidor' => ['badge--taronja', 'fa-truck-field'],
        default => ['badge--gris', 'fa-database']
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-logs">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-history" aria-hidden="true"></i>
            Logs d'Accions del Sistema
        </h1>
        <p class="descripcio-seccio">
            Historial d'accions realitzades pels usuaris al sistema.
            Permet auditar i traçar totes les operacions importals.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <form method="GET" class="formulari-filtres">
        <div class="form-fila-3">
            <div class="form-grup">
                <label for="data_inici" class="form-label">Data inici</label>
                <input type="date" id="data_inici" name="data_inici" class="form-input" 
                       value="<?= e($filters['data_inici']) ?>">
            </div>
            <div class="form-grup">
                <label for="data_fi" class="form-label">Data fi</label>
                <input type="date" id="data_fi" name="data_fi" class="form-input" 
                       value="<?= e($filters['data_fi']) ?>">
            </div>
            <div class="form-grup">
                <label for="id_treballador" class="form-label">Treballador</label>
                <select id="id_treballador" name="id_treballador" class="form-select">
                    <option value="">Tots els treballadors</option>
                    <?php foreach ($treballadors as $t): ?>
                        <option value="<?= (int)$t['id_treballador'] ?>" 
                                <?= $filters['id_treballador'] == $t['id_treballador'] ? 'selected' : '' ?>>
                            <?= e($t['nom'] . ' ' . $t['cognoms']) ?>
                            (<?= e($t['nom_usuari']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-fila-3">
            <div class="form-grup">
                <label for="taula" class="form-label">Taula afectada</label>
                <select id="taula" name="taula" class="form-select">
                    <option value="">Totes les taules</option>
                    <?php foreach ($taules as $t): ?>
                        <option value="<?= e($t['taula_afectada']) ?>" 
                                <?= $filters['taula'] === $t['taula_afectada'] ? 'selected' : '' ?>>
                            <?= e($t['taula_afectada']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup">
                <label for="cerca" class="form-label">Cerca a acció/comentaris</label>
                <input type="text" id="cerca" name="cerca" class="form-input" 
                       value="<?= e($filters['cerca']) ?>" placeholder="Paraula clau...">
            </div>
            <div class="form-grup form-grup--accio">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
                </button>
                <a href="<?= BASE_URL ?>modules/admin/logs_accions.php" class="boto-secundari">
                    <i class="fas fa-xmark" aria-hidden="true"></i> Netjar
                </a>
            </div>
        </div>
    </form>

    <!-- Estadístiques -->
    <div class="kpi-grid kpi-grid--petit kpi-grid--spaced">
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-history"></i>
            </div>
            <span class="kpi-card__valor"><?= count($logs) ?></span>
            <span class="kpi-card__etiqueta">Total accions</span>
        </div>

        <?php 
        $accions_avi = array_filter($logs, fn($log) => strtotime($log['data_hora']) > strtotime('-24 hours'));
        ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-clock"></i>
            </div>
            <span class="kpi-card__valor"><?= count($accions_avi) ?></span>
            <span class="kpi-card__etiqueta">Últimes 24h</span>
        </div>

        <?php 
        $usuaris_unicos = array_unique(array_column($logs, 'id_treballador'));
        ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-users"></i>
            </div>
            <span class="kpi-card__valor"><?= count($usuaris_unicos) ?></span>
            <span class="kpi-card__etiqueta">Usuaris actius</span>
        </div>
    </div>

    <!-- Taula de logs -->
    <div class="taula-container">
        <table class="taula-simple taula-logs">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Usuari</th>
                    <th>Acció</th>
                    <th>Taula</th>
                    <th>ID Registre</th>
                    <th>Comentaris</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="sense-dades">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            No s'han trobat accions per als filtres seleccionats.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        [$classe_badge, $icona] = classeTaula($log['taula_afectada']);
                    ?>
                        <tr>
                            <td>
                                <span class="data-hora">
                                    <?= format_data($log['data_hora'], curta: true) ?>
                                    <br>
                                    <small class="text-suau"><?= date('H:i:s', strtotime($log['data_hora'])) ?></small>
                                </span>
                            </td>
                            <td>
                                <div class="usuari-info">
                                    <strong><?= e($log['treballador_nom'] . ' ' . $log['treballador_cognoms']) ?></strong>
                                    <br>
                                    <small class="text-suau">@<?= e($log['nom_usuari']) ?></small>
                                </div>
                            </td>
                            <td class="cel-text-llarg">
                                <?= e($log['accio']) ?>
                            </td>
                            <td>
                                <?php if ($log['taula_afectada']): ?>
                                    <span class="badge <?= $classe_badge ?>">
                                        <i class="fas <?= $icona ?>" aria-hidden="true"></i>
                                        <?= e($log['taula_afectada']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-suau">---</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-dreta">
                                <?= $log['id_registre_afectat'] ? '#' . (int)$log['id_registre_afectat'] : '---' ?>
                            </td>
                            <td class="cel-text-llarg">
                                <?= e($log['comentaris'] ?: '---') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (count($logs) >= 500): ?>
        <div class="info-box mt-l">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            <strong>Nota:</strong> Es mostren com a màxim 500 registres. Utilitza els filtres per refinar la cerca.
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
