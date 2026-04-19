<?php
/**
 * modules/clients/nou_client.php
 *
 * Formulari per crear o editar clients.
 * Suporta el patró PRG (Post-Redirect-Get) i validació de dades.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Nou Client';
$pagina_activa = 'clients';

$mode_editar = false;
$client      = null;
$errors      = [];
$missatge     = null;

// Comprovar si és mode edició
$id_editar = sanitize_int($_GET['editar'] ?? null);
if ($id_editar) {
    $mode_editar = true;
    $titol_pagina = 'Editar Client';
    
    try {
        $pdo = connectDB();
        $stmt = $pdo->prepare("SELECT * FROM client WHERE id_client = ?");
        $stmt->execute([$id_editar]);
        $client = $stmt->fetch();
        
        if (!$client) {
            set_flash('error', 'El client no existeix.');
            header('Location: ' . BASE_URL . 'modules/clients/clients.php');
            exit;
        }
        
    } catch (Exception $e) {
        error_log('[CultiuConnect] nou_client.php (càrrega): ' . $e->getMessage());
        $errors[] = 'Error carregant el client.';
    }
}

// Dades per al formulari
$dades = [
    'nom_client'    => $client['nom_client'] ?? '',
    'nif_cif'       => $client['nif_cif'] ?? '',
    'adreca'        => $client['adreca'] ?? '',
    'poblacio'      => $client['poblacio'] ?? '',
    'codi_postal'   => $client['codi_postal'] ?? '',
    'telefon'       => $client['telefon'] ?? '',
    'email'         => $client['email'] ?? '',
    'tipus_client'  => $client['tipus_client'] ?? 'particular',
    'observacions'  => $client['observacions'] ?? '',
    'estat'         => $client['estat'] ?? 'actiu'
];

// Processament POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dades['nom_client']   = sanitize($_POST['nom_client'] ?? '');
    $dades['nif_cif']      = sanitize($_POST['nif_cif'] ?? '');
    $dades['adreca']       = sanitize($_POST['adreca'] ?? '');
    $dades['poblacio']     = sanitize($_POST['poblacio'] ?? '');
    $dades['codi_postal']  = sanitize($_POST['codi_postal'] ?? '');
    $dades['telefon']      = sanitize($_POST['telefon'] ?? '');
    $dades['email']        = sanitize($_POST['email'] ?? '');
    $dades['tipus_client'] = sanitize($_POST['tipus_client'] ?? 'particular');
    $dades['observacions'] = sanitize($_POST['observacions'] ?? '');
    $dades['estat']        = sanitize($_POST['estat'] ?? 'actiu');
    
    // Validació
    if (empty(trim($dades['nom_client']))) {
        $errors[] = 'El nom del client és obligatori.';
    }
    
    if (strlen($dades['nom_client']) > 100) {
        $errors[] = 'El nom no pot superar els 100 caràcters.';
    }
    
    if (!empty($dades['nif_cif'])) {
        if (strlen($dades['nif_cif']) > 12) {
            $errors[] = 'El NIF/CIF no pot superar els 12 caràcters.';
        }
        
        // Validar format NIF/CIF bàsic
        if (!preg_match('/^[A-Za-z0-9]{8,12}$/', $dades['nif_cif'])) {
            $errors[] = 'El format del NIF/CIF no és vàlid.';
        }
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
    
    if (!empty($dades['codi_postal'])) {
        if (!preg_match('/^[0-9]{5}$/', $dades['codi_postal'])) {
            $errors[] = 'El codi postal ha de tenir 5 dígits.';
        }
    }
    
    $tipus_valids = ['particular', 'empresa', 'cooperativa', 'altres'];
    if (!in_array($dades['tipus_client'], $tipus_valids)) {
        $errors[] = 'El tipus de client no és vàlid.';
    }
    
    $estat_valids = ['actiu', 'inactiu'];
    if (!in_array($dades['estat'], $estat_valids)) {
        $errors[] = 'L\'estat del client no és vàlid.';
    }
    
    if (empty($errors)) {
        try {
            $pdo = connectDB();
            
            if ($mode_editar) {
                // Actualitzar client existent
                $stmt = $pdo->prepare("
                    UPDATE client 
                    SET nom_client = ?, nif_cif = ?, adreca = ?, poblacio = ?, codi_postal = ?,
                        telefon = ?, email = ?, tipus_client = ?, observacions = ?, estat = ?
                    WHERE id_client = ?
                ");
                $stmt->execute([
                    $dades['nom_client'],
                    $dades['nif_cif'] ?: null,
                    $dades['adreca'] ?: null,
                    $dades['poblacio'] ?: null,
                    $dades['codi_postal'] ?: null,
                    $dades['telefon'] ?: null,
                    $dades['email'] ?: null,
                    $dades['tipus_client'],
                    $dades['observacions'] ?: null,
                    $dades['estat'],
                    $id_editar
                ]);
                
                set_flash('success', 'Client actualitzat correctament.');
                
            } else {
                // Verificar que no existeix un client amb el mateix NIF/CIF
                if (!empty($dades['nif_cif'])) {
                    $stmt = $pdo->prepare("SELECT id_client FROM client WHERE nif_cif = ?");
                    $stmt->execute([$dades['nif_cif']]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Ja existeix un client amb aquest NIF/CIF.';
                    }
                }
                
                if (empty($errors)) {
                    // Crear nou client
                    $stmt = $pdo->prepare("
                        INSERT INTO client (nom_client, nif_cif, adreca, poblacio, codi_postal,
                                       telefon, email, tipus_client, observacions, estat)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $dades['nom_client'],
                        $dades['nif_cif'] ?: null,
                        $dades['adreca'] ?: null,
                        $dades['poblacio'] ?: null,
                        $dades['codi_postal'] ?: null,
                        $dades['telefon'] ?: null,
                        $dades['email'] ?: null,
                        $dades['tipus_client'],
                        $dades['observacions'] ?: null,
                        $dades['estat']
                    ]);
                    
                    set_flash('success', 'Client creat correctament.');
                }
            }
            
            if (empty($errors)) {
                header('Location: ' . BASE_URL . 'modules/clients/clients.php');
                exit;
            }
            
        } catch (Exception $e) {
            error_log('[CultiuConnect] nou_client.php (desar): ' . $e->getMessage());
            $errors[] = 'Error desant el client.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-users" aria-hidden="true"></i>
            <?= $mode_editar ? 'Editar Client' : 'Nou Client' ?>
        </h1>
        <p class="descripcio-seccio">
            <?= $mode_editar 
                ? 'Modifica les dades del client existent.' 
                : 'Registra un nou client al catàleg de clients.' ?>
        </p>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/clients/clients.php" class="boto-secundari">
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

    <form action="<?= BASE_URL ?>modules/clients/nou_client.php<?= $mode_editar ? '?editar=' . $id_editar : '' ?>" 
          method="POST" 
          class="formulari-card"
          novalidate>

        <!-- Dades principals -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-info-circle"></i> Dades Principals
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="nom_client" class="form-label form-label--requerit">
                        Nom del client *
                    </label>
                    <input type="text"
                           id="nom_client"
                           name="nom_client"
                           class="form-input camp-requerit"
                           data-etiqueta="El nom del client"
                           value="<?= e($dades['nom_client']) ?>"
                           maxlength="100"
                           required>
                </div>

                <div class="form-grup">
                    <label for="nif_cif" class="form-label">
                        NIF/CIF
                    </label>
                    <input type="text"
                           id="nif_cif"
                           name="nif_cif"
                           class="form-input"
                           value="<?= e($dades['nif_cif']) ?>"
                           maxlength="12"
                           placeholder="Ex: 12345678A">
                    <span class="form-ajuda">
                        NIF per a particulars, CIF per a empreses (opcional)
                    </span>
                </div>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="tipus_client" class="form-label form-label--requerit">
                        Tipus de client *
                    </label>
                    <select id="tipus_client"
                            name="tipus_client"
                            class="form-select camp-requerit"
                            data-etiqueta="El tipus de client"
                            required>
                        <option value="particular" <?= $dades['tipus_client'] === 'particular' ? 'selected' : '' ?>>
                            Particular
                        </option>
                        <option value="empresa" <?= $dades['tipus_client'] === 'empresa' ? 'selected' : '' ?>>
                            Empresa
                        </option>
                        <option value="cooperativa" <?= $dades['tipus_client'] === 'cooperativa' ? 'selected' : '' ?>>
                            Cooperativa
                        </option>
                        <option value="altres" <?= $dades['tipus_client'] === 'altres' ? 'selected' : '' ?>>
                            Altres
                        </option>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="estat" class="form-label form-label--requerit">
                        Estat *
                    </label>
                    <select id="estat"
                            name="estat"
                            class="form-select camp-requerit"
                            data-etiqueta="L\'estat del client"
                            required>
                        <option value="actiu" <?= $dades['estat'] === 'actiu' ? 'selected' : '' ?>>
                            Actiu
                        </option>
                        <option value="inactiu" <?= $dades['estat'] === 'inactiu' ? 'selected' : '' ?>>
                            Inactiu
                        </option>
                    </select>
                </div>
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
                           placeholder="Ex: contacte@client.cat">
                    <span class="form-ajuda">
                        Correu electrònic (opcional)
                    </span>
                </div>
            </div>
        </fieldset>

        <!-- Ubicació -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-map-marker-alt"></i> Ubicació
            </legend>

            <div class="form-grup">
                <label for="adreca" class="form-label">
                    Adreça
                </label>
                <input type="text"
                       id="adreca"
                       name="adreca"
                       class="form-input"
                       value="<?= e($dades['adreca']) ?>"
                       placeholder="Carrer, número, pis...">
                <span class="form-ajuda">
                    Adreça completa (opcional)
                </span>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="poblacio" class="form-label">
                        Població
                    </label>
                    <input type="text"
                           id="poblacio"
                           name="poblacio"
                           class="form-input"
                           value="<?= e($dades['poblacio']) ?>"
                           placeholder="Nom de la població">
                </div>

                <div class="form-grup">
                    <label for="codi_postal" class="form-label">
                        Codi Postal
                    </label>
                    <input type="text"
                           id="codi_postal"
                           name="codi_postal"
                           class="form-input"
                           value="<?= e($dades['codi_postal']) ?>"
                           maxlength="5"
                           placeholder="08001">
                </div>
            </div>
        </fieldset>

        <!-- Observacions -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-sticky-note"></i> Observacions
            </legend>

            <div class="form-grup">
                <label for="observacions" class="form-label">
                    Observacions
                </label>
                <textarea id="observacions"
                          name="observacions"
                          class="form-textarea"
                          rows="3"
                          placeholder="Notes addicionals sobre el client..."><?= e($dades['observacions']) ?></textarea>
                <span class="form-ajuda">
                    Notes addicionals sobre el client, preferències, etc.
                </span>
            </div>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-save" aria-hidden="true"></i>
                <?= $mode_editar ? 'Actualitzar Client' : 'Crear Client' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/clients/clients.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i> Cancel·lar
            </a>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
