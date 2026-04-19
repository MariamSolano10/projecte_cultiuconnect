<?php
/**
 * modules/finances/nova_inversio.php — Creació i edició d'inversions/despeses.
 *
 * GET  → mostra el formulari (mode nou o edició via ?editar=ID)
 * POST → valida, insereix/actualitza, redirigeix (PRG)
 *
 * Permet imputar despeses globals o vincular-les a un sector o màquina.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$errors    = [];
$sectors   = [];
$maquines  = [];
$id_edicio = sanitize_int($_GET['editar'] ?? null);
$es_edicio = $id_edicio !== null;

const CATEGORIES_INV = [
    'Maquinaria', 'Fitosanitaris', 'Adobs',
    'Infraestructura', 'Personal', 'Serveis', 'Altres',
];

$dades = [
    'concepte'      => '',
    'categoria'     => '',
    'data_inversio' => date('Y-m-d'),
    'import'        => '',
    'proveidor'     => '',
    'id_sector'     => '',
    'id_maquinaria' => '',
    'observacions'  => '',
];

// -----------------------------------------------------------
// Carregar selectors (sectors i màquines)
// -----------------------------------------------------------
try {
    $pdo      = connectDB();
    $sectors  = $pdo->query("SELECT id_sector, nom FROM sector ORDER BY nom ASC")->fetchAll();
    $maquines = $pdo->query("SELECT id_maquinaria, nom_maquina FROM maquinaria ORDER BY nom_maquina ASC")->fetchAll();
} catch (Exception $e) {
    error_log('[CultiuConnect] nova_inversio.php (selectors): ' . $e->getMessage());
}

// -----------------------------------------------------------
// Mode edició: carregar dades existents (GET)
// -----------------------------------------------------------
if ($es_edicio && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo  = connectDB();
        $stmt = $pdo->prepare("SELECT * FROM inversio WHERE id_inversio = ?");
        $stmt->execute([$id_edicio]);
        $existent = $stmt->fetch();

        if (!$existent) {
            set_flash('error', 'El registre d\'inversió que intentes editar no existeix.');
            header('Location: ' . BASE_URL . 'modules/finances/inversions.php');
            exit;
        }
        $dades = $existent;

    } catch (Exception $e) {
        error_log('[CultiuConnect] nova_inversio.php (SELECT): ' . $e->getMessage());
        set_flash('error', 'Error carregant les dades de la inversió.');
        header('Location: ' . BASE_URL . 'modules/finances/inversions.php');
        exit;
    }
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dades['concepte']      = sanitize($_POST['concepte']      ?? '');
    $dades['categoria']     = sanitize($_POST['categoria']      ?? '');
    $dades['data_inversio'] = sanitize($_POST['data_inversio']  ?? '');
    $dades['import']        = sanitize_decimal($_POST['import'] ?? null);
    $dades['proveidor']     = sanitize($_POST['proveidor']      ?? '');
    $dades['id_sector']     = sanitize_int($_POST['id_sector']     ?? null);
    $dades['id_maquinaria'] = sanitize_int($_POST['id_maquinaria'] ?? null);
    $dades['observacions']  = sanitize($_POST['observacions']   ?? '');

    // Validació
    if (empty($dades['concepte'])) {
        $errors[] = 'El concepte de la despesa és obligatori.';
    }
    if (!in_array($dades['categoria'], CATEGORIES_INV)) {
        $errors[] = 'La categoria seleccionada no és vàlida.';
    }
    if (empty($dades['data_inversio']) || !strtotime($dades['data_inversio'])) {
        $errors[] = 'La data de la inversió és obligatòria i ha de ser vàlida.';
    }
    if ($dades['import'] === null || $dades['import'] <= 0) {
        $errors[] = 'L\'import ha de ser un número positiu superior a 0.';
    }

    if (empty($errors)) {
        try {
            $pdo    = connectDB();
            $params = [
                ':concepte'      => $dades['concepte'],
                ':categoria'     => $dades['categoria'],
                ':data_inversio' => $dades['data_inversio'],
                ':import'        => $dades['import'],
                ':proveidor'     => $dades['proveidor']     ?: null,
                ':id_sector'     => $dades['id_sector']     ?: null,
                ':id_maquinaria' => $dades['id_maquinaria'] ?: null,
                ':observacions'  => $dades['observacions']  ?: null,
            ];

            if ($es_edicio) {
                $params[':id'] = $id_edicio;
                $stmt = $pdo->prepare("
                    UPDATE inversio SET
                        concepte      = :concepte,
                        categoria     = :categoria,
                        data_inversio = :data_inversio,
                        import        = :import,
                        proveidor     = :proveidor,
                        id_sector     = :id_sector,
                        id_maquinaria = :id_maquinaria,
                        observacions  = :observacions
                    WHERE id_inversio = :id
                ");
                $stmt->execute($params);
                set_flash('success', 'El registre d\'inversió s\'ha actualitzat correctament.');

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO inversio
                        (concepte, categoria, data_inversio, import, proveidor,
                         id_sector, id_maquinaria, observacions)
                    VALUES
                        (:concepte, :categoria, :data_inversio, :import, :proveidor,
                         :id_sector, :id_maquinaria, :observacions)
                ");
                $stmt->execute($params);
                set_flash('success', 'Nova despesa «' . $dades['concepte'] . '» registrada correctament.');
            }

            header('Location: ' . BASE_URL . 'modules/finances/inversions.php');
            exit;

        } catch (Exception $e) {
            error_log('[CultiuConnect] nova_inversio.php (POST): ' . $e->getMessage());
            $errors[] = 'Error intern en desar el registre. Torna-ho a intentar.';
        }
    }
}

// -----------------------------------------------------------
// Capçalera
// -----------------------------------------------------------
$titol_pagina  = $es_edicio ? 'Editar Inversió' : 'Nova Inversió / Despesa';
$pagina_activa = 'finances';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas <?= $es_edicio ? 'fa-pen' : 'fa-file-invoice-dollar' ?>"
               aria-hidden="true"></i>
            <?= $es_edicio
                ? 'Editar Registre: ' . e($dades['concepte'])
                : 'Registrar Nova Despesa o Inversió' ?>
        </h1>
        <p class="descripcio-seccio">
            Introdueix les dades econòmiques. Pots vincular la despesa a un sector
            o màquina concreta per millorar l'anàlisi de costos.
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

    <form action="<?= BASE_URL ?>modules/finances/nova_inversio.php<?= $es_edicio ? '?editar=' . (int)$id_edicio : '' ?>"
          method="POST"
          class="formulari-card"
          novalidate>

        <!-- ================================================
             BLOC 1: Dades principals
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-euro-sign"></i> Dades principals
            </legend>

            <div class="form-grup">
                <label for="concepte" class="form-label form-label--requerit">
                    Concepte / Descripció
                </label>
                <input type="text"
                       id="concepte"
                       name="concepte"
                       class="form-input camp-requerit"
                       data-etiqueta="El concepte"
                       value="<?= e($dades['concepte']) ?>"
                       placeholder="Ex: Reparació motor tractor, Compra d'adob NPK..."
                       maxlength="200"
                       required>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="import" class="form-label form-label--requerit">
                        Import sense IVA (€)
                    </label>
                    <input type="number"
                           id="import"
                           name="import"
                           class="form-input camp-requerit"
                           data-etiqueta="L'import"
                           data-tipus="decimal"
                           value="<?= e($dades['import']) ?>"
                           step="0.01"
                           min="0.01"
                           placeholder="Ex: 1250.50"
                           required>
                </div>

                <div class="form-grup">
                    <label for="data_inversio" class="form-label form-label--requerit">
                        Data
                    </label>
                    <input type="date"
                           id="data_inversio"
                           name="data_inversio"
                           class="form-input camp-requerit"
                           data-etiqueta="La data"
                           data-no-futur
                           value="<?= e($dades['data_inversio']) ?>"
                           required>
                </div>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="categoria" class="form-label form-label--requerit">
                        Categoria
                    </label>
                    <select id="categoria"
                            name="categoria"
                            class="form-select camp-requerit"
                            data-etiqueta="La categoria"
                            required>
                        <option value="">— Selecciona —</option>
                        <?php foreach (CATEGORIES_INV as $cat): ?>
                            <option value="<?= $cat ?>"
                                <?= $dades['categoria'] === $cat ? 'selected' : '' ?>>
                                <?= $cat ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="proveidor" class="form-label">
                        Proveïdor (opcional)
                    </label>
                    <input type="text"
                           id="proveidor"
                           name="proveidor"
                           class="form-input"
                           value="<?= e($dades['proveidor']) ?>"
                           placeholder="Ex: Agrícola del Segrià SL"
                           maxlength="150">
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 2: Imputació de costos (opcional)
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-link"></i> Imputació de costos
                <span class="form-bloc__opcional">(opcional)</span>
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="id_sector" class="form-label">
                        Vincular a un sector
                    </label>
                    <select id="id_sector" name="id_sector" class="form-select">
                        <option value="">— Cap sector específic —</option>
                        <?php foreach ($sectors as $s): ?>
                            <option value="<?= (int)$s['id_sector'] ?>"
                                <?= (int)$dades['id_sector'] === (int)$s['id_sector'] ? 'selected' : '' ?>>
                                <?= e($s['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-ajuda">Útil per calcular la rendibilitat per hectàrea.</span>
                </div>

                <div class="form-grup">
                    <label for="id_maquinaria" class="form-label">
                        Vincular a maquinària
                    </label>
                    <select id="id_maquinaria" name="id_maquinaria" class="form-select">
                        <option value="">— Cap màquina específica —</option>
                        <?php foreach ($maquines as $m): ?>
                            <option value="<?= (int)$m['id_maquinaria'] ?>"
                                <?= (int)$dades['id_maquinaria'] === (int)$m['id_maquinaria'] ? 'selected' : '' ?>>
                                <?= e($m['nom_maquina']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-ajuda">Útil per controlar la despesa de manteniment.</span>
                </div>
            </div>

            <div class="form-grup">
                <label for="observacions" class="form-label">
                    Observacions addicionals
                </label>
                <textarea id="observacions"
                          name="observacions"
                          class="form-textarea"
                          rows="3"
                          placeholder="Número de factura, referència albarà, apunts addicionals..."
                          maxlength="500"><?= e($dades['observacions']) ?></textarea>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                <?= $es_edicio ? 'Desar Canvis' : 'Registrar Inversió' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/finances/inversions.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i>
                Cancel·lar
            </a>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>