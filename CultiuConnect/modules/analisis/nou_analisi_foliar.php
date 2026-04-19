<?php
/**
 * modules/analisis/nou_analisi_foliar.php — Formulari per registrar o editar
 * una analítica foliar de deficiències nutricionals.
 *
 * Mapatge formulari → columnes reals de `analisi_foliar`:
 *   id_sector, id_plantacio (opcional), data_analisi, estat_fenologic
 *   N, P, K, Ca, Mg (macronutrients %)
 *   Fe, Mn, Zn, Cu, B (micronutrients ppm)
 *   deficiencies_detectades, recomanacions, observacions
 *
 * Mode edició: ?editar=ID
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$sectors    = [];
$plantacions = [];
$error_db   = null;
$dades      = [];

$editar_id = sanitize_int($_GET['editar'] ?? null);

try {
    $pdo = connectDB();

    $sectors = $pdo->query("
        SELECT s.id_sector, s.nom
        FROM sector s
        ORDER BY s.nom ASC
    ")->fetchAll();

    // Plantacions actives (sense data_arrencada) per al selector opcional
    $plantacions = $pdo->query("
        SELECT pl.id_plantacio, pl.id_sector,
               s.nom AS nom_sector,
               v.nom_varietat
        FROM plantacio pl
        JOIN sector  s ON s.id_sector  = pl.id_sector
        JOIN varietat v ON v.id_varietat = pl.id_varietat
        WHERE pl.data_arrencada IS NULL
        ORDER BY s.nom, v.nom_varietat
    ")->fetchAll();

    if ($editar_id) {
        $stmt = $pdo->prepare("SELECT * FROM analisi_foliar WHERE id_analisi_foliar = :id");
        $stmt->execute([':id' => $editar_id]);
        $dades = $stmt->fetch() ?: [];
        if (empty($dades)) {
            set_flash('error', 'Analítica foliar no trobada.');
            header('Location: ' . BASE_URL . 'modules/analisis/analisis_lab.php#foliar');
            exit;
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nou_analisi_foliar.php GET: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del formulari.';
}

$titol_pagina  = $editar_id ? 'Editar Analítica Foliar' : 'Nova Analítica Foliar';
$pagina_activa = 'monitoratge';
require_once __DIR__ . '/../../includes/header.php';

$etiquetes_fenologic = [
    'repos_hivernal'   => 'Repòs hivernal',
    'brotacio'         => 'Brotació',
    'floracio'         => 'Floració',
    'creixement_fruit' => 'Creixement de fruit',
    'maduresa'         => 'Maduresa',
    'post_collita'     => 'Post-collita',
];
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-leaf" aria-hidden="true"></i>
            <?= $editar_id ? 'Editar Analítica Foliar' : 'Registrar Nova Analítica Foliar' ?>
        </h1>
        <p class="descripcio-seccio">
            Introdueix els resultats de l'anàlisi de teixit foliar del laboratori.
            Permet diagnosticar deficiències nutricionals en el cultiu per corregir-les
            amb fertirrigació o aplicacions foliars.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form id="form-analisi-foliar"
          method="POST"
          action="<?= BASE_URL ?>modules/analisis/processar_analisi_foliar.php"
          class="formulari-card"
          novalidate>

        <?php if ($editar_id): ?>
            <input type="hidden" name="mode"      value="editar">
            <input type="hidden" name="editar_id" value="<?= (int)$editar_id ?>">
        <?php endif; ?>

        <!-- ── BLOC 1: Identificació ──────────────────────────────────────── -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-map-pin"></i> Identificació de la mostra
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="id_sector" class="form-label form-label--requerit">Sector</label>
                    <select id="id_sector" name="id_sector"
                            class="form-select camp-requerit"
                            data-etiqueta="El sector"
                            required>
                        <option value="0">— Selecciona un sector —</option>
                        <?php foreach ($sectors as $s):
                            $sel = (int)($_POST['id_sector'] ?? $dades['id_sector'] ?? 0);
                        ?>
                            <option value="<?= (int)$s['id_sector'] ?>"
                                <?= $sel === (int)$s['id_sector'] ? 'selected' : '' ?>>
                                <?= e($s['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="id_plantacio" class="form-label">
                        Plantació concreta
                        <span class="form-ajuda form-inline-display">(opcional)</span>
                    </label>
                    <select id="id_plantacio" name="id_plantacio" class="form-select">
                        <option value="">— Sector general —</option>
                        <?php foreach ($plantacions as $pl):
                            $sel_pl = (int)($_POST['id_plantacio'] ?? $dades['id_plantacio'] ?? 0);
                        ?>
                            <option value="<?= (int)$pl['id_plantacio'] ?>"
                                    data-sector="<?= (int)$pl['id_sector'] ?>"
                                <?= $sel_pl === (int)$pl['id_plantacio'] ? 'selected' : '' ?>>
                                <?= e($pl['nom_sector']) ?> — <?= e($pl['nom_varietat']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-ajuda">Afina si el sector té diverses varietats</span>
                </div>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_analisi" class="form-label form-label--requerit">Data de mostreig</label>
                    <input type="date"
                           id="data_analisi" name="data_analisi"
                           class="form-input camp-requerit"
                           data-etiqueta="La data d'anàlisi"
                           data-no-futur
                           value="<?= e((string)($_POST['data_analisi'] ?? $dades['data_analisi'] ?? date('Y-m-d'))) ?>"
                           required>
                </div>

                <div class="form-grup">
                    <label for="estat_fenologic" class="form-label form-label--requerit">
                        Estat fenològic en el moment del mostreig
                    </label>
                    <select id="estat_fenologic" name="estat_fenologic"
                            class="form-select camp-requerit" required>
                        <option value="">— Selecciona l'estat —</option>
                        <?php foreach ($etiquetes_fenologic as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= e((string)($_POST['estat_fenologic'] ?? $dades['estat_fenologic'] ?? '')) === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-ajuda">L'estat fenològic condiciona els valors de referència</span>
                </div>
            </div>
        </fieldset>

        <!-- ── BLOC 2: Macronutrients (%) ─────────────────────────────────── -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-leaf"></i> Macronutrients (% sobre matèria seca)
            </legend>

            <div class="alert-referencia flash flash--info flash--mb-m">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                Valors orientatius de referència per a fruita dolça en floració:
                N 2.0–3.0% · P 0.15–0.35% · K 1.5–2.5% · Ca 1.2–2.5% · Mg 0.25–0.50%
            </div>

            <div class="form-fila-3">
                <div class="form-grup">
                    <label for="N" class="form-label">Nitrogen (N %)</label>
                    <input type="number" id="N" name="N"
                           class="form-input" step="0.01" min="0" max="10"
                           placeholder="Ex: 2.50"
                           value="<?= e((string)($_POST['N'] ?? $dades['N'] ?? '')) ?>">
                    <span class="form-ajuda">&lt; 2.0% = dèficit</span>
                </div>
                <div class="form-grup">
                    <label for="P" class="form-label">Fòsfor (P %)</label>
                    <input type="number" id="P" name="P"
                           class="form-input" step="0.01" min="0" max="5"
                           placeholder="Ex: 0.22"
                           value="<?= e((string)($_POST['P'] ?? $dades['P'] ?? '')) ?>">
                </div>
                <div class="form-grup">
                    <label for="K" class="form-label">Potassi (K %)</label>
                    <input type="number" id="K" name="K"
                           class="form-input" step="0.01" min="0" max="10"
                           placeholder="Ex: 1.80"
                           value="<?= e((string)($_POST['K'] ?? $dades['K'] ?? '')) ?>">
                    <span class="form-ajuda">&lt; 1.0% = dèficit</span>
                </div>
                <div class="form-grup">
                    <label for="Ca" class="form-label">Calci (Ca %)</label>
                    <input type="number" id="Ca" name="Ca"
                           class="form-input" step="0.01" min="0" max="10"
                           placeholder="Ex: 1.50"
                           value="<?= e((string)($_POST['Ca'] ?? $dades['Ca'] ?? '')) ?>">
                    <span class="form-ajuda">&lt; 1.0% = dèficit (pot causar bitter pit)</span>
                </div>
                <div class="form-grup">
                    <label for="Mg" class="form-label">Magnesi (Mg %)</label>
                    <input type="number" id="Mg" name="Mg"
                           class="form-input" step="0.01" min="0" max="5"
                           placeholder="Ex: 0.35"
                           value="<?= e((string)($_POST['Mg'] ?? $dades['Mg'] ?? '')) ?>">
                </div>
            </div>
        </fieldset>

        <!-- ── BLOC 3: Micronutrients (ppm) ───────────────────────────────── -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-atom"></i> Micronutrients (ppm sobre matèria seca)
            </legend>

            <div class="alert-referencia flash flash--info flash--mb-m">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                Valors orientatius: Fe 60–200 ppm · Mn 40–150 ppm · Zn 25–80 ppm · Cu 6–20 ppm · B 25–60 ppm
            </div>

            <div class="form-fila-3">
                <div class="form-grup">
                    <label for="Fe" class="form-label">Ferro (Fe ppm)</label>
                    <input type="number" id="Fe" name="Fe"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 120.0"
                           value="<?= e((string)($_POST['Fe'] ?? $dades['Fe'] ?? '')) ?>">
                    <span class="form-ajuda">&lt; 50 ppm = clorosi fèrrica</span>
                </div>
                <div class="form-grup">
                    <label for="Mn" class="form-label">Manganès (Mn ppm)</label>
                    <input type="number" id="Mn" name="Mn"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 80.0"
                           value="<?= e((string)($_POST['Mn'] ?? $dades['Mn'] ?? '')) ?>">
                </div>
                <div class="form-grup">
                    <label for="Zn" class="form-label">Zinc (Zn ppm)</label>
                    <input type="number" id="Zn" name="Zn"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 35.0"
                           value="<?= e((string)($_POST['Zn'] ?? $dades['Zn'] ?? '')) ?>">
                </div>
                <div class="form-grup">
                    <label for="Cu" class="form-label">Coure (Cu ppm)</label>
                    <input type="number" id="Cu" name="Cu"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 10.0"
                           value="<?= e((string)($_POST['Cu'] ?? $dades['Cu'] ?? '')) ?>">
                </div>
                <div class="form-grup">
                    <label for="B" class="form-label">Bor (B ppm)</label>
                    <input type="number" id="B" name="B"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 35.0"
                           value="<?= e((string)($_POST['B'] ?? $dades['B'] ?? '')) ?>">
                    <span class="form-ajuda">Essencial per a la pol·linització i fructificació</span>
                </div>
            </div>
        </fieldset>

        <!-- ── BLOC 4: Diagnosi ───────────────────────────────────────────── -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-stethoscope"></i> Diagnosi i recomanacions
            </legend>

            <div class="form-grup">
                <label for="deficiencies_detectades" class="form-label">Deficiències detectades</label>
                <textarea id="deficiencies_detectades" name="deficiencies_detectades"
                          class="form-textarea" rows="2"
                          placeholder="Ex: Clorosi fèrrica generalitzada. Dèficit lleu de zinc a les fulles apicals."><?= e((string)($_POST['deficiencies_detectades'] ?? $dades['deficiencies_detectades'] ?? '')) ?></textarea>
            </div>

            <div class="form-grup">
                <label for="recomanacions" class="form-label">Recomanacions del laboratori</label>
                <textarea id="recomanacions" name="recomanacions"
                          class="form-textarea" rows="2"
                          placeholder="Ex: Aplicació foliar de quelat de ferro EDDHA. Incrementar K a l'abonat de primavera."><?= e((string)($_POST['recomanacions'] ?? $dades['recomanacions'] ?? '')) ?></textarea>
            </div>

            <div class="form-grup">
                <label for="observacions" class="form-label">Observacions addicionals</label>
                <textarea id="observacions" name="observacions"
                          class="form-textarea" rows="2"
                          placeholder="Condicions del mostreig, varietat concreta afectada..."><?= e((string)($_POST['observacions'] ?? $dades['observacions'] ?? '')) ?></textarea>
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk"></i>
                <?= $editar_id ? 'Actualitzar Analítica' : 'Guardar Analítica' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/analisis/analisis_lab.php#foliar" class="boto-secundari">
                <i class="fas fa-xmark"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
