<?php
/**
 * modules/personal/nou_treballador.php — Formulari per donar d'alta o editar un treballador.
 *
 * GET  → mostra el formulari (nou o mode edició ?editar=ID)
 * POST → valida, insereix/actualitza, redirigeix (PRG) amb flash
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

const ROLS_VALIDS      = ['Operari', 'Supervisor', 'Tècnic', 'Responsable', 'Altres'];
const CONTRACTES_VALIDS = ['Temporal', 'Indefinit', 'Practicum', 'Altres'];

$dades     = [];
$error_db  = null;
$id_editar = sanitize_int($_GET['editar'] ?? null);

// -----------------------------------------------------------
// Carregar dades per a mode edició (GET)
// -----------------------------------------------------------
if ($id_editar && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo  = connectDB();
        $stmt = $pdo->prepare("SELECT * FROM treballador WHERE id_treballador = ?");
        $stmt->execute([$id_editar]);
        $dades = $stmt->fetch() ?: [];
        if (empty($dades)) {
            set_flash('error', 'El treballador que vols editar no existeix.');
            header('Location: ' . BASE_URL . 'modules/personal/personal.php');
            exit;
        }
    } catch (Exception $e) {
        error_log('[CultiuConnect] nou_treballador.php GET: ' . $e->getMessage());
        $error_db = 'No s\'han pogut carregar les dades del treballador.';
    }
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_editar_post  = sanitize_int($_POST['id_editar']        ?? null);
    $nom             = sanitize($_POST['nom']                   ?? '');
    $cognoms         = sanitize($_POST['cognoms']               ?? '');
    $dni             = sanitize($_POST['dni']                   ?? '');
    $data_naix       = sanitize($_POST['data_naixement']        ?? '');
    $rol             = sanitize($_POST['rol']                   ?? '');
    $telefon         = sanitize($_POST['telefon']               ?? '');
    $email_raw       = trim($_POST['email']                     ?? '');
    $data_alta       = sanitize($_POST['data_alta']             ?? '');
    $tipus_contracte = sanitize($_POST['tipus_contracte']       ?? '');
    $estat           = sanitize($_POST['estat']                 ?? 'actiu');

    $email   = filter_var($email_raw, FILTER_VALIDATE_EMAIL) ?: null;
    $data_alta = empty($data_alta) ? date('Y-m-d') : $data_alta;

    $errors = [];
    if (empty($nom))                              $errors[] = 'El nom és obligatori.';
    if (empty($dni))                              $errors[] = 'El DNI / NIE és obligatori.';
    if (empty($data_naix))                        $errors[] = 'La data de naixement és obligatòria.';
    if (!in_array($rol, ROLS_VALIDS))             $errors[] = 'El rol seleccionat no és vàlid.';
    if (!in_array($tipus_contracte, CONTRACTES_VALIDS)) $errors[] = 'El tipus de contracte no és vàlid.';
    if (!empty($email_raw) && !$email)            $errors[] = 'El correu electrònic no té un format vàlid.';

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        $redir = $id_editar_post
            ? BASE_URL . 'modules/personal/nou_treballador.php?editar=' . $id_editar_post
            : BASE_URL . 'modules/personal/nou_treballador.php';
        header('Location: ' . $redir);
        exit;
    }

    try {
        $pdo = connectDB();

        $params = [
            ':nom'             => $nom,
            ':cognoms'         => $cognoms ?: null,
            ':dni'             => $dni,
            ':data_naixement'  => $data_naix,
            ':rol'             => $rol,
            ':telefon'         => $telefon ?: null,
            ':email'           => $email,
            ':data_alta'       => $data_alta,
            ':tipus_contracte' => $tipus_contracte,
        ];

        if ($id_editar_post) {
            $params[':estat'] = in_array($estat, ['actiu', 'baixa']) ? $estat : 'actiu';
            $params[':id']    = $id_editar_post;
            $stmt = $pdo->prepare("
                UPDATE treballador SET
                    nom              = :nom,
                    cognoms          = :cognoms,
                    dni              = :dni,
                    data_naixement   = :data_naixement,
                    rol              = :rol,
                    telefon          = :telefon,
                    email            = :email,
                    data_alta        = :data_alta,
                    tipus_contracte  = :tipus_contracte,
                    estat            = :estat
                WHERE id_treballador = :id
            ");
            $stmt->execute($params);
            $msg = 'Treballador «' . $nom . ' ' . ($cognoms ?: '') . '» actualitzat correctament.';

        } else {
            $params[':estat'] = 'actiu';
            $stmt = $pdo->prepare("
                INSERT INTO treballador
                    (nom, cognoms, dni, data_naixement, rol, telefon, email,
                     data_alta, tipus_contracte, estat)
                VALUES
                    (:nom, :cognoms, :dni, :data_naixement, :rol, :telefon, :email,
                     :data_alta, :tipus_contracte, :estat)
            ");
            $stmt->execute($params);
            $msg = 'Treballador «' . $nom . ' ' . ($cognoms ?: '') . '» donat d\'alta correctament.';
        }

        set_flash('success', $msg);
        header('Location: ' . BASE_URL . 'modules/personal/personal.php');
        exit;

    } catch (Exception $e) {
        if ($e->getCode() === '23000') {
            set_flash('error', 'Ja existeix un treballador registrat amb el DNI «' . $dni . '».');
        } else {
            error_log('[CultiuConnect] nou_treballador.php POST: ' . $e->getMessage());
            set_flash('error', 'Error intern en guardar el treballador.');
        }
        $redir = $id_editar_post
            ? BASE_URL . 'modules/personal/nou_treballador.php?editar=' . $id_editar_post
            : BASE_URL . 'modules/personal/nou_treballador.php';
        header('Location: ' . $redir);
        exit;
    }
}

// -----------------------------------------------------------
// Capçalera (GET)
// -----------------------------------------------------------
$titol_pagina  = $id_editar ? 'Editar Treballador' : 'Nou Treballador';
$pagina_activa = 'personal';
require_once __DIR__ . '/../../includes/header.php';

$v = fn(string $camp, mixed $def = '') =>
    e((string)($_POST[$camp] ?? $dades[$camp] ?? $def));
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-<?= $id_editar ? 'pen' : 'user-plus' ?>" aria-hidden="true"></i>
            <?= $id_editar ? 'Editar Treballador' : 'Donar d\'Alta Nou Treballador' ?>
        </h1>
        <p class="descripcio-seccio">
            <?= $id_editar
                ? 'Modifica les dades personals o laborals del treballador.'
                : 'Introdueix les dades per incorporar un nou membre a la plantilla activa.' ?>
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form id="form-treballador"
          method="POST"
          action="<?= BASE_URL ?>modules/personal/nou_treballador.php"
          class="formulari-card"
          novalidate>

        <?php if ($id_editar): ?>
            <input type="hidden" name="id_editar" value="<?= (int)$id_editar ?>">
        <?php endif; ?>

        <!-- ================================================
             BLOC 1: Dades personals
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-id-card"></i> Dades personals
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="nom" class="form-label form-label--requerit">Nom</label>
                    <input type="text" id="nom" name="nom"
                           class="form-input camp-requerit"
                           data-etiqueta="El nom"
                           placeholder="Ex: Joan"
                           maxlength="100"
                           value="<?= $v('nom') ?>"
                           required>
                </div>
                <div class="form-grup">
                    <label for="cognoms" class="form-label">Cognoms</label>
                    <input type="text" id="cognoms" name="cognoms"
                           class="form-input"
                           placeholder="Ex: Martí Vila"
                           maxlength="150"
                           value="<?= $v('cognoms') ?>">
                </div>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="dni" class="form-label form-label--requerit">DNI / NIE / ID</label>
                    <input type="text" id="dni" name="dni"
                           class="form-input camp-requerit"
                           data-etiqueta="El DNI"
                           data-tipus="dni"
                           placeholder="Ex: 12345678A"
                           maxlength="20"
                           value="<?= $v('dni') ?>"
                           required>
                </div>
                <div class="form-grup">
                    <label for="data_naixement" class="form-label form-label--requerit">
                        Data de naixement
                    </label>
                    <input type="date" id="data_naixement" name="data_naixement"
                           class="form-input camp-requerit"
                           data-etiqueta="La data de naixement"
                           max="<?= date('Y-m-d') ?>"
                           value="<?= $v('data_naixement') ?>"
                           required>
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 2: Contacte
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-phone"></i> Contacte
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="telefon" class="form-label">Telèfon</label>
                    <input type="tel" id="telefon" name="telefon"
                           class="form-input"
                           placeholder="Ex: 600 123 456"
                           maxlength="20"
                           value="<?= $v('telefon') ?>">
                </div>
                <div class="form-grup">
                    <label for="email" class="form-label">Correu electrònic</label>
                    <input type="email" id="email" name="email"
                           class="form-input"
                           data-tipus="email"
                           data-etiqueta="El correu electrònic"
                           placeholder="Ex: joan@cultiuconnect.cat"
                           maxlength="150"
                           value="<?= $v('email') ?>">
                </div>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 3: Dades de contractació
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-briefcase"></i> Dades de contractació
            </legend>

            <div class="form-fila-3">
                <div class="form-grup">
                    <label for="rol" class="form-label form-label--requerit">Rol / Categoria</label>
                    <select id="rol" name="rol"
                            class="form-select camp-requerit"
                            data-etiqueta="El rol"
                            required>
                        <?php
                        $rols_ui = [
                            'Operari'     => 'Operari general',
                            'Supervisor'  => 'Supervisor de quadrilla',
                            'Tècnic'      => 'Tècnic agrícola / Enginyer',
                            'Responsable' => 'Responsable de finca',
                            'Altres'      => 'Altres',
                        ];
                        $rol_sel = $_POST['rol'] ?? $dades['rol'] ?? 'Operari';
                        foreach ($rols_ui as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= $rol_sel === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="tipus_contracte" class="form-label form-label--requerit">
                        Tipus de contracte
                    </label>
                    <select id="tipus_contracte" name="tipus_contracte"
                            class="form-select camp-requerit"
                            data-etiqueta="El tipus de contracte"
                            required>
                        <?php
                        $contractes_ui = [
                            'Temporal'  => 'De temporada (fix-discontinu)',
                            'Indefinit' => 'Indefinit',
                            'Practicum' => 'En pràctiques',
                            'Altres'    => 'Altres modalitats',
                        ];
                        $contracte_sel = $_POST['tipus_contracte'] ?? $dades['tipus_contracte'] ?? 'Temporal';
                        foreach ($contractes_ui as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= $contracte_sel === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="data_alta" class="form-label">Data d'alta (contracte)</label>
                    <input type="date" id="data_alta" name="data_alta"
                           class="form-input"
                           value="<?= $v('data_alta', date('Y-m-d')) ?>">
                </div>
            </div>

            <?php if ($id_editar): ?>
                <div class="form-grup">
                    <label for="estat" class="form-label">Estat</label>
                    <select id="estat" name="estat" class="form-select">
                        <?php
                        $estat_sel = $_POST['estat'] ?? $dades['estat'] ?? 'actiu';
                        foreach (['actiu' => 'Actiu', 'baixa' => 'Baixa'] as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= $estat_sel === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk"></i>
                <?= $id_editar ? 'Actualitzar Treballador' : 'Registrar Treballador' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/personal/personal.php" class="boto-secundari">
                <i class="fas fa-xmark"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
