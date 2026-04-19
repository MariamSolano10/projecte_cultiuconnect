<?php
/**
 * modules/estoc/nou_producte.php — Creació i edició de productes de l'inventari.
 *
 * GET  → mostra el formulari (mode nou o edició via ?editar=ID)
 * POST → valida, insereix/actualitza, redirigeix (PRG)
 *
 * ENUM tipus BD real: 'Fitosanitari', 'Fertilitzant', 'Herbicida'
 * En mode edició l'estoc_actual NO es modifica aquí → usar moviment_estoc.php
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$errors    = [];
$id_edicio = sanitize_int($_GET['editar'] ?? null);
$es_edicio = $id_edicio !== null;

$dades = [
    'nom_comercial' => '',
    'tipus'         => '',
    'estoc_actual'  => '0',
    'estoc_minim'   => '0',
    'unitat_mesura' => 'L',
];

// ENUM real de la BD
const TIPUS_PRODUCTE = [
    'Fitosanitari' => 'Fitosanitari (pesticida / fungicida)',
    'Fertilitzant' => 'Fertilitzant / Adob',
    'Herbicida'    => 'Herbicida',
];

// -----------------------------------------------------------
// Mode edició: carregar dades existents (GET)
// -----------------------------------------------------------
if ($es_edicio && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo  = connectDB();
        $stmt = $pdo->prepare(
            "SELECT nom_comercial, tipus, estoc_actual, estoc_minim, unitat_mesura
             FROM producte_quimic WHERE id_producte = ?"
        );
        $stmt->execute([$id_edicio]);
        $existent = $stmt->fetch();

        if (!$existent) {
            set_flash('error', 'El producte que intentes editar no existeix.');
            header('Location: ' . BASE_URL . 'modules/estoc/estoc.php');
            exit;
        }
        $dades = $existent;

    } catch (Exception $e) {
        error_log('[CultiuConnect] nou_producte.php (SELECT): ' . $e->getMessage());
        set_flash('error', 'Error carregant les dades del producte.');
        header('Location: ' . BASE_URL . 'modules/estoc/estoc.php');
        exit;
    }
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dades['nom_comercial'] = sanitize($_POST['nom_comercial'] ?? '');
    $dades['tipus']         = sanitize($_POST['tipus']         ?? '');
    $dades['estoc_minim']   = sanitize_decimal($_POST['estoc_minim']   ?? null) ?? 0;
    $dades['unitat_mesura'] = sanitize($_POST['unitat_mesura'] ?? 'L');

    if (!$es_edicio) {
        $dades['estoc_actual'] = sanitize_decimal($_POST['estoc_actual'] ?? null) ?? 0;
    }

    // Validació
    if (empty($dades['nom_comercial'])) {
        $errors[] = 'El nom comercial és obligatori.';
    }
    if (empty($dades['tipus']) || !array_key_exists($dades['tipus'], TIPUS_PRODUCTE)) {
        $errors[] = 'Has de seleccionar un tipus de producte vàlid.';
    }
    if ($dades['estoc_minim'] < 0) {
        $errors[] = "L'estoc mínim ha de ser 0 o superior.";
    }
    if (!$es_edicio && $dades['estoc_actual'] < 0) {
        $errors[] = "L'estoc inicial ha de ser 0 o superior.";
    }

    if (empty($errors)) {
        try {
            $pdo = connectDB();

            $sql_check  = "SELECT id_producte FROM producte_quimic WHERE nom_comercial = ?";
            $params_c   = [$dades['nom_comercial']];
            if ($es_edicio) {
                $sql_check .= " AND id_producte != ?";
                $params_c[] = $id_edicio;
            }
            $check = $pdo->prepare($sql_check);
            $check->execute($params_c);

            if ($check->fetch()) {
                $errors[] = 'Ja existeix un producte amb aquest nom comercial.';
            } else {
                if ($es_edicio) {
                    $stmt = $pdo->prepare("
                        UPDATE producte_quimic
                        SET nom_comercial = :nom,
                            tipus         = :tipus,
                            estoc_minim   = :emin,
                            unitat_mesura = :umes
                        WHERE id_producte = :id
                    ");
                    $stmt->execute([
                        ':nom'   => $dades['nom_comercial'],
                        ':tipus' => $dades['tipus'],
                        ':emin'  => $dades['estoc_minim'],
                        ':umes'  => $dades['unitat_mesura'],
                        ':id'    => $id_edicio,
                    ]);
                    set_flash('success', 'Producte «' . $dades['nom_comercial'] . '» actualitzat correctament.');
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO producte_quimic
                            (nom_comercial, tipus, estoc_actual, estoc_minim, unitat_mesura)
                        VALUES
                            (:nom, :tipus, :eact, :emin, :umes)
                    ");
                    $stmt->execute([
                        ':nom'   => $dades['nom_comercial'],
                        ':tipus' => $dades['tipus'],
                        ':eact'  => $dades['estoc_actual'],
                        ':emin'  => $dades['estoc_minim'],
                        ':umes'  => $dades['unitat_mesura'],
                    ]);
                    set_flash('success', 'Producte «' . $dades['nom_comercial'] . '» afegit a l\'inventari.');
                }

                header('Location: ' . BASE_URL . 'modules/estoc/estoc.php');
                exit;
            }

        } catch (Exception $e) {
            error_log('[CultiuConnect] nou_producte.php (POST): ' . $e->getMessage());
            $errors[] = 'Error intern en desar el producte. Torna-ho a intentar.';
        }
    }
}

$titol_pagina  = $es_edicio ? 'Editar Producte' : 'Nou Producte';
$pagina_activa = 'inventari';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas <?= $es_edicio ? 'fa-pen' : 'fa-box-open' ?>" aria-hidden="true"></i>
            <?= $es_edicio
                ? 'Editar Producte: ' . e($dades['nom_comercial'])
                : 'Afegir Nou Producte' ?>
        </h1>
        <p class="descripcio-seccio">
            <?= $es_edicio
                ? 'Actualitza les dades mestres del producte. Per ajustar l\'estoc, usa els moviments d\'estoc.'
                : 'Introdueix les dades del nou producte per començar a fer-ne el seguiment.' ?>
        </p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <ul class="llista-errors">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>modules/estoc/nou_producte.php<?= $es_edicio ? '?editar=' . (int)$id_edicio : '' ?>"
          method="POST"
          class="formulari-card"
          novalidate>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-tag"></i> Identificació del producte
            </legend>

            <div class="form-grup">
                <label for="nom_comercial" class="form-label form-label--requerit">
                    Nom comercial
                </label>
                <input type="text"
                       id="nom_comercial"
                       name="nom_comercial"
                       class="form-input camp-requerit"
                       data-etiqueta="El nom comercial"
                       value="<?= e($dades['nom_comercial']) ?>"
                       placeholder="Ex: CobrePlus 50, HerbiMax 36..."
                       maxlength="150"
                       required>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="tipus" class="form-label form-label--requerit">
                        Tipus de producte
                    </label>
                    <select id="tipus" name="tipus"
                            class="form-select camp-requerit"
                            data-etiqueta="El tipus de producte"
                            required>
                        <option value="">— Selecciona —</option>
                        <?php foreach (TIPUS_PRODUCTE as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= $dades['tipus'] === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="unitat_mesura" class="form-label form-label--requerit">
                        Unitat de mesura
                    </label>
                    <select id="unitat_mesura" name="unitat_mesura"
                            class="form-select camp-requerit"
                            data-etiqueta="La unitat de mesura"
                            required>
                        <?php foreach ([
                            'L'  => 'Litres (L)',
                            'Kg' => 'Quilograms (Kg)',
                            'U'  => 'Unitats (U)',
                        ] as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= $dades['unitat_mesura'] === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-boxes-stacked"></i> Estoc
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="estoc_actual"
                           class="form-label <?= !$es_edicio ? 'form-label--requerit' : '' ?>">
                        Estoc <?= $es_edicio ? 'actual (només lectura)' : 'inicial' ?>
                    </label>
                    <input type="number"
                           id="estoc_actual"
                           name="estoc_actual"
                           class="form-input <?= !$es_edicio ? 'camp-requerit' : 'form-input--readonly' ?>"
                           data-etiqueta="L'estoc"
                           data-tipus="decimal"
                           value="<?= e($dades['estoc_actual']) ?>"
                           step="0.01" min="0"
                           <?= $es_edicio ? 'readonly' : 'required' ?>>
                    <?php if ($es_edicio): ?>
                        <span class="form-ajuda">
                            <i class="fas fa-info-circle"></i>
                            Per modificar l'estoc, usa
                            <a href="<?= BASE_URL ?>modules/estoc/moviment_estoc.php">
                                Moviments d'Estoc
                            </a>.
                        </span>
                    <?php endif; ?>
                </div>

                <div class="form-grup">
                    <label for="estoc_minim" class="form-label form-label--requerit">
                        Estoc d'alerta (mínim)
                    </label>
                    <input type="number"
                           id="estoc_minim"
                           name="estoc_minim"
                           class="form-input camp-requerit"
                           data-etiqueta="L'estoc mínim"
                           data-tipus="decimal"
                           value="<?= e($dades['estoc_minim']) ?>"
                           step="0.01" min="0"
                           required>
                    <span class="form-ajuda">
                        El sistema avisarà si l'estoc baixa d'aquesta quantitat.
                    </span>
                </div>
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                <?= $es_edicio ? 'Desar Canvis' : 'Crear Producte' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/estoc/estoc.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i>
                Cancel·lar
            </a>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>