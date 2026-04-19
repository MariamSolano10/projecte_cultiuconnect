<?php
/**
 * modules/sectors/renovar_plantacio.php — Renovació / rotació de cultiu d'un sector.
 *
 * Funcionament:
 *   GET  ?id_sector=X  → mostra la plantació activa + formulari de nova plantació
 *   POST              → tanca la plantació activa (data_arrencada) i n'insereix una de nova
 *
 * Regles de negoci:
 *   - Un sector pot tenir màxim UNA plantació activa (data_arrencada IS NULL).
 *   - Per renovar cal indicar la data d'arrencada de l'antiga i les dades de la nova.
 *   - La data d'arrencada ha de ser >= data_plantacio de l'antiga.
 *   - La nova data de plantació ha de ser >= data d'arrencada.
 *   - Si no hi ha plantació activa, s'obre directament el formulari d'alta.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// -----------------------------------------------------------
// Paràmetre bàsic: id_sector
// -----------------------------------------------------------
$id_sector = sanitize_int($_REQUEST['id_sector'] ?? null);

if (!$id_sector) {
    set_flash('error', 'Cal indicar un sector vàlid.');
    header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
    exit;
}

// -----------------------------------------------------------
// Carregar dades del sector i plantació activa
// -----------------------------------------------------------
$sector          = null;
$plantacio_activa = null;
$varietats       = [];
$errors          = [];
$error_db        = null;

try {
    $pdo = connectDB();

    // Sector
    $stmt = $pdo->prepare("SELECT id_sector, nom FROM sector WHERE id_sector = :id");
    $stmt->execute([':id' => $id_sector]);
    $sector = $stmt->fetch();

    if (!$sector) {
        set_flash('error', 'Sector no trobat.');
        header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
        exit;
    }

    // Plantació activa (data_arrencada IS NULL)
    $stmt = $pdo->prepare("
        SELECT p.id_plantacio,
               p.data_plantacio,
               p.marc_fila,
               p.marc_arbre,
               p.previsio_entrada_produccio,
               p.data_arrencada,
               p.num_arbres_plantats,
               p.sistema_formacio,
               CONCAT(v.nom_varietat, ' (', e.nom_comu, ')') AS nom_varietat_complet
        FROM   plantacio p
        JOIN   varietat  v ON v.id_varietat = p.id_varietat
        JOIN   especie   e ON e.id_especie  = v.id_especie
        WHERE  p.id_sector    = :id_sector
          AND  p.data_arrencada IS NULL
        ORDER BY p.data_plantacio DESC
        LIMIT 1
    ");
    $stmt->execute([':id_sector' => $id_sector]);
    $plantacio_activa = $stmt->fetch();

    // Varietats disponibles per al selector
    $varietats = $pdo->query("
        SELECT v.id_varietat,
               CONCAT(v.nom_varietat, ' (', e.nom_comu, ')') AS nom_complet
        FROM   varietat v
        JOIN   especie  e ON e.id_especie = v.id_especie
        ORDER BY e.nom_comu ASC, v.nom_varietat ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] renovar_plantacio.php GET: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades. Torna-ho a intentar.';
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error_db) {

    // --- Recollida de camps ---
    $id_plantacio_antiga = sanitize_int($_POST['id_plantacio_antiga'] ?? null);
    $data_arrencada      = sanitize($_POST['data_arrencada']      ?? '');

    $id_varietat_nova    = sanitize_int($_POST['id_varietat']      ?? null);
    $data_plantacio_nova = sanitize($_POST['data_plantacio']       ?? '');
    $marc_fila_nou       = sanitize_decimal($_POST['marc_fila']    ?? null);
    $marc_arbre_nou      = sanitize_decimal($_POST['marc_arbre']   ?? null);
    $prev_prod_nova      = sanitize($_POST['previsio_entrada_produccio'] ?? '');
    $sistema_formacio    = sanitize($_POST['sistema_formacio']     ?? '');
    $num_arbres          = sanitize_int($_POST['num_arbres_plantats'] ?? null);
    $origen_material     = sanitize($_POST['origen_material']      ?? '');

    // UX/flux: si hi ha plantació activa i no s'ha informat data d'arrencada,
    // la fixem automàticament a la data de plantació del nou cultiu.
    if ($id_plantacio_antiga && empty($data_arrencada) && !empty($data_plantacio_nova)) {
        $data_arrencada = $data_plantacio_nova;
    }

    // --- Validació ---

    // Bloc "tancament de l'antiga" (només si hi havia plantació activa)
    if ($id_plantacio_antiga) {
        if (empty($data_arrencada)) {
            $errors[] = 'Cal indicar la data d\'arrencada de la plantació actual.';
        } else {
            // La data d'arrencada ha de ser >= data_plantacio de l'antiga
            $stmt = $pdo->prepare(
                "SELECT data_plantacio FROM plantacio WHERE id_plantacio = :id"
            );
            $stmt->execute([':id' => $id_plantacio_antiga]);
            $row = $stmt->fetch();
            if ($row && $data_arrencada < $row['data_plantacio']) {
                $errors[] = 'La data d\'arrencada no pot ser anterior a la data de plantació del cultiu actual ('
                    . format_data($row['data_plantacio']) . ').';
            }
        }
    }

    // Bloc "nova plantació" — obligatòria
    if (!$id_varietat_nova) {
        $errors[] = 'Cal seleccionar la varietat del nou cultiu.';
    }
    if (empty($data_plantacio_nova)) {
        $errors[] = 'Cal indicar la data de plantació del nou cultiu.';
    }
    if ($marc_fila_nou === null || $marc_fila_nou <= 0) {
        $errors[] = 'El marc entre files ha de ser un valor positiu.';
    }
    if ($marc_arbre_nou === null || $marc_arbre_nou <= 0) {
        $errors[] = 'El marc entre arbres ha de ser un valor positiu.';
    }

    // La nova data de plantació ha de ser >= data_arrencada de l'antiga
    if (empty($errors) && $id_plantacio_antiga && !empty($data_arrencada)) {
        if ($data_plantacio_nova < $data_arrencada) {
            $errors[] = 'La data de plantació del nou cultiu no pot ser anterior a la data d\'arrencada ('
                . format_data($data_arrencada) . ').';
        }
    }

    // --- Transacció ---
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Tancar la plantació antiga (si n'hi havia)
            if ($id_plantacio_antiga && !empty($data_arrencada)) {
                $stmt = $pdo->prepare("
                    UPDATE plantacio
                    SET    data_arrencada = :data_arrencada
                    WHERE  id_plantacio  = :id_plantacio
                      AND  id_sector     = :id_sector
                ");
                $stmt->execute([
                    ':data_arrencada' => $data_arrencada,
                    ':id_plantacio'   => $id_plantacio_antiga,
                    ':id_sector'      => $id_sector,
                ]);
            }

            // 2. Inserir la nova plantació
            $stmt = $pdo->prepare("
                INSERT INTO plantacio
                    (id_sector, id_varietat, data_plantacio,
                     marc_fila, marc_arbre, previsio_entrada_produccio,
                     sistema_formacio, num_arbres_plantats, origen_material)
                VALUES
                    (:id_sector, :id_varietat, :data_plantacio,
                     :marc_fila, :marc_arbre, :prev_prod,
                     :sistema_formacio, :num_arbres, :origen_material)
            ");
            $stmt->execute([
                ':id_sector'        => $id_sector,
                ':id_varietat'      => $id_varietat_nova,
                ':data_plantacio'   => $data_plantacio_nova,
                ':marc_fila'        => $marc_fila_nou,
                ':marc_arbre'       => $marc_arbre_nou,
                ':prev_prod'        => !empty($prev_prod_nova) ? $prev_prod_nova : null,
                ':sistema_formacio' => !empty($sistema_formacio) ? $sistema_formacio : null,
                ':num_arbres'       => $num_arbres ?: null,
                ':origen_material'  => !empty($origen_material) ? $origen_material : null,
            ]);

            $pdo->commit();

            $nom_sector = e($sector['nom']);
            set_flash('success', "Plantació renovada correctament al sector «{$nom_sector}».");
            header('Location: ' . BASE_URL . 'modules/sectors/detall_sector.php?id=' . $id_sector);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[CultiuConnect] renovar_plantacio.php POST: ' . $e->getMessage());
            $errors[] = 'Error intern en guardar la renovació. Torna-ho a intentar.';
        }
    }
}

// -----------------------------------------------------------
// Capçalera
// -----------------------------------------------------------
$titol_pagina  = 'Renovar Plantació – ' . e($sector['nom'] ?? '');
$pagina_activa = 'sectors';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-rotate" aria-hidden="true"></i>
            Renovar Plantació
        </h1>
        <p class="descripcio-seccio">
            Sector: <strong><?= e($sector['nom']) ?></strong> —
            <?php if ($plantacio_activa): ?>
                Es tancarà el cultiu actual i es registrarà el nou, mantenint l'historial complet.
            <?php else: ?>
                Aquest sector no té cap plantació activa. Podeu afegir-ne una directament.
            <?php endif; ?>
        </p>
    </div>

    <!-- Errors de BD -->
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

    <form id="form-renovar"
          method="POST"
          action="<?= BASE_URL ?>modules/sectors/renovar_plantacio.php?id_sector=<?= $id_sector ?>"
          class="formulari-card"
          novalidate>

        <input type="hidden" name="id_sector" value="<?= $id_sector ?>">

        <!-- ================================================
             BLOC 1: Tancament del cultiu actual
             (Només si existeix plantació activa)
        ================================================ -->
        <?php if ($plantacio_activa): ?>
        <input type="hidden"
               name="id_plantacio_antiga"
               value="<?= (int)$plantacio_activa['id_plantacio'] ?>">

        <fieldset class="form-bloc form-bloc--alerta">
            <legend class="form-bloc__titol">
                <i class="fas fa-circle-stop" aria-hidden="true"></i>
                Tancament del cultiu actual
            </legend>

            <!-- Resum informatiu de la plantació activa -->
            <div class="info-plantacio-actual">
                <dl class="dades-resum">
                    <div class="dades-resum__item">
                        <dt>Cultiu actual</dt>
                        <dd><strong><?= e($plantacio_activa['nom_varietat_complet']) ?></strong></dd>
                    </div>
                    <div class="dades-resum__item">
                        <dt>Plantat el</dt>
                        <dd><?= format_data($plantacio_activa['data_plantacio']) ?></dd>
                    </div>
                    <?php if ($plantacio_activa['num_arbres_plantats']): ?>
                    <div class="dades-resum__item">
                        <dt>Arbres plantats</dt>
                        <dd><?= (int)$plantacio_activa['num_arbres_plantats'] ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="dades-resum__item">
                        <dt>Marc (fila &times; arbre)</dt>
                        <dd>
                            <?= number_format((float)$plantacio_activa['marc_fila'], 1, ',', '.') ?> m &times; <?= number_format((float)$plantacio_activa['marc_arbre'], 1, ',', '.') ?> m
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="flash flash--warning" role="note">
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                En guardar, aquest cultiu quedarà tancat amb la data d'arrencada indicada.
                Les dades es conservaran íntegrament a l'historial.
            </div>

            <div class="form-grup">
                <label for="data_arrencada" class="form-label form-label--requerit">
                    Data d'arrencada del cultiu actual
                </label>
                <input type="date"
                       id="data_arrencada"
                       name="data_arrencada"
                       class="form-input camp-requerit"
                       data-etiqueta="La data d'arrencada"
                       min="<?= e($plantacio_activa['data_plantacio']) ?>"
                       value="<?= e($_POST['data_arrencada'] ?? ($_POST['data_plantacio'] ?? '')) ?>"
                       required>
                <span class="form-ajuda">
                    Ha de ser igual o posterior al
                    <?= format_data($plantacio_activa['data_plantacio']) ?> (data de plantació).
                </span>
            </div>
        </fieldset>
        <?php else: ?>
            <!-- Sector sense plantació activa: no cal camp data_arrencada -->
            <input type="hidden" name="id_plantacio_antiga" value="">
            <div class="flash flash--info" role="note">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                Aquest sector no té cap plantació activa registrada.
                Podeu crear-ne una directament sense necessitat de tancar cap cultiu anterior.
            </div>
        <?php endif; ?>


        <!-- ================================================
             BLOC 2: Dades del nou cultiu
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-seedling" aria-hidden="true"></i>
                Nou cultiu
            </legend>

            <!-- Varietat -->
            <div class="form-grup">
                <label for="id_varietat" class="form-label form-label--requerit">
                    Varietat / Cultiu
                </label>
                <select id="id_varietat"
                        name="id_varietat"
                        class="form-select camp-requerit"
                        data-etiqueta="La varietat"
                        required>
                    <option value="">— Selecciona una varietat —</option>
                    <?php foreach ($varietats as $v): ?>
                        <option value="<?= (int)$v['id_varietat'] ?>"
                            <?= ((int)($_POST['id_varietat'] ?? 0) === (int)$v['id_varietat']) ? 'selected' : '' ?>>
                            <?= e($v['nom_complet']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Dates -->
            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_plantacio" class="form-label form-label--requerit">
                        Data de plantació
                    </label>
                    <input type="date"
                           id="data_plantacio"
                           name="data_plantacio"
                           class="form-input camp-requerit"
                           data-etiqueta="La data de plantació del nou cultiu"
                           value="<?= e($_POST['data_plantacio'] ?? '') ?>"
                           required>
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

            <!-- Marc de plantació -->
            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="marc_fila" class="form-label form-label--requerit">
                        Marc entre files (m)
                    </label>
                    <input type="number"
                           id="marc_fila"
                           name="marc_fila"
                           class="form-input camp-requerit"
                           data-tipus="positiu"
                           data-etiqueta="El marc entre files"
                           step="0.1" min="0.1" max="20"
                           placeholder="Ex: 4.0"
                           value="<?= e($_POST['marc_fila'] ?? '') ?>"
                           required>
                </div>

                <div class="form-grup">
                    <label for="marc_arbre" class="form-label form-label--requerit">
                        Marc entre arbres (m)
                    </label>
                    <input type="number"
                           id="marc_arbre"
                           name="marc_arbre"
                           class="form-input camp-requerit"
                           data-tipus="positiu"
                           data-etiqueta="El marc entre arbres"
                           step="0.1" min="0.1" max="20"
                           placeholder="Ex: 1.5"
                           value="<?= e($_POST['marc_arbre'] ?? '') ?>"
                           required>
                </div>
            </div>

            <!-- Camps opcionals addicionals -->
            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="num_arbres_plantats" class="form-label">
                        Nombre d'arbres plantats
                    </label>
                    <input type="number"
                           id="num_arbres_plantats"
                           name="num_arbres_plantats"
                           class="form-input"
                           data-tipus="positiu"
                           data-etiqueta="El nombre d'arbres"
                           min="1"
                           placeholder="Ex: 320"
                           value="<?= e($_POST['num_arbres_plantats'] ?? '') ?>">
                </div>

                <div class="form-grup">
                    <label for="sistema_formacio" class="form-label">
                        Sistema de formació
                    </label>
                    <input type="text"
                           id="sistema_formacio"
                           name="sistema_formacio"
                           class="form-input"
                           placeholder="Ex: Eix central, Palmeta, Vas"
                           maxlength="100"
                           value="<?= e($_POST['sistema_formacio'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grup">
                <label for="origen_material" class="form-label">
                    Origen del material vegetal
                </label>
                <input type="text"
                       id="origen_material"
                       name="origen_material"
                       class="form-input"
                       placeholder="Ex: Viver Mas Coll – Lleida, portaempelts M9"
                       maxlength="255"
                       value="<?= e($_POST['origen_material'] ?? '') ?>">
                <span class="form-ajuda">Útil per a la traçabilitat del material d'origen.</span>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-rotate" aria-hidden="true"></i>
                <?= $plantacio_activa ? 'Tancar cultiu actual i iniciar el nou' : 'Crear nova plantació' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/sectors/detall_sector.php?id=<?= $id_sector ?>"
               class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i>
                Cancel·lar
            </a>
        </div>

    </form>

</div><!-- /.contingut-formulari -->

<script>
(function () {
    'use strict';
    const dataPlant = document.getElementById('data_plantacio');
    const dataArr   = document.getElementById('data_arrencada');
    if (!dataPlant || !dataArr) return;

    // Si l'usuari encara no ha informat data d'arrencada manualment,
    // sincronitzem automàticament amb la data de plantació del nou cultiu.
    let touched = false;
    dataArr.addEventListener('input', () => { touched = true; });
    dataPlant.addEventListener('change', () => {
        if (touched) return;
        if (dataPlant.value) dataArr.value = dataPlant.value;
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>