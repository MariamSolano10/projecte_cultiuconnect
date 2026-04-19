<?php
/**
 * modules/maquinaria/nova_maquinaria.php — Formulari per donar d'alta o editar maquinària.
 *
 * GET  → mostra el formulari (nou o mode edició ?editar=ID)
 * POST → valida, insereix/actualitza manteniment_json, redirigeix (PRG) amb flash
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

const TIPUS_MAQUINARIA_VALIDS = ['Tractor', 'Pulveritzador', 'Poda', 'Cistella', 'Altres'];

$dades     = [];
$error_db  = null;
$id_editar = sanitize_int($_GET['editar'] ?? null);

// -----------------------------------------------------------
// Carregar dades per a mode edició (GET)
// -----------------------------------------------------------
if ($id_editar && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo  = connectDB();
        $stmt = $pdo->prepare("SELECT * FROM maquinaria WHERE id_maquinaria = ?");
        $stmt->execute([$id_editar]);
        $dades = $stmt->fetch() ?: [];
        if (empty($dades)) {
            set_flash('error', 'La màquina que vols editar no existeix.');
            header('Location: ' . BASE_URL . 'modules/maquinaria/maquinaria.php');
            exit;
        }
    } catch (Exception $e) {
        error_log('[CultiuConnect] nova_maquinaria.php GET: ' . $e->getMessage());
        $error_db = 'No s\'han pogut carregar les dades de la màquina.';
    }
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_editar_post  = sanitize_int($_POST['id_editar']       ?? null);
    $nom             = sanitize($_POST['nom_maquina']          ?? '');
    $tipus           = sanitize($_POST['tipus']                ?? '');
    $any_fabricacio  = sanitize_int($_POST['any_fabricacio']   ?? null);
    $darrera_revisio = sanitize($_POST['darrera_revisio']      ?? '');

    $errors = [];
    if (empty($nom))                                      $errors[] = 'Cal introduir el nom o matrícula de la màquina.';
    if (!in_array($tipus, TIPUS_MAQUINARIA_VALIDS))       $errors[] = 'Cal seleccionar un tipus de màquina vàlid.';
    if ($any_fabricacio && ($any_fabricacio < 1900 || $any_fabricacio > (int)date('Y'))) {
        $errors[] = 'L\'any de fabricació no és vàlid.';
    }

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        $redir = $id_editar_post
            ? BASE_URL . 'modules/maquinaria/nova_maquinaria.php?editar=' . $id_editar_post
            : BASE_URL . 'modules/maquinaria/nova_maquinaria.php';
        header('Location: ' . $redir);
        exit;
    }

    // JSON de manteniment: si s'edita, conservem els camps existents i actualitzem la revisió
    $manteniment_nou = [
        'darrera_revisio' => !empty($darrera_revisio) ? $darrera_revisio : date('Y-m-d'),
    ];

    try {
        $pdo = connectDB();

        if ($id_editar_post) {
            // Conservar camps JSON existents que no toquem
            $stmt_old = $pdo->prepare("SELECT manteniment_json FROM maquinaria WHERE id_maquinaria = ?");
            $stmt_old->execute([$id_editar_post]);
            $row_old = $stmt_old->fetch();
            $json_old = $row_old ? (json_decode($row_old['manteniment_json'] ?? '{}', true) ?: []) : [];
            $manteniment_final = array_merge($json_old, $manteniment_nou);

            $stmt = $pdo->prepare("
                UPDATE maquinaria SET
                    nom_maquina      = :nom,
                    tipus            = :tipus,
                    any_fabricacio   = :any,
                    manteniment_json = :json
                WHERE id_maquinaria = :id
            ");
            $stmt->execute([
                ':nom'  => $nom,
                ':tipus' => $tipus,
                ':any'  => $any_fabricacio,
                ':json' => json_encode($manteniment_final),
                ':id'   => $id_editar_post,
            ]);
            $msg = 'Màquina «' . $nom . '» actualitzada correctament.';

        } else {
            $stmt = $pdo->prepare("
                INSERT INTO maquinaria
                    (nom_maquina, tipus, any_fabricacio, manteniment_json)
                VALUES
                    (:nom, :tipus, :any, :json)
            ");
            $stmt->execute([
                ':nom'  => $nom,
                ':tipus' => $tipus,
                ':any'  => $any_fabricacio,
                ':json' => json_encode($manteniment_nou),
            ]);
            $msg = 'Màquina «' . $nom . '» registrada al parc de maquinària.';
        }

        set_flash('success', $msg);
        header('Location: ' . BASE_URL . 'modules/maquinaria/maquinaria.php');
        exit;

    } catch (Exception $e) {
        if ($e->getCode() === '23000') {
            set_flash('error', 'Ja existeix una màquina registrada amb aquest nom o matrícula.');
        } else {
            error_log('[CultiuConnect] nova_maquinaria.php POST: ' . $e->getMessage());
            set_flash('error', 'Error intern en guardar la màquina.');
        }
        $redir = $id_editar_post
            ? BASE_URL . 'modules/maquinaria/nova_maquinaria.php?editar=' . $id_editar_post
            : BASE_URL . 'modules/maquinaria/nova_maquinaria.php';
        header('Location: ' . $redir);
        exit;
    }
}

// -----------------------------------------------------------
// Capçalera (GET)
// -----------------------------------------------------------
$titol_pagina  = $id_editar ? 'Editar Màquina' : 'Nova Màquina';
$pagina_activa = 'maquinaria';
require_once __DIR__ . '/../../includes/header.php';

// Preomple dades del JSON de manteniment si estem en mode edició
$json_dades  = json_decode($dades['manteniment_json'] ?? '{}', true) ?: [];
$data_revisio_val = $json_dades['darrera_revisio'] ?? date('Y-m-d');

$v = fn(string $camp, mixed $def = '') =>
    e((string)($_POST[$camp] ?? $dades[$camp] ?? $def));
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-<?= $id_editar ? 'pen' : 'truck-pickup' ?>" aria-hidden="true"></i>
            <?= $id_editar ? 'Editar Màquina' : 'Donar d\'Alta Nova Màquina' ?>
        </h1>
        <p class="descripcio-seccio">
            <?= $id_editar
                ? 'Modifica les dades o la data de revisió d\'aquesta màquina.'
                : 'Afegeix tractors, pulveritzadors, plataformes o altres eines mecàniques al parc.' ?>
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark"></i> <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form id="form-maquinaria"
          method="POST"
          action="<?= BASE_URL ?>modules/maquinaria/nova_maquinaria.php"
          class="formulari-card"
          novalidate>

        <?php if ($id_editar): ?>
            <input type="hidden" name="id_editar" value="<?= (int)$id_editar ?>">
        <?php endif; ?>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-tractor"></i> Dades de la màquina
            </legend>

            <div class="form-grup">
                <label for="nom_maquina" class="form-label form-label--requerit">
                    Nom del vehicle / Matrícula o referència interna
                </label>
                <input type="text"
                       id="nom_maquina"
                       name="nom_maquina"
                       class="form-input camp-requerit"
                       data-etiqueta="El nom de la màquina"
                       placeholder="Ex: Tractor John Deere 5090 — E1234BBB"
                       maxlength="150"
                       value="<?= $v('nom_maquina') ?>"
                       required>
            </div>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="tipus" class="form-label form-label--requerit">
                        Classificació / Tipus funcional
                    </label>
                    <select id="tipus" name="tipus"
                            class="form-select camp-requerit"
                            data-etiqueta="El tipus de màquina"
                            required>
                        <?php
                        $opcions_tipus = [
                            'Tractor'       => 'Tractor principal / substitut',
                            'Pulveritzador' => 'Cisterna de polvorització / cubeta',
                            'Poda'          => 'Equipament de poda / fresa',
                            'Cistella'      => 'Plataforma recol·lectora (cistella)',
                            'Altres'        => 'Altres (remolc, carro, etc.)',
                        ];
                        $tipus_sel = $_POST['tipus'] ?? $dades['tipus'] ?? 'Tractor';
                        foreach ($opcions_tipus as $val => $lbl): ?>
                            <option value="<?= $val ?>"
                                <?= $tipus_sel === $val ? 'selected' : '' ?>>
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="any_fabricacio" class="form-label">Any de fabricació</label>
                    <input type="number"
                           id="any_fabricacio"
                           name="any_fabricacio"
                           class="form-input"
                           data-tipus="positiu"
                           data-etiqueta="L'any de fabricació"
                           min="1900"
                           max="<?= date('Y') ?>"
                           placeholder="Ex: 2018"
                           value="<?= $v('any_fabricacio') ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-wrench"></i> Manteniment
            </legend>

            <div class="form-grup">
                <label for="darrera_revisio" class="form-label">
                    Data de la darrera ITV o revisió de manteniment
                </label>
                <input type="date"
                       id="darrera_revisio"
                       name="darrera_revisio"
                       class="form-input"
                       data-no-futur
                       value="<?= e($_POST['darrera_revisio'] ?? $data_revisio_val) ?>">
                <span class="form-ajuda">
                    El sistema generarà un avís quan s'acosti l'any des d'aquesta data.
                </span>
            </div>
        </fieldset>

        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk"></i>
                <?= $id_editar ? 'Actualitzar Màquina' : 'Registrar al Parc' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/maquinaria/maquinaria.php" class="boto-secundari">
                <i class="fas fa-xmark"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
