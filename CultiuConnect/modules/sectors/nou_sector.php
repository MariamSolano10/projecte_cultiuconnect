<?php
/**
 * modules/sectors/nou_sector.php — Formulari per crear un nou sector i plantació.
 *
 * Funcionament:
 *   GET  → mostra el formulari
 *   POST → valida, insereix a `sector` i `parcela_sector`, redirigeix amb flash
 *
 * Un sector necessita obligatòriament:
 *   - Nom intern
 *   - Parcel·la física associada (parcela_sector)
 *
 * Opcionalment es pot registrar la plantació inicial:
 *   - Varietat, data de plantació, marc de plantació, previsió entrada producció
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nom         = sanitize($_POST['nom']          ?? '');
    $id_parcela  = sanitize_int($_POST['id_parcela'] ?? null);
    $id_varietat = sanitize_int($_POST['id_varietat'] ?? null);
    $data_plant  = sanitize($_POST['data_plantacio'] ?? '');
    $marc_fila   = sanitize_decimal($_POST['marc_fila']  ?? null);
    $marc_arbre  = sanitize_decimal($_POST['marc_arbre'] ?? null);
    $prev_prod   = sanitize($_POST['previsio_entrada_produccio'] ?? '');

    $errors = [];
    if (empty($nom))        $errors[] = 'El nom del sector és obligatori.';
    if (!$id_parcela)       $errors[] = 'Cal seleccionar una parcel·la física.';

    if (empty($errors)) {
        try {
            $pdo = connectDB();
            $pdo->beginTransaction();

            // 1. Inserir el sector
            $stmt = $pdo->prepare("INSERT INTO sector (nom) VALUES (:nom)");
            $stmt->execute([':nom' => $nom]);
            $id_sector = (int)$pdo->lastInsertId();

            // 2. Relacionar sector ↔ parcel·la
            $stmt = $pdo->prepare(
                "INSERT INTO parcela_sector (id_parcela, id_sector) VALUES (:id_parcela, :id_sector)"
            );
            $stmt->execute([':id_parcela' => $id_parcela, ':id_sector' => $id_sector]);

            // 3. Si s'ha seleccionat varietat, crear la plantació inicial
            if ($id_varietat && !empty($data_plant)) {
                $stmt = $pdo->prepare("
                    INSERT INTO plantacio
                        (id_sector, id_varietat, data_plantacio, marc_fila, marc_arbre, previsio_entrada_produccio)
                    VALUES
                        (:id_sector, :id_varietat, :data_plantacio, :marc_fila, :marc_arbre, :prev_prod)
                ");
                $stmt->execute([
                    ':id_sector'    => $id_sector,
                    ':id_varietat'  => $id_varietat,
                    ':data_plantacio' => $data_plant,
                    ':marc_fila'    => $marc_fila,
                    ':marc_arbre'   => $marc_arbre,
                    ':prev_prod'    => !empty($prev_prod) ? $prev_prod : null,
                ]);
            }

            $pdo->commit();

            set_flash('success', "Sector «{$nom}» creat correctament.");
            header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[CultiuConnect] nou_sector.php POST: ' . $e->getMessage());
            $errors[] = 'Error intern en guardar el sector. Torna-ho a intentar.';
        }
    }
}

// -----------------------------------------------------------
// Dades per als selectors del formulari
// -----------------------------------------------------------
$parceles  = [];
$varietats = [];
$error_db  = null;

try {
    $pdo = connectDB();

    $parceles = $pdo->query(
        "SELECT id_parcela, nom, superficie_ha FROM parcela ORDER BY nom ASC"
    )->fetchAll();

    $varietats = $pdo->query("
        SELECT v.id_varietat,
               CONCAT(v.nom_varietat, ' (', e.nom_comu, ')') AS nom_complet
        FROM varietat v
        JOIN especie e ON e.id_especie = v.id_especie
        ORDER BY e.nom_comu ASC, v.nom_varietat ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] nou_sector.php GET: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del formulari.';
}

// -----------------------------------------------------------
// Capçalera
// -----------------------------------------------------------
$titol_pagina  = 'Nou Sector';
$pagina_activa = 'sectors';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-seedling" aria-hidden="true"></i>
            Nou Sector / Plantació
        </h1>
        <p class="descripcio-seccio">
            Un sector és la unitat bàsica de gestió del cultiu. Cada sector pertany a una
            parcel·la física i pot tenir una plantació activa associada.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Errors de validació POST -->
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

    <form id="form-sector"
          method="POST"
          action="<?= BASE_URL ?>modules/sectors/nou_sector.php"
          class="formulari-card"
          novalidate>

        <!-- ================================================
             BLOC 1: Identificació del sector
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-tag" aria-hidden="true"></i>
                Identificació
            </legend>

            <div class="form-grup">
                <label for="nom" class="form-label form-label--requerit">
                    Nom intern del sector
                </label>
                <input type="text"
                       id="nom"
                       name="nom"
                       class="form-input camp-requerit"
                       data-etiqueta="El nom del sector"
                       placeholder="Ex: Sector A – Poma Golden Nord"
                       value="<?= e($_POST['nom'] ?? '') ?>"
                       maxlength="100"
                       required>
                <span class="form-ajuda">Nom únic que identificarà aquest sector en tot el sistema.</span>
            </div>

            <div class="form-grup">
                <label for="id_parcela" class="form-label form-label--requerit">
                    Parcel·la física
                </label>
                <select id="id_parcela"
                        name="id_parcela"
                        class="form-select camp-requerit"
                        data-etiqueta="La parcel·la"
                        required>
                    <option value="0">— Selecciona una parcel·la —</option>
                    <?php foreach ($parceles as $p): ?>
                        <option value="<?= (int)$p['id_parcela'] ?>"
                            <?= ((int)($_POST['id_parcela'] ?? 0) === (int)$p['id_parcela']) ? 'selected' : '' ?>>
                            <?= e($p['nom']) ?>
                            (<?= format_ha((float)$p['superficie_ha']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-ajuda">Parcel·la de terreny on s'ubica físicament aquest sector.</span>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 2: Plantació inicial (opcional)
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-apple-whole" aria-hidden="true"></i>
                Plantació inicial
                <span class="form-bloc__opcional">(opcional — es pot afegir més endavant)</span>
            </legend>

            <div class="form-grup">
                <label for="id_varietat" class="form-label">Varietat / Cultiu</label>
                <select id="id_varietat" name="id_varietat" class="form-select">
                    <option value="">— Sense plantació de moment —</option>
                    <?php foreach ($varietats as $v): ?>
                        <option value="<?= (int)$v['id_varietat'] ?>"
                            <?= ((int)($_POST['id_varietat'] ?? 0) === (int)$v['id_varietat']) ? 'selected' : '' ?>>
                            <?= e($v['nom_complet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_plantacio" class="form-label">Data de plantació</label>
                    <input type="date"
                           id="data_plantacio"
                           name="data_plantacio"
                           class="form-input"
                           data-no-futur
                           value="<?= e($_POST['data_plantacio'] ?? '') ?>">
                </div>

                <div class="form-grup">
                    <label for="previsio_entrada_produccio" class="form-label">
                        Previsió entrada en producció
                    </label>
                    <input type="date"
                           id="previsio_entrada_produccio"
                           name="previsio_entrada_produccio"
                           class="form-input"
                           data-data-fi="data_plantacio"
                           data-etiqueta="La previsió d'entrada en producció"
                           value="<?= e($_POST['previsio_entrada_produccio'] ?? '') ?>">
                </div>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="marc_fila" class="form-label">Marc entre files (m)</label>
                    <input type="number"
                           id="marc_fila"
                           name="marc_fila"
                           class="form-input"
                           data-tipus="positiu"
                           data-etiqueta="El marc entre files"
                           step="0.1" min="0.1" max="20"
                           placeholder="Ex: 4.0"
                           value="<?= e($_POST['marc_fila'] ?? '') ?>">
                </div>

                <div class="form-grup">
                    <label for="marc_arbre" class="form-label">Marc entre arbres (m)</label>
                    <input type="number"
                           id="marc_arbre"
                           name="marc_arbre"
                           class="form-input"
                           data-tipus="positiu"
                           data-etiqueta="El marc entre arbres"
                           step="0.1" min="0.1" max="20"
                           placeholder="Ex: 1.5"
                           value="<?= e($_POST['marc_arbre'] ?? '') ?>">
                </div>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                Guardar Sector
            </button>
            <a href="<?= BASE_URL ?>modules/sectors/sectors.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i>
                Cancel·lar
            </a>
        </div>

    </form>

</div><!-- /.contingut-formulari -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
