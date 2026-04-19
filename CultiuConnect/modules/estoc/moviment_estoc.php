<?php
/**
 * modules/estoc/moviment_estoc.php — Registre d'entrades i sortides d'estoc.
 *
 * Utilitza transaccions PDO per garantir que l'historial de moviments
 * i l'estoc actual del producte sempre queden sincronitzats.
 *
 * Tipus de moviment:
 *   Entrada       → suma quantitat a estoc_actual
 *   Sortida       → resta quantitat (comprova estoc suficient)
 *   Regularitzacio → fixa estoc_actual al valor introduït (inventari físic)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$errors           = [];
$llista_productes = [];

// Preselecció de producte via GET (?id_producte=N)
$id_producte_get = sanitize_int($_GET['id_producte'] ?? null);

$dades = [
    'id_producte'    => $id_producte_get ?: '',
    'tipus_moviment' => 'Entrada',
    'quantitat'      => '',
    'data_moviment'  => date('Y-m-d'),
    'motiu'          => '',
];

// Carregar llistat de productes per al selector
try {
    $pdo              = connectDB();
    $llista_productes = $pdo->query(
        "SELECT id_producte, nom_comercial, estoc_actual, unitat_mesura
         FROM producte_quimic
         ORDER BY nom_comercial ASC"
    )->fetchAll();
} catch (Exception $e) {
    error_log('[CultiuConnect] moviment_estoc.php (SELECT): ' . $e->getMessage());
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dades['id_producte']    = sanitize_int($_POST['id_producte']    ?? null);
    $dades['tipus_moviment'] = sanitize($_POST['tipus_moviment']     ?? '');
    $dades['quantitat']      = sanitize_decimal($_POST['quantitat']  ?? null);
    $dades['data_moviment']  = sanitize($_POST['data_moviment']      ?? '');
    $dades['motiu']          = sanitize($_POST['motiu']              ?? '');

    // Validació
    $tipus_valids = ['Entrada', 'Sortida', 'Regularitzacio'];

    if (!$dades['id_producte']) {
        $errors[] = 'Has de seleccionar un producte.';
    }
    if (!in_array($dades['tipus_moviment'], $tipus_valids)) {
        $errors[] = 'El tipus de moviment no és vàlid.';
    }
    if ($dades['quantitat'] === null || $dades['quantitat'] <= 0) {
        $errors[] = 'La quantitat ha de ser un número superior a 0.';
    }
    if (empty($dades['data_moviment']) || !strtotime($dades['data_moviment'])) {
        $errors[] = 'La data del moviment és obligatòria i ha de ser vàlida.';
    }

    if (empty($errors)) {
        try {
            $pdo = connectDB();

            // Verificar que el producte existeix i obtenir estoc actual
            $check = $pdo->prepare(
                "SELECT estoc_actual, nom_comercial, unitat_mesura
                 FROM producte_quimic WHERE id_producte = ?"
            );
            $check->execute([$dades['id_producte']]);
            $producte = $check->fetch();

            if (!$producte) {
                $errors[] = 'El producte seleccionat no existeix.';
            } else {
                $estoc_actual = (float)$producte['estoc_actual'];
                $quantitat    = (float)$dades['quantitat'];

                // Comprovar estoc suficient per a sortides
                if ($dades['tipus_moviment'] === 'Sortida' && $estoc_actual < $quantitat) {
                    $errors[] = sprintf(
                        'Estoc insuficient per a la sortida. Disponible: %s %s.',
                        number_format($estoc_actual, 2, ',', '.'),
                        $producte['unitat_mesura']
                    );
                } else {
                    // Calcular nou estoc
                    $nou_estoc = match ($dades['tipus_moviment']) {
                        'Entrada'        => $estoc_actual + $quantitat,
                        'Sortida'        => $estoc_actual - $quantitat,
                        'Regularitzacio' => $quantitat,  // Sobreescriu l'estoc (inventari físic)
                    };

                    // Transacció: inserir moviment + actualitzar estoc
                    $pdo->beginTransaction();

                    $ins = $pdo->prepare("
                        INSERT INTO moviment_estoc
                            (id_producte, tipus_moviment, quantitat, data_moviment, motiu)
                        VALUES
                            (:id, :tipus, :quantitat, :data_mov, :motiu)
                    ");
                    $ins->execute([
                        ':id'        => $dades['id_producte'],
                        ':tipus'     => $dades['tipus_moviment'],
                        ':quantitat' => $quantitat,
                        ':data_mov'  => $dades['data_moviment'],
                        ':motiu'     => $dades['motiu'] ?: null,
                    ]);

                    $upd = $pdo->prepare(
                        "UPDATE producte_quimic SET estoc_actual = ? WHERE id_producte = ?"
                    );
                    $upd->execute([$nou_estoc, $dades['id_producte']]);

                    $pdo->commit();

                    // Registrar acció al log
                    registrar_accio(
                        'MOVIMENT ESTOC: ' . $dades['tipus_moviment'] . ' de ' . number_format($quantitat, 2, ',', '.') . ' ' . $producte['unitat_mesura'],
                        'moviment_estoc',
                        (int)$pdo->lastInsertId(),
                        'Producte: ' . $producte['nom_comercial'] . ', Motiu: ' . $dades['motiu']
                    );

                    set_flash('success', 'Moviment d\'estoc registrat correctament.');
                    header('Location: ' . BASE_URL . 'modules/estoc/estoc.php');
                    exit;
                }
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[CultiuConnect] moviment_estoc.php (transacció): ' . $e->getMessage());
            $errors[] = 'Error crític en registrar el moviment. Cap dada ha estat alterada.';
        }
    }
}

// -----------------------------------------------------------
// Capçalera
// -----------------------------------------------------------
$titol_pagina  = 'Nou Moviment d\'Estoc';
$pagina_activa = 'inventari';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-exchange-alt" aria-hidden="true"></i>
            Registrar Moviment d'Estoc
        </h1>
        <p class="descripcio-seccio">
            Registra una entrada (compra), sortida manual o regularització per mantenir
            l'inventari al dia. Cada moviment queda traçat a l'historial.
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

    <form action="<?= BASE_URL ?>modules/estoc/moviment_estoc.php"
          method="POST"
          class="formulari-card"
          novalidate>

        <!-- ================================================
             BLOC 1: Producte i tipus
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-boxes-stacked"></i> Producte i tipus de moviment
            </legend>

            <div class="form-grup">
                <label for="id_producte" class="form-label form-label--requerit">
                    Producte
                </label>
                <select id="id_producte"
                        name="id_producte"
                        class="form-select camp-requerit"
                        data-etiqueta="El producte"
                        required>
                    <option value="">— Selecciona un producte —</option>
                    <?php foreach ($llista_productes as $prod): ?>
                        <option value="<?= (int)$prod['id_producte'] ?>"
                            <?= (int)$dades['id_producte'] === (int)$prod['id_producte'] ? 'selected' : '' ?>>
                            <?= e($prod['nom_comercial']) ?>
                            (estoc: <?= number_format((float)$prod['estoc_actual'], 2, ',', '.') ?>
                            <?= e($prod['unitat_mesura']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grup">
                <label for="tipus_moviment" class="form-label form-label--requerit">
                    Tipus de moviment
                </label>
                <select id="tipus_moviment"
                        name="tipus_moviment"
                        class="form-select camp-requerit"
                        data-etiqueta="El tipus de moviment"
                        required>
                    <option value="Entrada"
                        <?= $dades['tipus_moviment'] === 'Entrada' ? 'selected' : '' ?>>
                        Entrada (+ estoc) — compra o recepció
                    </option>
                    <option value="Sortida"
                        <?= $dades['tipus_moviment'] === 'Sortida' ? 'selected' : '' ?>>
                        Sortida (− estoc) — baixa manual
                    </option>
                    <option value="Regularitzacio"
                        <?= $dades['tipus_moviment'] === 'Regularitzacio' ? 'selected' : '' ?>>
                        Regularització — fixar estoc (inventari físic)
                    </option>
                </select>
                <span class="form-ajuda">
                    La <strong>regularització</strong> sobreescriu l'estoc actual amb la quantitat introduïda.
                </span>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 2: Quantitat, data i motiu
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-scale-balanced"></i> Detalls del moviment
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="quantitat" class="form-label form-label--requerit">
                        Quantitat
                    </label>
                    <input type="number"
                           id="quantitat"
                           name="quantitat"
                           class="form-input camp-requerit"
                           data-etiqueta="La quantitat"
                           data-tipus="decimal"
                           value="<?= e($dades['quantitat']) ?>"
                           step="0.01"
                           min="0.01"
                           placeholder="Ex: 10.50"
                           required>
                </div>

                <div class="form-grup">
                    <label for="data_moviment" class="form-label form-label--requerit">
                        Data del moviment
                    </label>
                    <input type="date"
                           id="data_moviment"
                           name="data_moviment"
                           class="form-input camp-requerit"
                           data-etiqueta="La data"
                           data-no-futur
                           value="<?= e($dades['data_moviment']) ?>"
                           required>
                </div>
            </div>

            <div class="form-grup">
                <label for="motiu" class="form-label">
                    Motiu / Observacions (opcional)
                </label>
                <input type="text"
                       id="motiu"
                       name="motiu"
                       class="form-input"
                       value="<?= e($dades['motiu']) ?>"
                       placeholder="Ex: Albarà núm. 1234, envàs trencat, inventari anual..."
                       maxlength="255">
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                Confirmar Moviment
            </button>
            <a href="<?= BASE_URL ?>modules/estoc/estoc.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i>
                Cancel·lar
            </a>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>