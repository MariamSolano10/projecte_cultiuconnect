<?php
/**
 * modules/varietats/varietats.php — Catàleg de varietats de cultiu.
 *
 * Mostra totes les varietats registrades amb espècie, característiques
 * agronòmiques, cicle vegetatiu i sensibilitats clau.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$varietats = [];
$error_db = null;

try {
    $pdo = connectDB();

    $varietats = $pdo->query("
        SELECT
            v.id_varietat,
            v.nom_varietat,
            v.caracteristiques_agronomiques,
            v.cicle_vegetatiu,
            v.requisits_pollinitzacio,
            v.productivitat_mitjana_esperada,
            v.qualitats_comercials,
            e.nom_comu      AS especie,
            e.nom_cientific AS especie_cientific,
            -- Recompte de plantacions actives amb aquesta varietat
            (SELECT COUNT(*)
             FROM plantacio pl
             WHERE pl.id_varietat    = v.id_varietat
               AND pl.data_arrencada IS NULL) AS plantacions_actives
        FROM varietat v
        JOIN especie e ON e.id_especie = v.id_especie
        ORDER BY e.nom_comu ASC, v.nom_varietat ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] varietats.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les varietats.';
}

/**
 * Detecta si les qualitats/sensibilitats inclouen problemes crítics.
 */
function classeSensibilitat(?string $text): string
{
    if (empty($text))
        return '';
    $lower = strtolower($text);
    if (str_contains($lower, 'foc bacterià') || str_contains($lower, 'crític')) {
        return 'text-alert';
    }
    return '';
}

$titol_pagina = 'Catàleg de Varietats';
$pagina_activa = 'varietats';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-varietats">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-seedling" aria-hidden="true"></i>
            Catàleg de Varietats de Cultiu
        </h1>
        <p class="descripcio-seccio">
            Informació agronòmica de les varietats actives a l'explotació:
            fenologia, pol·linització i sensibilitats fitosanitàries clau.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/varietats/nova_varietat.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Nova Varietat
        </a>
    </div>

    <!-- Cercador -->
    <div class="cerca-container">
        <input type="search" data-filtre-taula="taula-varietats" placeholder="Cerca per nom, espècie o sensibilitat..."
            class="input-cerca" aria-label="Cercar varietats">
    </div>

    <table class="taula-simple" id="taula-varietats">
        <thead>
            <tr>
                <th>ID</th>
                <th>Varietat</th>
                <th>Espècie</th>
                <th>Característiques agronòmiques</th>
                <th>Cicle vegetatiu</th>
                <th>Pol·linització</th>
                <th>Prod. esperada</th>
                <th>Sensibilitats</th>
                <th>Activa</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($varietats)): ?>
                <tr>
                    <td colspan="10" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        El catàleg de varietats és buit.
                        <a href="<?= BASE_URL ?>modules/varietats/nova_varietat.php">Afegeix-ne una.</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($varietats as $v):
                    $classe_sens = classeSensibilitat($v['qualitats_comercials']);
                    ?>
                    <tr>
                        <td><?= (int) $v['id_varietat'] ?></td>
                        <td data-cerca><strong><?= e($v['nom_varietat']) ?></strong></td>
                        <td data-cerca>
                            <?= e($v['especie']) ?>
                            <?php if ($v['especie_cientific']): ?>
                                <br><em class="text-suau"><?= e($v['especie_cientific']) ?></em>
                            <?php endif; ?>
                        </td>
                        <td class="cel-text-llarg">
                            <?= $v['caracteristiques_agronomiques']
                                ? e($v['caracteristiques_agronomiques'])
                                : '<em class="text-suau">—</em>'
                                ?>
                        </td>
                        <td><?= $v['cicle_vegetatiu'] ? e($v['cicle_vegetatiu']) : '—' ?></td>
                        <td><?= $v['requisits_pollinitzacio'] ? e($v['requisits_pollinitzacio']) : '—' ?></td>
                        <td>
                            <?= $v['productivitat_mitjana_esperada'] !== null
                                ? format_kg((float) $v['productivitat_mitjana_esperada'], 0) . '/ha'
                                : '—'
                                ?>
                        </td>
                        <td data-cerca>
                            <?php if ($v['qualitats_comercials']): ?>
                                <span class="<?= $classe_sens ?>">
                                    <?= e($v['qualitats_comercials']) ?>
                                </span>
                            <?php else: ?>
                                <em class="text-suau">—</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $v['plantacions_actives'] > 0): ?>
                                <span class="badge badge--verd">
                                    <?= (int) $v['plantacions_actives'] ?> sector<?= (int) $v['plantacions_actives'] > 1 ? 's' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge--gris">Sense plantar</span>
                            <?php endif; ?>
                        </td>
                        <td class="cel-accions">
                            <a href="<?= BASE_URL ?>modules/varietats/detall_varietat.php?id=<?= (int) $v['id_varietat'] ?>"
                               title="Veure detall de la varietat" class="btn-accio btn-accio--veure">
                                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                <span class="sr-only">Veure detall</span>
                            </a>
                            <a href="<?= BASE_URL ?>modules/varietats/nova_varietat.php?editar=<?= (int) $v['id_varietat'] ?>"
                                title="Editar varietat" class="btn-accio btn-accio--editar">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                                <span class="sr-only">Editar</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="nota-peu">
        * La gestió per varietats permet ajustar els calendaris de tractament al cicle vital de cada fruit.
    </p>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>