<?php
/**
 * modules/maquinaria/nou_manteniment.php
 *
 * Formulari per crear o editar manteniments de maquinària.
 * Suporta el patró PRG (Post-Redirect-Get) i validació de dades.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$titol_pagina  = 'Nou Manteniment';
$pagina_activa = 'manteniment';

$mode_editar = false;
$manteniment = null;
$errors      = [];
$missatge     = null;

// Comprovar si és mode edició
$id_editar = sanitize_int($_GET['editar'] ?? null);
if ($id_editar) {
    $mode_editar = true;
    $titol_pagina = 'Editar Manteniment';
    
    try {
        $pdo = connectDB();
        $stmt = $pdo->prepare("
            SELECT * FROM manteniment_maquinaria 
            WHERE id_manteniment = ?
        ");
        $stmt->execute([$id_editar]);
        $manteniment = $stmt->fetch();
        
        if (!$manteniment) {
            set_flash('error', 'El manteniment no existeix.');
            header('Location: ' . BASE_URL . 'modules/maquinaria/manteniment.php');
            exit;
        }
        
    } catch (Exception $e) {
        error_log('[CultiuConnect] nou_manteniment.php (càrrega): ' . $e->getMessage());
        $errors[] = 'Error carregant el manteniment.';
    }
}

// Dades per al formulari
$dades = [
    'id_maquinaria'     => $manteniment['id_maquinaria'] ?? '',
    'data_programada'   => $manteniment['data_programada'] ?? date('Y-m-d'),
    'data_realitzada'   => $manteniment['data_realitzada'] ?? '',
    'tipus_manteniment' => $manteniment['tipus_manteniment'] ?? 'preventiu',
    'descripcio'        => $manteniment['descripcio'] ?? '',
    'cost'              => $manteniment['cost'] ?? '',
    'realitzat'         => $manteniment['realitzat'] ?? false,
    'observacions'      => $manteniment['observacions'] ?? ''
];

// Processament POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dades['id_maquinaria']     = sanitize_int($_POST['id_maquinaria'] ?? null);
    $dades['data_programada']   = sanitize($_POST['data_programada'] ?? '');
    $dades['data_realitzada']   = sanitize($_POST['data_realitzada'] ?? '');
    $dades['tipus_manteniment'] = sanitize($_POST['tipus_manteniment'] ?? 'preventiu');
    $dades['descripcio']        = sanitize($_POST['descripcio'] ?? '');
    $dades['cost']              = sanitize_decimal($_POST['cost'] ?? null);
    $dades['realitzat']         = isset($_POST['realitzat']);
    $dades['observacions']      = sanitize($_POST['observacions'] ?? '');
    
    // Validació
    if (!$dades['id_maquinaria']) {
        $errors[] = 'La màquina és obligatòria.';
    }
    
    if (empty($dades['data_programada']) || !strtotime($dades['data_programada'])) {
        $errors[] = 'La data programada és obligatòria i ha de ser vàlida.';
    }
    
    if (!empty($dades['data_realitzada']) && !strtotime($dades['data_realitzada'])) {
        $errors[] = 'La data realitzada no té un format vàlid.';
    }
    
    if (empty(trim($dades['descripcio']))) {
        $errors[] = 'La descripció del manteniment és obligatòria.';
    }
    
    if ($dades['cost'] !== null && $dades['cost'] < 0) {
        $errors[] = 'El cost no pot ser negatiu.';
    }
    
    $tipus_valids = ['preventiu', 'correctiu', 'inspeccio', 'reparacio'];
    if (!in_array($dades['tipus_manteniment'], $tipus_valids)) {
        $errors[] = 'El tipus de manteniment no és vàlid.';
    }
    
    if (empty($errors)) {
        try {
            $pdo = connectDB();
            
            // Verificar que la màquina existeix
            $stmt = $pdo->prepare("SELECT id_maquinaria FROM maquinaria WHERE id_maquinaria = ?");
            $stmt->execute([$dades['id_maquinaria']]);
            if (!$stmt->fetch()) {
                $errors[] = 'La màquina seleccionada no existeix.';
            } else {
                if ($mode_editar) {
                    // Actualitzar manteniment existent
                    $stmt = $pdo->prepare("
                        UPDATE manteniment_maquinaria 
                        SET id_maquinaria = ?, data_programada = ?, data_realitzada = ?, 
                            tipus_manteniment = ?, descripcio = ?, cost = ?, 
                            realitzat = ?, observacions = ?
                        WHERE id_manteniment = ?
                    ");
                    $stmt->execute([
                        $dades['id_maquinaria'],
                        $dades['data_programada'],
                        $dades['data_realitzada'] ?: null,
                        $dades['tipus_manteniment'],
                        $dades['descripcio'],
                        $dades['cost'],
                        $dades['realitzat'],
                        $dades['observacions'] ?: null,
                        $id_editar
                    ]);
                    
                    set_flash('success', 'Manteniment actualitzat correctament.');
                    
                } else {
                    // Crear nou manteniment
                    $stmt = $pdo->prepare("
                        INSERT INTO manteniment_maquinaria
                            (id_maquinaria, data_programada, tipus_manteniment, descripcio, 
                             cost, observacions)
                        VALUES
                            (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $dades['id_maquinaria'],
                        $dades['data_programada'],
                        $dades['tipus_manteniment'],
                        $dades['descripcio'],
                        $dades['cost'],
                        $dades['observacions'] ?: null
                    ]);
                    
                    set_flash('success', 'Manteniment creat correctament.');
                }
                
                header('Location: ' . BASE_URL . 'modules/maquinaria/manteniment.php');
                exit;
            }
            
        } catch (Exception $e) {
            error_log('[CultiuConnect] nou_manteniment.php (desar): ' . $e->getMessage());
            $errors[] = 'Error desant el manteniment.';
        }
    }
}

// Carregar màquines per al selector
try {
    $pdo = connectDB();
    $maquinaries = $pdo->query("
        SELECT id_maquinaria, nom, tipus, marca, model
        FROM maquinaria
        ORDER BY nom ASC
    ")->fetchAll();
} catch (Exception $e) {
    error_log('[CultiuConnect] nou_manteniment.php (màquines): ' . $e->getMessage());
    $errors[] = 'Error carregant les màquines.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-wrench" aria-hidden="true"></i>
            <?= $mode_editar ? 'Editar Manteniment' : 'Nou Manteniment' ?>
        </h1>
        <p class="descripcio-seccio">
            <?= $mode_editar 
                ? 'Modifica les dades del manteniment existent.' 
                : 'Registra una nova tasca de manteniment per a la maquinària.' ?>
        </p>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/maquinaria/manteniment.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <ul class="llista-errors">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= BASE_URL ?>modules/maquinaria/nou_manteniment.php<?= $mode_editar ? '?editar=' . $id_editar : '' ?>" 
          method="POST" 
          class="formulari-card"
          novalidate>

        <!-- Dades principals -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-info-circle"></i> Dades del Manteniment
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="id_maquinaria" class="form-label form-label--requerit">
                        Màquina *
                    </label>
                    <select id="id_maquinaria"
                            name="id_maquinaria"
                            class="form-select camp-requerit"
                            data-etiqueta="La màquina"
                            required>
                        <option value="">Selecciona una màquina</option>
                        <?php foreach ($maquinaries as $m): ?>
                            <option value="<?= (int)$m['id_maquinaria'] ?>"
                                    <?= $dades['id_maquinaria'] == $m['id_maquinaria'] ? 'selected' : '' ?>>
                                <?= e($m['nom']) ?> (<?= e($m['tipus']) ?>)
                                <?php if ($m['marca'] || $m['model']): ?>
                                    - <?= e($m['marca']) ?> <?= e($m['model']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="tipus_manteniment" class="form-label form-label--requerit">
                        Tipus de manteniment *
                    </label>
                    <select id="tipus_manteniment"
                            name="tipus_manteniment"
                            class="form-select camp-requerit"
                            data-etiqueta="El tipus de manteniment"
                            required>
                        <option value="preventiu" <?= $dades['tipus_manteniment'] === 'preventiu' ? 'selected' : '' ?>>
                            Preventiu
                        </option>
                        <option value="correctiu" <?= $dades['tipus_manteniment'] === 'correctiu' ? 'selected' : '' ?>>
                            Correctiu
                        </option>
                        <option value="inspeccio" <?= $dades['tipus_manteniment'] === 'inspeccio' ? 'selected' : '' ?>>
                            Inspecció
                        </option>
                        <option value="reparacio" <?= $dades['tipus_manteniment'] === 'reparacio' ? 'selected' : '' ?>>
                            Reparació
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-grup">
                <label for="descripcio" class="form-label form-label--requerit">
                    Descripció *
                </label>
                <textarea id="descripcio"
                          name="descripcio"
                          class="form-textarea camp-requerit"
                          data-etiqueta="La descripció"
                          rows="4"
                          required><?= e($dades['descripcio']) ?></textarea>
                <span class="form-ajuda">
                    Descripció detallada de les tasques a realitzar
                </span>
            </div>
        </fieldset>

        <!-- Dates i cost -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-calendar"></i> Planificació i Cost
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="data_programada" class="form-label form-label--requerit">
                        Data programada *
                    </label>
                    <input type="date"
                           id="data_programada"
                           name="data_programada"
                           class="form-input camp-requerit"
                           data-etiqueta="La data programada"
                           value="<?= e($dades['data_programada']) ?>"
                           required>
                </div>

                <div class="form-grup">
                    <label for="data_realitzada" class="form-label">
                        Data realitzada
                    </label>
                    <input type="date"
                           id="data_realitzada"
                           name="data_realitzada"
                           class="form-input"
                           value="<?= e($dades['data_realitzada']) ?>"
                           placeholder="Data en que es va realitzar">
                </div>
            </div>

            <div class="form-grup">
                <label for="cost" class="form-label">
                    Cost (EUR)
                </label>
                <input type="number"
                       id="cost"
                       name="cost"
                       class="form-input"
                       value="<?= e($dades['cost']) ?>"
                       step="0.01"
                       min="0"
                       placeholder="0.00">
                <span class="form-ajuda">
                    Cost del manteniment (opcional)
                </span>
            </div>
        </fieldset>

        <!-- Estat i observacions -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-flag"></i> Estat i Observacions
            </legend>

            <div class="form-grup">
                <label class="checkbox-label">
                    <input type="checkbox" 
                           id="realitzat" 
                           name="realitzat" 
                           value="1"
                           <?= $dades['realitzat'] ? 'checked' : '' ?>>
                    <span class="checkbox-text">Manteniment realitzat</span>
                </label>
                <span class="form-ajuda">
                    Marca si el manteniment ja s'ha completat
                </span>
            </div>

            <div class="form-grup">
                <label for="observacions" class="form-label">
                    Observacions
                </label>
                <textarea id="observacions"
                          name="observacions"
                          class="form-textarea"
                          rows="3"
                          placeholder="Notes addicionals sobre el manteniment..."><?= e($dades['observacions']) ?></textarea>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-save" aria-hidden="true"></i>
                <?= $mode_editar ? 'Actualitzar Manteniment' : 'Crear Manteniment' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/maquinaria/manteniment.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i> Cancel·lar
            </a>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
