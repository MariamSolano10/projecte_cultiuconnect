<?php
/**
 * modules/tractaments/tractaments_programats.php
 *
 * Llista tots els tractaments programats amb sistema d'alertes:
 *  - Vermell:  data_prevista <= avui (vençut o urgent)
 *  - Groc:     data_prevista <= avui + dies_avis (proper)
 *  - Verd:     la resta
 *
 * Filtres: estat, tipus, sector.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Tractaments Programats';
$pagina_activa = 'tractaments_programats';

$tractaments = [];
$sectors     = [];
$protocols   = [];
$error_db    = null;

$avui = new DateTimeImmutable('today');

// Filtres
$filtre_estat  = in_array($_GET['estat'] ?? '', ['pendent', 'completat', 'cancel·lat', 'tots'])
    ? $_GET['estat'] : 'pendent';
$filtre_tipus  = in_array($_GET['tipus'] ?? '', ['preventiu', 'correctiu', 'fertilitzacio', 'tots'])
    ? $_GET['tipus'] : 'tots';
$filtre_sector = is_numeric($_GET['id_sector'] ?? '') ? (int)$_GET['id_sector'] : 0;

try {
    $pdo = connectDB();

    $sectors   = $pdo->query("SELECT id_sector, nom FROM sector ORDER BY nom")->fetchAll();
    $protocols = $pdo->query("SELECT id_protocol, nom_protocol FROM protocol_tractament ORDER BY nom_protocol")->fetchAll();

    $condicions = [];
    $params     = [];

    if ($filtre_estat !== 'tots') {
        $condicions[] = 'tp.estat = :estat';
        $params[':estat'] = $filtre_estat;
    }
    if ($filtre_tipus !== 'tots') {
        $condicions[] = 'tp.tipus = :tipus';
        $params[':tipus'] = $filtre_tipus;
    }
    if ($filtre_sector > 0) {
        $condicions[] = 'tp.id_sector = :id_sector';
        $params[':id_sector'] = $filtre_sector;
    }

    $where = $condicions ? 'WHERE ' . implode(' AND ', $condicions) : '';

    $sql = "
        SELECT
            tp.id_programat,
            tp.data_prevista,
            tp.tipus,
            tp.motiu,
            tp.estat,
            tp.dies_avis,
            tp.observacions,
            s.nom  AS nom_sector,
            pt.nom_protocol,
            pt.descripcio  AS protocol_descripcio,
            DATEDIFF(tp.data_prevista, CURDATE()) AS dies_restants
        FROM tractament_programat tp
        LEFT JOIN sector              s  ON s.id_sector   = tp.id_sector
        LEFT JOIN protocol_tractament pt ON pt.id_protocol = tp.id_protocol
        $where
        ORDER BY
            FIELD(tp.estat, 'pendent', 'completat', 'cancel·lat'),
            tp.data_prevista ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tractaments = $stmt->fetchAll();

    // Comptadors per a les pestanyes de resum
    $stmtComptadors = $pdo->query("
        SELECT
            SUM(estat = 'pendent'    AND DATEDIFF(data_prevista, CURDATE()) < 0)              AS vencuts,
            SUM(estat = 'pendent'    AND DATEDIFF(data_prevista, CURDATE()) BETWEEN 0 AND dies_avis) AS propers,
            SUM(estat = 'pendent'    AND DATEDIFF(data_prevista, CURDATE()) > dies_avis)      AS planificats,
            SUM(estat = 'completat')                                                           AS completats
        FROM tractament_programat
    ");
    $comptadors = $stmtComptadors->fetch();

} catch (Exception $e) {
    error_log('[CultiuConnect] tractaments_programats.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els tractaments programats.';
}

function classePrioritat(int $dies_restants, int $dies_avis, string $estat): string
{
    if ($estat !== 'pendent') return '';
    if ($dies_restants < 0)            return 'fila--alerta-critica';
    if ($dies_restants <= $dies_avis)  return 'fila--alerta-propera';
    return '';
}

function badgePrioritat(int $dies_restants, int $dies_avis, string $estat): string
{
    if ($estat !== 'pendent') return '';
    if ($dies_restants < 0) {
        return '<span class="badge badge--vermell"><i class="fas fa-triangle-exclamation"></i> Vençut</span>';
    }
    if ($dies_restants === 0) {
        return '<span class="badge badge--vermell"><i class="fas fa-bell"></i> Avui!</span>';
    }
    if ($dies_restants <= $dies_avis) {
        return '<span class="badge badge--groc"><i class="fas fa-bell"></i> ' . $dies_restants . ' dies</span>';
    }
    return '<span class="badge badge--verd">' . $dies_restants . ' dies</span>';
}

function classeEstatTractament(string $estat): string
{
    return match ($estat) {
        'pendent'    => 'badge--groc',
        'completat'  => 'badge--verd',
        'cancel·lat' => 'badge--vermell',
        default      => 'badge--gris',
    };
}

// Helper per mantenir els filtres actius en els formularis inline
function filtre_inputs_hidden(string $estat, string $tipus, int $sector): string
{
    return '<input type="hidden" name="filtre_estat"  value="' . htmlspecialchars($estat) . '">'
         . '<input type="hidden" name="filtre_tipus"  value="' . htmlspecialchars($tipus) . '">'
         . '<input type="hidden" name="filtre_sector" value="' . (int)$sector . '">';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-tractaments-programats">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-calendar-check" aria-hidden="true"></i>
            Tractaments Programats
        </h1>
        <p class="descripcio-seccio">
            Planificació i alertes de tractaments fitosanitaris, fertilitzacions i correccions.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- TARGETES RESUM / ALERTES -->
    <?php if ($comptadors && !$error_db): ?>
    <div class="resum-cards">

        <div class="stat-card stat-card--vermell">
            <div class="stat-card__icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="stat-card__valor"><?= (int)$comptadors['vencuts'] ?></div>
            <div class="stat-card__label">Vençuts</div>
        </div>

        <div class="stat-card stat-card--groc">
            <div class="stat-card__icon"><i class="fas fa-bell"></i></div>
            <div class="stat-card__valor"><?= (int)$comptadors['propers'] ?></div>
            <div class="stat-card__label">Propers</div>
        </div>

        <div class="stat-card stat-card--blau">
            <div class="stat-card__icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-card__valor"><?= (int)$comptadors['planificats'] ?></div>
            <div class="stat-card__label">Planificats</div>
        </div>

        <div class="stat-card stat-card--verd">
            <div class="stat-card__icon"><i class="fas fa-circle-check"></i></div>
            <div class="stat-card__valor"><?= (int)$comptadors['completats'] ?></div>
            <div class="stat-card__label">Completats</div>
        </div>

    </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/tractaments/nou_tractament_programat.php"
           class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Nou Tractament Programat
        </a>
    </div>

    <!-- FILTRES -->
    <div class="filtres-avancats card">
        <form method="GET" class="form-fila">

            <div class="form-camp">
                <label for="estat">Estat</label>
                <select name="estat" id="estat">
                    <?php foreach (['pendent' => 'Pendents', 'completat' => 'Completats', 'cancel·lat' => 'Cancel·lats', 'tots' => 'Tots'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $filtre_estat === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-camp">
                <label for="tipus">Tipus</label>
                <select name="tipus" id="tipus">
                    <?php foreach (['tots' => 'Tots', 'preventiu' => 'Preventiu', 'correctiu' => 'Correctiu', 'fertilitzacio' => 'Fertilització'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $filtre_tipus === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-camp">
                <label for="id_sector">Sector</label>
                <select name="id_sector" id="id_sector">
                    <option value="0">— Tots —</option>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?= (int)$s['id_sector'] ?>"
                            <?= $filtre_sector === (int)$s['id_sector'] ? 'selected' : '' ?>>
                            <?= e($s['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-camp">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
                </button>
                <a href="?" class="boto-secundari">
                    <i class="fas fa-xmark"></i> Netejar
                </a>
            </div>
        </form>
    </div>

    <!-- LLEGENDA -->
    <div class="llegenda-tractaments">
        <span><span class="llegenda-tractaments__swatch llegenda-tractaments__swatch--critica"></span>Vençut o urgent</span>
        <span><span class="llegenda-tractaments__swatch llegenda-tractaments__swatch--propera"></span>Proper (dins del període d'avís)</span>
        <span><span class="llegenda-tractaments__swatch llegenda-tractaments__swatch--planificat"></span>Planificat</span>
    </div>

    <!-- CERCA -->
    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-tractaments"
               placeholder="Cerca per sector, protocol o motiu..."
               class="input-cerca"
               aria-label="Cercar tractaments">
    </div>

    <!-- TAULA -->
    <table class="taula-simple" id="taula-tractaments">
        <thead>
            <tr>
                <th>Alerta</th>
                <th>Data prevista</th>
                <th>Tipus</th>
                <th>Sector</th>
                <th>Protocol</th>
                <th>Motiu</th>
                <th>Dies avís</th>
                <th>Estat</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tractaments)): ?>
                <tr>
                    <td colspan="9" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha tractaments programats per als filtres seleccionats.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tractaments as $t):
                    $dies  = (int)$t['dies_restants'];
                    $avis  = (int)($t['dies_avis'] ?? 3);
                    $classe_fila = classePrioritat($dies, $avis, $t['estat']);

                    // Dades del modal serialitzades de forma segura
                    $modal_data = htmlspecialchars(json_encode([
                        'sector'               => $t['nom_sector']          ?? null,
                        'protocol'             => $t['nom_protocol']         ?? null,
                        'protocol_descripcio'  => $t['protocol_descripcio']  ?? null,
                        'tipus'                => $t['tipus'],
                        'data_prevista'        => $t['data_prevista'],
                        'dies_restants'        => $dies,
                        'dies_avis'            => $avis,
                        'estat'                => $t['estat'],
                        'motiu'                => $t['motiu']                ?? null,
                        'observacions'         => $t['observacions']         ?? null,
                    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                ?>
                <tr class="<?= $classe_fila ?>">
                    <td><?= badgePrioritat($dies, $avis, $t['estat']) ?></td>
                    <td>
                        <strong><?= format_data($t['data_prevista'], curta: true) ?></strong>
                    </td>
                    <td data-cerca>
                        <?php
                        $icons_tipus = ['preventiu' => 'fa-shield', 'correctiu' => 'fa-screwdriver-wrench', 'fertilitzacio' => 'fa-flask'];
                        $icon = $icons_tipus[$t['tipus']] ?? 'fa-calendar';
                        ?>
                        <i class="fas <?= $icon ?>" aria-hidden="true"></i>
                        <?= e(ucfirst($t['tipus'])) ?>
                    </td>
                    <td data-cerca><?= $t['nom_sector']  ? e($t['nom_sector'])  : '—' ?></td>
                    <td data-cerca><?= $t['nom_protocol'] ? e($t['nom_protocol']) : '—' ?></td>
                    <td data-cerca>
                        <?php if ($t['motiu']): ?>
                            <?= e(mb_substr($t['motiu'], 0, 50)) ?><?= mb_strlen($t['motiu']) > 50 ? '…' : '' ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= $avis ?> dies</td>
                    <td>
                        <span class="badge <?= classeEstatTractament($t['estat']) ?>">
                            <?= e(ucfirst($t['estat'])) ?>
                        </span>
                    </td>
                    <td class="cel-accions">

                        <!-- Veure detall -->
                        <button type="button"
                                class="btn-accio btn-accio--veure"
                                title="Veure detall"
                                aria-label="Veure detall del tractament"
                                onclick="obrirDetall(<?= $modal_data ?>)">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>

                        <!-- Editar -->
                        <a href="<?= BASE_URL ?>modules/tractaments/nou_tractament_programat.php?editar=<?= (int)$t['id_programat'] ?>"
                           title="Editar"
                           class="btn-accio btn-accio--editar">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </a>

                        <?php if ($t['estat'] === 'pendent'): ?>
                        <!-- Marcar com a completat -->
                        <form method="POST"
                              action="<?= BASE_URL ?>modules/tractaments/processar_tractament_programat.php"
                              class="form-inline">
                            <input type="hidden" name="accio"        value="completar">
                            <input type="hidden" name="id_programat" value="<?= (int)$t['id_programat'] ?>">
                            <?= filtre_inputs_hidden($filtre_estat, $filtre_tipus, $filtre_sector) ?>
                            <button type="submit"
                                    class="btn-accio btn-accio--completar"
                                    title="Marcar com a completat">
                                <i class="fas fa-check" aria-hidden="true"></i>
                            </button>
                        </form>

                        <!-- Cancel·lar -->
                        <form method="POST"
                              action="<?= BASE_URL ?>modules/tractaments/processar_tractament_programat.php"
                              class="form-inline"
                              onsubmit="return confirm('Cancel·lar aquest tractament programat?')">
                            <input type="hidden" name="accio"        value="cancellar">
                            <input type="hidden" name="id_programat" value="<?= (int)$t['id_programat'] ?>">
                            <?= filtre_inputs_hidden($filtre_estat, $filtre_tipus, $filtre_sector) ?>
                            <button type="submit"
                                    class="btn-accio btn-accio--eliminar"
                                    title="Cancel·lar tractament">
                                <i class="fas fa-ban" aria-hidden="true"></i>
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- Eliminar -->
                        <form method="POST"
                              action="<?= BASE_URL ?>modules/tractaments/processar_tractament_programat.php"
                              class="form-inline"
                              onsubmit="return confirm('Eliminar definitivament aquest tractament?')">
                            <input type="hidden" name="accio"        value="eliminar">
                            <input type="hidden" name="id_programat" value="<?= (int)$t['id_programat'] ?>">
                            <?= filtre_inputs_hidden($filtre_estat, $filtre_tipus, $filtre_sector) ?>
                            <button type="submit"
                                    class="btn-accio btn-accio--eliminar"
                                    title="Eliminar">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>

                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- MODAL DE DETALL -->
    <dialog id="modal-detall-tractament" class="modal-detall" aria-labelledby="modal-detall-titol">
        <div class="modal-detall__capcalera">
            <h2 id="modal-detall-titol">
                <i class="fas fa-calendar-check" aria-hidden="true"></i>
                Detall del tractament
            </h2>
            <button type="button"
                    class="boto-tancar"
                    aria-label="Tancar"
                    onclick="document.getElementById('modal-detall-tractament').close()">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <dl class="modal-detall__cos" id="modal-detall-cos"></dl>
        <div class="modal-detall__peu">
            <button type="button"
                    class="boto-secundari"
                    onclick="document.getElementById('modal-detall-tractament').close()">
                Tancar
            </button>
        </div>
    </dialog>

</div>

<script>
function obrirDetall(d) {
    const icons = {
        preventiu:     'fa-shield',
        correctiu:     'fa-screwdriver-wrench',
        fertilitzacio: 'fa-flask'
    };
    const tipusIcon = icons[d.tipus] || 'fa-calendar';

    let diesLabel = '—';
    if (d.estat === 'pendent') {
        if (d.dies_restants < 0) {
            diesLabel = '<span class="dies-restants dies-restants--critica">Vençut fa ' + Math.abs(d.dies_restants) + ' dies</span>';
        } else if (d.dies_restants === 0) {
            diesLabel = '<span class="dies-restants dies-restants--critica">Avui!</span>';
        } else {
            diesLabel = d.dies_restants + ' dies restants';
        }
    }

    const camps = [
        ['Sector',        d.sector      || '—',  'fa-map-marker-alt'],
        ['Tipus',         '<i class="fas ' + tipusIcon + '" aria-hidden="true"></i> ' + (d.tipus ? d.tipus.charAt(0).toUpperCase() + d.tipus.slice(1) : '—'), null],
        ['Data prevista', d.data_prevista || '—', 'fa-calendar'],
        ['Dies restants', diesLabel,              null],
        ['Període avís',  d.dies_avis + ' dies',  'fa-bell'],
        ['Estat',         d.estat ? d.estat.charAt(0).toUpperCase() + d.estat.slice(1) : '—', 'fa-circle-info'],
        ['Protocol',      d.protocol    || '—',  'fa-file-medical'],
        ['Desc. protocol',d.protocol_descripcio || '—', null],
        ['Motiu',         d.motiu       || '—',  'fa-comment'],
        ['Observacions',  d.observacions|| '—',  'fa-clipboard'],
    ];

    document.getElementById('modal-detall-cos').innerHTML = camps.map(([k, v, ic]) =>
        '<div class="modal-detall__fila">' +
        '<dt>' + (ic ? '<i class="fas ' + ic + '" aria-hidden="true"></i> ' : '') + k + '</dt>' +
        '<dd>' + v + '</dd>' +
        '</div>'
    ).join('');

    document.getElementById('modal-detall-tractament').showModal();
}

// Tanca el modal en clicar fora del contingut
document.getElementById('modal-detall-tractament').addEventListener('click', function (e) {
    if (e.target === this) this.close();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>