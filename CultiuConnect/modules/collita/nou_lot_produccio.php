<?php
/**
 * modules/collita/nou_lot_produccio.php — Crear un nou lot de producció (envasat).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$error_db = null;
$collites = [];
$presel_collita = sanitize_int($_GET['collita_id'] ?? null);

try {
    $pdo = connectDB();
    // Collites finalitzades per poder crear lots d'envasat
    $collites = $pdo->query("
        SELECT
            c.id_collita,
            DATE(c.data_inici) AS data_inici,
            DATE(c.data_fi)    AS data_fi,
            c.quantitat,
            c.unitat_mesura,
            s.nom AS nom_sector,
            COALESCE(v.nom_varietat, 'Sense varietat') AS nom_varietat
        FROM collita c
        JOIN plantacio pl ON pl.id_plantacio = c.id_plantacio
        JOIN sector s     ON s.id_sector     = pl.id_sector
        LEFT JOIN varietat v ON v.id_varietat = pl.id_varietat
        WHERE c.data_fi IS NOT NULL
        ORDER BY c.data_fi DESC
    ")->fetchAll();
} catch (Exception $e) {
    error_log('[CultiuConnect] nou_lot_produccio.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les collites.';
}

$titol_pagina  = 'Nou lot de producció';
$pagina_activa = 'collita';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-boxes-packing" aria-hidden="true"></i>
            Crear Lot de Producció
        </h1>
        <p class="descripcio-seccio">
            Registra un lot d'envasat vinculat a una collita real. El QR de traçabilitat es podrà generar després.
        </p>
        <div class="botons-accions">
            <a href="<?= BASE_URL ?>modules/collita/lot_produccio.php" class="boto-secundari">
                <i class="fas fa-arrow-left"></i> Tornar a lots
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= BASE_URL ?>modules/collita/processar_lot_produccio.php"
          class="formulari-card"
          novalidate>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-link" aria-hidden="true"></i> Vinculació
            </legend>

            <div class="form-grup">
                <label for="id_collita" class="form-label form-label--requerit">Collita</label>
                <select id="id_collita" name="id_collita" class="form-select camp-requerit" required>
                    <option value="0">— Selecciona una collita finalitzada —</option>
                    <?php foreach ($collites as $c): ?>
                        <?php
                            $lbl = '#' . (int)$c['id_collita'] . ' · ' . $c['nom_sector'] . ' — ' . $c['nom_varietat'];
                            $lbl .= ' · ' . format_data((string)$c['data_inici'], curta: true);
                            $lbl .= $c['data_fi'] ? '→' . format_data((string)$c['data_fi'], curta: true) : '';
                            $lbl .= ' · ' . number_format((float)$c['quantitat'], 0, ',', '.') . ' ' . $c['unitat_mesura'];
                        ?>
                        <option value="<?= (int)$c['id_collita'] ?>"
                            <?= (int)($presel_collita ?? 0) === (int)$c['id_collita'] ? 'selected' : '' ?>>
                            <?= e($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-tag" aria-hidden="true"></i> Dades del lot
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="identificador" class="form-label form-label--requerit">Identificador (codi lot)</label>
                    <input type="text" id="identificador" name="identificador"
                           class="form-input camp-requerit"
                           maxlength="100"
                           required
                           placeholder="Ex: LOT-POM-26-003"
                           value="<?= e($_POST['identificador'] ?? '') ?>">
                </div>
                <div class="form-grup">
                    <label for="data_processat" class="form-label">Data de processat</label>
                    <input type="date" id="data_processat" name="data_processat"
                           class="form-input"
                           value="<?= e($_POST['data_processat'] ?? date('Y-m-d')) ?>">
                </div>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="pes_kg" class="form-label">Pes (kg)</label>
                    <input type="number" id="pes_kg" name="pes_kg"
                           class="form-input"
                           step="0.01" min="0"
                           value="<?= e($_POST['pes_kg'] ?? '') ?>"
                           placeholder="Ex: 15000">
                </div>
                <div class="form-grup">
                    <label for="qualitat" class="form-label">Qualitat</label>
                    <select id="qualitat" name="qualitat" class="form-select">
                        <?php foreach (['Extra','Primera','Segona','Industrial'] as $q): ?>
                            <option value="<?= $q ?>" <?= ($_POST['qualitat'] ?? 'Primera') === $q ? 'selected' : '' ?>>
                                <?= $q ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="desti" class="form-label">Destí</label>
                    <input type="text" id="desti" name="desti"
                           class="form-input"
                           maxlength="255"
                           value="<?= e($_POST['desti'] ?? '') ?>"
                           placeholder="Ex: Mercat nacional, exportació...">
                </div>
                <div class="form-grup">
                    <label for="client_final" class="form-label">Client final</label>
                    <input type="text" id="client_final" name="client_final"
                           class="form-input"
                           maxlength="255"
                           value="<?= e($_POST['client_final'] ?? '') ?>"
                           placeholder="Ex: Mercabarna">
                </div>
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i> Crear lot
            </button>
            <a href="<?= BASE_URL ?>modules/collita/lot_produccio.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

