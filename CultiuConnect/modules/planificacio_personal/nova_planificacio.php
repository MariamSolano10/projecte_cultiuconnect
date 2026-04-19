<?php
/**
 * modules/planificacio_personal/nova_planificacio.php
 * Formulari per crear o editar un període de planificació de personal.
 * POST processa aquí mateix i redirigeix.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Nova Planificació de Personal';
$pagina_activa = 'planificacio_personal';

$planificacio     = null;
$error_db         = null;
$es_edicio        = false;
$temporada_actual = is_numeric($_GET['temporada'] ?? '') ? (int)$_GET['temporada'] : (int)date('Y');

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accio          = $_POST['accio'] ?? 'crear';
    $id_planif      = !empty($_POST['id_planificacio']) ? (int)$_POST['id_planificacio'] : null;
    $temporada      = is_numeric($_POST['temporada'] ?? '')  ? (int)$_POST['temporada']  : (int)date('Y');
    $periode        = trim($_POST['periode']      ?? '');
    $data_inici     = trim($_POST['data_inici']   ?? '');
    $data_fi        = trim($_POST['data_fi']      ?? '');
    $num_treb       = is_numeric($_POST['num_treballadors_necessaris'] ?? '') ? (int)$_POST['num_treballadors_necessaris'] : null;
    $perfil         = trim($_POST['perfil_necessari'] ?? '') ?: null;
    $observacions   = trim($_POST['observacions']     ?? '') ?: null;

    $url_retorn = BASE_URL . 'modules/planificacio_personal/planificacio_personal.php?temporada=' . $temporada;

    if (!$periode || !$data_inici || !$data_fi || !$num_treb) {
        set_flash('error', 'El període, les dates i el nombre de treballadors són obligatoris.');
        header('Location: ' . ($id_planif
            ? BASE_URL . 'modules/planificacio_personal/nova_planificacio.php?editar=' . $id_planif
            : BASE_URL . 'modules/planificacio_personal/nova_planificacio.php?temporada=' . $temporada));
        exit;
    }

    if ($data_fi < $data_inici) {
        set_flash('error', 'La data de fi no pot ser anterior a la data d\'inici.');
        header('Location: ' . ($id_planif
            ? BASE_URL . 'modules/planificacio_personal/nova_planificacio.php?editar=' . $id_planif
            : BASE_URL . 'modules/planificacio_personal/nova_planificacio.php?temporada=' . $temporada));
        exit;
    }

    try {
        $pdo = connectDB();

        if ($accio === 'editar' && $id_planif) {
            $pdo->prepare("
                UPDATE planificacio_personal SET
                    temporada = :temporada, periode = :periode,
                    data_inici = :inici, data_fi = :fi,
                    num_treballadors_necessaris = :num,
                    perfil_necessari = :perfil, observacions = :obs
                WHERE id_planificacio = :id
            ")->execute([
                ':temporada' => $temporada, ':periode' => $periode,
                ':inici' => $data_inici, ':fi' => $data_fi,
                ':num' => $num_treb, ':perfil' => $perfil,
                ':obs' => $observacions, ':id' => $id_planif,
            ]);
            set_flash('success', 'Planificació actualitzada correctament.');
        } else {
            $pdo->prepare("
                INSERT INTO planificacio_personal
                    (temporada, periode, data_inici, data_fi, num_treballadors_necessaris, perfil_necessari, observacions)
                VALUES
                    (:temporada, :periode, :inici, :fi, :num, :perfil, :obs)
            ")->execute([
                ':temporada' => $temporada, ':periode' => $periode,
                ':inici' => $data_inici, ':fi' => $data_fi,
                ':num' => $num_treb, ':perfil' => $perfil, ':obs' => $observacions,
            ]);
            set_flash('success', 'Planificació creada correctament.');
        }

        header('Location: ' . $url_retorn);
        exit;

    } catch (Exception $e) {
        error_log('[CultiuConnect] nova_planificacio.php POST: ' . $e->getMessage());
        set_flash('error', 'Error intern en desar.');
        header('Location: ' . $url_retorn);
        exit;
    }
}

// ── GET ───────────────────────────────────────────────────────
try {
    $pdo = connectDB();

    if (!empty($_GET['editar']) && is_numeric($_GET['editar'])) {
        $es_edicio    = true;
        $titol_pagina = 'Editar Planificació';

        $stmt = $pdo->prepare("SELECT * FROM planificacio_personal WHERE id_planificacio = :id");
        $stmt->execute([':id' => (int)$_GET['editar']]);
        $planificacio = $stmt->fetch();

        if (!$planificacio) {
            set_flash('error', 'Planificació no trobada.');
            header('Location: ' . BASE_URL . 'modules/planificacio_personal/planificacio_personal.php');
            exit;
        }
        $temporada_actual = (int)$planificacio['temporada'];
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nova_planificacio.php GET: ' . $e->getMessage());
    $error_db = 'Error en carregar el formulari.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-form">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-<?= $es_edicio ? 'pen' : 'plus' ?>"></i>
            <?= $es_edicio ? 'Editar Planificació de Personal' : 'Nova Planificació de Personal' ?>
        </h1>
        <a href="<?= BASE_URL ?>modules/planificacio_personal/planificacio_personal.php?temporada=<?= $temporada_actual ?>"
           class="boto-secundari">
            <i class="fas fa-arrow-left"></i> Tornar
        </a>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error"><i class="fas fa-circle-xmark"></i> <?= e($error_db) ?></div>
    <?php endif; ?>

    <form method="POST" class="form-card" novalidate>

        <input type="hidden" name="accio" value="<?= $es_edicio ? 'editar' : 'crear' ?>">
        <?php if ($es_edicio): ?>
            <input type="hidden" name="id_planificacio" value="<?= (int)$planificacio['id_planificacio'] ?>">
        <?php endif; ?>

        <fieldset class="form-grup">
            <legend>Informació del període</legend>

            <div class="form-fila">
                <div class="form-camp">
                    <label for="temporada">Temporada <span class="obligatori">*</span></label>
                    <input type="number" name="temporada" id="temporada"
                           min="2000" max="2100" required
                           value="<?= (int)($planificacio['temporada'] ?? $temporada_actual) ?>">
                </div>

                <div class="form-camp form-camp--grow-2">
                    <label for="periode">Nom del període <span class="obligatori">*</span></label>
                    <input type="text" name="periode" id="periode" required
                           value="<?= e($planificacio['periode'] ?? '') ?>"
                           placeholder="Ex: Collita pomeres, Poda hivernal, Aclarida...">
                    <!-- Suggeriments ràpids -->
                    <div class="etiquetes-inline">
                        <?php foreach (['Poda hivernal','Aclarida','Collita','Fertilització primavera','Tractaments preventius','Reg estiu'] as $sug): ?>
                            <button type="button" class="filtre-boto badge--tag-petit"
                                    onclick="document.getElementById('periode').value='<?= $sug ?>'">
                                <?= $sug ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-fila">
                <div class="form-camp">
                    <label for="data_inici">Data d'inici <span class="obligatori">*</span></label>
                    <input type="date" name="data_inici" id="data_inici" required
                           value="<?= e($planificacio['data_inici'] ?? '') ?>">
                </div>
                <div class="form-camp">
                    <label for="data_fi">Data de fi <span class="obligatori">*</span></label>
                    <input type="date" name="data_fi" id="data_fi" required
                           value="<?= e($planificacio['data_fi'] ?? '') ?>">
                </div>
                <div class="form-camp">
                    <label for="num_treballadors_necessaris">
                        Treballadors necessaris <span class="obligatori">*</span>
                    </label>
                    <input type="number" name="num_treballadors_necessaris"
                           id="num_treballadors_necessaris"
                           min="1" required
                           value="<?= (int)($planificacio['num_treballadors_necessaris'] ?? '') ?>"
                           placeholder="Ex: 8">
                </div>
            </div>

            <!-- Dies calculats automàticament -->
            <div id="info-dies" class="flash flash--info flash--stack-s">
                <i class="fas fa-calendar"></i>
                Durada: <strong id="text-dies"></strong>
            </div>
        </fieldset>

        <fieldset class="form-grup">
            <legend>Detalls addicionals</legend>

            <div class="form-camp">
                <label for="perfil_necessari">Perfil de treballador necessari</label>
                <input type="text" name="perfil_necessari" id="perfil_necessari"
                       value="<?= e($planificacio['perfil_necessari'] ?? '') ?>"
                       placeholder="Ex: Operari agrícola amb experiència en collita de poma">
            </div>

            <div class="form-camp">
                <label for="observacions">Observacions</label>
                <textarea name="observacions" id="observacions" rows="3"
                          placeholder="Notes de planificació, contactes ETT, precedents d'anys anteriors..."><?= e($planificacio['observacions'] ?? '') ?></textarea>
            </div>
        </fieldset>

        <div class="form-accions">
            <button type="submit" class="boto-principal">
                <i class="fas fa-save"></i>
                <?= $es_edicio ? 'Desar canvis' : 'Crear planificació' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/planificacio_personal/planificacio_personal.php?temporada=<?= $temporada_actual ?>"
               class="boto-secundari">Cancel·lar</a>
        </div>

    </form>
</div>

<script>
// Càlcul automàtic de dies
const inpInici = document.getElementById('data_inici');
const inpFi    = document.getElementById('data_fi');
const infoDies = document.getElementById('info-dies');
const textDies = document.getElementById('text-dies');

function calcDies() {
    if (!inpInici.value || !inpFi.value) { infoDies.style.display = 'none'; return; }
    const d = (new Date(inpFi.value) - new Date(inpInici.value)) / 86400000 + 1;
    if (d <= 0) { infoDies.style.display = 'none'; return; }
    textDies.textContent = d + (d === 1 ? ' dia' : ' dies');
    infoDies.style.display = 'flex';
}

inpInici.addEventListener('change', calcDies);
inpFi.addEventListener('change', calcDies);
calcDies();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
