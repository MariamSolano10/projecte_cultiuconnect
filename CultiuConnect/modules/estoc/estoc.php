<?php
/**
 * modules/estoc/estoc.php — Gestió d'inventari de productes fitosanitaris i adobs.
 *
 * Llegeix de `producte_quimic` (estoc_actual, estoc_minim, unitat_mesura) i
 * obté la pròxima caducitat des de `inventari_estoc` (quantitat_disponible > 0).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Inventari i Estocs';
$pagina_activa = 'inventari';

$productes = [];
$error_db  = null;

try {
    $pdo = connectDB();

    // *** CORREGIT: inventari_estoc (no lot_producte), quantitat_disponible (no quantitat_restant) ***
    $productes = $pdo->query("
        SELECT
            p.id_producte,
            p.nom_comercial,
            p.tipus,
            p.estoc_actual,
            p.estoc_minim,
            p.unitat_mesura,
            (SELECT MIN(ie.data_caducitat)
             FROM inventari_estoc ie
             WHERE ie.id_producte = p.id_producte
               AND ie.quantitat_disponible > 0) AS proxima_caducitat
        FROM producte_quimic p
        ORDER BY p.tipus ASC, p.nom_comercial ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] estoc.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els productes de l\'inventari.';
}

function estatEstoc(mixed $actual, mixed $minim): array
{
    $actual = (float)($actual ?? 0);
    $minim  = (float)($minim  ?? 0);

    if ($actual <= 0)      return ['badge--vermell', 'Esgotat'];
    if ($actual <= $minim) return ['badge--taronja', 'Estoc baix'];
    return ['badge--verd', 'Correcte'];
}

function estatCaducitat(?string $data): array
{
    if (empty($data)) return ['badge--gris', '—'];

    $avui      = new DateTime('today');
    $caducitat = new DateTime($data);
    $es_passat = $avui > $caducitat;
    $diff      = (int)$avui->diff($caducitat)->days;

    if ($es_passat)  return ['badge--vermell', 'Caducat'];
    if ($diff <= 30) return ['badge--taronja', 'Caduca ' . dies_restants($data)];
    return ['badge--verd', format_data($data, curta: true)];
}

// *** Formata quantitat amb la seva unitat real (no sempre 'kg') ***
function formatEstoc(?float $valor, string $unitat): string
{
    if ($valor === null) return '—';
    return number_format($valor, 2, ',', '.') . ' ' . $unitat;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-estoc">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-boxes-stacked" aria-hidden="true"></i>
            Inventari i Magatzem
        </h1>
        <p class="descripcio-seccio">
            Control d'existències de productes fitosanitaris, herbicides i fertilitzants.
            El sistema avisa si un producte caduca en menys de 30 dies o l'estoc cau per sota del mínim.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/estoc/nou_producte.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Nou Producte
        </a>
        <a href="<?= BASE_URL ?>modules/estoc/moviment_estoc.php" class="boto-secundari">
            <i class="fas fa-exchange-alt" aria-hidden="true"></i> Entrada / Sortida d'Estoc
        </a>
        <a href="<?= BASE_URL ?>modules/estoc/previsio_necessitats.php" class="boto-secundari">
            <i class="fas fa-chart-line" aria-hidden="true"></i> Previsió de Necessitats
        </a>
        <a href="<?= BASE_URL ?>modules/estoc/inventari_fisic.php" class="boto-secundari">
            <i class="fas fa-clipboard-check" aria-hidden="true"></i> Inventari Físic
        </a>
    </div>

    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-estoc"
               placeholder="Cerca per nom o tipus de producte..."
               class="input-cerca"
               aria-label="Cercar productes">
    </div>

    <table class="taula-simple" id="taula-estoc">
        <thead>
            <tr>
                <th>Producte</th>
                <th>Tipus</th>
                <th>Estoc actual</th>
                <th>Mínim</th>
                <th>Estat</th>
                <th>Pròxima caducitat</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($productes)): ?>
                <tr>
                    <td colspan="7" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha productes a l'inventari.
                        <a href="<?= BASE_URL ?>modules/estoc/nou_producte.php">Afegeix-ne un.</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($productes as $p):
                    $unitat = $p['unitat_mesura'] ?? '';
                    [$classe_estoc,     $text_estoc]     = estatEstoc($p['estoc_actual'], $p['estoc_minim']);
                    [$classe_caducitat, $text_caducitat] = estatCaducitat($p['proxima_caducitat']);
                ?>
                    <tr>
                        <td data-cerca><strong><?= e($p['nom_comercial']) ?></strong></td>
                        <td data-cerca>
                            <span class="badge <?= match($p['tipus']) {
                                'Fitosanitari' => 'badge--vermell',
                                'Fertilitzant' => 'badge--verd',
                                'Herbicida'    => 'badge--taronja',
                                default        => 'badge--gris',
                            } ?>">
                                <?= e($p['tipus']) ?>
                            </span>
                        </td>
                        <td><?= formatEstoc((float)($p['estoc_actual'] ?? 0), $unitat) ?></td>
                        <td><?= formatEstoc((float)($p['estoc_minim']  ?? 0), $unitat) ?></td>
                        <td>
                            <span class="badge <?= $classe_estoc ?>">
                                <?= $text_estoc ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $classe_caducitat ?>">
                                <?= $text_caducitat ?>
                            </span>
                        </td>
                        <td class="cel-accions">
                            <a href="<?= BASE_URL ?>modules/estoc/detall_producte.php?id=<?= (int)$p['id_producte'] ?>"
                               title="Veure detall"
                               aria-label="Veure detall de <?= e($p['nom_comercial']) ?>"
                               class="btn-accio btn-accio--veure">
                                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                <span class="sr-only">Detall</span>
                            </a>
                            <a href="<?= BASE_URL ?>modules/estoc/nou_producte.php?editar=<?= (int)$p['id_producte'] ?>"
                               title="Editar producte"
                               aria-label="Editar <?= e($p['nom_comercial']) ?>"
                               class="btn-accio btn-accio--editar">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                                <span class="sr-only">Editar</span>
                            </a>
                            <a href="<?= BASE_URL ?>modules/estoc/moviment_estoc.php?id_producte=<?= (int)$p['id_producte'] ?>"
                               title="Registrar moviment"
                               aria-label="Moviment de <?= e($p['nom_comercial']) ?>"
                               class="btn-accio">
                                <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                                <span class="sr-only">Moviment</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>