<?php
/**
 * modules/varietats/upload_fotos.php
 *
 * Sistema d'upload d'imatges per a varietats.
 * Permet pujar fotos d'arbre, flor i fruit des de la fitxa detallada.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_varietat = sanitize_int($_GET['id'] ?? null);

if (!$id_varietat) {
    set_flash('error', 'ID de varietat invàlid.');
    header('Location: ' . BASE_URL . 'modules/varietats/varietats.php');
    exit;
}

$titol_pagina = 'Pujar Fotos - Varietat';
$pagina_activa = 'varietats';

$varietat = null;
$fotos_existentes = [];
$error_db = null;
$upload_resultat = null;

// Màxim 5MB per imatge
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
// Tipus permesos
define('UPLOAD_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

try {
    $pdo = connectDB();

    // Obtenir dades de la varietat
    $stmt = $pdo->prepare("
        SELECT id_varietat, nom_varietat, v.nom_cientific, e.nom_comu AS especie
        FROM varietat v
        JOIN especie e ON e.id_especie = v.id_especie
        WHERE v.id_varietat = ?
    ");
    $stmt->execute([$id_varietat]);
    $varietat = $stmt->fetch();

    if (!$varietat) {
        set_flash('error', 'La varietat no existeix.');
        header('Location: ' . BASE_URL . 'modules/varietats/varietats.php');
        exit;
    }

    // Obtenir fotos existents
    $stmt = $pdo->prepare("
        SELECT * FROM foto_varietat 
        WHERE id_varietat = ? 
        ORDER BY tipus ASC, id_foto ASC
    ");
    $stmt->execute([$id_varietat]);
    $fotos_existentes = $stmt->fetchAll();

    // Processar upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fotos'])) {
        $tipus_foto = filter_var($_POST['tipus'] ?? '', FILTER_SANITIZE_STRING);
        $descripcio = filter_var($_POST['descripcio'] ?? '', FILTER_SANITIZE_STRING);

        if (!in_array($tipus_foto, ['arbre', 'flor', 'fruit'])) {
            $upload_resultat = ['error' => 'Has de seleccionar un tipus de foto vàlid.'];
        } else {
            // Processar cada fitxer
            $fotos_pujades = 0;
            $errors_upload = [];

            foreach ($_FILES['fotos']['name'] as $index => $name) {
                if ($_FILES['fotos']['error'][$index] !== UPLOAD_ERR_OK) {
                    $errors_upload[] = 'Error pujant fitxer: ' . $name;
                    continue;
                }

                $tmp_name = $_FILES['fotos']['tmp_name'][$index];
                $size = $_FILES['fotos']['size'][$index];
                $type = $_FILES['fotos']['type'][$index];

                // Validar mida
                if ($size > MAX_UPLOAD_SIZE) {
                    $errors_upload[] = 'El fitxer ' . $name . ' és massa gran (màxim 5MB).';
                    continue;
                }

                // Validar tipus
                if (!in_array($type, UPLOAD_TYPES)) {
                    $errors_upload[] = 'El fitxer ' . $name . ' no és un tipus d\'imatge vàlid.';
                    continue;
                }

                // Generar nom únic
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $nom_fitxer = 'varietat_' . $id_varietat . '_' . $tipus_foto . '_' . time() . '_' . uniqid() . '.' . $extension;
                $ruta_desti = __DIR__ . '/../../uploads/varietats/' . $nom_fitxer;

                // Crear directori si no existeix
                $directori_desti = dirname($ruta_desti);
                if (!is_dir($directori_desti)) {
                    mkdir($directori_desti, 0755, true);
                }

                // Moure fitxer
                if (move_uploaded_file($tmp_name, $ruta_desti)) {
                    // Guardar a BD
                    $stmt = $pdo->prepare("
                        INSERT INTO foto_varietat 
                            (id_varietat, url_foto, descripcio, tipus, data_pujada)
                        VALUES 
                            (:id_varietat, :url_foto, :descripcio, :tipus, NOW())
                    ");
                    $stmt->execute([
                        ':id_varietat' => $id_varietat,
                        ':url_foto' => 'uploads/varietats/' . $nom_fitxer,
                        ':descripcio' => $descripcio,
                        ':tipus' => $tipus_foto
                    ]);

                    $fotos_pujades++;
                } else {
                    $errors_upload[] = 'Error movent el fitxer ' . $name;
                }
            }

            if (empty($errors_upload)) {
                $upload_resultat = [
                    'success' => true,
                    'missatge' => "S'han pujat $fotos_pujades fotos correctament."
                ];
            } else {
                $upload_resultat = [
                    'error' => true,
                    'missatge' => 'Errors en la pujada: ' . implode(', ', $errors_upload)
                ];
            }
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] upload_fotos.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-upload">
    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-camera" aria-hidden="true"></i>
            Pujar Fotos - <?= e($varietat['nom_varietat']) ?>
        </h1>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/varietats/detall_varietat.php?id=<?= (int)$id_varietat ?>" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar a la fitxa
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <?php if ($upload_resultat): ?>
        <div class="flash flash--<?= $upload_resultat['error'] ? 'error' : 'success' ?>" role="alert">
            <i class="fas fa-<?= $upload_resultat['error'] ? 'circle-xmark' : 'check-circle' ?>" aria-hidden="true"></i>
            <?= e($upload_resultat['missatge']) ?>
        </div>
    <?php endif; ?>

    <div class="upload-grid">
        <!-- Formulari d'upload -->
        <div class="upload-formulari">
            <h2 class="upload-titol">
                <i class="fas fa-upload" aria-hidden="true"></i>
                Pujar Noves Fotos
            </h2>
            
            <form method="POST" enctype="multipart/form-data" class="formulari-card">
                <div class="form-grup">
                    <label for="tipus" class="form-label form-label--requerit">
                        Tipus de Foto
                    </label>
                    <select id="tipus" name="tipus" class="form-select" required>
                        <option value="">Selecciona tipus</option>
                        <option value="arbre">🌳 Arbre</option>
                        <option value="flor">🌸 Flor</option>
                        <option value="fruit">🍎 Fruit</option>
                    </select>
                </div>

                <div class="form-grup">
                    <label for="fotos" class="form-label form-label--requerit">
                        Fitxers d'imatge
                    </label>
                    <input type="file" 
                           id="fotos" 
                           name="fotos[]" 
                           class="form-input" 
                           accept="image/*" 
                           multiple 
                           required>
                    <small class="form-ajuda">
                        Pots seleccionar múltiples fitxers. Mida màxima: 5MB per fitxer.
                        Formats permesos: JPG, PNG, GIF, WebP.
                    </small>
                </div>

                <div class="form-grup">
                    <label for="descripcio" class="form-label">
                        Descripció (opcional)
                    </label>
                    <textarea id="descripcio" 
                              name="descripcio" 
                              class="form-textarea" 
                              rows="3"
                              placeholder="Descripció de les fotos..."></textarea>
                </div>

                <div class="form-botons">
                    <button type="submit" class="boto-principal">
                        <i class="fas fa-cloud-upload-alt" aria-hidden="true"></i>
                        Pujar Fotos
                    </button>
                </div>
            </form>
        </div>

        <!-- Fotos existents -->
        <div class="fotos-existents">
            <h2 class="upload-titol">
                <i class="fas fa-images" aria-hidden="true"></i>
                Fotos Existentents
            </h2>
            
            <?php if (empty($fotos_existentes)): ?>
                <div class="sense-fotos">
                    <i class="fas fa-image" aria-hidden="true"></i>
                    <p>Encara no hi ha fotos per aquesta varietat.</p>
                </div>
            <?php else: ?>
                <div class="fotos-grid">
                    <?php foreach ($fotos_existentes as $foto): ?>
                        <div class="foto-item">
                            <img src="<?= BASE_URL . e($foto['url_foto']) ?>" 
                                 alt="<?= e($foto['descripcio'] ?? 'Foto ' . ucfirst($foto['tipus'])) ?>"
                                 class="foto-imatge"
                                 loading="lazy">
                            <div class="foto-info">
                                <span class="badge badge--<?= $foto['tipus'] === 'arbre' ? 'verd' : ($foto['tipus'] === 'flor' ? 'rosa' : 'taronja') ?>">
                                    <?= ucfirst($foto['tipus']) ?>
                                </span>
                                <p class="foto-descripcio"><?= e($foto['descripcio']) ?></p>
                                <small class="foto-data">
                                    <?= format_data($foto['data_pujada']) ?>
                                </small>
                            </div>
                            <div class="foto-accions">
                                <a href="<?= BASE_URL ?>modules/varietats/eliminar_foto.php?id=<?= (int)$foto['id_foto'] ?>&id_varietat=<?= (int)$id_varietat ?>" 
                                   class="boto-eliminar" 
                                   onclick="return confirm('Estàs segur que vols eliminar aquesta foto?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
