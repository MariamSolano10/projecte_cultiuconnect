<?php
/**
 * modules/operacions/operacio_nova.php — Formulari per registrar un tractament fitosanitari.
 *
 * Insereix a: aplicacio + detall_aplicacio_producte + desconta inventari_estoc
 * Processa a: processar_operacio.php (POST)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$sectors   = [];
$productes = [];
$error_db  = null;

try {
    $pdo = connectDB();

    $sectors = $pdo->query(
        "SELECT s.id_sector, s.nom,
                COALESCE(v.nom_varietat, '—') AS varietat,
                (SELECT COALESCE(SUM(ps.superficie_m2) / 10000, 0) FROM parcela_sector ps WHERE ps.id_sector = s.id_sector) AS superficie_ha
         FROM sector s
         LEFT JOIN plantacio pl ON pl.id_sector = s.id_sector AND pl.data_arrencada IS NULL
         LEFT JOIN varietat  v  ON v.id_varietat = pl.id_varietat
         ORDER BY s.nom ASC"
    )->fetchAll();

    // Carregar llista de maquinària
    $maquines = $pdo->query(
        "SELECT id_maquinaria, nom_maquina, tipus, capacitat_diposit_l
         FROM maquinaria
         ORDER BY tipus ASC, nom_maquina ASC"
    )->fetchAll();

    // Productes amb estoc disponible, agrupats per tipus
    $productes = $pdo->query(
        "SELECT pq.id_producte,
                pq.nom_comercial,
                pq.tipus,
                pq.unitat_mesura,
                COALESCE(SUM(ie.quantitat_disponible), 0) AS estoc_total
         FROM producte_quimic pq
         LEFT JOIN inventari_estoc ie ON ie.id_producte = pq.id_producte
                                     AND ie.quantitat_disponible > 0
         GROUP BY pq.id_producte, pq.nom_comercial, pq.tipus, pq.unitat_mesura
         HAVING estoc_total > 0
         ORDER BY pq.tipus ASC, pq.nom_comercial ASC"
    )->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] operacio_nova.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del formulari.';
}

// Agrupar productes per tipus per al <optgroup>
$productes_per_tipus = [];
foreach ($productes as $p) {
    $productes_per_tipus[$p['tipus']][] = $p;
}

$titol_pagina  = 'Nou Tractament';
$pagina_activa = 'tractaments';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-spray-can-sparkles" aria-hidden="true"></i>
            Registrar Tractament Fitosanitari
        </h1>
        <p class="descripcio-seccio">
            Qualsevol aplicació de producte queda registrada al quadern d'explotació
            i desconta automàticament l'estoc de l'inventari.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form id="form-tractament"
          method="POST"
          action="<?= BASE_URL ?>modules/operacions/processar_operacio.php"
          class="formulari-card"
          novalidate>

        <!-- ================================================
             BLOC 1: On i quan
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-map-pin" aria-hidden="true"></i>
                Ubicació, data i equip
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="parcela_aplicacio" class="form-label form-label--requerit">
                        Sector d'aplicació
                    </label>
                    <select id="parcela_aplicacio"
                            name="parcela_aplicacio"
                            class="form-select camp-requerit"
                            data-etiqueta="El sector"
                            required>
                        <option value="0" data-sup="0">— Selecciona un sector —</option>
                        <?php foreach ($sectors as $s): ?>
                            <option value="<?= (int)$s['id_sector'] ?>" data-sup="<?= (float)$s['superficie_ha'] ?>">
                                <?= e($s['nom']) ?>
                                <?= $s['varietat'] !== '—' ? '(' . e($s['varietat']) . ')' : '' ?>
                                - <?= number_format($s['superficie_ha'], 2, ',', '.') ?> ha
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="data_aplicacio" class="form-label form-label--requerit">
                        Data i hora d'aplicació
                    </label>
                    <input type="datetime-local"
                           id="data_aplicacio"
                           name="data_aplicacio"
                           class="form-input camp-requerit"
                           data-etiqueta="La data d'aplicació"
                           data-no-futur
                           value="<?= date('Y-m-d\TH:i') ?>"
                           required>
                </div>
            </div>

            <div class="form-fila-2 mt-l">
                <div class="form-grup">
                    <label for="id_maquinaria" class="form-label">Maquinària aplicadora</label>
                    <select id="id_maquinaria" name="id_maquinaria" class="form-select">
                        <option value="0" data-diposit="0">— Cap maquinària (o aplicació manual) —</option>
                        <?php foreach ($maquines as $m): ?>
                            <option value="<?= (int)$m['id_maquinaria'] ?>" data-diposit="<?= (float)$m['capacitat_diposit_l'] ?>">
                                <?= e($m['nom_maquina']) ?> (<?= e($m['tipus']) ?>)
                                <?= $m['capacitat_diposit_l'] ? ' - Dipòsit: ' . (float)$m['capacitat_diposit_l'] . 'L' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grup">
                    <label for="hores_maquinaria" class="form-label">Hores d'ús (per manteniment)</label>
                    <input type="number" step="0.1" min="0" id="hores_maquinaria" name="hores_maquinaria" class="form-input" placeholder="Ex: 2.5">
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 2: Producte
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-flask" aria-hidden="true"></i>
                Producte fitosanitari
            </legend>

            <div class="form-grup">
                <label for="producte_quimic" class="form-label form-label--requerit">
                    Producte
                </label>
                <select id="producte_quimic"
                        name="producte_quimic"
                        class="form-select camp-requerit"
                        data-etiqueta="El producte"
                        required>
                    <option value="0">— Selecciona un producte —</option>
                    <?php foreach ($productes_per_tipus as $tipus => $llista): ?>
                        <optgroup label="<?= e(ucfirst(strtolower($tipus))) ?>">
                            <?php foreach ($llista as $p): ?>
                                <option value="<?= (int)$p['id_producte'] ?>"
                                        data-unitat="<?= e($p['unitat_mesura']) ?>"
                                        data-estoc="<?= (float)$p['estoc_total'] ?>">
                                    <?= e($p['nom_comercial']) ?>
                                    (estoc: <?= number_format((float)$p['estoc_total'], 2, ',', '.') ?>
                                    <?= e($p['unitat_mesura']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($productes)): ?>
                    <span class="form-ajuda form-ajuda--avís">
                        <i class="fas fa-triangle-exclamation"></i>
                        No hi ha productes amb estoc disponible.
                        <a href="<?= BASE_URL ?>modules/estoc/estoc.php">Gestiona l'estoc.</a>
                    </span>
                <?php endif; ?>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="dosi" class="form-label form-label--requerit">
                        Dosi per hectàrea
                        <span id="unitat-dosi" class="form-label__unitat">(unitat)</span>
                    </label>
                    <input type="number"
                           id="dosi"
                           name="dosi"
                           class="form-input camp-requerit"
                           data-etiqueta="La dosi"
                           data-tipus="positiu"
                           step="0.01" min="0.01"
                           placeholder="Ex: 1.50"
                           required>
                </div>

                <div class="form-grup">
                    <label for="quantitat_total" class="form-label form-label--requerit">
                        Quantitat total consumida
                        <span class="form-label__unitat" id="unitat-total">(unitat)</span>
                    </label>
                    <input type="number"
                           id="quantitat_total"
                           name="quantitat_total"
                           class="form-input camp-requerit"
                           data-etiqueta="La quantitat total"
                           data-tipus="positiu"
                           step="0.01" min="0.01"
                           placeholder="Ex: 12.00 (calculat automàticament)"
                           required>
                    <span class="form-ajuda" id="avís-estoc"></span>
                </div>
            </div>

            <div id="calculadora-dosis" class="card card--calculadora">
                <h4 class="card__titol--compacte">
                    <i class="fas fa-calculator"></i> Calculadora Automàtica de Mescles
                </h4>
                <div class="fila-dades--compacta">
                    <div class="columna-flex-1">
                        <strong>Superfície a tractar:</strong> <span id="calc-sup">0.00 ha</span><br>
                        <strong>Producte estimat:</strong> <span id="calc-prod">0.00 unitats</span>
                    </div>
                    <div class="columna-flex-2">
                        <strong>Recomanació aplicació:</strong> <span id="calc-maq">Cap (Manual)</span><br>
                        <span id="calc-diposits">N/A</span>
                    </div>
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 3: Condicions i observacions
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-cloud-sun" aria-hidden="true"></i>
                Condicions i observacions
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="tipus_event" class="form-label">Tipus d'operació</label>
                    <select id="tipus_event" name="tipus_event" class="form-select">
                        <option value="Tractament fitosanitari">Tractament fitosanitari</option>
                        <option value="Adob foliar">Adob foliar</option>
                        <option value="Fertirrigació">Fertirrigació</option>
                        <option value="Tractament preventiu">Tractament preventiu</option>
                        <option value="Tractament curatiu">Tractament curatiu</option>
                        <option value="Altres operacions">Altres operacions</option>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="condicions_ambientals" class="form-label">
                        Condicions ambientals
                    </label>
                    <input type="text"
                           id="condicions_ambientals"
                           name="condicions_ambientals"
                           class="form-input"
                           placeholder="Ex: 22°C, humitat 55%, vent < 3 km/h"
                           maxlength="255">
                </div>
            </div>

            <div class="form-grup">
                <label for="comentaris" class="form-label">Observacions addicionals</label>
                <textarea id="comentaris"
                          name="comentaris"
                          class="form-textarea"
                          rows="3"
                          placeholder="Incidències observades, fenologia, equip de protecció..."></textarea>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal" id="boto-registrar">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                Registrar Tractament
            </button>
            <a href="<?= BASE_URL ?>modules/quadern/quadern.php" class="boto-secundari">
                <i class="fas fa-file-invoice" aria-hidden="true"></i>
                Veure Quadern
            </a>
        </div>

            <div id="zona-alertes-normativa" class="mt-l"></div>

    </form>

</div><!-- /.contingut-formulari -->

<script>
// Actualitza les etiquetes d'unitat i avisa si la quantitat supera l'estoc
const elFormSect = document.getElementById('parcela_aplicacio');
const elFormProd = document.getElementById('producte_quimic');
const elFormDosi = document.getElementById('dosi');
const elFormTot  = document.getElementById('quantitat_total');
const elFormMaq  = document.getElementById('id_maquinaria');

const uiCalcSup  = document.getElementById('calc-sup');
const uiCalcProd = document.getElementById('calc-prod');
const uiCalcMaq  = document.getElementById('calc-maq');
const uiCalcDipo = document.getElementById('calc-diposits');

function actualitzaCalculadora() {
    if (!elFormDosi || !elFormTot || !elFormProd || !elFormSect) return;

    const optSect = elFormSect.options[elFormSect.selectedIndex];
    const supHa   = parseFloat(optSect?.dataset.sup || 0);
    const dosiHa  = parseFloat(elFormDosi.value || 0);

    const optProd = elFormProd.options[elFormProd.selectedIndex];
    const unitat  = optProd?.dataset.unitat || '(unitat)';

    // Càlcul bàsic
    const totalCalc = supHa * dosiHa;

    if (totalCalc > 0) {
        // Omplim el camp obligatori de Quantitat Total si l'usuari no ho està teclejant directament
        if (document.activeElement !== elFormTot) {
            elFormTot.value = totalCalc.toFixed(2);
        }
    }

    uiCalcSup.textContent  = supHa.toFixed(2) + ' ha';
    uiCalcProd.textContent = totalCalc.toFixed(2) + ' ' + unitat;

    // Càlcul dipòsits
    const optMaq = elFormMaq.options[elFormMaq.selectedIndex];
    const volDep = parseFloat(optMaq?.dataset.diposit || 0);

    if (volDep > 0 && supHa > 0) {
        uiCalcMaq.textContent = optMaq.text.split('(')[0].trim() || 'Màquina X';
        // Volum de caldo referencial orientatiu per ha
        const VOL_CALDO_HA = 1000;
        const volumAiguaRecomanat = supHa * VOL_CALDO_HA;
        const numDips = Math.ceil(volumAiguaRecomanat / volDep);
        const prodPerDiposit = totalCalc / numDips;

        uiCalcDipo.innerHTML = `<strong>${numDips}</strong> dipòsit(s) ple(ns) necessari(s).<br>` +
            `<span class="text-ajuda-inline">Afegir <strong>${prodPerDiposit.toFixed(2)} ${unitat}</strong>  per cada ${volDep}L d'aigua.</span>`;
    } else {
        uiCalcMaq.textContent = 'Cap (Manual/Sense Dades)';
        uiCalcDipo.textContent = 'N/A';
    }

    if (optProd) comprovarEstoc(parseFloat(optProd.dataset.estoc || 0));
}

document.getElementById('producte_quimic').addEventListener('change', function () {
    const opt    = this.options[this.selectedIndex];
    const unitat = opt.dataset.unitat ?? '?';
    const estoc  = parseFloat(opt.dataset.estoc ?? 0);

    document.getElementById('unitat-dosi').textContent  = `(${unitat}/ha)`;
    document.getElementById('unitat-total').textContent = `(${unitat})`;

    actualitzaCalculadora();
});

elFormSect.addEventListener('change', actualitzaCalculadora);
elFormDosi.addEventListener('input', actualitzaCalculadora);
elFormMaq.addEventListener('change', actualitzaCalculadora);

document.getElementById('quantitat_total').addEventListener('input', function () {
    const opt   = document.getElementById('producte_quimic').options[
                    document.getElementById('producte_quimic').selectedIndex];
    const estoc = parseFloat(opt.dataset.estoc ?? 0);
    comprovarEstoc(estoc);
});

function comprovarEstoc(estocDisponible) {
    const quantitat = parseFloat(document.getElementById('quantitat_total').value ?? 0);
    const avis      = document.getElementById('avís-estoc');

    if (quantitat > 0 && quantitat > estocDisponible) {
        avis.textContent = `�s�️ Supera l'estoc disponible (${estocDisponible.toLocaleString('ca-ES', {minimumFractionDigits:2})} u.)`;
        avis.className   = 'form-ajuda form-ajuda--avis';
    } else {
        avis.textContent = '';
        avis.className   = 'form-ajuda';
    }
}
</script>

<script>
(function () {
    'use strict';

    const selSector  = document.getElementById('parcela_aplicacio');
    const selProd    = document.getElementById('producte_quimic');
    const inputDT    = document.getElementById('data_aplicacio');
    const inputDosi  = document.getElementById('dosi');
    const zona       = document.getElementById('zona-alertes-normativa');
    const botoDesar  = document.getElementById('boto-registrar');

    const URL_VERIFICAR = '<?= BASE_URL ?>modules/tractaments/verificar_normativa.php';
    let _timer = null;

    function renderAlerta(tipus, ico, text) {
        const mapCls = { error: 'flash--error', avis: 'flash--warning', ok: 'flash--success', info: 'flash--info' };
        const cls = mapCls[tipus] ?? 'flash--info';
        return `<div class="flash ${cls} flash--mt-s" role="alert">
            <i class="fas ${ico}" aria-hidden="true"></i>
            <span>${text}</span>
        </div>`;
    }

    function dataISODeDatetimeLocal(v) {
        if (!v) return '';
        // YYYY-MM-DDTHH:mm → YYYY-MM-DD
        return String(v).slice(0, 10);
    }

    function setBloqueig(b) {
        if (!botoDesar) return;
        botoDesar.disabled = !!b;
        botoDesar.classList.toggle('boto-bloquejat', !!b);
        botoDesar.title = b ? 'Corregeix els errors normatius abans de registrar.' : '';
    }

    async function verificar() {
        clearTimeout(_timer);
        _timer = setTimeout(async () => {
            if (!selSector.value || selSector.value === '0' || !selProd.value || selProd.value === '0' || !inputDT.value) {
                zona.innerHTML = '';
                setBloqueig(false);
                return;
            }

            zona.innerHTML = '<p class="text-suau"><i class="fas fa-spinner fa-spin"></i> Verificant normativa...</p>';

            const body = new URLSearchParams({
                id_producte:   selProd.value,
                id_sector:     selSector.value,
                data_prevista: dataISODeDatetimeLocal(inputDT.value),
                dosi_ha:       inputDosi.value || '',
            });

            try {
                const res = await fetch(URL_VERIFICAR, { method: 'POST', body });
                const data = await res.json();

                if (!data.ok) {
                    zona.innerHTML = renderAlerta('avis', 'fa-triangle-exclamation',
                        'No s\'ha pogut verificar la normativa: ' + (data.missatge || 'Error desconegut.'));
                    setBloqueig(false);
                    return;
                }

                const icones = { error:'fa-circle-xmark', avis:'fa-triangle-exclamation', ok:'fa-circle-check', info:'fa-circle-info' };
                zona.innerHTML = (data.alertes || []).map(a => renderAlerta(a.tipus, icones[a.tipus] ?? 'fa-circle-info', a.text)).join('');
                setBloqueig(!!data.bloqueig);

            } catch (e) {
                console.error('[CultiuConnect] verificar normativa:', e);
                zona.innerHTML = renderAlerta('avis', 'fa-triangle-exclamation', 'Error de connexió en verificar la normativa.');
                setBloqueig(false);
            }
        }, 400);
    }

    selSector?.addEventListener('change', verificar);
    selProd?.addEventListener('change', verificar);
    inputDT?.addEventListener('change', verificar);
    inputDosi?.addEventListener('input', verificar);

})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

