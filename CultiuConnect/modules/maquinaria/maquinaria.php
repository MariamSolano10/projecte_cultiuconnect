<?php
/**
 * modules/maquinaria/maquinaria.php — Parc de maquinària agrícola.
 *
 * Columnes de `maquinaria`: id_maquinaria, nom_maquina, tipus,
 *   any_fabricacio, manteniment_json  (JSON: {"darrera_revisio":"YYYY-MM-DD"})
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Parc de Maquinària';
$pagina_activa = 'maquinaria';

$maquines = [];
$error_db = null;

try {
    $pdo = connectDB();

    $maquines = $pdo->query("
        SELECT m.id_maquinaria, m.nom_maquina, m.tipus, m.any_fabricacio, m.manteniment_json,
               COALESCE(SUM(ma.hores_utilitzades), 0) AS hores_acumulades
        FROM maquinaria m
        LEFT JOIN maquinaria_aplicacio ma ON m.id_maquinaria = ma.id_maquinaria
        GROUP BY m.id_maquinaria, m.nom_maquina, m.tipus, m.any_fabricacio, m.manteniment_json
        ORDER BY m.tipus ASC, m.nom_maquina ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] maquinaria.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar l\'inventari de maquinària.';
}

/**
 * Retorna [classe_badge, text] a partir del JSON de manteniment.
 */
function estatManteniment(?string $json): array
{
    if (empty($json)) return ['badge--gris', 'Sense dades'];

    $data = json_decode($json, true);
    if (!isset($data['darrera_revisio'])) return ['badge--gris', 'Sense dades'];

    try {
        $revisio = new DateTime($data['darrera_revisio']);
        $avui    = new DateTime('today');
        $dies    = (int)$avui->diff($revisio)->days;
    } catch (Exception) {
        return ['badge--gris', 'Data invàlida'];
    }

    if ($dies > 365) return ['badge--vermell', 'Revisió pendent (>1 any)'];
    if ($dies > 300) return ['badge--taronja', 'Revisió pròxima'];
    return                  ['badge--verd',    'Manteniment OK'];
}

/**
 * Icona Font Awesome per a cada tipus de maquinària.
 */
function iconaTipus(?string $tipus): string
{
    return match ($tipus) {
        'Tractor'       => 'tractor',
        'Pulveritzador' => 'spray-can',
        'Poda'          => 'scissors',
        'Cistella'      => 'person-shelter',
        default         => 'gears',
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-maquinaria">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-tractor" aria-hidden="true"></i>
            Parc de Maquinària i Eines
        </h1>
        <p class="descripcio-seccio">
            Tractors, pulveritzadors, remolcs i altres eines mecàniques de l'explotació.
            El sistema avisa quan una màquina supera l'any sense revisió.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/maquinaria/nova_maquinaria.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Nova Màquina
        </a>
    </div>

    <!-- Cercador -->
    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-maquinaria"
               placeholder="Cerca per nom o tipus..."
               class="input-cerca"
               aria-label="Cercar maquinària">
    </div>

    <table class="taula-simple" id="taula-maquinaria">
        <thead>
            <tr>
                <th>Ref.</th>
                <th>Nom / Matrícula</th>
                <th>Tipus</th>
                <th>Any fabricació</th>
                <th>Hores Acumulades</th>
                <th>Darrera revisió</th>
                <th>Estat manteniment</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($maquines)): ?>
                <tr>
                    <td colspan="8" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        Cap màquina registrada.
                        <a href="<?= BASE_URL ?>modules/maquinaria/nova_maquinaria.php">Afegeix-ne una.</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($maquines as $m):
                    [$classe_badge, $text_estat] = estatManteniment($m['manteniment_json']);
                    $icona  = iconaTipus($m['tipus']);
                    $json   = json_decode($m['manteniment_json'] ?? '{}', true);
                    $data_r = $json['darrera_revisio'] ?? null;
                ?>
                    <tr>
                        <td>#<?= str_pad((int)$m['id_maquinaria'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td data-cerca><strong><?= e($m['nom_maquina']) ?></strong></td>
                        <td data-cerca>
                            <i class="fas fa-<?= $icona ?>" aria-hidden="true"></i>
                            <?= e($m['tipus'] ?? '—') ?>
                        </td>
                        <td><?= $m['any_fabricacio'] ? (int)$m['any_fabricacio'] : '—' ?></td>
                        <td><strong><?= number_format((float)$m['hores_acumulades'], 2, ',', '.') ?> h</strong></td>
                        <td><?= $data_r ? format_data($data_r, curta: true) : '—' ?></td>
                        <td>
                            <span class="badge <?= $classe_badge ?>">
                                <?= $text_estat ?>
                            </span>
                        </td>
                        <td class="cel-accions">
                            <a href="<?= BASE_URL ?>modules/maquinaria/nova_maquinaria.php?editar=<?= (int)$m['id_maquinaria'] ?>"
                               title="Editar màquina"
                               class="btn-accio btn-accio--editar">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                            </a>
                            <a href="<?= BASE_URL ?>modules/maquinaria/baixa_maquinaria.php?id=<?= (int)$m['id_maquinaria'] ?>"
                               title="Donar de baixa"
                               class="btn-accio btn-accio--eliminar"
                               data-confirma="Segur que vols donar de baixa «<?= e($m['nom_maquina']) ?>»?">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
