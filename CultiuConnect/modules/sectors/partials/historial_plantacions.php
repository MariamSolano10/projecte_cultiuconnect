<?php
/**
 * modules/sectors/partials/historial_plantacions.php
 *
 * Partial reutilitzable: mostra la taula d'historial de plantacions d'un sector
 * i el botó per iniciar una renovació.
 *
 * VARIABLES NECESSÀRIES (han d'existir en l'àmbit del fitxer pare):
 *   $id_sector  (int)  — ID del sector actual
 *   $pdo        (PDO)  — Connexió activa a la base de dades
 *
 * INCLUSIÓ RECOMANADA a detall_sector.php:
 *   require __DIR__ . '/partials/historial_plantacions.php';
 */

// -----------------------------------------------------------
// Consulta: totes les plantacions del sector, ordenades
// La plantació activa (data_arrencada IS NULL) apareix primera
// -----------------------------------------------------------
$plantacions_historial = [];
$error_historial       = null;

try {
    $stmt_hist = $pdo->prepare("
        SELECT  p.id_plantacio,
                CONCAT(v.nom_varietat, ' (', e.nom_comu, ')') AS cultiu,
                p.data_plantacio,
                p.data_arrencada,
                p.marc_fila,
                p.marc_arbre,
                p.num_arbres_plantats,
                p.sistema_formacio,
                p.previsio_entrada_produccio,
                -- Anys de vida del cultiu (fins avui o fins a data_arrencada)
                TIMESTAMPDIFF(
                    YEAR,
                    p.data_plantacio,
                    COALESCE(p.data_arrencada, CURDATE())
                ) AS anys_vida,
                -- Rendiment total registrat (suma dels seguiments anuals)
                (
                    SELECT COALESCE(SUM(sa.rendiment_kg_ha), 0)
                    FROM   seguiment_anual sa
                    WHERE  sa.id_plantacio = p.id_plantacio
                ) AS rendiment_total_kg_ha
        FROM    plantacio p
        JOIN    varietat  v ON v.id_varietat = p.id_varietat
        JOIN    especie   e ON e.id_especie  = v.id_especie
        WHERE   p.id_sector = :id_sector
        ORDER BY
                p.data_arrencada IS NULL DESC,   -- activa primer
                p.data_plantacio DESC
    ");
    $stmt_hist->execute([':id_sector' => $id_sector]);
    $plantacions_historial = $stmt_hist->fetchAll();

} catch (Exception $ex) {
    error_log('[CultiuConnect] historial_plantacions.php: ' . $ex->getMessage());
    $error_historial = 'No s\'ha pogut carregar l\'historial de plantacions.';
}

// Determinar si ja hi ha una plantació activa
$te_plantacio_activa = false;
foreach ($plantacions_historial as $p) {
    if ($p['data_arrencada'] === null) {
        $te_plantacio_activa = true;
        break;
    }
}
?>

<!-- ============================================================
     SECCIÓ: Historial de Plantacions i Rotacions
============================================================ -->
<section class="seccio-historial" aria-labelledby="titol-historial">

    <div class="capcalera-seccio capcalera-seccio--inline">
        <h2 class="titol-seccio" id="titol-historial">
            <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
            Historial de Plantacions
        </h2>

        <!-- Botó de renovació: sempre visible -->
        <a href="<?= BASE_URL ?>modules/sectors/renovar_plantacio.php?id_sector=<?= (int)$id_sector ?>"
           class="boto-principal boto-principal--petit"
           title="<?= $te_plantacio_activa
               ? 'Tancar el cultiu actual i iniciar una rotació'
               : 'Afegir una nova plantació a aquest sector' ?>">
            <i class="fas fa-rotate" aria-hidden="true"></i>
            <?= $te_plantacio_activa ? 'Renovar Plantació' : 'Afegir Plantació' ?>
        </a>
    </div>

    <?php if ($error_historial): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_historial) ?>
        </div>

    <?php elseif (empty($plantacions_historial)): ?>
        <div class="estat-buit">
            <i class="fas fa-seedling estat-buit__icona" aria-hidden="true"></i>
            <p class="estat-buit__text">
                Aquest sector encara no té cap plantació registrada.
            </p>
            <a href="<?= BASE_URL ?>modules/sectors/renovar_plantacio.php?id_sector=<?= (int)$id_sector ?>"
               class="boto-principal">
                <i class="fas fa-plus" aria-hidden="true"></i>
                Afegir primera plantació
            </a>
        </div>

    <?php else: ?>
        <div class="taula-responsive">
            <table class="taula taula--historial" aria-label="Historial de plantacions del sector">
                <thead>
                    <tr>
                        <th scope="col">Estat</th>
                        <th scope="col">Cultiu / Varietat</th>
                        <th scope="col">Data plantació</th>
                        <th scope="col">Data arrencada</th>
                        <th scope="col">Anys de vida</th>
                        <th scope="col">Marc (m)</th>
                        <th scope="col">Arbres</th>
                        <th scope="col">Rendiment total<br><small>(kg/ha acumulat)</small></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plantacions_historial as $fila): ?>
                        <?php $es_activa = ($fila['data_arrencada'] === null); ?>
                        <tr class="<?= $es_activa ? 'fila-activa' : 'fila-historica' ?>">

                            <!-- Estat -->
                            <td>
                                <?php if ($es_activa): ?>
                                    <span class="badge badge--success">
                                        <i class="fas fa-circle" aria-hidden="true"></i>
                                        Activa
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge--neutral">
                                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                                        Finalitzada
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Cultiu -->
                            <td>
                                <strong><?= e($fila['cultiu']) ?></strong>
                                <?php if ($fila['sistema_formacio']): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?= e($fila['sistema_formacio']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <!-- Data plantació -->
                            <td><?= format_data($fila['data_plantacio']) ?></td>

                            <!-- Data arrencada -->
                            <td>
                                <?= $fila['data_arrencada']
                                    ? format_data($fila['data_arrencada'])
                                    : '<span class="text-muted">—</span>' ?>
                            </td>

                            <!-- Anys de vida -->
                            <td class="cel-numerica">
                                <?php
                                    $anys = (int)$fila['anys_vida'];
                                    echo $anys > 0
                                        ? $anys . ' ' . ($anys === 1 ? 'any' : 'anys')
                                        : '< 1 any';
                                ?>
                            </td>

                            <!-- Marc -->
                            <td class="cel-numerica">
                                <?= number_format((float)$fila['marc_fila'], 1, ',', '.') ?> &times; <?= number_format((float)$fila['marc_arbre'], 1, ',', '.') ?> m
                            </td>

                            <!-- Arbres -->
                            <td class="cel-numerica">
                                <?= $fila['num_arbres_plantats']
                                    ? number_format((int)$fila['num_arbres_plantats'])
                                    : '<span class="text-muted">—</span>' ?>
                            </td>

                            <!-- Rendiment acumulat -->
                            <td class="cel-numerica">
                                <?php
                                    $rend = (float)$fila['rendiment_total_kg_ha'];
                                    echo $rend > 0
                                        ? number_format($rend, 0, ',', '.') . ' kg/ha'
                                        : '<span class="text-muted">—</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div><!-- /.taula-responsive -->

    <?php endif; ?>

</section><!-- /.seccio-historial -->