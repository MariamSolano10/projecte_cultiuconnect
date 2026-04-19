<?php
/**
 * modules/personal/personal.php — Directori de treballadors de l'explotació.
 *
 * Mostra la plantilla activa i ex-treballadors filtrats per rol i estat.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Directori de Personal';
$pagina_activa = 'personal';

$treballadors = [];
$error_db     = null;
$missatge     = null;

// Processament d'accions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accio = sanitize($_POST['accio'] ?? '');
    
    if ($accio === 'eliminar') {
        $id_treballador = sanitize_int($_POST['id_treballador'] ?? null);
        
        if ($id_treballador) {
            try {
                $pdo = connectDB();
                
                // Verificar si té jornades associades
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM jornada WHERE id_treballador = ?");
                $stmt->execute([$id_treballador]);
                $te_jornades = (int)$stmt->fetchColumn() > 0;
                
                if ($te_jornades) {
                    $missatge = 'No es pot eliminar el treballador perquè té jornades associades.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM treballador WHERE id_treballador = ?");
                    $stmt->execute([$id_treballador]);
                    
                    if ($stmt->rowCount() > 0) {
                        set_flash('success', 'Treballador eliminat correctament.');
                    } else {
                        $missatge = 'El treballador no existeix.';
                    }
                }
                
                header('Location: ' . BASE_URL . 'modules/personal/personal.php');
                exit;
                
            } catch (Exception $e) {
                error_log('[CultiuConnect] personal.php (eliminar): ' . $e->getMessage());
                $missatge = 'Error eliminant el treballador.';
            }
        }
    }
}

// Filtre d'estat (per defecte només actius, ajustat als enums de la teva BD)
$filtre_estat = in_array($_GET['estat'] ?? '', ['actiu', 'inactiu', 'tots'])
    ? $_GET['estat']
    : 'actiu';

try {
    $pdo = connectDB();

    // Ajustem el WHERE a l'Enum de la BD (actiu, inactiu, baix, vacances)
    $where = '';
    if ($filtre_estat === 'actiu') {
        $where = "WHERE t.estat = 'actiu'";
    } elseif ($filtre_estat === 'inactiu') {
        $where = "WHERE t.estat IN ('inactiu', 'baix', 'baixa')"; // Cobrim possibles variacions
    }

    // CORRECCIÓ: Canviem registre_hores per collita_treballador
    $sql = "
        SELECT
            t.id_treballador,
            t.nom,
            t.cognoms,
            t.dni,
            t.rol,
            t.telefon,
            t.email,
            t.data_alta,
            t.data_baixa,
            t.tipus_contracte,
            t.estat,
            (SELECT COALESCE(SUM(ct.hores_treballades), 0)
             FROM collita_treballador ct
             JOIN collita c ON c.id_collita = ct.id_collita
             WHERE ct.id_treballador = t.id_treballador
               AND MONTH(c.data_inici) = MONTH(CURDATE())
               AND YEAR(c.data_inici)  = YEAR(CURDATE())
            ) AS hores_mes
        FROM treballador t
        $where
        ORDER BY t.estat ASC, t.cognoms ASC, t.nom ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $treballadors = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] personal.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els treballadors ara mateix.';
}

function classeEstat(string $estat): string
{
    return match (strtolower($estat)) {
        'actiu' => 'badge--verd',
        'baix', 'baixa', 'inactiu' => 'badge--vermell',
        'vacances' => 'badge--blau',
        default => 'badge--gris',
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-personal">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-users" aria-hidden="true"></i>
            Directori de Personal
        </h1>
        <p class="descripcio-seccio">
            Gestió de la plantilla de l'explotació: operaris, supervisors i tècnics agrícoles.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/personal/nou_treballador.php" class="boto-principal">
            <i class="fas fa-user-plus" aria-hidden="true"></i> Nou Treballador
        </a>
            <a href="<?= BASE_URL ?>modules/personal/rendiment_global.php" class="boto-principal btn-principal--secundari">
            <i class="fas fa-chart-bar" aria-hidden="true"></i> Rànquings i Rendiment
        </a>
        <a href="<?= BASE_URL ?>modules/personal/permisos.php" class="boto-secundari">
            <i class="fas fa-calendar-xmark" aria-hidden="true"></i> Permisos i absències
        </a>
    </div>

    <div class="filtres-container">
        <?php foreach (['actiu' => 'Actius', 'inactiu' => 'Inactius/Baixes', 'tots' => 'Tots'] as $val => $lbl): ?>
            <a href="?estat=<?= $val ?>"
               class="filtre-boto <?= $filtre_estat === $val ? 'filtre-boto--actiu' : '' ?>">
                <?= $lbl ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-personal"
               placeholder="Cerca per nom, DNI o rol..."
               class="input-cerca"
               aria-label="Cercar treballadors">
    </div>

    <table class="taula-simple" id="taula-personal">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom i cognoms</th>
                <th>DNI / NIE</th>
                <th>Rol</th>
                <th>Contracte</th>
                <th>Alta</th>
                <th>Telèfon</th>
                <th>Hores mes</th>
                <th>Estat</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($treballadors)): ?>
                <tr>
                    <td colspan="10" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha treballadors <?= $filtre_estat !== 'tots' ? "en aquesta categoria" : '' ?>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($treballadors as $t): ?>
                    <tr>
                        <td><?= (int)$t['id_treballador'] ?></td>
                        <td data-cerca>
                            <strong><?= e($t['nom']) ?> <?= e($t['cognoms'] ?? '') ?></strong>
                            <?php if ($t['email']): ?>
                                <br><a href="mailto:<?= e($t['email']) ?>" class="text-suau">
                                    <?= e($t['email']) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td data-cerca><?= e($t['dni']) ?></td>
                        <td data-cerca><?= e($t['rol']) ?></td>
                        <td><?= e($t['tipus_contracte']) ?></td>
                        <td><?= format_data($t['data_alta'], curta: true) ?></td>
                        <td>
                            <?php if ($t['telefon']): ?>
                                <a href="tel:<?= e($t['telefon']) ?>"><?= e($t['telefon']) ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= format_hores((float)$t['hores_mes']) ?></td>
                        <td>
                            <span class="badge <?= classeEstat($t['estat']) ?>">
                                <?= e(ucfirst($t['estat'])) ?>
                            </span>
                        </td>
                        <td class="cel-accions">
                            <a href="<?= BASE_URL ?>modules/personal/detall_treballador.php?id=<?= (int)$t['id_treballador'] ?>"
                               title="Veure Fitxa i Rendiment"
                               class="btn-accio btn-accio--secundari">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                                <span class="sr-only">Fitxa</span>
                            </a>
                            <a href="<?= BASE_URL ?>modules/personal/nou_treballador.php?editar=<?= (int)$t['id_treballador'] ?>"
                               title="Editar treballador"
                               class="btn-accio btn-accio--editar">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                                <span class="sr-only">Editar</span>
                            </a>
                            <a href="<?= BASE_URL ?>modules/personal/personal.php?accio=eliminar&id=<?= (int)$t['id_treballador'] ?>"
                               title="Eliminar treballador"
                               class="btn-accio btn-accio--eliminar"
                               onclick="return confirm('Estàs segur que vols eliminar aquest treballador?')">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                                <span class="sr-only">Eliminar</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
