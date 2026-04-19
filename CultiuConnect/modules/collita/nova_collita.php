<?php
/**
 * modules/collita/nova_collita.php — Formulari per registrar una nova collita.
 *
 * Insereix a:
 *   - `collita`           (dades de la partida)
 *   - `collita_treballador` (treballadors i hores, dins transacció)
 *
 * Columnes correctes de la taula `collita`:
 *   id_plantacio, data_inici, data_fi (opt.), quantitat, unitat_mesura, qualitat, observacions
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$plantacions  = [];
$treballadors = [];
$error_db     = null;

try {
    $pdo = connectDB();

    $plantacions = $pdo->query("
        SELECT
            pl.id_plantacio,
            s.nom                                           AS nom_sector,
            COALESCE(v.nom_varietat, 'Sense varietat')     AS nom_varietat
        FROM plantacio pl
        JOIN sector  s ON pl.id_sector   = s.id_sector
        LEFT JOIN varietat v ON pl.id_varietat = v.id_varietat
        WHERE pl.data_arrencada IS NULL
        ORDER BY s.nom ASC
    ")->fetchAll();

    $treballadors = $pdo->query("
        SELECT
            id_treballador,
            CONCAT(nom, ' ', COALESCE(cognoms, '')) AS nom_complet,
            rol
        FROM treballador
        WHERE estat = 'actiu'
        ORDER BY cognoms ASC, nom ASC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] nova_collita.php GET: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del formulari.';
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_plantacio   = sanitize_int($_POST['id_plantacio']   ?? null);
    $data_inici     = sanitize($_POST['data_inici']         ?? '');
    $data_fi        = sanitize($_POST['data_fi']            ?? '');
    $quantitat      = sanitize_decimal($_POST['quantitat']  ?? null);
    $unitat         = sanitize($_POST['unitat_mesura']      ?? '');
    $qualitat       = sanitize($_POST['qualitat']           ?? '');
    $observacions   = sanitize($_POST['observacions']       ?? '');
    $id_treballador = sanitize_int($_POST['id_treballador'] ?? null);
    $hores          = sanitize_decimal($_POST['hores_treballades'] ?? null);

    $errors = [];

    if (!$id_plantacio)  $errors[] = 'Cal seleccionar un sector productiu.';
    if (empty($data_inici) || !strtotime($data_inici)) $errors[] = 'La data d\'inici no és vàlida.';
    if ($quantitat === null || $quantitat <= 0) $errors[] = 'La quantitat ha de ser un valor positiu.';
    if (!in_array($unitat, ['kg', 't', 'caixes'])) $errors[] = 'La unitat de mesura no és vàlida.';
    if (!in_array($qualitat, ['Extra', 'Primera', 'Segona', 'Industrial'])) $errors[] = 'La qualitat no és vàlida.';
    if (!$id_treballador) $errors[] = 'Cal indicar el treballador responsable.';

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        header('Location: ' . BASE_URL . 'modules/collita/nova_collita.php');
        exit;
    }

    try {
        $pdo = connectDB();

        // Verificar plantació activa
        $check = $pdo->prepare("SELECT COUNT(*) FROM plantacio WHERE id_plantacio = ? AND data_arrencada IS NULL");
        $check->execute([$id_plantacio]);
        if ((int)$check->fetchColumn() === 0) {
            throw new RuntimeException('La plantació seleccionada no és vàlida.');
        }

        $pdo->beginTransaction();

        // Inserció a `collita` — columnes reals de la BD
        $stmt = $pdo->prepare("
            INSERT INTO collita
                (id_plantacio, data_inici, data_fi, quantitat, unitat_mesura, qualitat, observacions)
            VALUES
                (:id_plantacio, :data_inici, :data_fi, :quantitat, :unitat_mesura, :qualitat, :observacions)
        ");
        $stmt->execute([
            ':id_plantacio'  => $id_plantacio,
            ':data_inici'    => $data_inici,
            ':data_fi'       => !empty($data_fi) ? $data_fi : null,
            ':quantitat'     => $quantitat,
            ':unitat_mesura' => $unitat,
            ':qualitat'      => $qualitat,
            ':observacions'  => $observacions ?: null,
        ]);
        $id_collita = (int)$pdo->lastInsertId();

        // Inserció a `collita_treballador`
        $stmt_t = $pdo->prepare("
            INSERT INTO collita_treballador (id_collita, id_treballador, hores_treballades)
            VALUES (:id_collita, :id_treballador, :hores)
        ");
        $stmt_t->execute([
            ':id_collita'      => $id_collita,
            ':id_treballador'  => $id_treballador,
            ':hores'           => $hores,
        ]);

        $pdo->commit();

        set_flash('success', sprintf(
            'Collita registrada correctament: %s %s de qualitat %s.',
            number_format($quantitat, 2, ',', '.'), $unitat, $qualitat
        ));
        header('Location: ' . BASE_URL . 'modules/collita/collita.php');
        exit;

    } catch (RuntimeException $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[CultiuConnect] nova_collita.php runtime: ' . $e->getMessage());
        set_flash('error', 'No s\'ha pogut registrar la collita.');
        header('Location: ' . BASE_URL . 'modules/collita/nova_collita.php');
        exit;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('[CultiuConnect] nova_collita.php POST: ' . $e->getMessage());
        set_flash('error', 'Error intern en registrar la collita.');
        header('Location: ' . BASE_URL . 'modules/collita/nova_collita.php');
        exit;
    }
}

$titol_pagina  = 'Nova Collita';
$pagina_activa = 'collita';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-apple-whole" aria-hidden="true"></i>
            Registrar Nova Collita
        </h1>
        <p class="descripcio-seccio">
            Selecciona el sector, el responsable i els kg ingressats per garantir la traçabilitat.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form id="form-collita"
          method="POST"
          action="<?= BASE_URL ?>modules/collita/nova_collita.php"
          class="formulari-card"
          novalidate>

        <!-- BLOC 1: Plantació i dates -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-seedling"></i> Origen i dates
            </legend>

            <div class="form-grup">
                <label for="id_plantacio" class="form-label form-label--requerit">
                    Sector productiu (plantació)
                </label>
                <select id="id_plantacio" name="id_plantacio"
                        class="form-select camp-requerit"
                        data-etiqueta="El sector productiu" required>
                    <option value="0">— Selecciona d'on prové la fruita —</option>
                    <?php foreach ($plantacions as $pl): ?>
                        <option value="<?= (int)$pl['id_plantacio'] ?>"
                            <?= ((int)($_POST['id_plantacio'] ?? 0) === (int)$pl['id_plantacio']) ? 'selected' : '' ?>>
                            <?= e($pl['nom_sector']) ?> — <?= e($pl['nom_varietat']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_inici" class="form-label form-label--requerit">
                        Data d'inici de la collita
                    </label>
                    <input type="date" id="data_inici" name="data_inici"
                           class="form-input camp-requerit"
                           data-etiqueta="La data d'inici"
                           data-no-futur
                           value="<?= e($_POST['data_inici'] ?? date('Y-m-d')) ?>"
                           required>
                </div>
                <div class="form-grup">
                    <label for="data_fi" class="form-label">
                        Data de finalització
                        <span class="form-bloc__opcional">(opcional si és el mateix dia)</span>
                    </label>
                    <input type="date" id="data_fi" name="data_fi"
                           class="form-input"
                           data-data-fi="data_inici"
                           data-etiqueta="La data de finalització"
                           value="<?= e($_POST['data_fi'] ?? '') ?>">
                </div>
            </div>
        </fieldset>

        <!-- BLOC 2: Producció -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-weight-hanging"></i> Dades de producció
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="quantitat" class="form-label form-label--requerit">
                        Quantitat recol·lectada
                    </label>
                    <input type="number" id="quantitat" name="quantitat"
                           class="form-input camp-requerit"
                           data-etiqueta="La quantitat"
                           data-tipus="positiu"
                           step="0.01" min="0.01"
                           placeholder="Ex: 2500.50"
                           value="<?= e($_POST['quantitat'] ?? '') ?>"
                           required>
                </div>
                <div class="form-grup">
                    <label for="unitat_mesura" class="form-label form-label--requerit">
                        Unitat de mesura
                    </label>
                    <select id="unitat_mesura" name="unitat_mesura"
                            class="form-select camp-requerit"
                            data-etiqueta="La unitat" required>
                        <option value="0">— Selecciona —</option>
                        <?php foreach (['kg' => 'kg (Quilograms)', 't' => 't (Tones)', 'caixes' => 'Caixes'] as $v => $lbl): ?>
                            <option value="<?= $v ?>" <?= ($_POST['unitat_mesura'] ?? '') === $v ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grup">
                <label for="qualitat" class="form-label form-label--requerit">
                    Qualitat de la partida
                </label>
                <select id="qualitat" name="qualitat"
                        class="form-select camp-requerit"
                        data-etiqueta="La qualitat" required>
                    <option value="0">— Selecciona la categoria —</option>
                    <?php foreach ([
                        'Extra'      => 'Categoria Extra',
                        'Primera'    => 'Primera',
                        'Segona'     => 'Segona',
                        'Industrial' => 'Destrip / Industrial',
                    ] as $v => $lbl): ?>
                        <option value="<?= $v ?>" <?= ($_POST['qualitat'] ?? 'Primera') === $v ? 'selected' : '' ?>>
                            <?= $lbl ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <!-- BLOC 3: Responsable -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-user-hard-hat"></i> Responsable
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="id_treballador" class="form-label form-label--requerit">
                        Treballador responsable
                    </label>
                    <select id="id_treballador" name="id_treballador"
                            class="form-select camp-requerit"
                            data-etiqueta="El treballador responsable" required>
                        <option value="0">— Qui valida la collita? —</option>
                        <?php foreach ($treballadors as $t): ?>
                            <option value="<?= (int)$t['id_treballador'] ?>"
                                <?= ((int)($_POST['id_treballador'] ?? 0) === (int)$t['id_treballador']) ? 'selected' : '' ?>>
                                <?= e(trim($t['nom_complet'])) ?> (<?= e($t['rol']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grup">
                    <label for="hores_treballades" class="form-label">
                        Hores treballades
                    </label>
                    <input type="number" id="hores_treballades" name="hores_treballades"
                           class="form-input"
                           data-tipus="decimal"
                           data-etiqueta="Les hores treballades"
                           step="0.5" min="0"
                           placeholder="Ex: 7.5"
                           value="<?= e($_POST['hores_treballades'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grup">
                <label for="observacions" class="form-label">Observacions</label>
                <textarea id="observacions" name="observacions"
                          class="form-textarea" rows="3"
                          placeholder="Anotacions, incidències, condicions meteorològiques..."><?= e($_POST['observacions'] ?? '') ?></textarea>
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk"></i> Guardar Collita
            </button>
            <a href="<?= BASE_URL ?>modules/collita/collita.php" class="boto-secundari">
                <i class="fas fa-xmark"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
