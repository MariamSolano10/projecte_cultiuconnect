<?php
/**
 * modules/previsio/nova_previsio.php — Formulari per crear o editar una previsió de collita.
 *
 * GET ?editar=ID        → mode edició
 * GET ?temporada=YYYY   → preselecciona la temporada
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Nova Previsió de Collita';
$pagina_activa = 'previsio';

$previsio    = null;
$plantacions = [];
$error_db    = null;
$es_edicio   = false;

$temporada_actual = (int)date('Y');
$presel_temporada = is_numeric($_GET['temporada'] ?? '') ? (int)$_GET['temporada'] : $temporada_actual;

try {
    $pdo = connectDB();

    // Plantacions actives amb les seves dades de producció per al càlcul automàtic
    $plantacions = $pdo->query("
        SELECT
            pl.id_plantacio,
            pl.num_arbres_plantats,
            pl.num_falles,
            pl.data_plantacio,
            v.nom_varietat,
            v.productivitat_mitjana_esperada,
            e.nom_comu,
            s.nom AS nom_sector
        FROM plantacio pl
        JOIN varietat v ON v.id_varietat = pl.id_varietat
        JOIN especie  e ON e.id_especie  = v.id_especie
        JOIN sector   s ON s.id_sector   = pl.id_sector
        WHERE pl.data_arrencada IS NULL
        ORDER BY s.nom, v.nom_varietat
    ")->fetchAll();

    if (!empty($_GET['editar']) && is_numeric($_GET['editar'])) {
        $es_edicio    = true;
        $titol_pagina = 'Editar Previsió de Collita';
        $id_edit      = (int)$_GET['editar'];

        $stmt = $pdo->prepare("SELECT * FROM previsio_collita WHERE id_previsio = :id");
        $stmt->execute([':id' => $id_edit]);
        $previsio = $stmt->fetch();

        if (!$previsio) {
            set_flash('error', 'Previsió no trobada.');
            header('Location: ' . BASE_URL . 'modules/previsio/previsio.php');
            exit;
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nova_previsio.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar el formulari.';
}

// Codifiquem les dades de plantació per al càlcul automàtic en JS
$plantacions_js = json_encode(array_column($plantacions, null, 'id_plantacio'));

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-form">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-<?= $es_edicio ? 'pen' : 'plus' ?>" aria-hidden="true"></i>
            <?= $es_edicio ? 'Editar Previsió de Collita' : 'Nova Previsió de Collita' ?>
        </h1>
        <a href="<?= BASE_URL ?>modules/previsio/previsio.php" class="boto-secundari">
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
          action="<?= BASE_URL ?>modules/previsio/processar_previsio.php"
          class="form-card"
          novalidate>

        <input type="hidden" name="accio" value="<?= $es_edicio ? 'editar' : 'crear' ?>">
        <?php if ($es_edicio): ?>
            <input type="hidden" name="id_previsio" value="<?= (int)$previsio['id_previsio'] ?>">
        <?php endif; ?>

        <!-- PLANTACIÓ I TEMPORADA -->
        <fieldset class="form-grup">
            <legend>Plantació i temporada</legend>

            <div class="form-fila">
                <div class="form-camp form-camp--grow-2">
                    <label for="id_plantacio">Plantació <span class="obligatori">*</span></label>
                    <select name="id_plantacio" id="id_plantacio" required>
                        <option value="">— Selecciona una plantació —</option>
                        <?php foreach ($plantacions as $pl): ?>
                            <option value="<?= (int)$pl['id_plantacio'] ?>"
                                <?= (int)($previsio['id_plantacio'] ?? 0) === (int)$pl['id_plantacio'] ? 'selected' : '' ?>>
                                <?= e($pl['nom_sector'] . ' — ' . $pl['nom_comu'] . ' ' . $pl['nom_varietat']) ?>
                                (<?= (int)($pl['num_arbres_plantats'] ?? 0) - (int)($pl['num_falles'] ?? 0) ?> arbres)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-camp">
                    <label for="temporada">Temporada <span class="obligatori">*</span></label>
                    <input type="number"
                           name="temporada"
                           id="temporada"
                           min="2000" max="2100"
                           required
                           value="<?= (int)($previsio['temporada'] ?? $presel_temporada) ?>">
                </div>

                <div class="form-camp">
                    <label for="data_previsio">Data de la previsió <span class="obligatori">*</span></label>
                    <input type="date"
                           name="data_previsio"
                           id="data_previsio"
                           required
                           value="<?= e($previsio['data_previsio'] ?? date('Y-m-d')) ?>">
                </div>
            </div>

            <!-- Info automàtica de la plantació seleccionada -->
            <div id="info-plantacio"
                 class="flash flash--info flash--stack-s">
                <i class="fas fa-seedling" aria-hidden="true"></i>
                <div id="info-plantacio-text"></div>
            </div>
        </fieldset>

        <!-- ESTIMACIÓ DE PRODUCCIÓ -->
        <fieldset class="form-grup">
            <legend>Estimació de producció</legend>

            <div class="form-fila">
                <div class="form-camp">
                    <label for="produccio_estimada_kg">
                        Producció estimada total (kg)
                    </label>
                    <input type="number"
                           name="produccio_estimada_kg"
                           id="produccio_estimada_kg"
                           min="0" step="0.01"
                           value="<?= e($previsio['produccio_estimada_kg'] ?? '') ?>"
                           placeholder="Ex: 45000">
                </div>

                <div class="form-camp">
                    <label for="produccio_per_arbre_kg">kg per arbre</label>
                    <input type="number"
                           name="produccio_per_arbre_kg"
                           id="produccio_per_arbre_kg"
                           min="0" step="0.01"
                           value="<?= e($previsio['produccio_per_arbre_kg'] ?? '') ?>"
                           placeholder="Ex: 25">
                </div>

                <div class="form-camp">
                    <label for="mo_necessaria_jornal">Mà d'obra estimada (jornals)</label>
                    <input type="number"
                           name="mo_necessaria_jornal"
                           id="mo_necessaria_jornal"
                           min="0"
                           value="<?= e($previsio['mo_necessaria_jornal'] ?? '') ?>"
                           placeholder="Ex: 12">
                </div>
            </div>

            <!-- Calculadora d'estimació automàtica -->
            <div class="flash flash--info flash--stack-m">
                <i class="fas fa-calculator" aria-hidden="true"></i>
                <div>
                    <strong>Càlcul automàtic:</strong> omplint <em>kg per arbre</em> s'actualitzarà
                    la producció total automàticament (i viceversa).
                    <span id="calc-resultat" class="badge--space-left"></span>
                </div>
            </div>

            <!-- Suggeriment predictiu basat en històric -->
            <div id="bloc-suggeriment" class="flash flash--info flash--stack-l">
                <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
                <div class="filtres-layout">
                    <div>
                        <strong>Suggeriment automàtic (model):</strong>
                        <span id="suggeriment-text" class="text-suau">Selecciona una plantació i temporada per calcular una previsió basada en collites anteriors.</span>
                        <div id="suggeriment-avisos" class="text-suau stack-s"></div>
                    </div>
                    <div class="flex-inline-actions">
                        <button type="button" id="btn-calcular" class="boto-secundari">
                            <i class="fas fa-bolt" aria-hidden="true"></i> Calcular
                        </button>
                        <button type="button" id="btn-aplicar" class="boto-principal" disabled>
                            <i class="fas fa-check" aria-hidden="true"></i> Aplicar al formulari
                        </button>
                    </div>
                </div>
            </div>
        </fieldset>

        <!-- DATES DE COLLITA -->
        <fieldset class="form-grup">
            <legend>Dates estimades de collita</legend>

            <div class="form-fila">
                <div class="form-camp">
                    <label for="data_inici_collita_estimada">Inici de la collita</label>
                    <input type="date"
                           name="data_inici_collita_estimada"
                           id="data_inici_collita_estimada"
                           value="<?= e($previsio['data_inici_collita_estimada'] ?? '') ?>">
                </div>

                <div class="form-camp">
                    <label for="data_fi_collita_estimada">Fi de la collita</label>
                    <input type="date"
                           name="data_fi_collita_estimada"
                           id="data_fi_collita_estimada"
                           value="<?= e($previsio['data_fi_collita_estimada'] ?? '') ?>">
                </div>

                <div class="form-camp">
                    <label for="calibre_previst">Calibre previst</label>
                    <input type="text"
                           name="calibre_previst"
                           id="calibre_previst"
                           value="<?= e($previsio['calibre_previst'] ?? '') ?>"
                           placeholder="Ex: 70/75 mm, Categoria I">
                </div>

                <div class="form-camp">
                    <label for="qualitat_prevista">Qualitat prevista</label>
                    <select name="qualitat_prevista" id="qualitat_prevista">
                        <option value="">— Sense especificar —</option>
                        <?php foreach (['Extra', 'Primera', 'Segona', 'Industrial'] as $q): ?>
                            <option value="<?= $q ?>"
                                <?= ($previsio['qualitat_prevista'] ?? '') === $q ? 'selected' : '' ?>>
                                <?= $q ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <!-- FACTORS CONSIDERATS -->
        <fieldset class="form-grup">
            <legend>Factors considerats</legend>
            <div class="form-camp">
                <label for="factors_considerats">
                    Floració, quallat, clima, incidències, pràctiques culturals...
                </label>
                <textarea name="factors_considerats"
                          id="factors_considerats"
                          rows="5"
                          placeholder="Descriu els factors que han condicionat aquesta estimació: intensitat de floració, percentatge de quallat observat, previsions meteorològiques, incidències de plagues, poda realitzada, reg i fertilització..."><?= e($previsio['factors_considerats'] ?? '') ?></textarea>
            </div>
        </fieldset>

        <div class="form-accions">
            <button type="submit" class="boto-principal">
                <i class="fas fa-save" aria-hidden="true"></i>
                <?= $es_edicio ? 'Desar canvis' : 'Crear previsió' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/previsio/previsio.php" class="boto-secundari">
                Cancel·lar
            </a>
        </div>

    </form>
</div>

<script>
const PLANTACIONS = <?= $plantacions_js ?>;

const selPlantacio   = document.getElementById('id_plantacio');
const inpTemporada   = document.getElementById('temporada');
const infoBox        = document.getElementById('info-plantacio');
const infoText       = document.getElementById('info-plantacio-text');
const inpTotal       = document.getElementById('produccio_estimada_kg');
const inpPerArbre    = document.getElementById('produccio_per_arbre_kg');
const calcResultat   = document.getElementById('calc-resultat');

const btnCalcular    = document.getElementById('btn-calcular');
const btnAplicar     = document.getElementById('btn-aplicar');
const sugText        = document.getElementById('suggeriment-text');
const sugAvisos      = document.getElementById('suggeriment-avisos');

let arbresEfectius = 0;
let suggeriment = null;

// Mostra info de la plantació i preomple kg/arbre des de productivitat mitjana
selPlantacio.addEventListener('change', function () {
    const pl = PLANTACIONS[this.value];
    if (!pl) { infoBox.style.display = 'none'; arbresEfectius = 0; return; }

    arbresEfectius = (parseInt(pl.num_arbres_plantats) || 0) - (parseInt(pl.num_falles) || 0);
    const prodMitjana = parseFloat(pl.productivitat_mitjana_esperada) || 0;

    let html = `<strong>${pl.nom_sector} — ${pl.nom_comu} ${pl.nom_varietat}</strong><br>`;
    html += `Arbres efectius: <strong>${arbresEfectius}</strong>`;
    if (prodMitjana > 0) {
        html += ` · Productivitat mitjana: <strong>${prodMitjana.toLocaleString('ca')} kg/ha</strong>`;
    }

    infoText.innerHTML = html;
    infoBox.style.display = 'flex';

    // Si no hi ha valor manual, suggerim un kg/arbre basat en l'històric
    if (!inpPerArbre.value && prodMitjana > 0 && arbresEfectius > 0) {
        // Estimació: productivitat_mitjana_esperada és kg/ha, però l'usem com a referència
        // No posem valor automàtic per no confondre, però recalculem si ja n'hi ha
    }

    recalcular();
});

// Recalcula suggeriment quan canvia temporada/plantació
async function calcularSuggeriment() {
    suggeriment = null;
    btnAplicar.disabled = true;
    sugAvisos.textContent = '';

    const idp = selPlantacio.value;
    const temp = inpTemporada.value;
    if (!idp || !temp) {
        sugText.textContent = 'Selecciona una plantació i temporada per calcular una previsió basada en collites anteriors.';
        return;
    }

    sugText.textContent = 'Calculant amb l’històric...';

    try {
        const url = `<?= BASE_URL ?>api/api_previsio_suggerida.php?id_plantacio=${encodeURIComponent(idp)}&temporada=${encodeURIComponent(temp)}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();

        if (!data || !data.ok) {
            throw new Error(data?.error || 'No s’ha pogut calcular el suggeriment.');
        }

        suggeriment = data;

        if (!data.suggerit_total_kg) {
            sugText.textContent = 'No hi ha dades suficients per proposar una estimació automàtica.';
            if (Array.isArray(data.avisos) && data.avisos.length) {
                sugAvisos.innerHTML = data.avisos.map(a => `- ${a}`).join('<br>');
            }
            return;
        }

        const metodeLbl = data.metode === 'plantacio' ? 'plantació' : 'sector+varietat';
        const rang = (data.rang_min_kg && data.rang_max_kg)
            ? ` (rang orientatiu ${Number(data.rang_min_kg).toLocaleString('ca')}–${Number(data.rang_max_kg).toLocaleString('ca')} kg)`
            : '';

        sugText.innerHTML =
            `Suggerit: <strong>${Number(data.suggerit_total_kg).toLocaleString('ca')} kg</strong>` +
            (data.suggerit_kg_arbre ? ` · <strong>${Number(data.suggerit_kg_arbre).toLocaleString('ca')} kg/arbre</strong>` : '') +
            `${rang} · Mètode: <strong>${metodeLbl}</strong>.`;

        if (Array.isArray(data.avisos) && data.avisos.length) {
            sugAvisos.innerHTML = data.avisos.map(a => `- ${a}`).join('<br>');
        } else {
            sugAvisos.textContent = '';
        }

        btnAplicar.disabled = false;

    } catch (e) {
        sugText.textContent = 'Error en calcular el suggeriment automàtic.';
        sugAvisos.textContent = e?.message || '';
    }
}

function aplicarSuggeriment() {
    if (!suggeriment?.suggerit_total_kg) return;

    // Prioritat: si hi ha arbres, posem kg/arbre i deixem que recalculi el total
    if (arbresEfectius > 0 && suggeriment.suggerit_kg_arbre) {
        inpPerArbre.value = Number(suggeriment.suggerit_kg_arbre).toFixed(2);
        recalcular();
    } else {
        inpTotal.value = Number(suggeriment.suggerit_total_kg).toFixed(0);
        if (arbresEfectius > 0) {
            const pa = Number(suggeriment.suggerit_total_kg) / arbresEfectius;
            inpPerArbre.value = isFinite(pa) ? pa.toFixed(2) : '';
        }
    }
}

btnCalcular?.addEventListener('click', calcularSuggeriment);
btnAplicar?.addEventListener('click', aplicarSuggeriment);
inpTemporada?.addEventListener('change', () => { btnAplicar.disabled = true; });

// Quan canvia total → calcula per arbre
inpTotal.addEventListener('input', function () {
    if (!arbresEfectius || !this.value) { calcResultat.textContent = ''; return; }
    const perArbre = parseFloat(this.value) / arbresEfectius;
    inpPerArbre.value = perArbre.toFixed(2);
    calcResultat.textContent = `→ ${perArbre.toFixed(2)} kg/arbre`;
});

// Quan canvia per arbre → calcula total
inpPerArbre.addEventListener('input', function () {
    recalcular();
});

function recalcular() {
    if (!arbresEfectius || !inpPerArbre.value) { calcResultat.textContent = ''; return; }
    const total = parseFloat(inpPerArbre.value) * arbresEfectius;
    inpTotal.value = total.toFixed(0);
    calcResultat.textContent = `→ ${total.toLocaleString('ca')} kg totals estimats`;
}

// Inicialitzem si estem en mode edició
if (selPlantacio.value) {
    selPlantacio.dispatchEvent(new Event('change'));
    // Restaurem els valors del formulari (el canvi els hauria sobreescrit)
    <?php if ($es_edicio): ?>
    inpTotal.value    = '<?= e($previsio['produccio_estimada_kg'] ?? '') ?>';
    inpPerArbre.value = '<?= e($previsio['produccio_per_arbre_kg'] ?? '') ?>';
    <?php endif; ?>
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
