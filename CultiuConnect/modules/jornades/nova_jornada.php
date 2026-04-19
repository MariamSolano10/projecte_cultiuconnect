<?php
/**
 * modules/jornades/nova_jornada.php — Formulari per registrar o editar una jornada.
 *
 * GET ?editar=ID  → mode edició.
 * POST            → redirigeix a processar_jornada.php
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Registrar Jornada';
$pagina_activa = 'jornades';

$jornada      = null;
$treballadors = [];
$tasques      = [];
$error_db     = null;
$es_edicio    = false;

try {
    $pdo = connectDB();

    $treballadors = $pdo->query("
        SELECT id_treballador, nom, cognoms, rol
        FROM treballador
        WHERE estat = 'actiu'
        ORDER BY cognoms, nom
    ")->fetchAll();

    $tasques = $pdo->query("
        SELECT id_tasca, tipus, descripcio, data_inici_prevista
        FROM tasca
        WHERE estat IN ('pendent', 'en_proces')
        ORDER BY data_inici_prevista DESC
        LIMIT 50
    ")->fetchAll();

    if (!empty($_GET['editar']) && is_numeric($_GET['editar'])) {
        $es_edicio    = true;
        $titol_pagina = 'Editar Jornada';
        $id_edit      = (int)$_GET['editar'];

        $stmt = $pdo->prepare("SELECT * FROM jornada WHERE id_jornada = :id");
        $stmt->execute([':id' => $id_edit]);
        $jornada = $stmt->fetch();

        if (!$jornada) {
            set_flash('error', 'Jornada no trobada.');
            header('Location: ' . BASE_URL . 'modules/jornades/jornades.php');
            exit;
        }
    }

    // Pre-selecció de treballador per GET (des del directori de personal)
    $presel_treballador = !empty($_GET['id_treballador']) ? (int)$_GET['id_treballador'] : 0;

} catch (Exception $e) {
    error_log('[CultiuConnect] nova_jornada.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar el formulari.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-form">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-<?= $es_edicio ? 'pen' : 'plus' ?>" aria-hidden="true"></i>
            <?= $es_edicio ? 'Editar Jornada' : 'Registrar Jornada' ?>
        </h1>
        <a href="<?= BASE_URL ?>modules/jornades/jornades.php" class="boto-secundari">
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
          action="<?= BASE_URL ?>modules/jornades/processar_jornada.php"
          class="form-card"
          novalidate>

        <input type="hidden" name="accio" value="<?= $es_edicio ? 'editar' : 'crear' ?>">
        <?php if ($es_edicio): ?>
            <input type="hidden" name="id_jornada" value="<?= (int)$jornada['id_jornada'] ?>">
        <?php endif; ?>

        <!-- TREBALLADOR I TASCA -->
        <fieldset class="form-grup">
            <legend>Treballador i tasca</legend>

            <div class="form-fila">
                <div class="form-camp">
                    <label for="id_treballador">Treballador <span class="obligatori">*</span></label>
                    <select name="id_treballador" id="id_treballador" required>
                        <option value="">— Selecciona un treballador —</option>
                        <?php foreach ($treballadors as $t): ?>
                            <?php
                            $sel = false;
                            if ($es_edicio) {
                                $sel = (int)$jornada['id_treballador'] === (int)$t['id_treballador'];
                            } elseif ($presel_treballador) {
                                $sel = $presel_treballador === (int)$t['id_treballador'];
                            }
                            ?>
                            <option value="<?= (int)$t['id_treballador'] ?>" <?= $sel ? 'selected' : '' ?>>
                                <?= e($t['nom'] . ' ' . ($t['cognoms'] ?? '')) ?>
                                (<?= e($t['rol']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-camp">
                    <label for="id_tasca">Tasca associada</label>
                    <select name="id_tasca" id="id_tasca">
                        <option value="">— Cap tasca específica —</option>
                        <?php foreach ($tasques as $ta): ?>
                            <option value="<?= (int)$ta['id_tasca'] ?>"
                                <?= (int)($jornada['id_tasca'] ?? 0) === (int)$ta['id_tasca'] ? 'selected' : '' ?>>
                                #<?= (int)$ta['id_tasca'] ?> — <?= e(ucfirst($ta['tipus'])) ?>
                                <?= $ta['descripcio'] ? ': ' . e(mb_substr($ta['descripcio'], 0, 35)) . '...' : '' ?>
                                (<?= format_data($ta['data_inici_prevista'], curta: true) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <!-- HORARI -->
        <fieldset class="form-grup">
            <legend>Horari de la jornada</legend>

            <div class="form-fila">
                <div class="form-camp">
                    <label for="data_hora_inici">Inici <span class="obligatori">*</span></label>
                    <input type="datetime-local"
                           name="data_hora_inici"
                           id="data_hora_inici"
                           required
                           value="<?= $es_edicio && $jornada['data_hora_inici']
                               ? date('Y-m-d\TH:i', strtotime($jornada['data_hora_inici']))
                               : date('Y-m-d\T08:00') ?>">
                </div>

                <div class="form-camp">
                    <label for="data_hora_fi">
                        Fi
                        <span class="text-suau">(deixar buit si la jornada continua)</span>
                    </label>
                    <input type="datetime-local"
                           name="data_hora_fi"
                           id="data_hora_fi"
                           value="<?= $es_edicio && $jornada['data_hora_fi']
                               ? date('Y-m-d\TH:i', strtotime($jornada['data_hora_fi']))
                               : '' ?>">
                </div>

                <div class="form-camp">
                    <label for="pausa_minuts">Pausa (minuts)</label>
                    <input type="number"
                           name="pausa_minuts"
                           id="pausa_minuts"
                           min="0" step="5"
                           value="<?= (int)($jornada['pausa_minuts'] ?? 0) ?>"
                           placeholder="Ex: 30">
                </div>
            </div>

            <!-- Càlcul automàtic en temps real -->
            <div class="info-calcul info-calcul--stack" id="info-hores">
                <i class="fas fa-clock" aria-hidden="true"></i>
                Hores netes calculades: <strong id="hores-calculades">—</strong>
            </div>
        </fieldset>

        <!-- UBICACIÓ I OBSERVACIONS -->
        <fieldset class="form-grup">
            <legend>Ubicació i observacions</legend>

            <div class="form-camp">
                <label for="ubicacio">Ubicació / Sector</label>
                <input type="text"
                       name="ubicacio"
                       id="ubicacio"
                       value="<?= e($jornada['ubicacio'] ?? '') ?>"
                       placeholder="Ex: Sector Olivars del Sol, parcel·la nord...">
            </div>

            <div class="form-camp">
                <label for="incidencies">Incidències o observacions</label>
                <textarea name="incidencies"
                          id="incidencies"
                          rows="3"
                          placeholder="Qualsevol incidència, anomalia o nota rellevant..."><?= e($jornada['incidencies'] ?? '') ?></textarea>
            </div>

            <?php if ($es_edicio): ?>
            <div class="form-camp">
                <label class="label-checkbox">
                    <input type="checkbox"
                           name="validada"
                           value="1"
                           <?= !empty($jornada['validada']) ? 'checked' : '' ?>>
                    Jornada validada pel responsable
                </label>
            </div>
            <?php endif; ?>
        </fieldset>

        <div class="form-accions">
            <button type="submit" class="boto-principal">
                <i class="fas fa-save" aria-hidden="true"></i>
                <?= $es_edicio ? 'Desar canvis' : 'Registrar jornada' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/jornades/jornades.php" class="boto-secundari">
                Cancel·lar
            </a>
        </div>

    </form>
</div>

<script>
// Càlcul automàtic d'hores netes
(function () {
    const inici  = document.getElementById('data_hora_inici');
    const fi     = document.getElementById('data_hora_fi');
    const pausa  = document.getElementById('pausa_minuts');
    const info   = document.getElementById('info-hores');
    const result = document.getElementById('hores-calculades');

    function actualitzar() {
        if (!inici.value || !fi.value) { info.style.display = 'none'; return; }
        const ms = new Date(fi.value) - new Date(inici.value);
        if (ms <= 0) { info.style.display = 'none'; return; }
        const minutsTotals = Math.floor(ms / 60000) - (parseInt(pausa.value) || 0);
        if (minutsTotals < 0) { result.textContent = 'Error: la pausa supera la jornada'; info.style.display = 'block'; return; }
        const h = Math.floor(minutsTotals / 60);
        const m = minutsTotals % 60;
        result.textContent = h + 'h ' + String(m).padStart(2, '0') + 'min';
        info.style.display = 'block';
    }

    inici.addEventListener('change', actualitzar);
    fi.addEventListener('change', actualitzar);
    pausa.addEventListener('input', actualitzar);
    actualitzar();
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
