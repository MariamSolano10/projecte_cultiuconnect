<?php
/**
 * modules/varietats/detall_varietat.php
 *
 * Vista detallada d'una varietat de cultiu.
 * Mostra tota la informació agronòmica, fotos i especificitats.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_varietat = sanitize_int($_GET['id'] ?? null);

if (!$id_varietat) {
    set_flash('error', 'ID de varietat invàlid.');
    header('Location: ' . BASE_URL . 'modules/varietats/varietats.php');
    exit;
}

$titol_pagina  = 'Detall de Varietat';
$pagina_activa = 'varietats';

$varietat   = null;
$fotos      = [];
$especie    = null;
$error_db   = null;

try {
    $pdo = connectDB();

    // Dades de la varietat
    $stmt = $pdo->prepare("
        SELECT v.*, e.nom_cientific, e.nom_comu AS nom_especie
        FROM varietat v
        JOIN especie e ON e.id_especie = v.id_especie
        WHERE v.id_varietat = ?
    ");
    $stmt->execute([$id_varietat]);
    $varietat = $stmt->fetch();

    if (!$varietat) {
        set_flash('error', 'La varietat no existeix.');
        header('Location: ' . BASE_URL . 'modules/varietats/varietats.php');
        exit;
    }

    // Fotos de la varietat
    $stmt = $pdo->prepare("
        SELECT * FROM foto_varietat 
        WHERE id_varietat = ? 
        ORDER BY tipus ASC, id_foto ASC
    ");
    $stmt->execute([$id_varietat]);
    $fotos = $stmt->fetchAll();

    // Estadístics addicionals
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_plantacions,
               SUM(superficie_m2) AS superficie_total
        FROM plantacio p
        JOIN parcela_sector ps ON ps.id_parcela_sector = p.id_parcela_sector
        WHERE p.id_varietat = ?
    ");
    $stmt->execute([$id_varietat]);
    $estadistiques = $stmt->fetch();

} catch (Exception $e) {
    error_log('[CultiuConnect] detall_varietat.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades de la varietat.';
}

function classeTipusFoto($tipus): array
{
    return match($tipus) {
        'arbre' => ['badge--verd', 'fa-tree', 'Arbre'],
        'flor' => ['badge--rosa', 'fa-spa', 'Flor'],
        'fruit' => ['badge--taronja', 'fa-apple-alt', 'Fruit'],
        default => ['badge--gris', 'fa-image', 'General']
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-detall-varietat">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-leaf" aria-hidden="true"></i>
            Detall de Varietat
        </h1>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/varietats/varietats.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
            </a>
            <a href="<?= BASE_URL ?>modules/varietats/nova_varietat.php?editar=<?= (int)$id_varietat ?>" class="boto-principal">
                <i class="fas fa-pen" aria-hidden="true"></i> Editar
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <?php if ($varietat): ?>
        <!-- Capçalera principal -->
        <div class="varietat-cap">
            <div class="varietat-info">
                <h2 class="varietat-nom">
                    <?= e($varietat['nom_varietat']) ?>
                </h2>
                <div class="varietat-especie">
                    <i class="fas fa-dna" aria-hidden="true"></i>
                    <?= e($varietat['nom_especie']) ?>
                    <span class="text-suau">(<?= e($varietat['nom_cientific']) ?>)</span>
                </div>
            </div>
            <div class="varietat-estadistiques">
                <div class="estadistica">
                    <span class="estadistica-valor"><?= (int)$estadistiques['total_plantacions'] ?></span>
                    <span class="estadistica-etiqueta">Plantacions</span>
                </div>
                <div class="estadistica">
                    <span class="estadistica-valor"><?= number_format((float)($estadistiques['superficie_total'] ?? 0), 1, ',', '.') ?> m²</span>
                    <span class="estadistica-etiqueta">Superfície</span>
                </div>
                <div class="estadistica">
                    <span class="estadistica-valor"><?= number_format((float)$varietat['productivitat_mitjana_esperada'], 1, ',', '.') ?></span>
                    <span class="estadistica-etiqueta">kg/ha</span>
                </div>
            </div>
        </div>

        <!-- Galería de fotos -->
        <?php if (!empty($fotos)): ?>
            <div class="detall-bloc">
                <h3 class="detall-bloc__titol">
                    <i class="fas fa-images" aria-hidden="true"></i>
                    Galería de Fotos
                </h3>
                <div class="fotos-grid">
                    <?php foreach ($fotos as $foto): 
                        [$classe_tipus, $icona_tipus, $text_tipus] = classeTipusFoto($foto['tipus']);
                    ?>
                        <div class="foto-item">
                            <div class="foto-container">
                                <img src="<?= e($foto['url_foto']) ?>" 
                                     alt="<?= e($foto['descripcio'] ?? 'Foto ' . $text_tipus) ?>"
                                     class="foto-img"
                                     loading="lazy">
                                <div class="foto-overlay">
                                    <span class="badge <?= $classe_tipus ?>">
                                        <i class="fas <?= $icona_tipus ?>"></i>
                                        <?= $text_tipus ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($foto['descripcio']): ?>
                                <p class="foto-descripcio"><?= e($foto['descripcio']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Característiques agronòmiques -->
        <div class="detall-grid">
            <div class="detall-bloc">
                <h3 class="detall-bloc__titol">
                    <i class="fas fa-seedling" aria-hidden="true"></i>
                    Característiques Agronòmiques
                </h3>
                <div class="detall-contingut">
                    <?php if ($varietat['caracteristiques_agronomiques']): ?>
                        <div class="detall-camp">
                            <h4>Característiques generals</h4>
                            <p><?= nl2br(e($varietat['caracteristiques_agronomiques'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="detall-camp">
                        <h4>Productivitat mitjana</h4>
                        <p class="valor-destacat">
                            <?= number_format((float)$varietat['productivitat_mitjana_esperada'], 1, ',', '.') ?> kg/ha
                        </p>
                    </div>
                </div>
            </div>

            <div class="detall-bloc">
                <h3 class="detall-bloc__titol">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    Cicle Vegetatiu
                </h3>
                <div class="detall-contingut">
                    <?php if ($varietat['cicle_vegetatiu']): ?>
                        <p><?= nl2br(e($varietat['cicle_vegetatiu'])) ?></p>
                    <?php else: ?>
                        <p class="text-suau">No hi ha informació del cicle vegetatiu.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="detall-grid">
            <div class="detall-bloc">
                <h3 class="detall-bloc__titol">
                    <i class="fas fa-heart" aria-hidden="true"></i>
                    Requisits de Pol·linització
                </h3>
                <div class="detall-contingut">
                    <?php if ($varietat['requisits_pollinitzacio']): ?>
                        <p><?= nl2br(e($varietat['requisits_pollinitzacio'])) ?></p>
                    <?php else: ?>
                        <p class="text-suau">No hi ha informació sobre requisits de pol·linització.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detall-bloc">
                <h3 class="detall-bloc__titol">
                    <i class="fas fa-star" aria-hidden="true"></i>
                    Qualitats Comercials
                </h3>
                <div class="detall-contingut">
                    <?php if ($varietat['qualitats_comercials']): ?>
                        <p><?= nl2br(e($varietat['qualitats_comercials'])) ?></p>
                    <?php else: ?>
                        <p class="text-suau">No hi ha informació sobre qualitats comercials.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Plantacions actives -->
        <?php if ((int)$estadistiques['total_plantacions'] > 0): ?>
            <div class="detall-bloc">
                <h3 class="detall-bloc__titol">
                    <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
                    Plantacions Actives
                </h3>
                <div class="taula-container">
                    <table class="taula-simple">
                        <thead>
                            <tr>
                                <th>Sector</th>
                                <th>Data Plantació</th>
                                <th>Superfície</th>
                                <th>Estat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT p.data_plantacio, ps.superficie_m2, s.nom AS nom_sector, p.estat
                                FROM plantacio p
                                JOIN parcela_sector ps ON ps.id_parcela_sector = p.id_parcela_sector
                                JOIN sector s ON s.id_sector = ps.id_sector
                                WHERE p.id_varietat = ?
                                ORDER BY p.data_plantacio DESC
                                LIMIT 10
                            ");
                            $stmt->execute([$id_varietat]);
                            $plantacions = $stmt->fetchAll();
                            
                            foreach ($plantacions as $plantacio):
                            ?>
                                <tr>
                                    <td><?= e($plantacio['nom_sector']) ?></td>
                                    <td><?= format_data($plantacio['data_plantacio']) ?></td>
                                    <td><?= number_format((float)$plantacio['superficie_m2'], 1, ',', '.') ?> m²</td>
                                    <td>
                                        <span class="badge badge--blau">
                                            <?= e($plantacio['estat'] ?? 'Activa') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
