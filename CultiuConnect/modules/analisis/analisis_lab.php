<?php
/**
 * modules/analisis/analisis_lab.php — Llistat d'analítiques de laboratori.
 *
 * Mostra tres pestanyes:
 *   1. Sòl     → taula `caracteristiques_sol`
 *   2. Aigua   → taula `analisi_aigua`
 *   3. Foliar  → taula `analisi_foliar`
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$analisis_sol    = [];
$analisis_aigua  = [];
$analisis_foliar = [];
$error_db        = null;

try {
    $pdo = connectDB();

    // ── SÒL ──────────────────────────────────────────────────────────────────
    $analisis_sol = $pdo->query("
        SELECT
            cs.id_sector,
            cs.data_analisi,
            cs.textura,
            cs.pH,
            cs.materia_organica,
            cs.N, cs.P, cs.K,
            cs.conductivitat_electrica,
            COALESCE(s.nom, '—') AS nom_sector,
            CASE
                WHEN cs.pH < 5.5 OR cs.pH > 8.0 THEN 'pH crític'
                WHEN cs.N < 10                   THEN 'Dèficit N'
                WHEN cs.materia_organica < 1.5   THEN 'M.O. baixa'
                ELSE 'Adequat'
            END AS estat_nutricional
        FROM caracteristiques_sol cs
        INNER JOIN sector s ON cs.id_sector = s.id_sector
        ORDER BY cs.data_analisi DESC, s.nom ASC
    ")->fetchAll();

    // ── AIGUA ────────────────────────────────────────────────────────────────
    $analisis_aigua = $pdo->query("
        SELECT
            aa.id_analisi_aigua,
            aa.id_sector,
            aa.data_analisi,
            aa.origen_mostra,
            aa.pH,
            aa.conductivitat_electrica,
            aa.duresa,
            aa.nitrats,
            aa.clorurs,
            aa.Na,
            aa.SAR,
            COALESCE(s.nom, '—') AS nom_sector,
            CASE
                WHEN aa.pH IS NOT NULL AND (aa.pH < 6.0 OR aa.pH > 8.5) THEN 'pH crític'
                WHEN aa.conductivitat_electrica > 3                       THEN 'Salinitat alta'
                WHEN aa.nitrats > 50                                      THEN 'Nitrats elevats'
                WHEN aa.SAR > 10                                          THEN 'Risc sòdic'
                ELSE 'Adequada'
            END AS estat_aigua
        FROM analisi_aigua aa
        INNER JOIN sector s ON aa.id_sector = s.id_sector
        ORDER BY aa.data_analisi DESC, s.nom ASC
    ")->fetchAll();

    // ── FOLIAR ───────────────────────────────────────────────────────────────
    $analisis_foliar = $pdo->query("
        SELECT
            af.id_analisi_foliar,
            af.id_sector,
            af.data_analisi,
            af.estat_fenologic,
            af.N, af.P, af.K, af.Ca, af.Mg,
            af.Fe, af.Zn, af.B,
            af.deficiencies_detectades,
            COALESCE(s.nom,          '—') AS nom_sector,
            COALESCE(v.nom_varietat, '—') AS nom_varietat,
            CASE
                WHEN af.N  IS NOT NULL AND af.N  < 2.0 THEN 'Dèficit N'
                WHEN af.K  IS NOT NULL AND af.K  < 1.0 THEN 'Dèficit K'
                WHEN af.Ca IS NOT NULL AND af.Ca < 1.0 THEN 'Dèficit Ca'
                WHEN af.Fe IS NOT NULL AND af.Fe < 50  THEN 'Dèficit Fe'
                WHEN af.deficiencies_detectades IS NOT NULL
                     AND af.deficiencies_detectades != '' THEN 'Deficiències'
                ELSE 'Adequat'
            END AS estat_foliar
        FROM analisi_foliar af
        INNER JOIN sector   s  ON af.id_sector   = s.id_sector
        LEFT  JOIN plantacio pl ON af.id_plantacio = pl.id_plantacio
        LEFT  JOIN varietat  v  ON pl.id_varietat  = v.id_varietat
        ORDER BY af.data_analisi DESC, s.nom ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] analisis_lab.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les anàlisis.';
}

// Helpers de badge
function classeBadge(?string $estat, string $ok = 'Adequat'): string
{
    if (empty($estat) || $estat === $ok) return 'badge--verd';
    return 'badge--vermell';
}

$etiquetes_fenologic = [
    'repos_hivernal'  => 'Repòs hivernal',
    'brotacio'        => 'Brotació',
    'floracio'        => 'Floració',
    'creixement_fruit'=> 'Creixement fruit',
    'maduresa'        => 'Maduresa',
    'post_collita'    => 'Post-collita',
];

$titol_pagina  = 'Anàlisis de Laboratori';
$pagina_activa = 'monitoratge';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-analisis">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-flask" aria-hidden="true"></i>
            Anàlisis de Laboratori i Gestió Nutricional
        </h1>
        <p class="descripcio-seccio">
            Registre històric de les analítiques de sòl, aigua de reg i estat foliar.
            Informació essencial per a la fertilització de precisió i el balanç nutricional.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- ── PESTANYES ──────────────────────────────────────────────────────── -->
    <div class="pestanyes-analisi" id="pestanyes-analisi">

        <nav class="pestanyes-nav" role="tablist" aria-label="Tipus d'anàlisi">
            <button class="pestanya-boto pestanya-boto--activa"
                    role="tab" aria-selected="true"
                    aria-controls="tab-sol" id="btn-sol"
                    data-pestanya="sol">
                <i class="fas fa-mountain-sun" aria-hidden="true"></i>
                Sòl
                <span class="pestanya-comptador"><?= count($analisis_sol) ?></span>
            </button>
            <button class="pestanya-boto"
                    role="tab" aria-selected="false"
                    aria-controls="tab-aigua" id="btn-aigua"
                    data-pestanya="aigua">
                <i class="fas fa-droplet" aria-hidden="true"></i>
                Aigua de Reg
                <span class="pestanya-comptador"><?= count($analisis_aigua) ?></span>
            </button>
            <button class="pestanya-boto"
                    role="tab" aria-selected="false"
                    aria-controls="tab-foliar" id="btn-foliar"
                    data-pestanya="foliar">
                <i class="fas fa-leaf" aria-hidden="true"></i>
                Foliar
                <span class="pestanya-comptador"><?= count($analisis_foliar) ?></span>
            </button>
        </nav>

        <!-- ── PESTANYA SÒL ─────────────────────────────────────────────── -->
        <div class="pestanya-contingut pestanya-contingut--activa"
             id="tab-sol" role="tabpanel" aria-labelledby="btn-sol">

            <div class="botons-accions">
                <a href="<?= BASE_URL ?>modules/analisis/nou_analisi.php" class="boto-principal">
                    <i class="fas fa-plus" aria-hidden="true"></i> Nova Analítica de Sòl
                </a>
            </div>

            <div class="cerca-container">
                <input type="search"
                       data-filtre-taula="taula-sol"
                       placeholder="Cerca per sector o textura..."
                       class="input-cerca"
                       aria-label="Cercar anàlisis de sòl">
            </div>

            <table class="taula-simple" id="taula-sol">
                <thead>
                    <tr>
                        <th>Sector</th>
                        <th>Data</th>
                        <th>Textura</th>
                        <th>pH</th>
                        <th>M.O. (%)</th>
                        <th>CE (dS/m)</th>
                        <th>N</th>
                        <th>P</th>
                        <th>K</th>
                        <th>Estat</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($analisis_sol)): ?>
                        <tr>
                            <td colspan="11" class="sense-dades">
                                <i class="fas fa-info-circle" aria-hidden="true"></i>
                                No hi ha anàlisis de sòl registrades.
                                <a href="<?= BASE_URL ?>modules/analisis/nou_analisi.php">Registra'n una.</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($analisis_sol as $a): ?>
                            <tr>
                                <td data-cerca><strong><?= e($a['nom_sector']) ?></strong></td>
                                <td><?= format_data($a['data_analisi'], curta: true) ?></td>
                                <td data-cerca><?= e($a['textura'] ?? '—') ?></td>
                                <td>
                                    <?php if ($a['pH'] !== null):
                                        $ph = (float)$a['pH'];
                                        $c = ($ph < 5.5 || $ph > 8.0) ? 'text-alert' : '';
                                    ?>
                                        <strong class="<?= $c ?>"><?= number_format($ph, 1, ',', '.') ?></strong>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($a['materia_organica'] !== null):
                                        $mo = (float)$a['materia_organica'];
                                        $c = $mo < 1.5 ? 'text-alert' : '';
                                    ?>
                                        <span class="<?= $c ?>"><?= number_format($mo, 2, ',', '.') ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= $a['conductivitat_electrica'] !== null ? number_format((float)$a['conductivitat_electrica'], 3, ',', '.') : '—' ?></td>
                                <td><?= $a['N'] !== null ? number_format((float)$a['N'], 1, ',', '.') : '—' ?></td>
                                <td><?= $a['P'] !== null ? number_format((float)$a['P'], 1, ',', '.') : '—' ?></td>
                                <td><?= $a['K'] !== null ? number_format((float)$a['K'], 1, ',', '.') : '—' ?></td>
                                <td>
                                    <span class="badge <?= classeBadge($a['estat_nutricional']) ?>">
                                        <?= e($a['estat_nutricional']) ?>
                                    </span>
                                </td>
                                <td class="cel-accions">
                                    <a href="<?= BASE_URL ?>modules/analisis/nou_analisi.php?editar_sector=<?= (int)$a['id_sector'] ?>&editar_data=<?= urlencode($a['data_analisi']) ?>"
                                       title="Editar" class="btn-accio btn-accio--editar">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="nota-peu">pH òptim per a fruita dolça: 6.0—7.0. M.O. &lt; 1.5% indica sòl pobre.</p>
        </div>

        <!-- ── PESTANYA AIGUA ────────────────────────────────────────────── -->
        <div class="pestanya-contingut"
             id="tab-aigua" role="tabpanel" aria-labelledby="btn-aigua" hidden>

            <div class="botons-accions">
                <a href="<?= BASE_URL ?>modules/analisis/nou_analisi_aigua.php" class="boto-principal">
                    <i class="fas fa-plus" aria-hidden="true"></i> Nova Analítica d'Aigua
                </a>
            </div>

            <div class="cerca-container">
                <input type="search"
                       data-filtre-taula="taula-aigua"
                       placeholder="Cerca per sector o origen..."
                       class="input-cerca"
                       aria-label="Cercar anàlisis d'aigua">
            </div>

            <table class="taula-simple" id="taula-aigua">
                <thead>
                    <tr>
                        <th>Sector</th>
                        <th>Data</th>
                        <th>Origen</th>
                        <th>pH</th>
                        <th>CE (dS/m)</th>
                        <th>Duresa (mg/L)</th>
                        <th>Nitrats (ppm)</th>
                        <th>Clorurs (ppm)</th>
                        <th>Na (ppm)</th>
                        <th>SAR</th>
                        <th>Estat</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($analisis_aigua)): ?>
                        <tr>
                            <td colspan="12" class="sense-dades">
                                <i class="fas fa-info-circle" aria-hidden="true"></i>
                                No hi ha anàlisis d'aigua registrades.
                                <a href="<?= BASE_URL ?>modules/analisis/nou_analisi_aigua.php">Registra'n una.</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($analisis_aigua as $a): ?>
                            <tr>
                                <td data-cerca><strong><?= e($a['nom_sector']) ?></strong></td>
                                <td><?= format_data($a['data_analisi'], curta: true) ?></td>
                                <td data-cerca><?= e(ucfirst($a['origen_mostra'])) ?></td>
                                <td>
                                    <?php if ($a['pH'] !== null):
                                        $ph = (float)$a['pH'];
                                        $c = ($ph < 6.0 || $ph > 8.5) ? 'text-alert' : '';
                                    ?>
                                        <strong class="<?= $c ?>"><?= number_format($ph, 1, ',', '.') ?></strong>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($a['conductivitat_electrica'] !== null):
                                        $ce = (float)$a['conductivitat_electrica'];
                                        $c = $ce > 3 ? 'text-alert' : '';
                                    ?>
                                        <span class="<?= $c ?>"><?= number_format($ce, 3, ',', '.') ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= $a['duresa']  !== null ? number_format((float)$a['duresa'],  1, ',', '.') : '—' ?></td>
                                <td>
                                    <?php if ($a['nitrats'] !== null):
                                        $no3 = (float)$a['nitrats'];
                                        $c = $no3 > 50 ? 'text-alert' : '';
                                    ?>
                                        <span class="<?= $c ?>"><?= number_format($no3, 1, ',', '.') ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= $a['clorurs'] !== null ? number_format((float)$a['clorurs'], 1, ',', '.') : '—' ?></td>
                                <td><?= $a['Na']      !== null ? number_format((float)$a['Na'],      1, ',', '.') : '—' ?></td>
                                <td>
                                    <?php if ($a['SAR'] !== null):
                                        $sar = (float)$a['SAR'];
                                        $c = $sar > 10 ? 'text-alert' : '';
                                    ?>
                                        <span class="<?= $c ?>"><?= number_format($sar, 2, ',', '.') ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= classeBadge($a['estat_aigua'], 'Adequada') ?>">
                                        <?= e($a['estat_aigua']) ?>
                                    </span>
                                </td>
                                <td class="cel-accions">
                                    <a href="<?= BASE_URL ?>modules/analisis/nou_analisi_aigua.php?editar=<?= (int)$a['id_analisi_aigua'] ?>"
                                       title="Editar" class="btn-accio btn-accio--editar">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="nota-peu">CE &gt; 3 dS/m indica salinitat elevada. SAR &gt; 10 implica risc de degradació de l'estructura del sòl.</p>
        </div>

        <!-- ── PESTANYA FOLIAR ───────────────────────────────────────────── -->
        <div class="pestanya-contingut"
             id="tab-foliar" role="tabpanel" aria-labelledby="btn-foliar" hidden>

            <div class="botons-accions">
                <a href="<?= BASE_URL ?>modules/analisis/nou_analisi_foliar.php" class="boto-principal">
                    <i class="fas fa-plus" aria-hidden="true"></i> Nova Analítica Foliar
                </a>
            </div>

            <div class="cerca-container">
                <input type="search"
                       data-filtre-taula="taula-foliar"
                       placeholder="Cerca per sector o varietat..."
                       class="input-cerca"
                       aria-label="Cercar anàlisis foliars">
            </div>

            <table class="taula-simple" id="taula-foliar">
                <thead>
                    <tr>
                        <th>Sector / Varietat</th>
                        <th>Data</th>
                        <th>Estat fenològic</th>
                        <th>N (%)</th>
                        <th>P (%)</th>
                        <th>K (%)</th>
                        <th>Ca (%)</th>
                        <th>Fe (ppm)</th>
                        <th>Zn (ppm)</th>
                        <th>Deficiències</th>
                        <th>Estat</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($analisis_foliar)): ?>
                        <tr>
                            <td colspan="12" class="sense-dades">
                                <i class="fas fa-info-circle" aria-hidden="true"></i>
                                No hi ha anàlisis foliars registrades.
                                <a href="<?= BASE_URL ?>modules/analisis/nou_analisi_foliar.php">Registra'n una.</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($analisis_foliar as $a): ?>
                            <tr>
                                <td data-cerca>
                                    <strong><?= e($a['nom_sector']) ?></strong>
                                    <?php if ($a['nom_varietat'] !== '—'): ?>
                                        <br><small class="text-suau"><?= e($a['nom_varietat']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= format_data($a['data_analisi'], curta: true) ?></td>
                                <td data-cerca>
                                    <span class="badge badge--blau">
                                        <?= e($etiquetes_fenologic[$a['estat_fenologic']] ?? $a['estat_fenologic']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($a['N'] !== null):
                                        $c = (float)$a['N'] < 2.0 ? 'text-alert' : '';
                                    ?>
                                        <span class="<?= $c ?>"><?= number_format((float)$a['N'], 2, ',', '.') ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= $a['P']  !== null ? number_format((float)$a['P'],  2, ',', '.') : '—' ?></td>
                                <td>
                                    <?php if ($a['K'] !== null):
                                        $c = (float)$a['K'] < 1.0 ? 'text-alert' : '';
                                    ?>
                                        <span class="<?= $c ?>"><?= number_format((float)$a['K'], 2, ',', '.') ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($a['Ca'] !== null):
                                        $c = (float)$a['Ca'] < 1.0 ? 'text-alert' : '';
                                    ?>
                                        <span class="<?= $c ?>"><?= number_format((float)$a['Ca'], 2, ',', '.') ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($a['Fe'] !== null):
                                        $c = (float)$a['Fe'] < 50 ? 'text-alert' : '';
                                    ?>
                                        <span class="<?= $c ?>"><?= number_format((float)$a['Fe'], 1, ',', '.') ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= $a['Zn'] !== null ? number_format((float)$a['Zn'], 1, ',', '.') : '—' ?></td>
                                <td class="cel-deficiencies">
                                    <?php if (!empty($a['deficiencies_detectades'])): ?>
                                        <span title="<?= e($a['deficiencies_detectades']) ?>" class="text-truncat">
                                            <?= e(mb_substr($a['deficiencies_detectades'], 0, 40)) ?>�?�
                                        </span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= classeBadge($a['estat_foliar']) ?>">
                                        <?= e($a['estat_foliar']) ?>
                                    </span>
                                </td>
                                <td class="cel-accions">
                                    <a href="<?= BASE_URL ?>modules/analisis/nou_analisi_foliar.php?editar=<?= (int)$a['id_analisi_foliar'] ?>"
                                       title="Editar" class="btn-accio btn-accio--editar">
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="nota-peu">Valors de referència orientatius per a fruita dolça. Consulta sempre l'informe del laboratori.</p>
        </div>

    </div><!-- /.pestanyes-analisi -->

</div>
<script>
(function () {
    'use strict';

    const botons     = document.querySelectorAll('.pestanya-boto');
    const continguts = document.querySelectorAll('.pestanya-contingut');

    function activar(pestanya) {
        botons.forEach(b => {
            const activa = b.dataset.pestanya === pestanya;
            b.classList.toggle('pestanya-boto--activa', activa);
            b.setAttribute('aria-selected', activa);
        });
        continguts.forEach(c => {
            c.hidden = c.id !== 'tab-' + pestanya;
            c.classList.toggle('pestanya-contingut--activa', !c.hidden);
        });
        // Persistim la pestanya activa a la URL sense recarregar
        history.replaceState(null, '', '#' + pestanya);
    }

    botons.forEach(b => b.addEventListener('click', () => activar(b.dataset.pestanya)));

    // Restaura la pestanya des del hash de la URL
    const hash = location.hash.replace('#', '');
    if (['sol', 'aigua', 'foliar'].includes(hash)) {
        activar(hash);
    }
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
