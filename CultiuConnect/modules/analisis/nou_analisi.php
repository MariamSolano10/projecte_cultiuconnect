<?php
/**
 * modules/analisis/nou_analisi.php — Formulari per registrar o editar una analítica de sòl.
 *
 * Mapatge formulari → columnes reals de `caracteristiques_sol`:
 *   id_sector, data_analisi (PK composta)
 *   textura, pH, materia_organica
 *   N, P, K, Ca, Mg, Na
 *   conductivitat_electrica
 *
 * Mode edició: ?editar_sector=N&editar_data=YYYY-MM-DD
 * (preomple el formulari amb les dades existents)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$sectors  = [];
$error_db = null;
$dades    = [];  // Dades per a mode edició

// Paràmetres mode edició
$editar_sector = sanitize_int($_GET['editar_sector'] ?? null);
$editar_data   = sanitize($_GET['editar_data']       ?? '');

try {
    $pdo = connectDB();

    // Sectors (LEFT JOIN per incloure sectors sense plantació)
    $sectors = $pdo->query("
        SELECT
            s.id_sector,
            s.nom,
            COALESCE(v.nom_varietat, '—') AS varietat,
            COALESCE(
                (SELECT SUM(p.superficie_ha)
                 FROM parcela p
                 JOIN parcela_sector ps ON p.id_parcela = ps.id_parcela
                 WHERE ps.id_sector = s.id_sector),
                0
            ) AS superficie_ha
        FROM sector s
        LEFT JOIN plantacio pl ON pl.id_sector    = s.id_sector
                               AND pl.data_arrencada IS NULL
        LEFT JOIN varietat  v  ON v.id_varietat   = pl.id_varietat
        ORDER BY s.nom ASC
    ")->fetchAll();

    // Mode edició: carregar dades existents
    if ($editar_sector && !empty($editar_data)) {
        $stmt = $pdo->prepare(
            "SELECT * FROM caracteristiques_sol
             WHERE id_sector = :id AND data_analisi = :data"
        );
        $stmt->execute([':id' => $editar_sector, ':data' => $editar_data]);
        $dades = $stmt->fetch() ?: [];
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nou_analisi.php GET: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del formulari.';
}

// -----------------------------------------------------------
// Processament POST → deleguem a processar_analisi.php
// (el formulari apunta allà per separació de responsabilitats)
// -----------------------------------------------------------

$titol_pagina  = 'Nova Analítica de Sòl';
$pagina_activa = 'monitoratge';
require_once __DIR__ . '/../../includes/header.php';

// Preomple: mode edició > POST fallat > valor per defecte
$v = fn(string $camp, mixed $defecte = '') =>
    e((string)($_POST[$camp] ?? $dades[$camp] ?? $defecte));
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-flask" aria-hidden="true"></i>
            <?= $editar_sector ? 'Editar Analítica de Sòl' : 'Registrar Nova Analítica de Sòl' ?>
        </h1>
        <p class="descripcio-seccio">
            Introdueix els paràmetres obtinguts de l'informe del laboratori.
            Tots els camps numèrics s'emmagatzemen com a valors nuls si es deixen en blanc.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form id="form-analisi"
          method="POST"
          action="<?= BASE_URL ?>modules/analisis/processar_analisi.php"
          class="formulari-card"
          novalidate>

        <!-- Camps ocults per al mode edició -->
        <?php if ($editar_sector): ?>
            <input type="hidden" name="mode" value="editar">
            <input type="hidden" name="editar_sector" value="<?= (int)$editar_sector ?>">
            <input type="hidden" name="editar_data"   value="<?= e($editar_data) ?>">
        <?php endif; ?>

        <!-- ================================================
             BLOC 1: Identificació
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-map-pin"></i> Identificació
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="id_sector" class="form-label form-label--requerit">
                        Sector analitzat
                    </label>
                    <select id="id_sector" name="id_sector"
                            class="form-select camp-requerit"
                            data-etiqueta="El sector"
                            <?= $editar_sector ? 'disabled' : '' ?>
                            required>
                        <option value="0">— Selecciona un sector —</option>
                        <?php foreach ($sectors as $s):
                            $sel_id = (int)($_POST['id_sector'] ?? $dades['id_sector'] ?? 0);
                        ?>
                            <option value="<?= (int)$s['id_sector'] ?>"
                                <?= ($sel_id === (int)$s['id_sector'] || $editar_sector === (int)$s['id_sector']) ? 'selected' : '' ?>>
                                <?= e($s['nom']) ?>
                                <?= $s['varietat'] !== '—' ? '— ' . e($s['varietat']) : '' ?>
                                (<?= format_ha((float)$s['superficie_ha']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editar_sector): ?>
                        <input type="hidden" name="id_sector" value="<?= (int)$editar_sector ?>">
                    <?php endif; ?>
                </div>

                <div class="form-grup">
                    <label for="data_analisi" class="form-label form-label--requerit">
                        Data de mostreig / anàlisi
                    </label>
                    <input type="date"
                           id="data_analisi"
                           name="data_analisi"
                           class="form-input camp-requerit"
                           data-etiqueta="La data d'anàlisi"
                           data-no-futur
                           <?= $editar_sector ? 'readonly' : '' ?>
                           value="<?= $editar_data ?: $v('data_analisi', date('Y-m-d')) ?>"
                           required>
                </div>
            </div>

            <div class="form-grup">
                <label for="textura" class="form-label">Textura / Tipus de mostra</label>
                <select id="textura" name="textura" class="form-select">
                    <option value="">— Selecciona —</option>
                    <?php foreach ([
                        'Sorra'          => 'Sorrenca',
                        'Franc-sorrenca' => 'Franc-sorrenca',
                        'Franca'         => 'Franca',
                        'Franc-argilosa' => 'Franc-argilosa',
                        'Argilosa'       => 'Argilosa',
                        'Llimosa'        => 'Llimosa',
                        'Fulla'          => 'Fulla (anàlisi foliar)',
                        'Aigua'          => 'Aigua (anàlisi reg)',
                    ] as $val => $lbl): ?>
                        <option value="<?= $val ?>"
                            <?= ($v('textura') === $val) ? 'selected' : '' ?>>
                            <?= $lbl ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 2: Paràmetres bàsics
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-vial"></i> Paràmetres bàsics
            </legend>

            <div class="form-fila-3">
                <div class="form-grup">
                    <label for="pH" class="form-label">pH</label>
                    <input type="number" id="pH" name="pH"
                           class="form-input"
                           data-tipus="decimal"
                           data-etiqueta="El pH"
                           step="0.01" min="0" max="14"
                           placeholder="Ex: 6.80"
                           value="<?= $v('pH') ?>">
                    <span class="form-ajuda">Rang òptim fruita: 6.0 – 7.0</span>
                </div>

                <div class="form-grup">
                    <label for="materia_organica" class="form-label">Matèria Orgànica (%)</label>
                    <input type="number" id="materia_organica" name="materia_organica"
                           class="form-input"
                           data-tipus="decimal"
                           data-etiqueta="La matèria orgànica"
                           step="0.01" min="0"
                           placeholder="Ex: 2.50"
                           value="<?= $v('materia_organica') ?>">
                    <span class="form-ajuda">Valors &lt; 1.5% indiquen sòl pobre</span>
                </div>

                <div class="form-grup">
                    <label for="conductivitat_electrica" class="form-label">Conductivitat Elèctrica (dS/m)</label>
                    <input type="number" id="conductivitat_electrica" name="conductivitat_electrica"
                           class="form-input"
                           data-tipus="decimal"
                           data-etiqueta="La conductivitat elèctrica"
                           step="0.001" min="0"
                           placeholder="Ex: 0.950"
                           value="<?= $v('conductivitat_electrica') ?>">
                    <span class="form-ajuda">Salinitat: &gt; 2 dS/m limita cultius</span>
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 3: Macronutrients (N, P, K)
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-leaf"></i> Macronutrients principals (ppm)
            </legend>

            <div class="form-fila-3">
                <div class="form-grup">
                    <label for="N" class="form-label">Nitrogen (N)</label>
                    <input type="number" id="N" name="N"
                           class="form-input"
                           data-tipus="decimal"
                           step="0.1" min="0"
                           placeholder="Ex: 150.0"
                           value="<?= $v('N') ?>">
                </div>
                <div class="form-grup">
                    <label for="P" class="form-label">Fòsfor (P₂O₅)</label>
                    <input type="number" id="P" name="P"
                           class="form-input"
                           data-tipus="decimal"
                           step="0.1" min="0"
                           placeholder="Ex: 35.0"
                           value="<?= $v('P') ?>">
                </div>
                <div class="form-grup">
                    <label for="K" class="form-label">Potassi (K₂O)</label>
                    <input type="number" id="K" name="K"
                           class="form-input"
                           data-tipus="decimal"
                           step="0.1" min="0"
                           placeholder="Ex: 420.0"
                           value="<?= $v('K') ?>">
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 4: Macronutrients secundaris (Ca, Mg, Na)
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-atom"></i> Macronutrients secundaris (ppm)
            </legend>

            <div class="form-fila-3">
                <div class="form-grup">
                    <label for="Ca" class="form-label">Calci (Ca)</label>
                    <input type="number" id="Ca" name="Ca"
                           class="form-input"
                           data-tipus="decimal"
                           step="0.1" min="0"
                           placeholder="Ex: 2800.0"
                           value="<?= $v('Ca') ?>">
                </div>
                <div class="form-grup">
                    <label for="Mg" class="form-label">Magnesi (Mg)</label>
                    <input type="number" id="Mg" name="Mg"
                           class="form-input"
                           data-tipus="decimal"
                           step="0.1" min="0"
                           placeholder="Ex: 180.0"
                           value="<?= $v('Mg') ?>">
                </div>
                <div class="form-grup">
                    <label for="Na" class="form-label">Sodi (Na)</label>
                    <input type="number" id="Na" name="Na"
                           class="form-input"
                           data-tipus="decimal"
                           step="0.1" min="0"
                           placeholder="Ex: 45.0"
                           value="<?= $v('Na') ?>">
                    <span class="form-ajuda">Valors alts indiquen risc de salinització</span>
                </div>
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk"></i>
                <?= $editar_sector ? 'Actualitzar Analítica' : 'Guardar Analítica' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/analisis/analisis_lab.php" class="boto-secundari">
                <i class="fas fa-xmark"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
