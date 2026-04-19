<?php
/**
 * modules/tractaments/nou_tractament_programat.php
 *
 * Formulari per crear o editar un tractament programat.
 * Inclou verificació normativa en temps real (AJAX) per a:
 *   - Termini de seguretat abans de la collita
 *   - Dosi màxima per hectàrea
 *   - Nombre màxim d'aplicacions per temporada
 *
 * GET ?editar=ID → mode edició.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Nou Tractament Programat';
$pagina_activa = 'tractaments_programats';

$tractament = null;
$sectors    = [];
$protocols  = [];
$productes  = [];
$error_db   = null;
$es_edicio  = false;

try {
    $pdo = connectDB();

    $sectors   = $pdo->query("SELECT id_sector, nom FROM sector ORDER BY nom")->fetchAll();
    $protocols = $pdo->query("
        SELECT id_protocol, nom_protocol, descripcio, condicions_ambientals
        FROM protocol_tractament
        ORDER BY nom_protocol
    ")->fetchAll();

    // Productes fitosanitaris i fertilitzants disponibles amb dades normatives
    $productes = $pdo->query("
        SELECT id_producte,
               nom_comercial,
               tipus,
               termini_seguretat_dies,
               dosi_max_ha,
               num_aplicacions_max,
               classificacio_tox,
               unitat_mesura,
               fitxa_seguretat_link
        FROM producte_quimic
        WHERE tipus IN ('Fitosanitari','Fertilitzant')
        ORDER BY tipus, nom_comercial
    ")->fetchAll();

    if (!empty($_GET['editar']) && is_numeric($_GET['editar'])) {
        $es_edicio    = true;
        $titol_pagina = 'Editar Tractament Programat';
        $id_edit      = (int)$_GET['editar'];

        $stmt = $pdo->prepare("SELECT * FROM tractament_programat WHERE id_programat = :id");
        $stmt->execute([':id' => $id_edit]);
        $tractament = $stmt->fetch();

        if (!$tractament) {
            set_flash('error', 'Tractament no trobat.');
            header('Location: ' . BASE_URL . 'modules/tractaments/tractaments_programats.php');
            exit;
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nou_tractament_programat.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar el formulari.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-form">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-<?= $es_edicio ? 'pen' : 'plus' ?>" aria-hidden="true"></i>
            <?= $es_edicio ? 'Editar Tractament Programat' : 'Nou Tractament Programat' ?>
        </h1>
        <a href="<?= BASE_URL ?>modules/tractaments/tractaments_programats.php"
           class="boto-secundari">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
        </a>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= BASE_URL ?>modules/tractaments/processar_tractament_programat.php"
          class="form-card"
          id="form-tractament"
          novalidate>

        <input type="hidden" name="accio" value="<?= $es_edicio ? 'editar' : 'crear' ?>">
        <?php if ($es_edicio): ?>
            <input type="hidden" name="id_programat" value="<?= (int)$tractament['id_programat'] ?>">
        <?php endif; ?>

        <!-- ── INFORMACIÓ PRINCIPAL ──────────────────────────────────────── -->
        <fieldset class="form-grup">
            <legend>Informació principal</legend>

            <div class="form-fila">
                <div class="form-camp">
                    <label for="tipus">Tipus <span class="obligatori">*</span></label>
                    <select name="tipus" id="tipus" required>
                        <?php foreach (['preventiu' => 'Preventiu', 'correctiu' => 'Correctiu', 'fertilitzacio' => 'Fertilització'] as $v => $l): ?>
                            <option value="<?= $v ?>"
                                <?= ($tractament['tipus'] ?? 'preventiu') === $v ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-camp">
                    <label for="data_prevista">Data prevista <span class="obligatori">*</span></label>
                    <input type="date"
                           name="data_prevista"
                           id="data_prevista"
                           required
                           value="<?= e($tractament['data_prevista'] ?? date('Y-m-d')) ?>">
                </div>

                <div class="form-camp">
                    <label for="dies_avis">
                        Dies d'avís previ
                        <span class="text-suau">(alerta quan falten X dies)</span>
                    </label>
                    <input type="number"
                           name="dies_avis"
                           id="dies_avis"
                           min="0" max="30"
                           value="<?= (int)($tractament['dies_avis'] ?? 3) ?>">
                </div>
            </div>

            <div class="form-fila">
                <div class="form-camp">
                    <label for="id_sector">Sector <span class="obligatori">*</span></label>
                    <select name="id_sector" id="id_sector" required>
                        <option value="">— Selecciona un sector —</option>
                        <?php foreach ($sectors as $s): ?>
                            <option value="<?= (int)$s['id_sector'] ?>"
                                <?= (int)($tractament['id_sector'] ?? 0) === (int)$s['id_sector'] ? 'selected' : '' ?>>
                                <?= e($s['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-camp">
                    <label for="id_protocol">Protocol de tractament</label>
                    <select name="id_protocol" id="id_protocol">
                        <option value="">— Sense protocol —</option>
                        <?php foreach ($protocols as $p): ?>
                            <option value="<?= (int)$p['id_protocol'] ?>"
                                    data-desc="<?= e($p['descripcio'] ?? '') ?>"
                                    data-cond="<?= e($p['condicions_ambientals'] ?? '') ?>"
                                <?= (int)($tractament['id_protocol'] ?? 0) === (int)$p['id_protocol'] ? 'selected' : '' ?>>
                                <?= e($p['nom_protocol']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Info del protocol seleccionat -->
            <div id="info-protocol"
                 class="flash flash--info bloc-ocult stack-s">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <div>
                    <strong id="protocol-desc"></strong>
                    <div id="protocol-cond" class="text-suau stack-s"></div>
                </div>
            </div>
        </fieldset>

        <!-- ── PRODUCTE I VERIFICACIÓ NORMATIVA ──────────────────────────── -->
        <fieldset class="form-grup" id="fieldset-producte">
            <legend>
                Producte fitosanitari / fertilitzant
                <span class="text-suau inline-subtle">
                    — verificació normativa automàtica
                </span>
            </legend>

            <div class="form-fila">
                <div class="form-camp form-camp--ample">
                    <label for="id_producte">Producte</label>
                    <select name="id_producte" id="id_producte">
                        <option value="">— Sense producte específic —</option>
                        <?php
                        $tipus_actual = '';
                        foreach ($productes as $prod):
                            // Separador de grup
                            if ($prod['tipus'] !== $tipus_actual):
                                if ($tipus_actual !== '') echo '</optgroup>';
                                echo '<optgroup label="' . e($prod['tipus']) . '">';
                                $tipus_actual = $prod['tipus'];
                            endif;
                        ?>
                            <option value="<?= (int)$prod['id_producte'] ?>"
                                    data-termini="<?= (int)($prod['termini_seguretat_dies'] ?? 0) ?>"
                                    data-dosi-max="<?= (float)($prod['dosi_max_ha'] ?? 0) ?>"
                                    data-apl-max="<?= (int)($prod['num_aplicacions_max'] ?? 0) ?>"
                                    data-unitat="<?= e($prod['unitat_mesura']) ?>"
                                    data-tox="<?= e($prod['classificacio_tox'] ?? '') ?>"
                                    data-fitxa="<?= e($prod['fitxa_seguretat_link'] ?? '') ?>"
                                <?= (int)($tractament['id_producte'] ?? 0) === (int)$prod['id_producte'] ? 'selected' : '' ?>>
                                <?= e($prod['nom_comercial']) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($tipus_actual !== '') echo '</optgroup>'; ?>
                    </select>
                </div>

                <div class="form-camp">
                    <label for="dosi_prevista_ha">
                        Dosi prevista
                        <span class="text-suau" id="label-unitat"></span>
                    </label>
                    <input type="number"
                           name="dosi_prevista_ha"
                           id="dosi_prevista_ha"
                           min="0"
                           step="0.01"
                           value="<?= e($tractament['dosi_prevista_ha'] ?? '') ?>"
                           placeholder="l/ha o kg/ha">
                </div>
            </div>

            <!-- Resum normatiu del producte seleccionat -->
            <div id="resum-normatiu" class="bloc-ocult stack-m">
                <div class="resum-normatiu-grid">
                    <div class="resum-item" id="rn-termini">
                        <i class="fas fa-calendar-xmark"></i>
                        <span class="rn-label">Termini seguretat</span>
                        <strong class="rn-valor" id="rn-termini-val">—</strong>
                    </div>
                    <div class="resum-item" id="rn-dosi">
                        <i class="fas fa-flask"></i>
                        <span class="rn-label">Dosi màxima</span>
                        <strong class="rn-valor" id="rn-dosi-val">—</strong>
                    </div>
                    <div class="resum-item" id="rn-apl">
                        <i class="fas fa-repeat"></i>
                        <span class="rn-label">Aplicacions màx.</span>
                        <strong class="rn-valor" id="rn-apl-val">—</strong>
                    </div>
                    <div class="resum-item" id="rn-tox">
                        <i class="fas fa-skull-crossbones"></i>
                        <span class="rn-label">Toxicitat</span>
                        <strong class="rn-valor" id="rn-tox-val">—</strong>
                    </div>
                </div>
                <div id="rn-fitxa" class="bloc-ocult stack-s">
                    <a id="rn-fitxa-link" href="#" target="_blank" rel="noopener noreferrer" class="link-extern">
                        <i class="fas fa-file-pdf"></i> Fitxa de seguretat
                    </a>
                </div>
            </div>

            <!-- Zona d'alertes normatives (resultat AJAX) -->
            <div id="zona-alertes" class="stack-l" aria-live="polite"></div>

        </fieldset>

        <!-- ── DETALLS ────────────────────────────────────────────────────── -->
        <fieldset class="form-grup">
            <legend>Detalls</legend>

            <div class="form-camp">
                <label for="motiu">Motiu / Descripció</label>
                <input type="text"
                       name="motiu"
                       id="motiu"
                       value="<?= e($tractament['motiu'] ?? '') ?>"
                       placeholder="Ex: Tractament preventiu contra el míldiu en floració">
            </div>

            <div class="form-camp">
                <label for="observacions">Observacions addicionals</label>
                <textarea name="observacions"
                          id="observacions"
                          rows="3"
                          placeholder="Notes, condicions especials, materials necessaris..."><?= e($tractament['observacions'] ?? '') ?></textarea>
            </div>
        </fieldset>

        <!-- ── ESTAT (només en edició) ────────────────────────────────────── -->
        <?php if ($es_edicio): ?>
        <fieldset class="form-grup">
            <legend>Estat</legend>
            <div class="form-camp">
                <label for="estat">Estat del tractament</label>
                <select name="estat" id="estat">
                    <?php foreach (['pendent' => 'Pendent', 'completat' => 'Completat', 'cancel·lat' => 'Cancel·lat'] as $v => $l): ?>
                        <option value="<?= $v ?>"
                            <?= ($tractament['estat'] ?? 'pendent') === $v ? 'selected' : '' ?>>
                            <?= $l ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>
        <?php endif; ?>

        <div class="form-accions">
            <button type="submit" class="boto-principal" id="boto-desar">
                <i class="fas fa-save" aria-hidden="true"></i>
                <?= $es_edicio ? 'Desar canvis' : 'Programar tractament' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/tractaments/tractaments_programats.php"
               class="boto-secundari">
                Cancel·lar
            </a>
        </div>

    </form>
</div>

<!-- ── ESTILS ADDICIONALS ────────────────────────────────────────────────── -->


<!-- ── JAVASCRIPT ────────────────────────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    // ── Elements del DOM ──────────────────────────────────────────────────────
    const selProtocol  = document.getElementById('id_protocol');
    const infoProtocol = document.getElementById('info-protocol');
    const protDesc     = document.getElementById('protocol-desc');
    const protCond     = document.getElementById('protocol-cond');

    const selProducte  = document.getElementById('id_producte');
    const inputDosi    = document.getElementById('dosi_prevista_ha');
    const inputData    = document.getElementById('data_prevista');
    const selSector    = document.getElementById('id_sector');
    const labelUnitat  = document.getElementById('label-unitat');

    const resumNormatiu = document.getElementById('resum-normatiu');
    const zonaAlertes   = document.getElementById('zona-alertes');
    const botoDesar     = document.getElementById('boto-desar');

    // Resum normatiu (fitxes)
    const rnTerminiVal = document.getElementById('rn-termini-val');
    const rnDosiVal    = document.getElementById('rn-dosi-val');
    const rnAplVal     = document.getElementById('rn-apl-val');
    const rnToxVal     = document.getElementById('rn-tox-val');
    const rnFitxa      = document.getElementById('rn-fitxa');
    const rnFitxaLink  = document.getElementById('rn-fitxa-link');

    // ── 1. Protocol: mostra descripció i condicions ───────────────────────────
    function actualitzarProtocol() {
        const opt  = selProtocol.options[selProtocol.selectedIndex];
        const desc = opt?.dataset?.desc || '';
        const cond = opt?.dataset?.cond || '';
        if (selProtocol.value && (desc || cond)) {
            protDesc.textContent = desc;
            protCond.textContent = cond ? 'Condicions: ' + cond : '';
            infoProtocol.style.display = 'flex';
        } else {
            infoProtocol.style.display = 'none';
        }
    }
    selProtocol.addEventListener('change', actualitzarProtocol);
    actualitzarProtocol();

    // ── 2. Producte: actualitza el resum normatiu i activa la verificació ─────
    function actualitzarResumProducte() {
        const opt = selProducte.options[selProducte.selectedIndex];
        if (!selProducte.value) {
            resumNormatiu.style.display = 'none';
            labelUnitat.textContent = '';
            return;
        }

        const termini  = opt.dataset.termini;
        const dosiMax  = opt.dataset.dosiMax;
        const aplMax   = opt.dataset.aplMax;
        const unitat   = opt.dataset.unitat;
        const tox      = opt.dataset.tox;
        const fitxa    = opt.dataset.fitxa;

        rnTerminiVal.textContent = termini ? termini + ' dies'       : 'No especificat';
        rnDosiVal.textContent    = dosiMax ? dosiMax + ' ' + unitat + '/ha' : 'No especificat';
        rnAplVal.textContent     = aplMax  ? aplMax + ' /temporada'  : 'No especificat';
        rnToxVal.textContent     = tox     || 'No especificada';
        labelUnitat.textContent  = unitat  ? '(' + unitat + '/ha)'   : '';

        if (fitxa) {
            rnFitxaLink.href       = fitxa;
            rnFitxa.style.display  = 'block';
        } else {
            rnFitxa.style.display  = 'none';
        }

        resumNormatiu.style.display = 'block';
    }
    selProducte.addEventListener('change', () => {
        actualitzarResumProducte();
        verificarNormativa();
    });
    actualitzarResumProducte(); // Per si estem en mode edició

    // ── 3. Verificació normativa (AJAX) ───────────────────────────────────────
    let _timerNormativa = null;
    const URL_VERIFICAR = '<?= BASE_URL ?>modules/tractaments/verificar_normativa.php';

    function verificarNormativa() {
        clearTimeout(_timerNormativa);

        // Només disparem si hi ha producte + sector + data
        if (!selProducte.value || !selSector.value || !inputData.value) {
            zonaAlertes.innerHTML = '';
            botoDesar.disabled = false;
            botoDesar.classList.remove('boto-bloquejat');
            return;
        }

        // Petit debounce per evitar cridades contínues mentre s'escriu la dosi
        _timerNormativa = setTimeout(async () => {
            zonaAlertes.innerHTML = '<p class="verificant-badge"><i class="fas fa-spinner fa-spin"></i> Verificant normativa...</p>';

            const body = new URLSearchParams({
                id_producte:   selProducte.value,
                id_sector:     selSector.value,
                data_prevista: inputData.value,
                dosi_ha:       inputDosi.value || '',
            });

            try {
                const res  = await fetch(URL_VERIFICAR, { method: 'POST', body });
                const data = await res.json();

                if (!data.ok) {
                    zonaAlertes.innerHTML = renderAlerta('avis', 'fa-triangle-exclamation',
                        'No s\'ha pogut verificar la normativa: ' + (data.missatge || 'Error desconegut.'));
                    return;
                }

                // Renderitza totes les alertes
                zonaAlertes.innerHTML = data.alertes.map(a => {
                    const icones = {
                        error: 'fa-circle-xmark',
                        avis:  'fa-triangle-exclamation',
                        ok:    'fa-circle-check',
                        info:  'fa-circle-info',
                    };
                    return renderAlerta(a.tipus, icones[a.tipus] ?? 'fa-circle-info', a.text);
                }).join('');

                // Bloqueja el botó si hi ha errors normatius
                if (data.bloqueig) {
                    botoDesar.disabled = true;
                    botoDesar.classList.add('boto-bloquejat');
                    botoDesar.title = 'Corregeix els errors normatius abans de desar.';
                } else {
                    botoDesar.disabled = false;
                    botoDesar.classList.remove('boto-bloquejat');
                    botoDesar.title = '';
                }

            } catch (err) {
                console.error('[CultiuConnect] verificarNormativa:', err);
                zonaAlertes.innerHTML = renderAlerta('avis', 'fa-triangle-exclamation',
                    'Error de connexió en verificar la normativa. Comprova la xarxa.');
            }
        }, 400); // 400ms de debounce
    }

    function renderAlerta(tipus, ico, text) {
        return `<div class="alerta-normativa alerta-normativa--${tipus}" role="alert">
                    <i class="fas ${ico}" aria-hidden="true"></i>
                    <span>${text}</span>
                </div>`;
    }

    // Disparadors de la verificació
    inputData.addEventListener('change',  verificarNormativa);
    selSector.addEventListener('change',  verificarNormativa);
    inputDosi.addEventListener('input',   verificarNormativa);

    // Si estem en edició i ja hi ha valors precarregats, verificar en càrrega
    if (selProducte.value && selSector.value && inputData.value) {
        verificarNormativa();
    }

})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

