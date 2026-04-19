<?php
/**
 * modules/sectors/sectors.php — Llistat de sectors i plantacions actives.
 *
 * Mostra per cada sector: parcel·la física, varietat, superfície,
 * any de plantació, marc, fenologia actual i estat sanitari.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$llistat_sectors = [];
$error_db        = null;

try {
    $pdo = connectDB();

    $sql = "
        SELECT
            s.id_sector,
            s.nom                                               AS nom_intern,

            -- Parcel·la física associada (primera trobada)
            (SELECT p.nom
             FROM parcela p
             JOIN parcela_sector ps ON p.id_parcela = ps.id_parcela
             WHERE ps.id_sector = s.id_sector
             ORDER BY p.nom ASC
             LIMIT 1)                                           AS parcela_fisica,

            -- Varietat i espècie de la plantació activa
            v.nom_varietat                                      AS varietat,
            e.nom_comu                                          AS especie,

            -- Superfície total sumada de totes les parcel·les del sector
            (SELECT COALESCE(SUM(p2.superficie_ha), 0)
             FROM parcela p2
             JOIN parcela_sector ps2 ON p2.id_parcela = ps2.id_parcela
             WHERE ps2.id_sector = s.id_sector)                 AS superficie_ha,

            -- Any de plantació
            YEAR(pl.data_plantacio)                             AS any_plantacio,

            -- Marc de plantació (fila x arbre)
            CASE
                WHEN pl.marc_fila IS NOT NULL AND pl.marc_arbre IS NOT NULL
                THEN CONCAT(pl.marc_fila, ' x ', pl.marc_arbre, ' m')
                ELSE NULL
            END                                                 AS marc_plantacio,

            -- Estat fenològic de l'any actual
            (SELECT sa.estat_fenologic
             FROM seguiment_anual sa
             WHERE sa.id_plantacio = pl.id_plantacio
               AND sa.`any` = YEAR(CURRENT_DATE())
             LIMIT 1)                                           AS fenologia_actual,

            -- Estat sanitari: alerta si hi ha monitoratge recent amb llindar assolit
            (SELECT CASE
                        WHEN mp.llindar_intervencio_assolit = 1
                        THEN CONCAT('Alerta: ', mp.descripcio_breu)
                        ELSE 'OK'
                    END
             FROM monitoratge_plaga mp
             WHERE mp.id_sector = s.id_sector
             ORDER BY mp.data_observacio DESC
             LIMIT 1)                                           AS estat_sanitari

        FROM sector s
        LEFT JOIN plantacio pl ON pl.id_sector    = s.id_sector
                               AND pl.data_arrencada IS NULL
        LEFT JOIN varietat  v  ON v.id_varietat   = pl.id_varietat
        LEFT JOIN especie   e  ON e.id_especie     = v.id_especie
        ORDER BY s.nom ASC
    ";

    $llistat_sectors = $pdo->query($sql)->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] sectors.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els sectors. Contacta amb l\'administrador.';
}


function classeEstatSanitari(?string $estat): string
{
    if (empty($estat)) return 'estat-normal';
    return str_contains(strtolower($estat), 'alerta') ? 'estat-alerta' : 'estat-ok';
}

// -----------------------------------------------------------
// Capçalera
// -----------------------------------------------------------
$titol_pagina  = 'Sectors i Plantacions';
$pagina_activa = 'sectors';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-sectors">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-seedling" aria-hidden="true"></i>
            Sectors i Plantacions
        </h1>
        <p class="descripcio-seccio">
            Detall de les unitats de cultiu: varietat, any, marc de plantació i estat sanitari.
            Totes les operacions del quadern fan referència als sectors.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Botons d'acció -->
    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/sectors/nou_sector.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Nou Sector / Plantació
        </a>
    </div>

    <!-- Cercador -->
    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-sectors"
               placeholder="Cerca per nom, parcel·la o varietat..."
               class="input-cerca"
               aria-label="Cercar sectors">
    </div>

    <!-- Taula de sectors -->
    <table class="taula-simple" id="taula-sectors">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom Sector</th>
                <th>Parcel·la</th>
                <th>Varietat / Cultiu</th>
                <th>Superfície</th>
                <th>Any Plantació</th>
                <th>Marc (m)</th>
                <th>Fenologia</th>
                <th>Estat Sanitari</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($llistat_sectors)): ?>
                <tr>
                    <td colspan="10" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha sectors definits.
                        <a href="<?= BASE_URL ?>modules/sectors/nou_sector.php">Crea'n un.</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($llistat_sectors as $s):
                    $nom_cultiu = '—';
                    if (!empty($s['varietat'])) {
                        $nom_cultiu = e($s['varietat']);
                        if (!empty($s['especie'])) {
                            $nom_cultiu .= ' <span class="text-suau">(' . e($s['especie']) . ')</span>';
                        }
                    }
                    $classe_sanitari = classeEstatSanitari($s['estat_sanitari']);
                ?>
                    <tr>
                        <td><?= (int)$s['id_sector'] ?></td>
                        <td data-cerca><strong><?= e($s['nom_intern']) ?></strong></td>
                        <td data-cerca><?= $s['parcela_fisica'] ? e($s['parcela_fisica']) : '<em class="text-suau">—</em>' ?></td>
                        <td data-cerca><?= $nom_cultiu ?></td>
                        <td><?= format_ha((float)($s['superficie_ha'] ?? 0)) ?></td>
                        <td><?= $s['any_plantacio'] ? (int)$s['any_plantacio'] : '—' ?></td>
                        <td><?= $s['marc_plantacio'] ? e($s['marc_plantacio']) : '—' ?></td>
                        <td>
                            <?php if ($s['fenologia_actual']): ?>
                                <span class="badge badge--verd"><?= e($s['fenologia_actual']) ?></span>
                            <?php else: ?>
                                <em class="text-suau">—</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $classe_sanitari ?>">
                                <?= e($s['estat_sanitari'] ?? 'Sense dades') ?>
                            </span>
                        </td>
                        <td class="cel-accions">
                            <a href="<?= BASE_URL ?>modules/sectors/detall_sector.php?id=<?= (int)$s['id_sector'] ?>"
                               title="Veure detall i tractaments"
                               class="btn-accio btn-accio--veure">
                                <i class="fas fa-file-invoice" aria-hidden="true"></i>
                            </a>
                            <a href="<?= BASE_URL ?>modules/sectors/renovar_plantacio.php?id_sector=<?= (int)$s['id_sector'] ?>"
                               title="Renovar plantació (rotació/estacionalitat)"
                               class="btn-accio btn-accio--editar">
                                <i class="fas fa-rotate" aria-hidden="true"></i>
                            </a>
                            <a href="<?= BASE_URL ?>modules/monitoratge/monitoratge.php?sector_id=<?= (int)$s['id_sector'] ?>"
                               title="Monitoratge de plagues"
                               class="btn-accio btn-accio--alerta">
                                <i class="fas fa-bug" aria-hidden="true"></i>
                            </a>
                            <a href="<?= BASE_URL ?>modules/sectors/nou_sector.php?editar=<?= (int)$s['id_sector'] ?>"
                               title="Editar sector"
                               class="btn-accio btn-accio--editar">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="nota-peu">
        * La informació del sector s'utilitza per aplicar correctament la dosi i verificar
        els màxims legals de matèria activa al quadern d'explotació.
    </p>

</div><!-- /.contingut-sectors -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
