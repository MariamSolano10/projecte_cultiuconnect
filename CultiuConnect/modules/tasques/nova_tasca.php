<?php
/**
 * modules/tasques/nova_tasca.php — Formulari per crear o editar una tasca agrícola.
 *
 * GET ?editar=ID  → mode edició, carrega les dades de la tasca existent.
 * POST            → redirigeix a processar_tasca.php
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Nova Tasca';
$pagina_activa = 'tasques';

$tasca    = null;
$sectors  = [];
$tasques_precedents = [];
$error_db = null;
$es_edicio = false;

try {
    $pdo = connectDB();

    $sectors = $pdo->query("SELECT id_sector, nom FROM sector ORDER BY nom")->fetchAll();

    // Mode edició
    if (!empty($_GET['editar']) && is_numeric($_GET['editar'])) {
        $es_edicio = true;
        $id_edit   = (int)$_GET['editar'];
        $titol_pagina = 'Editar Tasca';

        $stmt = $pdo->prepare("SELECT * FROM tasca WHERE id_tasca = :id");
        $stmt->execute([':id' => $id_edit]);
        $tasca = $stmt->fetch();

        if (!$tasca) {
            set_flash('error', 'Tasca no trobada.');
            header('Location: ' . BASE_URL . 'modules/tasques/tasques.php');
            exit;
        }
    }

    // Tasques disponibles com a precedent (excloem la pròpia si estem editant)
    $sql_prec = "SELECT id_tasca, tipus, descripcio, data_inici_prevista FROM tasca";
    if ($es_edicio) {
        $sql_prec .= " WHERE id_tasca != " . (int)$tasca['id_tasca'];
    }
    $sql_prec .= " ORDER BY data_inici_prevista DESC LIMIT 100";
    $tasques_precedents = $pdo->query($sql_prec)->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] nova_tasca.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar el formulari.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-form">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-<?= $es_edicio ? 'pen' : 'plus' ?>" aria-hidden="true"></i>
            <?= $es_edicio ? 'Editar Tasca' : 'Nova Tasca' ?>
        </h1>
        <a href="<?= BASE_URL ?>modules/tasques/tasques.php" class="boto-secundari">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
        </a>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= BASE_URL ?>modules/tasques/processar_tasca.php"
          class="dashboard-layout dashboard-layout--alineat"
          novalidate>

        <?php if ($es_edicio): ?>
            <input type="hidden" name="id_tasca" value="<?= (int)$tasca['id_tasca'] ?>">
        <?php endif; ?>

        <!-- COLUMNA PRINCIPAL (Gran) -->
        <div class="dashboard-column dashboard-column--main">
            <div class="form-card form-card--spaced">
                <h2 class="card-section-title">
                    <i class="fas fa-file-contract"></i> Informació General
                </h2>

                <div class="form-fila">
                    <div class="form-camp form-camp--full">
                        <label>Tipus de tasca <span class="obligatori">*</span></label>
                        <div class="radio-cards-grid">
                            <?php
                            $tipus_opcions = [
                                'poda'          => ['Poda', 'fa-scissors'],
                                'aclarida'      => ['Aclarida', 'fa-leaf'],
                                'tractament'    => ['Tractament', 'fa-spray-can-sparkles'],
                                'collita'       => ['Collita', 'fa-apple-whole'],
                                'fertilitzacio' => ['Fertilització', 'fa-flask'],
                                'reg'           => ['Reg', 'fa-droplet'],
                                'manteniment'   => ['Manteniment', 'fa-wrench'],
                                'altres'        => ['Altres', 'fa-list-check'],
                            ];
                            $tip_act = $tasca['tipus'] ?? 'poda'; /* Per defecte seleccionat per UI maca */
                            foreach ($tipus_opcions as $val => $dades): ?>
                                <div>
                                    <input type="radio" name="tipus" id="tipus_<?= $val ?>" value="<?= $val ?>" class="radio-card-input" <?= $tip_act === $val ? 'checked' : '' ?> required>
                                    <label for="tipus_<?= $val ?>" class="radio-card-label">
                                        <i class="fas <?= $dades[1] ?>"></i>
                                        <span class="radio-card-text"><?= $dades[0] ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-fila form-fila--spaced">
                    <div class="form-camp">
                        <label for="id_sector">Sector d'Aplicació</label>
                        <div class="input-with-icon">
                            <i class="fas fa-map-location-dot input-icon"></i>
                            <select name="id_sector" id="id_sector" class="form-select">
                                <option value="">— Sense sector / Explotació general —</option>
                                <?php foreach ($sectors as $s): ?>
                                    <option value="<?= (int)$s['id_sector'] ?>"
                                        <?= (int)($tasca['id_sector'] ?? 0) === (int)$s['id_sector'] ? 'selected' : '' ?>>
                                        <?= e($s['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-camp">
                    <label for="descripcio">Motiu / Descripció Ràpida</label>
                    <textarea name="descripcio" id="descripcio" rows="2" class="form-input"
                              placeholder="Fes un breu resum de la tasca..."><?= e($tasca['descripcio'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-card form-card--spaced">
                <h2 class="card-section-title">
                    <i class="fas fa-toolbox"></i> Recursos Necessaris
                </h2>
                
                <div class="form-fila">
                    <div class="form-camp">
                        <label for="num_treballadors_necessaris">Treballadors necessaris</label>
                        <div class="input-with-icon">
                            <i class="fas fa-users input-icon"></i>
                            <input type="number" name="num_treballadors_necessaris" id="num_treballadors_necessaris" class="form-input"
                                   min="0"
                                   value="<?= e($tasca['num_treballadors_necessaris'] ?? '') ?>"
                                   placeholder="Ex: 3 operaris">
                        </div>
                    </div>
                    <div class="form-camp">
                        <label for="tasca_precedent">Tasca precedent (Dependència)</label>
                        <div class="input-with-icon">
                            <i class="fas fa-link input-icon"></i>
                            <select name="tasca_precedent" id="tasca_precedent" class="form-select">
                                <option value="">— Fluxe lliure —</option>
                                <?php foreach ($tasques_precedents as $tp): ?>
                                    <option value="<?= (int)$tp['id_tasca'] ?>"
                                        <?= (int)($tasca['tasca_precedent'] ?? 0) === (int)$tp['id_tasca'] ? 'selected' : '' ?>>
                                        #<?= (int)$tp['id_tasca'] ?> — <?= e(ucfirst($tp['tipus'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-fila">
                    <div class="form-camp">
                        <label for="qualificacions_necessaries">Certificat requerit</label>
                        <div class="input-with-icon">
                            <i class="fas fa-id-card input-icon"></i>
                            <input type="text" name="qualificacions_necessaries" id="qualificacions_necessaries" class="form-input"
                                   value="<?= e($tasca['qualificacions_necessaries'] ?? '') ?>"
                                   placeholder="Ex: Carnet aplicador bàsic">
                        </div>
                    </div>

                    <div class="form-camp">
                        <label for="equipament_necessari">Maquinària / Equip</label>
                        <div class="input-with-icon">
                            <i class="fas fa-tractor input-icon"></i>
                            <input type="text" name="equipament_necessari" id="equipament_necessari" class="form-input"
                                   value="<?= e($tasca['equipament_necessari'] ?? '') ?>"
                                   placeholder="Ex: Tractor M-101">
                        </div>
                    </div>
                </div>
                
                <div class="form-camp">
                    <label for="instruccions">Notes Addicionals (Manual d'Operacions)</label>
                    <textarea name="instruccions" id="instruccions" rows="3" class="form-input"
                              placeholder="Qualsevol precaució o protocol d'actuació..."><?= e($tasca['instruccions'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- COLUMNA LATERAL (Controls Tempo & Acció) -->
        <div class="dashboard-column dashboard-column--side">
            <div class="form-card premium-side-panel">
                <h2 class="side-panel-title">
                    <i class="fas fa-calendar-check side-panel-title__icon"></i> Temps i Execució
                </h2>
                
                <div class="form-camp">
                    <label for="data_inici_prevista">Dia d'Inici Previst <span class="obligatori">*</span></label>
                    <input type="date" name="data_inici_prevista" id="data_inici_prevista" class="form-input form-input--destacat"
                           value="<?= e($tasca['data_inici_prevista'] ?? '') ?>"
                           required>
                </div>
                
                <div class="form-camp">
                    <label for="data_fi_prevista">Límit de Finalització</label>
                    <input type="date" name="data_fi_prevista" id="data_fi_prevista" class="form-input form-input--blanc"
                           value="<?= e($tasca['data_fi_prevista'] ?? '') ?>">
                </div>
                <div class="form-camp">
                    <label for="duracio_estimada_h">Hores globals estimades</label>
                    <div class="input-unit-wrap">
                        <input type="number" name="duracio_estimada_h" id="duracio_estimada_h" class="form-input"
                               min="0" step="0.5"
                               value="<?= e($tasca['duracio_estimada_h'] ?? '') ?>"
                               placeholder="Ex: 8">
                        <span class="input-unit-sufix">hr</span>
                    </div>
                </div>
                
                <?php if ($es_edicio): ?>
                <hr class="separador-formulari">
                <div class="form-camp">
                    <label for="estat">Estat de la tasca</label>
                    <select name="estat" id="estat" class="form-select select-destacat">
                        <?php foreach (['pendent' => 'Pendent', 'en_proces' => 'En procés', 'completada' => 'Completada', 'cancel·lada' => 'Cancel·lada'] as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= ($tasca['estat'] ?? 'pendent') === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-accions form-accions--columna">
                    <button type="submit" class="boto-principal boto--ample">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <?= $es_edicio ? 'Desar canvis' : 'Activar Tasca' ?>
                    </button>
                    <a href="<?= BASE_URL ?>modules/tasques/tasques.php" class="boto-secundari boto--ample boto-secundari--blanc">
                        Cancel·lar Operació
                    </a>
                </div>
            </div>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
