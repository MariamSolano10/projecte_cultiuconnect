<?php
/**
 * modules/proveidors/nou_proveidor.php
 *
 * Formulari per crear o editar proveïdors.
 * Suporta el patró PRG (Post-Redirect-Get) i validació de dades.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Nou Proveïdor';
$pagina_activa = 'proveidors';

$mode_editar = false;
$proveidor    = null;
$errors       = [];
$missatge     = null;

// Comprovar si és mode edició
$id_editar = sanitize_int($_GET['editar'] ?? null);
if ($id_editar) {
    $mode_editar = true;
    $titol_pagina = 'Editar Proveïdor';
    
    try {
        $pdo = connectDB();
        $stmt = $pdo->prepare("SELECT * FROM proveidor WHERE id_proveidor = ?");
        $stmt->execute([$id_editar]);
        $proveidor = $stmt->fetch();
        
        if (!$proveidor) {
            set_flash('error', 'El proveïdor no existeix.');
            header('Location: ' . BASE_URL . 'modules/proveidors/proveidors.php');
            exit;
        }
        
    } catch (Exception $e) {
        error_log('[CultiuConnect] nou_proveidor.php (càrrega): ' . $e->getMessage());
        $errors[] = 'Error carregant el proveïdor.';
    }
}

// Dades per al formulari
$dades = [
    'nom'       => $proveidor['nom'] ?? '',
    'telefon'   => $proveidor['telefon'] ?? '',
    'email'     => $proveidor['email'] ?? '',
    'adreca'    => $proveidor['adreca'] ?? '',
    'tipus'     => $proveidor['tipus'] ?? 'Altres'
];

// Processament POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dades['nom']     = sanitize($_POST['nom'] ?? '');
    $dades['telefon'] = sanitize($_POST['telefon'] ?? '');
    $dades['email']   = sanitize($_POST['email'] ?? '');
    $dades['adreca']  = sanitize($_POST['adreca'] ?? '');
    $dades['tipus']   = sanitize($_POST['tipus'] ?? 'Altres');
    
    // Validació
    if (empty(trim($dades['nom']))) {
        $errors[] = 'El nom del proveïdor és obligatori.';
    }
    
    if (strlen($dades['nom']) > 100) {
        $errors[] = 'El nom no pot superar els 100 caràcters.';
    }
    
    if (!empty($dades['telefon'])) {
        if (!preg_match('/^[0-9 +()-]*$/', $dades['telefon'])) {
            $errors[] = 'El telèfon només pot contenir números, espais, +, ( i ).';
        }
        if (strlen($dades['telefon']) > 20) {
            $errors[] = 'El telèfon no pot superar els 20 caràcters.';
        }
    }
    
    if (!empty($dades['email'])) {
        if (!filter_var($dades['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'email no té un format vàlid.';
        }
        if (strlen($dades['email']) > 100) {
            $errors[] = 'L\'email no pot superar els 100 caràcters.';
        }
    }
    
    $tipus_valids = ['Fitosanitari', 'Fertilitzant', 'Llavor', 'Maquinaria', 'Altres'];
    if (!in_array($dades['tipus'], $tipus_valids)) {
        $errors[] = 'El tipus de proveïdor no és vàlid.';
    }
    
    if (empty($errors)) {
        try {
            $pdo = connectDB();
            
            if ($mode_editar) {
                // Actualitzar proveïdor existent
                $stmt = $pdo->prepare("
                    UPDATE proveidor 
                    SET nom = ?, telefon = ?, email = ?, adreca = ?, tipus = ?
                    WHERE id_proveidor = ?
                ");
                $stmt->execute([
                    $dades['nom'],
                    $dades['telefon'] ?: null,
                    $dades['email'] ?: null,
                    $dades['adreca'] ?: null,
                    $dades['tipus'],
                    $id_editar
                ]);
                
                // Registrar acció al log
                registrar_accio(
                    'PROVEÏDOR ACTUALITZAT: ' . $dades['nom'],
                    'proveidor',
                    $id_editar,
                    'Tipus: ' . $dades['tipus'] . ', Telèfon: ' . ($dades['telefon'] ?: '---') . ', Email: ' . ($dades['email'] ?: '---')
                );
                
                set_flash('success', 'Proveïdor actualitzat correctament.');
                
            } else {
                // Verificar que no existeix un proveïdor amb el mateix nom
                $stmt = $pdo->prepare("SELECT id_proveidor FROM proveidor WHERE nom = ?");
                $stmt->execute([$dades['nom']]);
                if ($stmt->fetch()) {
                    $errors[] = 'Ja existeix un proveïdor amb aquest nom.';
                } else {
                    // Crear nou proveïdor
                    $stmt = $pdo->prepare("
                        INSERT INTO proveidor (nom, telefon, email, adreca, tipus)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $dades['nom'],
                        $dades['telefon'] ?: null,
                        $dades['email'] ?: null,
                        $dades['adreca'] ?: null,
                        $dades['tipus']
                    ]);
                    
                    $id_nou = (int)$pdo->lastInsertId();
                    
                    // Registrar acció al log
                    registrar_accio(
                        'PROVEÏDOR CREAT: ' . $dades['nom'],
                        'proveidor',
                        $id_nou,
                        'Tipus: ' . $dades['tipus'] . ', Telèfon: ' . ($dades['telefon'] ?: '---') . ', Email: ' . ($dades['email'] ?: '---')
                    );
                    
                    set_flash('success', 'Proveïdor creat correctament.');
                }
            }
            
            if (empty($errors)) {
                header('Location: ' . BASE_URL . 'modules/proveidors/proveidors.php');
                exit;
            }
            
        } catch (Exception $e) {
            error_log('[CultiuConnect] nou_proveidor.php (desar): ' . $e->getMessage());
            $errors[] = 'Error desant el proveïdor.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-truck-field" aria-hidden="true"></i>
            <?= $mode_editar ? 'Editar Proveïdor' : 'Nou Proveïdor' ?>
        </h1>
        <p class="descripcio-seccio">
            <?= $mode_editar 
                ? 'Modifica les dades del proveïdor existent.' 
                : 'Registra un nou proveïdor al teu catàleg de subministraments.' ?>
        </p>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/proveidors/proveidors.php" class="boto-secundari">
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

    <form action="<?= BASE_URL ?>modules/proveidors/nou_proveidor.php<?= $mode_editar ? '?editar=' . $id_editar : '' ?>" 
          method="POST" 
          class="formulari-card"
          novalidate>

        <!-- Dades principals -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-info-circle"></i> Dades Principals
            </legend>

            <div class="form-grup">
                <label for="nom" class="form-label form-label--requerit">
                    Nom del proveïdor *
                </label>
                <input type="text"
                       id="nom"
                       name="nom"
                       class="form-input camp-requerit"
                       data-etiqueta="El nom del proveïdor"
                       value="<?= e($dades['nom']) ?>"
                       maxlength="100"
                       required>
                <span class="form-ajuda">
                    Nom comercial o empresa del proveïdor (màx. 100 caràcters)
                </span>
            </div>

            <div class="form-grup">
                <label for="tipus" class="form-label form-label--requerit">
                    Tipus de proveïdor *
                </label>
                <select id="tipus"
                        name="tipus"
                        class="form-select camp-requerit"
                        data-etiqueta="El tipus de proveïdor"
                        required>
                    <option value="Fitosanitari" <?= $dades['tipus'] === 'Fitosanitari' ? 'selected' : '' ?>>
                        Fitosanitari
                    </option>
                    <option value="Fertilitzant" <?= $dades['tipus'] === 'Fertilitzant' ? 'selected' : '' ?>>
                        Fertilitzant
                    </option>
                    <option value="Llavor" <?= $dades['tipus'] === 'Llavor' ? 'selected' : '' ?>>
                        Llavor
                    </option>
                    <option value="Maquinaria" <?= $dades['tipus'] === 'Maquinaria' ? 'selected' : '' ?>>
                        Maquinària
                    </option>
                    <option value="Altres" <?= $dades['tipus'] === 'Altres' ? 'selected' : '' ?>>
                        Altres
                    </option>
                </select>
                <span class="form-ajuda">
                    Principal tipus de productes o serveis que subministra
                </span>
            </div>
        </fieldset>

        <!-- Contacte -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-address-book"></i> Informació de Contacte
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="telefon" class="form-label">
                        Telèfon
                    </label>
                    <input type="tel"
                           id="telefon"
                           name="telefon"
                           class="form-input"
                           value="<?= e($dades['telefon']) ?>"
                           maxlength="20"
                           placeholder="Ex: 93 123 456">
                    <span class="form-ajuda">
                        Telèfon de contacte (opcional)
                    </span>
                </div>

                <div class="form-grup">
                    <label for="email" class="form-label">
                        Email
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           class="form-input"
                           value="<?= e($dades['email']) ?>"
                           maxlength="100"
                           placeholder="Ex: contacte@proveidor.cat">
                    <span class="form-ajuda">
                        Correu electrònic (opcional)
                    </span>
                </div>
            </div>

            <div class="form-grup">
                <label for="adreca" class="form-label">
                    Adreça
                </label>
                <textarea id="adreca"
                          name="adreca"
                          class="form-textarea"
                          rows="3"
                          placeholder="Carrer, número, població, codi postal..."><?= e($dades['adreca']) ?></textarea>
                <span class="form-ajuda">
                    Adreça física del proveïdor (opcional)
                </span>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-save" aria-hidden="true"></i>
                <?= $mode_editar ? 'Actualitzar Proveïdor' : 'Crear Proveïdor' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/proveidors/proveidors.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i> Cancel·lar
            </a>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
