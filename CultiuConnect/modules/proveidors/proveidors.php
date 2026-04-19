<?php
/**
 * modules/proveidors/proveidors.php
 *
 * Mòdul de gestió de proveïdors. Permet llistar, crear, editar i eliminar proveïdors
 * de productes fitosanitaris, fertilitzants, llavors, maquinària i altres.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Gestió de Proveïdors';
$pagina_activa = 'proveidors';

$proveidors = [];
$error_db   = null;
$missatge    = null;

// Processament d'accions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accio = sanitize($_POST['accio'] ?? '');
    
    if ($accio === 'eliminar') {
        $id_proveidor = sanitize_int($_POST['id_proveidor'] ?? null);
        
        if ($id_proveidor) {
            try {
                $pdo = connectDB();
                
                // Verificar si té compres associades
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM compra_producte WHERE id_proveidor = ?");
                $stmt->execute([$id_proveidor]);
                $te_compres = (int)$stmt->fetchColumn() > 0;
                
                if ($te_compres) {
                    $missatge = 'No es pot eliminar el proveïdor perquè té compres associades.';
                } else {
                    // Obtenir dades del proveïdor abans d'eliminar
                    $stmt = $pdo->prepare("SELECT nom, tipus FROM proveidor WHERE id_proveidor = ?");
                    $stmt->execute([$id_proveidor]);
                    $proveidor = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("DELETE FROM proveidor WHERE id_proveidor = ?");
                    $stmt->execute([$id_proveidor]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Registrar acció al log
                        registrar_accio(
                            'PROVEÏDOR ELIMINAT: ' . ($proveidor['nom'] ?? 'Desconegut'),
                            'proveidor',
                            $id_proveidor,
                            'Tipus: ' . ($proveidor['tipus'] ?? '---')
                        );
                        
                        set_flash('success', 'Proveïdor eliminat correctament.');
                    } else {
                        $missatge = 'El proveïdor no existeix.';
                    }
                }
                
                header('Location: ' . BASE_URL . 'modules/proveidors/proveidors.php');
                exit;
                
            } catch (Exception $e) {
                error_log('[CultiuConnect] proveidors.php (eliminar): ' . $e->getMessage());
                $missatge = 'Error eliminant el proveïdor.';
            }
        }
    }
}

try {
    $pdo = connectDB();
    
    // Carregar tots els proveïdors
    $proveidors = $pdo->query("
        SELECT id_proveidor, nom, telefon, email, adreca, tipus
        FROM proveidor
        ORDER BY nom ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] proveidors.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els proveïdors.';
}

function classeTipus($tipus): array
{
    return match($tipus) {
        'Fitosanitari' => ['badge--vermell', 'fa-spray-can'],
        'Fertilitzant' => ['badge--verd', 'fa-seedling'],
        'Llavor'       => ['badge--groc', 'fa-wheat-awn'],
        'Maquinaria'   => ['badge--blau', 'fa-tractor'],
        'Altres'       => ['badge--gris', 'fa-box'],
        default        => ['badge--gris', 'fa-box'],
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-proveidors">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-truck-field" aria-hidden="true"></i>
            Gestió de Proveïdors
        </h1>
        <p class="descripcio-seccio">
            Administra el teu catàleg de proveïdors de productes fitosanitaris, fertilitzants,
            llavors, maquinària i altres subministraments per a l'explotació.
        </p>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/proveidors/nou_proveidor.php" class="boto-principal">
                <i class="fas fa-plus" aria-hidden="true"></i> Nou Proveïdor
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <?php if ($missatge): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            <?= e($missatge) ?>
        </div>
    <?php endif; ?>

    <!-- Estadístiques ràpides -->
    <div class="kpi-grid kpi-grid--petit">
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-truck-field"></i>
            </div>
            <span class="kpi-card__valor"><?= count($proveidors) ?></span>
            <span class="kpi-card__etiqueta">Total proveïdors</span>
        </div>

        <?php 
        $tipus_counts = [];
        foreach ($proveidors as $p) {
            $tipus_counts[$p['tipus']] = ($tipus_counts[$p['tipus']] ?? 0) + 1;
        }
        ?>

        <?php if (!empty($tipus_counts['Fitosanitari'])): ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-spray-can"></i>
            </div>
            <span class="kpi-card__valor"><?= $tipus_counts['Fitosanitari'] ?></span>
            <span class="kpi-card__etiqueta">Fitosanitaris</span>
        </div>
        <?php endif; ?>

        <?php if (!empty($tipus_counts['Fertilitzant'])): ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-seedling"></i>
            </div>
            <span class="kpi-card__valor"><?= $tipus_counts['Fertilitzant'] ?></span>
            <span class="kpi-card__etiqueta">Fertilitzants</span>
        </div>
        <?php endif; ?>

        <?php if (!empty($tipus_counts['Maquinaria'])): ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-tractor"></i>
            </div>
            <span class="kpi-card__valor"><?= $tipus_counts['Maquinaria'] ?></span>
            <span class="kpi-card__etiqueta">Maquinària</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Cerca -->
    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-proveidors"
               placeholder="Cerca per nom, tipus, telèfon, email..."
               class="input-cerca"
               aria-label="Cercar proveïdors">
    </div>

    <!-- Taula de proveïdors -->
    <div class="taula-container">
        <table class="taula-simple" id="taula-proveidors">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Tipus</th>
                    <th>Telèfon</th>
                    <th>Email</th>
                    <th>Adreça</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($proveidors)): ?>
                    <tr>
                        <td colspan="6" class="sense-dades">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            No hi ha proveïdors registrats.
                            <a href="<?= BASE_URL ?>modules/proveidors/nou_proveidor.php">Afegeix-ne un.</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proveidors as $proveidor): 
                        [$classe_badge, $icona] = classeTipus($proveidor['tipus']);
                    ?>
                        <tr>
                            <td data-cerca>
                                <strong><?= e($proveidor['nom']) ?></strong>
                            </td>
                            <td data-cerca>
                                <span class="badge <?= $classe_badge ?>">
                                    <i class="fas <?= $icona ?>" aria-hidden="true"></i>
                                    <?= e($proveidor['tipus']) ?>
                                </span>
                            </td>
                            <td data-cerca>
                                <?php if ($proveidor['telefon']): ?>
                                    <a href="tel:<?= e($proveidor['telefon']) ?>" class="enllac-telefon">
                                        <i class="fas fa-phone" aria-hidden="true"></i>
                                        <?= e($proveidor['telefon']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-suau">---</span>
                                <?php endif; ?>
                            </td>
                            <td data-cerca>
                                <?php if ($proveidor['email']): ?>
                                    <a href="mailto:<?= e($proveidor['email']) ?>" class="enllac-email">
                                        <i class="fas fa-envelope" aria-hidden="true"></i>
                                        <?= e($proveidor['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-suau">---</span>
                                <?php endif; ?>
                            </td>
                            <td data-cerca class="cel-text-llarg">
                                <?= e($proveidor['adreca'] ?: '---') ?>
                            </td>
                            <td class="cel-accions">
                                <a href="<?= BASE_URL ?>modules/proveidors/veure_proveidor.php?id=<?= (int)$proveidor['id_proveidor'] ?>"
                                   title="Veure detall"
                                   aria-label="Veure detall de <?= e($proveidor['nom']) ?>"
                                   class="btn-accio btn-accio--veure">
                                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                    <span class="sr-only">Veure</span>
                                </a>
                                <a href="<?= BASE_URL ?>modules/proveidors/nou_proveidor.php?editar=<?= (int)$proveidor['id_proveidor'] ?>"
                                   title="Editar proveïdor"
                                   aria-label="Editar <?= e($proveidor['nom']) ?>"
                                   class="btn-accio btn-accio--editar">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                    <span class="sr-only">Editar</span>
                                </a>
                                <form method="POST" class="form-inline" 
                                      onsubmit="return confirm('Estàs segur que vols eliminar aquest proveïdor? Aquesta acció no es pot desfer.');">
                                    <input type="hidden" name="accio" value="eliminar">
                                    <input type="hidden" name="id_proveidor" value="<?= (int)$proveidor['id_proveidor'] ?>">
                                    <button type="submit" 
                                            title="Eliminar proveïdor"
                                            aria-label="Eliminar <?= e($proveidor['nom']) ?>"
                                            class="btn-accio btn-accio--eliminar">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                        <span class="sr-only">Eliminar</span>
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
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

