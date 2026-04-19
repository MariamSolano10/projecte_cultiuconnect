<?php
/**
 * modules/analisis/nou_analisi_aigua.php — Formulari per registrar o editar
 * una analítica fisicoquímica d'aigua de reg.
 *
 * Mapatge formulari → columnes reals de `analisi_aigua`:
 *   id_sector, data_analisi, origen_mostra
 *   pH, conductivitat_electrica, duresa
 *   nitrats, clorurs, sulfats, bicarbonat
 *   Na, Ca, Mg, K, SAR, observacions
 *
 * Mode edició: ?editar=ID
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$sectors  = [];
$error_db = null;
$dades    = [];

$editar_id = sanitize_int($_GET['editar'] ?? null);

try {
    $pdo = connectDB();

    $sectors = $pdo->query("
        SELECT s.id_sector, s.nom,
               COALESCE(v.nom_varietat, '—') AS varietat,
               COALESCE(
                   (SELECT SUM(p.superficie_ha)
                    FROM parcela p
                    JOIN parcela_sector ps ON p.id_parcela = ps.id_parcela
                    WHERE ps.id_sector = s.id_sector), 0
               ) AS superficie_ha
        FROM sector s
        LEFT JOIN plantacio pl ON pl.id_sector = s.id_sector AND pl.data_arrencada IS NULL
        LEFT JOIN varietat  v  ON v.id_varietat = pl.id_varietat
        ORDER BY s.nom ASC
    ")->fetchAll();

    if ($editar_id) {
        $stmt = $pdo->prepare("SELECT * FROM analisi_aigua WHERE id_analisi_aigua = :id");
        $stmt->execute([':id' => $editar_id]);
        $dades = $stmt->fetch() ?: [];
        if (empty($dades)) {
            set_flash('error', 'Analítica d\'aigua no trobada.');
            header('Location: ' . BASE_URL . 'modules/analisis/analisis_lab.php#aigua');
            exit;
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nou_analisi_aigua.php GET: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del formulari.';
}

$titol_pagina  = $editar_id ? 'Editar Analítica d\'Aigua' : 'Nova Analítica d\'Aigua';
$pagina_activa = 'monitoratge';
require_once __DIR__ . '/../../includes/header.php';

?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-droplet" aria-hidden="true"></i>
            <?= $editar_id ? 'Editar Analítica d\'Aigua de Reg' : 'Registrar Nova Analítica d\'Aigua de Reg' ?>
        </h1>
        <p class="descripcio-seccio">
            Introdueix els paràmetres obtinguts de l'informe del laboratori per a l'aigua de reg.
            Tots els camps numèrics s'emmagatzemen com a nuls si es deixen en blanc.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form id="form-analisi-aigua"
          method="POST"
          action="<?= BASE_URL ?>modules/analisis/processar_analisi_aigua.php"
          class="formulari-card"
          novalidate>

        <?php if ($editar_id): ?>
            <input type="hidden" name="mode"      value="editar">
            <input type="hidden" name="editar_id" value="<?= (int)$editar_id ?>">
        <?php endif; ?>

        <!-- ── BLOC 1: Identificació ──────────────────────────────────────── -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-map-pin"></i> Identificació
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="id_sector" class="form-label form-label--requerit">Sector analitzat</label>
                    <select id="id_sector" name="id_sector"
                            class="form-select camp-requerit"
                            data-etiqueta="El sector"
                            <?= $editar_id ? 'disabled' : '' ?>
                            required>
                        <option value="0">— Selecciona un sector —</option>
                        <?php foreach ($sectors as $s):
                            $sel = (int)($_POST['id_sector'] ?? $dades['id_sector'] ?? 0);
                        ?>
                            <option value="<?= (int)$s['id_sector'] ?>"
                                <?= ($sel === (int)$s['id_sector'] || (int)($dades['id_sector'] ?? 0) === (int)$s['id_sector']) ? 'selected' : '' ?>>
                                <?= e($s['nom']) ?>
                                <?= $s['varietat'] !== '—' ? '— ' . e($s['varietat']) : '' ?>
                                (<?= format_ha((float)$s['superficie_ha']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editar_id): ?>
                        <input type="hidden" name="id_sector" value="<?= (int)($dades['id_sector'] ?? 0) ?>">
                    <?php endif; ?>
                </div>

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
            </div>

            <div class="form-grup">
                <label for="origen_mostra" class="form-label form-label--requerit">Origen de la mostra</label>
                <select id="origen_mostra" name="origen_mostra"
                        class="form-select camp-requerit" required>
                    <?php foreach ([
                        'pou'    => 'Pou',
                        'bassa'  => 'Bassa / Embassament',
                        'xarxa'  => 'Xarxa de reg',
                        'riu'    => 'Riu / Séquia',
                        'altres' => 'Altres',
                    ] as $val => $lbl): ?>
                        <option value="<?= $val ?>"
                            <?= e((string)($_POST['origen_mostra'] ?? $dades['origen_mostra'] ?? 'pou')) === $val ? 'selected' : '' ?>>
                            <?= $lbl ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <!-- ── BLOC 2: Paràmetres bàsics ──────────────────────────────────── -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-vial"></i> Paràmetres bàsics
            </legend>

            <div class="form-fila-3">
                <div class="form-grup">
                    <label for="pH" class="form-label">pH</label>
                    <input type="number" id="pH" name="pH"
                           class="form-input" step="0.01" min="0" max="14"
                           placeholder="Ex: 7.20"
                           value="<?= e((string)($_POST['pH'] ?? $dades['pH'] ?? '')) ?>">
                    <span class="form-ajuda">Rang òptim reg: 6.0–8.5</span>
                </div>

                <div class="form-grup">
                    <label for="conductivitat_electrica" class="form-label">Conductivitat Elèctrica (dS/m)</label>
                    <input type="number" id="conductivitat_electrica" name="conductivitat_electrica"
                           class="form-input" step="0.001" min="0"
                           placeholder="Ex: 0.850"
                           value="<?= e((string)($_POST['conductivitat_electrica'] ?? $dades['conductivitat_electrica'] ?? '')) ?>">
                    <span class="form-ajuda">&gt; 3 dS/m = salinitat elevada</span>
                </div>

                <div class="form-grup">
                    <label for="duresa" class="form-label">Duresa total (mg/L CaCO₃)</label>
                    <input type="number" id="duresa" name="duresa"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 350.0"
                           value="<?= e((string)($_POST['duresa'] ?? $dades['duresa'] ?? '')) ?>">
                    <span class="form-ajuda">&gt; 500 mg/L = molt dura</span>
                </div>
            </div>
        </fieldset>

        <!-- ── BLOC 3: Anions ─────────────────────────────────────────────── -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-atom"></i> Anions (ppm)
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="nitrats" class="form-label">Nitrats (NO₃)</label>
                    <input type="number" id="nitrats" name="nitrats"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 25.0"
                           value="<?= e((string)($_POST['nitrats'] ?? $dades['nitrats'] ?? '')) ?>">
                    <span class="form-ajuda">Límit potabilitat: 50 ppm</span>
                </div>

                <div class="form-grup">
                    <label for="clorurs" class="form-label">Clorurs (Cl)</label>
                    <input type="number" id="clorurs" name="clorurs"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 80.0"
                           value="<?= e((string)($_POST['clorurs'] ?? $dades['clorurs'] ?? '')) ?>">
                </div>

                <div class="form-grup">
                    <label for="sulfats" class="form-label">Sulfats (SO₄)</label>
                    <input type="number" id="sulfats" name="sulfats"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 120.0"
                           value="<?= e((string)($_POST['sulfats'] ?? $dades['sulfats'] ?? '')) ?>">
                </div>

                <div class="form-grup">
                    <label for="bicarbonat" class="form-label">Bicarbonat (HCO₃)</label>
                    <input type="number" id="bicarbonat" name="bicarbonat"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 200.0"
                           value="<?= e((string)($_POST['bicarbonat'] ?? $dades['bicarbonat'] ?? '')) ?>">
                </div>
            </div>
        </fieldset>

        <!-- ── BLOC 4: Cations ────────────────────────────────────────────── -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-flask-vial"></i> Cations i SAR (ppm)
            </legend>

            <div class="form-fila-3">
                <div class="form-grup">
                    <label for="Na" class="form-label">Sodi (Na)</label>
                    <input type="number" id="Na" name="Na"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 45.0"
                           value="<?= e((string)($_POST['Na'] ?? $dades['Na'] ?? '')) ?>">
                </div>
                <div class="form-grup">
                    <label for="Ca" class="form-label">Calci (Ca)</label>
                    <input type="number" id="Ca" name="Ca"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 80.0"
                           value="<?= e((string)($_POST['Ca'] ?? $dades['Ca'] ?? '')) ?>">
                </div>
                <div class="form-grup">
                    <label for="Mg" class="form-label">Magnesi (Mg)</label>
                    <input type="number" id="Mg" name="Mg"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 15.0"
                           value="<?= e((string)($_POST['Mg'] ?? $dades['Mg'] ?? '')) ?>">
                </div>
                <div class="form-grup">
                    <label for="K" class="form-label">Potassi (K)</label>
                    <input type="number" id="K" name="K"
                           class="form-input" step="0.1" min="0"
                           placeholder="Ex: 5.0"
                           value="<?= e((string)($_POST['K'] ?? $dades['K'] ?? '')) ?>">
                </div>
                <div class="form-grup">
                    <label for="SAR" class="form-label">
                        SAR
                        <span class="form-ajuda form-inline-display">(o deixa buit per càlcul automàtic)</span>
                    </label>
                    <input type="number" id="SAR" name="SAR"
                           class="form-input" step="0.01" min="0"
                           placeholder="Calculat automàticament"
                           value="<?= e((string)($_POST['SAR'] ?? $dades['SAR'] ?? '')) ?>">
                    <span class="form-ajuda">&gt; 10 = risc sòdic. SAR = Na/√((Ca+Mg)/2)</span>
                </div>
            </div>
        </fieldset>

        <!-- ── BLOC 5: Observacions ───────────────────────────────────────── -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-note-sticky"></i> Observacions
            </legend>
            <div class="form-grup">
                <label for="observacions" class="form-label">Notes i recomanacions</label>
                <textarea id="observacions" name="observacions"
                          class="form-textarea" rows="3"
                          placeholder="Observacions del laboratori, recomanacions de tractament..."><?= e((string)($_POST['observacions'] ?? $dades['observacions'] ?? '')) ?></textarea>
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk"></i>
                <?= $editar_id ? 'Actualitzar Analítica' : 'Guardar Analítica' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/analisis/analisis_lab.php#aigua" class="boto-secundari">
                <i class="fas fa-xmark"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<script>
// Càlcul automàtic del SAR en temps real
(function () {
    const inputs  = ['Na','Ca','Mg'].map(id => document.getElementById(id));
    const sarInput = document.getElementById('SAR');

    function calcularSAR() {
        const [Na, Ca, Mg] = inputs.map(i => parseFloat(i.value) || 0);
        if (Na > 0 && (Ca + Mg) > 0) {
            const sar = Na / Math.sqrt((Ca + Mg) / 2);
            sarInput.value   = sar.toFixed(2);
            sarInput.title   = 'Calculat automàticament. Pots sobreescriure\'l.';
        }
    }

    // Només calcula si SAR és buit o havia estat calculat (no introduït manualment)
    inputs.forEach(i => i.addEventListener('input', () => {
        if (!sarInput.dataset.manual) calcularSAR();
    }));
    sarInput.addEventListener('input', () => { sarInput.dataset.manual = '1'; });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
