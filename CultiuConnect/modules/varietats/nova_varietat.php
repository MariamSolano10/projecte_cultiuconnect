<?php
/**
 * modules/varietats/nova_varietat.php — Formulari per registrar o editar una varietat.
 *
 * GET  → mostra el formulari (mode nou o edició via ?editar=ID)
 * POST → valida, insereix/actualitza, redirigeix amb flash
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$especies  = [];
$dades     = [];   // Dades per a mode edició
$error_db  = null;
$id_editar = sanitize_int($_GET['editar'] ?? null);

try {
    $pdo = connectDB();

    $especies = $pdo->query(
        "SELECT id_especie, nom_comu, nom_cientific
         FROM especie
         ORDER BY nom_comu ASC"
    )->fetchAll();

    // Mode edició: carregar dades existents
    if ($id_editar) {
        $stmt = $pdo->prepare("SELECT * FROM varietat WHERE id_varietat = ?");
        $stmt->execute([$id_editar]);
        $dades = $stmt->fetch() ?: [];
        if (empty($dades)) {
            set_flash('error', 'La varietat que vols editar no existeix.');
            header('Location: ' . BASE_URL . 'modules/varietats/varietats.php');
            exit;
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nova_varietat.php GET: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les espècies.';
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_editar_post  = sanitize_int($_POST['id_editar']    ?? null);
    $nom             = sanitize($_POST['nom_varietat']     ?? '');
    $id_especie      = sanitize_int($_POST['id_especie']   ?? null);
    $carac_agr       = sanitize($_POST['caracteristiques_agronomiques']    ?? '');
    $cicle           = sanitize($_POST['cicle_vegetatiu']                  ?? '');
    $pollinitzacio   = sanitize($_POST['requisits_pollinitzacio']          ?? '');
    $qualitats       = sanitize($_POST['qualitats_comercials']             ?? '');
    $productivitat   = sanitize_decimal($_POST['productivitat_mitjana_esperada'] ?? null);

    $errors = [];
    if (empty($nom))      $errors[] = 'El nom de la varietat és obligatori.';
    if (!$id_especie)     $errors[] = 'Cal seleccionar una espècie.';

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        $redir = $id_editar_post
            ? BASE_URL . 'modules/varietats/nova_varietat.php?editar=' . $id_editar_post
            : BASE_URL . 'modules/varietats/nova_varietat.php';
        header('Location: ' . $redir);
        exit;
    }

    try {
        $pdo = connectDB();

        $params = [
            ':nom'           => $nom,
            ':id_especie'    => $id_especie,
            ':carac_agr'     => $carac_agr    ?: null,
            ':cicle'         => $cicle        ?: null,
            ':pollinitzacio' => $pollinitzacio ?: null,
            ':productivitat' => $productivitat,
            ':qualitats'     => $qualitats    ?: null,
        ];

        if ($id_editar_post) {
            // Mode edició: UPDATE
            $stmt = $pdo->prepare("
                UPDATE varietat SET
                    nom_varietat                    = :nom,
                    id_especie                      = :id_especie,
                    caracteristiques_agronomiques   = :carac_agr,
                    cicle_vegetatiu                 = :cicle,
                    requisits_pollinitzacio         = :pollinitzacio,
                    productivitat_mitjana_esperada  = :productivitat,
                    qualitats_comercials            = :qualitats
                WHERE id_varietat = :id
            ");
            $params[':id'] = $id_editar_post;
            $stmt->execute($params);
            $msg = 'Varietat «' . $nom . '» actualitzada correctament.';

        } else {
            // Mode nou: INSERT
            $stmt = $pdo->prepare("
                INSERT INTO varietat
                    (nom_varietat, id_especie, caracteristiques_agronomiques,
                     cicle_vegetatiu, requisits_pollinitzacio,
                     productivitat_mitjana_esperada, qualitats_comercials)
                VALUES
                    (:nom, :id_especie, :carac_agr,
                     :cicle, :pollinitzacio,
                     :productivitat, :qualitats)
            ");
            $stmt->execute($params);
            $msg = 'Varietat «' . $nom . '» registrada correctament al catàleg.';
        }

        set_flash('success', $msg);
        header('Location: ' . BASE_URL . 'modules/varietats/varietats.php');
        exit;

    } catch (Exception $e) {
        if ($e->getCode() === '23000') {
            set_flash('error', 'Ja existeix una varietat amb aquest nom per a l\'espècie seleccionada.');
        } else {
            error_log('[CultiuConnect] nova_varietat.php POST: ' . $e->getMessage());
            set_flash('error', 'Error intern en guardar la varietat.');
        }
        $redir = $id_editar_post
            ? BASE_URL . 'modules/varietats/nova_varietat.php?editar=' . $id_editar_post
            : BASE_URL . 'modules/varietats/nova_varietat.php';
        header('Location: ' . $redir);
        exit;
    }
}

// -----------------------------------------------------------
// Capçalera (GET)
// -----------------------------------------------------------
$titol_pagina  = $id_editar ? 'Editar Varietat' : 'Nova Varietat';
$pagina_activa = 'varietats';
require_once __DIR__ . '/../../includes/header.php';

// Preomple: POST fallat > mode edició > buit
function vcamp(string $camp, string $def = ''): string
{
    global $dades;
    return e((string)($_POST[$camp] ?? ($dades[$camp] ?? $def)));
}
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-<?= $id_editar ? 'pen' : 'plus-circle' ?>" aria-hidden="true"></i>
            <?= $id_editar ? 'Editar Varietat' : 'Registrar Nova Varietat' ?>
        </h1>
        <p class="descripcio-seccio">
            <?= $id_editar
                ? 'Modifica les dades de la varietat del catàleg.'
                : 'Afegeix una nova varietat al catàleg mestre de l\'explotació.' ?>
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form id="form-varietat"
          method="POST"
          action="<?= BASE_URL ?>modules/varietats/nova_varietat.php"
          class="formulari-card"
          novalidate>

        <!-- Camp ocult per al mode edició -->
        <?php if ($id_editar): ?>
            <input type="hidden" name="id_editar" value="<?= (int)$id_editar ?>">
        <?php endif; ?>

        <!-- ================================================
             BLOC 1: Identificació
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-tag"></i> Identificació
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="nom_varietat" class="form-label form-label--requerit">
                        Nom de la varietat
                    </label>
                    <input type="text"
                           id="nom_varietat"
                           name="nom_varietat"
                           class="form-input camp-requerit"
                           data-etiqueta="El nom de la varietat"
                           placeholder="Ex: Fuji, Conference, Patrona"
                           maxlength="100"
                           value="<?= vcamp('nom_varietat') ?>"
                           required>
                </div>

                <div class="form-grup">
                    <label for="id_especie" class="form-label form-label--requerit">
                        Espècie / Cultiu
                    </label>
                    <select id="id_especie"
                            name="id_especie"
                            class="form-select camp-requerit"
                            data-etiqueta="L'espècie"
                            required>
                        <option value="0">— Selecciona una espècie —</option>
                        <?php foreach ($especies as $esp):
                            $sel = (int)($_POST['id_especie'] ?? $dades['id_especie'] ?? 0);
                        ?>
                            <option value="<?= (int)$esp['id_especie'] ?>"
                                <?= ($sel === (int)$esp['id_especie']) ? 'selected' : '' ?>>
                                <?= e($esp['nom_comu']) ?>
                                <?= $esp['nom_cientific'] ? '(' . e($esp['nom_cientific']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 2: Dades agronòmiques
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-leaf"></i> Dades agronòmiques
            </legend>

            <div class="form-grup">
                <label for="caracteristiques_agronomiques" class="form-label">
                    Característiques agronòmiques
                </label>
                <textarea id="caracteristiques_agronomiques"
                          name="caracteristiques_agronomiques"
                          class="form-textarea"
                          rows="3"
                          placeholder="Ex: Necessitats hídriques mitges, 800 hores de fred, alta resistència a roca bacteriana..."><?= vcamp('caracteristiques_agronomiques') ?></textarea>
            </div>

            <div class="form-grup">
                <label for="cicle_vegetatiu" class="form-label">Cicle vegetatiu</label>
                <textarea id="cicle_vegetatiu"
                          name="cicle_vegetatiu"
                          class="form-textarea"
                          rows="2"
                          placeholder="Ex: Floració abril, quallat maig, maduració agost-setembre..."><?= vcamp('cicle_vegetatiu') ?></textarea>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="requisits_pollinitzacio" class="form-label">
                        Requisits de pol·linització
                    </label>
                    <input type="text"
                           id="requisits_pollinitzacio"
                           name="requisits_pollinitzacio"
                           class="form-input"
                           placeholder="Ex: Autofèrtil / Necessita pol·linitzador Golden"
                           maxlength="255"
                           value="<?= vcamp('requisits_pollinitzacio') ?>">
                </div>

                <div class="form-grup">
                    <label for="productivitat_mitjana_esperada" class="form-label">
                        Productivitat esperada (kg/ha)
                    </label>
                    <input type="number"
                           id="productivitat_mitjana_esperada"
                           name="productivitat_mitjana_esperada"
                           class="form-input"
                           data-tipus="decimal"
                           data-etiqueta="La productivitat"
                           min="0" step="0.01"
                           placeholder="Ex: 45000"
                           value="<?= vcamp('productivitat_mitjana_esperada') ?>">
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 3: Qualitats i sensibilitats
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-shield-virus"></i> Qualitats i sensibilitats
            </legend>

            <div class="form-grup">
                <label for="qualitats_comercials" class="form-label">
                    Qualitats comercials / Sensibilitats fitosanitàries
                </label>
                <textarea id="qualitats_comercials"
                          name="qualitats_comercials"
                          class="form-textarea"
                          rows="3"
                          placeholder="Ex: Calibre AA, sensible a foc bacterià, bona acceptació mercat..."><?= vcamp('qualitats_comercials') ?></textarea>
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk"></i>
                <?= $id_editar ? 'Actualitzar Varietat' : 'Guardar Varietat' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/varietats/varietats.php" class="boto-secundari">
                <i class="fas fa-xmark"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
