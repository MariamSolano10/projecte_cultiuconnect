<?php
/**
 * modules/collita/nou_lot.php — Formulari per registrar un nou lot a inventari_estoc.
 *
 * Nota: Gestiona lots de productes químics (inventari_estoc), no lots de collita.
 * El fitxer és aquí per coherència amb l'estructura original del projecte.
 * Considera moure'l a modules/estoc/ en el futur.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$productes = [];
$error_db  = null;

try {
    $pdo = connectDB();
    $productes = $pdo->query("
        SELECT id_producte, nom_comercial, tipus
        FROM producte_quimic
        ORDER BY nom_comercial ASC
    ")->fetchAll();
} catch (Exception $e) {
    error_log('[CultiuConnect] nou_lot.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els productes.';
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_producte = sanitize_int($_POST['id_producte']           ?? null);
    $num_lot     = sanitize($_POST['num_lot']                   ?? '');
    $quantitat   = sanitize_decimal($_POST['quantitat_disponible'] ?? null);
    $unitat      = sanitize($_POST['unitat_mesura']             ?? '');
    $caducitat   = sanitize($_POST['data_caducitat']            ?? '');
    $ubicacio    = sanitize($_POST['ubicacio_magatzem']         ?? '');
    $data_compra = sanitize($_POST['data_compra']               ?? '');
    $proveidor   = sanitize($_POST['proveidor']                 ?? '');
    $preu        = sanitize_decimal($_POST['preu_adquisicio']   ?? null);

    $errors = [];
    if (!$id_producte)                          $errors[] = 'Cal seleccionar un producte.';
    if (empty($num_lot))                        $errors[] = 'El codi de lot és obligatori.';
    if ($quantitat === null || $quantitat <= 0) $errors[] = 'La quantitat ha de ser positiva.';
    if (!in_array($unitat, ['Kg', 'L', 'Unitat'])) $errors[] = 'La unitat de mesura no és vàlida.';
    if (empty($data_compra))                    $errors[] = 'La data de compra és obligatòria.';

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        header('Location: ' . BASE_URL . 'modules/collita/nou_lot.php');
        exit;
    }

    try {
        $pdo = connectDB();

        $stmt = $pdo->prepare("
            INSERT INTO inventari_estoc
                (id_producte, num_lot, quantitat_disponible, unitat_mesura,
                 data_caducitat, ubicacio_magatzem, data_compra, proveidor, preu_adquisicio)
            VALUES
                (:id_producte, :num_lot, :quantitat, :unitat,
                 :caducitat, :ubicacio, :data_compra, :proveidor, :preu)
        ");
        $stmt->execute([
            ':id_producte' => $id_producte,
            ':num_lot'     => $num_lot,
            ':quantitat'   => $quantitat,
            ':unitat'      => $unitat,
            ':caducitat'   => !empty($caducitat)  ? $caducitat  : null,
            ':ubicacio'    => !empty($ubicacio)   ? $ubicacio   : null,
            ':data_compra' => $data_compra,
            ':proveidor'   => !empty($proveidor)  ? $proveidor  : null,
            ':preu'        => $preu,
        ]);

        set_flash('success', 'Nou lot registrat correctament a l\'inventari.');
        header('Location: ' . BASE_URL . 'modules/estoc/estoc.php');
        exit;

    } catch (Exception $e) {
        $codi = $e->getCode();
        if ($codi === '23000') {
            set_flash('error', 'Ja existeix un lot amb el codi "' . e($num_lot) . '" per a aquest producte.');
        } else {
            error_log('[CultiuConnect] nou_lot.php POST: ' . $e->getMessage());
            set_flash('error', 'Error intern en registrar el lot.');
        }
        header('Location: ' . BASE_URL . 'modules/collita/nou_lot.php');
        exit;
    }
}

$titol_pagina  = 'Nou Lot d\'Inventari';
$pagina_activa = 'inventari';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-box-open" aria-hidden="true"></i>
            Registrar Entrada de Nou Lot
        </h1>
        <p class="descripcio-seccio">
            Registra un nou lot de producte fitosanitari o fertilitzant a l'inventari.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form id="form-nou-lot"
          method="POST"
          action="<?= BASE_URL ?>modules/collita/nou_lot.php"
          class="formulari-card"
          novalidate>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-flask"></i> Producte
            </legend>

            <div class="form-grup">
                <label for="id_producte" class="form-label form-label--requerit">
                    Producte químic
                </label>
                <select id="id_producte" name="id_producte"
                        class="form-select camp-requerit"
                        data-etiqueta="El producte" required>
                    <option value="0">— Selecciona un producte —</option>
                    <?php foreach ($productes as $p): ?>
                        <option value="<?= (int)$p['id_producte'] ?>"
                            <?= ((int)($_POST['id_producte'] ?? 0) === (int)$p['id_producte']) ? 'selected' : '' ?>>
                            <?= e($p['nom_comercial']) ?> (<?= e($p['tipus']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="num_lot" class="form-label form-label--requerit">Codi de lot</label>
                    <input type="text" id="num_lot" name="num_lot"
                           class="form-input camp-requerit"
                           data-etiqueta="El codi de lot"
                           maxlength="100"
                           value="<?= e($_POST['num_lot'] ?? '') ?>"
                           required>
                </div>
                <div class="form-grup">
                    <label for="data_caducitat" class="form-label">Data de caducitat</label>
                    <input type="date" id="data_caducitat" name="data_caducitat"
                           class="form-input"
                           value="<?= e($_POST['data_caducitat'] ?? '') ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-warehouse"></i> Quantitat i emmagatzematge
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="quantitat_disponible" class="form-label form-label--requerit">Quantitat</label>
                    <input type="number" id="quantitat_disponible" name="quantitat_disponible"
                           class="form-input camp-requerit"
                           data-etiqueta="La quantitat"
                           data-tipus="positiu"
                           step="0.01" min="0.01"
                           value="<?= e($_POST['quantitat_disponible'] ?? '') ?>"
                           required>
                </div>
                <div class="form-grup">
                    <label for="unitat_mesura" class="form-label form-label--requerit">Unitat</label>
                    <select id="unitat_mesura" name="unitat_mesura"
                            class="form-select camp-requerit"
                            data-etiqueta="La unitat" required>
                        <option value="0">— Selecciona —</option>
                        <?php foreach (['Kg' => 'Kg (Quilograms)', 'L' => 'L (Litres)', 'Unitat' => 'Unitat/Paquet'] as $v => $lbl): ?>
                            <option value="<?= $v ?>" <?= ($_POST['unitat_mesura'] ?? '') === $v ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grup">
                <label for="ubicacio_magatzem" class="form-label">Ubicació al magatzem</label>
                <input type="text" id="ubicacio_magatzem" name="ubicacio_magatzem"
                       class="form-input"
                       maxlength="100"
                       placeholder="Ex: Estant A3, Cambra Fitosanitaris"
                       value="<?= e($_POST['ubicacio_magatzem'] ?? '') ?>">
            </div>
        </fieldset>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-receipt"></i> Compra
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_compra" class="form-label form-label--requerit">Data de compra</label>
                    <input type="date" id="data_compra" name="data_compra"
                           class="form-input camp-requerit"
                           data-etiqueta="La data de compra"
                           data-no-futur
                           value="<?= e($_POST['data_compra'] ?? date('Y-m-d')) ?>"
                           required>
                </div>
                <div class="form-grup">
                    <label for="preu_adquisicio" class="form-label">Preu d'adquisició (€)</label>
                    <input type="number" id="preu_adquisicio" name="preu_adquisicio"
                           class="form-input"
                           data-tipus="decimal"
                           data-etiqueta="El preu"
                           step="0.01" min="0"
                           value="<?= e($_POST['preu_adquisicio'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grup">
                <label for="proveidor" class="form-label">Proveïdor</label>
                <input type="text" id="proveidor" name="proveidor"
                       class="form-input"
                       maxlength="100"
                       value="<?= e($_POST['proveidor'] ?? '') ?>">
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk"></i> Registrar Lot
            </button>
            <a href="<?= BASE_URL ?>modules/estoc/estoc.php" class="boto-secundari">
                <i class="fas fa-xmark"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
