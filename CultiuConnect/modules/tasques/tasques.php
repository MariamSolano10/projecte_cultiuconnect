<?php
/**
 * modules/tasques/tasques.php — Llistat i gestió de tasques agrícoles.
 *
 * Mostra totes les tasques amb filtres per estat i tipus,
 * i permet accedir al detall, editar i crear-ne de noves.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Gestió de Tasques';
$pagina_activa = 'tasques';

$tasques   = [];
$error_db  = null;
$sectors   = [];

$filtre_estat = in_array($_GET['estat'] ?? '', ['pendent', 'en_proces', 'completada', 'cancel·lada', 'tots'])
    ? $_GET['estat']
    : 'pendent';

$filtre_tipus = $_GET['tipus'] ?? 'tots';

try {
    $pdo = connectDB();

    // Carreguem sectors per al filtre
    $sectors = $pdo->query("SELECT id_sector, nom FROM sector ORDER BY nom")->fetchAll();

    // Construïm el WHERE
    $condicions = [];
    $params     = [];

    if ($filtre_estat !== 'tots') {
        $condicions[] = 't.estat = :estat';
        $params[':estat'] = $filtre_estat;
    }

    $tipus_valids = ['poda','aclarida','tractament','collita','fertilitzacio','reg','manteniment','altres'];
    if ($filtre_tipus !== 'tots' && in_array($filtre_tipus, $tipus_valids)) {
        $condicions[] = 't.tipus = :tipus';
        $params[':tipus'] = $filtre_tipus;
    }

    $where = $condicions ? 'WHERE ' . implode(' AND ', $condicions) : '';

    $sql = "
        SELECT
            t.id_tasca,
            t.tipus,
            t.descripcio,
            t.data_inici_prevista,
            t.data_fi_prevista,
            t.duracio_estimada_h,
            t.num_treballadors_necessaris,
            t.estat,
            s.nom AS nom_sector,
            COUNT(at.id_treballador) AS treballadors_assignats
        FROM tasca t
        LEFT JOIN sector s          ON s.id_sector = t.id_sector
        LEFT JOIN assignacio_tasca at ON at.id_tasca = t.id_tasca
        $where
        GROUP BY t.id_tasca
        ORDER BY
            FIELD(t.estat, 'en_proces', 'pendent', 'completada', 'cancel·lada'),
            t.data_inici_prevista ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasques = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] tasques.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les tasques.';
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

function iconaTipus(string $tipus): string
{
    return match ($tipus) {
        'poda'          => 'fa-scissors',
        'aclarida'      => 'fa-leaf',
        'tractament'    => 'fa-spray-can-sparkles',
        'collita'       => 'fa-apple-whole',
        'fertilitzacio' => 'fa-flask',
        'reg'           => 'fa-droplet',
        'manteniment'   => 'fa-wrench',
        default         => 'fa-list-check',
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-tasques">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-list-check" aria-hidden="true"></i>
            Gestió de Tasques
        </h1>
        <p class="descripcio-seccio">
            Planificació i seguiment de les tasques agrícoles de l'explotació.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Toolbar Integrada (Accions + Filtres + Cercador) -->
    <div class="feed-header feed-header--stacked">
        
        <!-- Fila Superior: Botó + Cerca -->
        <div class="flex-between-center-wrap">
            <a href="<?= BASE_URL ?>modules/tasques/nova_tasca.php" class="boto-principal">
                <i class="fas fa-plus"></i> Registrar Nova Tasca
            </a>
            
            <div class="toolbar-search--relative input-with-icon input-with-icon--compact">
                <i class="fas fa-search input-icon"></i>
                <input type="search"
                       data-filtre-taula="taula-tasques"
                       placeholder="Cerca per descripció o sector..."
                       class="form-input"
                       aria-label="Cercar tasques">
            </div>
        </div>
        
        <hr class="separator-soft">

        <!-- Fila Inferior: Filtres Dinàmics -->
        <div class="stack-filtres">
            
            <!-- Filtre Estat -->
            <div class="fila-filtres">
                <span class="badge badge--gris badge-etiqueta-fixa">Estat</span>
                <div class="filtres-container filtres-container--inline">
                    <?php
                    $estats = [
                        'pendent'     => 'Pendents',
                        'en_proces'   => 'En procés',
                        'completada'  => 'Completades',
                        'cancel·lada' => 'Cancel·lades',
                        'tots'        => 'Totes',
                    ];
                    foreach ($estats as $val => $lbl): ?>
                        <a href="?estat=<?= urlencode($val) ?>&tipus=<?= urlencode($filtre_tipus) ?>"
                           class="filtre-boto filtre-boto--m <?= $filtre_estat === $val ? 'filtre-boto--actiu' : '' ?>">
                            <?= $lbl ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Filtre Tipus -->
            <div class="fila-filtres">
                <span class="badge badge--gris badge-etiqueta-fixa">Tipus</span>
                <div class="filtres-container filtres-container--inline">
                    <?php
                    $tipus_opcions = [
                        'tots'          => 'Tots',
                        'poda'          => 'Poda',
                        'aclarida'      => 'Aclarida',
                        'tractament'    => 'Tractament',
                        'collita'       => 'Collita',
                        'fertilitzacio' => 'Fertilització',
                        'reg'           => 'Reg',
                        'manteniment'   => 'Manteniment',
                        'altres'        => 'Altres',
                    ];
                    foreach ($tipus_opcions as $val => $lbl): ?>
                        <a href="?estat=<?= urlencode($filtre_estat) ?>&tipus=<?= urlencode($val) ?>"
                           class="filtre-boto filtre-boto--s <?= $filtre_tipus === $val ? 'filtre-boto--actiu' : '' ?>">
                            <?= $lbl ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

    <table class="taula-simple" id="taula-tasques">
        <thead>
            <tr>
                <th>Tipus</th>
                <th>Descripció</th>
                <th>Sector</th>
                <th>Inici previst</th>
                <th>Fi prevista</th>
                <th>Durada (h)</th>
                <th>Treballadors</th>
                <th>Estat</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tasques)): ?>
                <tr>
                    <td colspan="9" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha tasques <?= $filtre_estat !== 'tots' ? "en aquesta categoria" : '' ?>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tasques as $t): ?>
                    <tr>
                        <td>
                            <span title="<?= e(ucfirst($t['tipus'])) ?>">
                                <i class="fas <?= iconaTipus($t['tipus']) ?>" aria-hidden="true"></i>
                                <?= e(ucfirst($t['tipus'])) ?>
                            </span>
                        </td>
                        <td data-cerca>
                            <?= e($t['descripcio'] ?? '—') ?>
                        </td>
                        <td data-cerca>
                            <?= $t['nom_sector'] ? e($t['nom_sector']) : '<span class="text-suau">—</span>' ?>
                        </td>
                        <td><?= format_data($t['data_inici_prevista'], curta: true) ?></td>
                        <td><?= $t['data_fi_prevista'] ? format_data($t['data_fi_prevista'], curta: true) : '—' ?></td>
                        <td><?= $t['duracio_estimada_h'] ? number_format((float)$t['duracio_estimada_h'], 1) . ' h' : '—' ?></td>
                        <td>
                            <span title="<?= (int)$t['treballadors_assignats'] ?> assignats de <?= (int)($t['num_treballadors_necessaris'] ?? 0) ?> necessaris">
                                <i class="fas fa-users" aria-hidden="true"></i>
                                <?= (int)$t['treballadors_assignats'] ?> / <?= (int)($t['num_treballadors_necessaris'] ?? '?') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= classeEstatTasca($t['estat']) ?>">
                                <?= e(ucfirst(str_replace('·', '·', $t['estat']))) ?>
                            </span>
                        </td>
                        <td class="cel-accions">
                            <a href="<?= BASE_URL ?>modules/tasques/detall_tasca.php?id=<?= (int)$t['id_tasca'] ?>"
                               title="Veure detall i assignar treballadors"
                               class="btn-accio btn-accio--veure">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </a>
                            <a href="<?= BASE_URL ?>modules/tasques/nova_tasca.php?editar=<?= (int)$t['id_tasca'] ?>"
                               title="Editar tasca"
                               class="btn-accio btn-accio--editar">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
