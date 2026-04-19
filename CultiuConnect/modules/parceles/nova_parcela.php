<?php
/**
 * modules/parceles/nova_parcela.php — Creació d'una nova parcel·la.
 *
 * GET  → mostra el formulari amb mapa de dibuix
 * POST → valida, insereix, redirigeix (PRG)
 *
 * Nota: en cas d'error de validació es torna a renderitzar el formulari
 * (no es fa redirect) per conservar el polígon GeoJSON dibuixat.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$errors = [];
$dades  = [
    'nom'              => '',
    'superficie_ha'    => '',
    'pendent'          => '',
    'orientacio'       => '',
    'documentacio_pdf' => '',
    'geojson'          => '',
];

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dades['nom']              = sanitize($_POST['nom']              ?? '');
    $dades['superficie_ha']    = sanitize($_POST['superficie_ha']    ?? '');
    $dades['pendent']          = sanitize($_POST['pendent']          ?? '');
    $dades['orientacio']       = sanitize($_POST['orientacio']       ?? '');
    $dades['documentacio_pdf'] = sanitize($_POST['documentacio_pdf'] ?? '');
    $dades['geojson']          = $_POST['geojson'] ?? ''; // JSON en brut, no sanititzar

    // Validació
    if (empty($dades['nom'])) {
        $errors[] = 'El nom de la parcel·la és obligatori.';
    }
    $sup = (float)$dades['superficie_ha'];
    if (empty($dades['superficie_ha']) || !is_numeric($dades['superficie_ha']) || $sup <= 0) {
        $errors[] = 'La superfície ha de ser un número positiu.';
    }
    if (empty($dades['geojson'])) {
        $errors[] = 'Has de dibuixar el perímetre de la parcel·la al mapa.';
    } elseif (json_decode($dades['geojson']) === null) {
        $errors[] = 'El perímetre dibuixat no és vàlid. Torna a dibuixar-lo.';
        $dades['geojson'] = '';
    }

    if (empty($errors)) {
        try {
            $pdo = connectDB();

            // Comprovar nom únic
            $check = $pdo->prepare("SELECT id_parcela FROM parcela WHERE nom = ?");
            $check->execute([$dades['nom']]);
            if ($check->fetch()) {
                $errors[] = 'Ja existeix una parcel·la amb aquest nom.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO parcela
                        (nom, superficie_ha, pendent, orientacio, documentacio_pdf, coordenades_geo)
                    VALUES
                        (:nom, :superficie_ha, :pendent, :orientacio, :doc_pdf,
                         ST_GeomFromGeoJSON(:geojson))
                ");
                $stmt->execute([
                    ':nom'           => $dades['nom'],
                    ':superficie_ha' => $sup,
                    ':pendent'       => $dades['pendent']          ?: null,
                    ':orientacio'    => $dades['orientacio']       ?: null,
                    ':doc_pdf'       => $dades['documentacio_pdf'] ?: null,
                    ':geojson'       => $dades['geojson'],
                ]);

                set_flash('success', "La parcel·la «{$dades['nom']}» s'ha creat correctament.");
                header('Location: ' . BASE_URL . 'modules/parceles/parceles.php');
                exit;
            }
        } catch (Exception $e) {
            error_log('[CultiuConnect] nova_parcela.php: ' . $e->getMessage());
            $errors[] = 'Error intern en desar la parcel·la. Torna-ho a intentar.';
        }
    }
}

// -----------------------------------------------------------
// Capçalera
// -----------------------------------------------------------
$titol_pagina  = 'Nova Parcel·la';
$pagina_activa = 'parceles';
$css_addicional = [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-plus-circle" aria-hidden="true"></i>
            Crear Nova Parcel·la
        </h1>
        <p class="descripcio-seccio">
            Introdueix les dades tècniques i dibuixa el perímetre de la nova parcel·la sobre el mapa.
        </p>
    </div>

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

    <form action="<?= BASE_URL ?>modules/parceles/nova_parcela.php"
          method="POST"
          class="formulari-card formulari-card--dues-columnes"
          novalidate>

        <!-- ================================================
             COLUMNA ESQUERRA: Dades textuals
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-tag"></i> Dades de la parcel·la
            </legend>

            <div class="form-grup">
                <label for="nom" class="form-label form-label--requerit">
                    Nom de la parcel·la
                </label>
                <input type="text"
                       id="nom"
                       name="nom"
                       class="form-input camp-requerit"
                       data-etiqueta="El nom de la parcel·la"
                       value="<?= e($dades['nom']) ?>"
                       placeholder="Ex: Finca Nord — Pomeres"
                       maxlength="100"
                       required>
            </div>

            <div class="form-grup">
                <label for="superficie_ha" class="form-label form-label--requerit">
                    Superfície (ha)
                </label>
                <input type="number"
                       id="superficie_ha"
                       name="superficie_ha"
                       class="form-input camp-requerit"
                       data-etiqueta="La superfície"
                       data-tipus="decimal"
                       value="<?= e($dades['superficie_ha']) ?>"
                       step="0.01"
                       min="0.01"
                       placeholder="Ex: 2.50"
                       required>
            </div>

            <div class="form-grup">
                <label for="pendent" class="form-label">Pendent del terreny</label>
                <select id="pendent" name="pendent" class="form-select">
                    <option value="">— Selecciona —</option>
                    <?php foreach (['Pla', 'Suau', 'Moderat', 'Fort', 'Pronunciat'] as $p): ?>
                        <option value="<?= $p ?>"
                            <?= $dades['pendent'] === $p ? 'selected' : '' ?>>
                            <?= $p ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grup">
                <label for="orientacio" class="form-label">Orientació</label>
                <select id="orientacio" name="orientacio" class="form-select">
                    <option value="">— Selecciona —</option>
                    <?php foreach ([
                        'Nord', 'Nord-Est', 'Est', 'Sud-Est',
                        'Sud', 'Sud-Oest', 'Oest', 'Nord-Oest',
                    ] as $o): ?>
                        <option value="<?= $o ?>"
                            <?= $dades['orientacio'] === $o ? 'selected' : '' ?>>
                            <?= $o ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grup">
                <label for="documentacio_pdf" class="form-label">
                    URL documentació cadastral (opcional)
                </label>
                <input type="url"
                       id="documentacio_pdf"
                       name="documentacio_pdf"
                       class="form-input"
                       value="<?= e($dades['documentacio_pdf']) ?>"
                       placeholder="https://.../escriptures.pdf"
                       maxlength="500">
                <span class="form-ajuda">
                    Enllaç a l'escriptura, plànol cadastral o nota simple.
                </span>
            </div>
        </fieldset>

        <!-- ================================================
             COLUMNA DRETA: Mapa de dibuix
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-draw-polygon"></i>
                Perímetre al mapa
                <span class="form-label--requerit" aria-hidden="true">*</span>
            </legend>

            <p class="form-ajuda">
                Utilitza l'eina del polígon per dibuixar els límits de la parcel·la.
                Pots editar-lo o esborrar-lo i tornar a dibuixar-lo.
            </p>

            <div id="mapa-dibuix"
                 class="mapa-dibuix"
                 role="application"
                 aria-label="Mapa per dibuixar el perímetre de la parcel·la"></div>

            <!-- Camp ocult que rep el GeoJSON generat per Leaflet.draw -->
            <input type="hidden" id="geojson" name="geojson"
                   value="<?= e($dades['geojson']) ?>">

            <?php if (!empty($dades['geojson'])): ?>
                <p class="form-ajuda form-ajuda--ok">
                    <i class="fas fa-check-circle"></i> Polígon carregat.
                </p>
            <?php endif; ?>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons form-botons--span2">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                Desar Parcel·la
            </button>
            <a href="<?= BASE_URL ?>modules/parceles/parceles.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i>
                Cancel·lar
            </a>
        </div>

    </form>

</div>

<!-- Leaflet + Leaflet.draw (JS al final del body, abans del footer) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<script>
(function () {
'use strict';

const map = L.map('mapa-dibuix').setView([41.6167, 0.6222], 13);

// Capa satèl·lit
L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: '© Esri', maxZoom: 20 }
).addTo(map);

// Grup que emmagatzema el polígon dibuixat
const drawnItems = new L.FeatureGroup().addTo(map);

// Controls de dibuix — només polígons per a parcel·les
const drawControl = new L.Control.Draw({
    edit: { featureGroup: drawnItems },
    draw: {
        polygon:      true,
        polyline:     false,
        rectangle:    false,
        circle:       false,
        circlemarker: false,
        marker:       false,
    },
});
map.addControl(drawControl);

const inputGeoJSON = document.getElementById('geojson');

// Restaurar polígon si hi havia dades (error de validació POST)
if (inputGeoJSON.value) {
    try {
        const geoData = JSON.parse(inputGeoJSON.value);
        L.geoJSON(geoData).eachLayer(layer => drawnItems.addLayer(layer));
        map.fitBounds(drawnItems.getBounds(), { padding: [20, 20] });
    } catch (err) {
        console.warn('[CultiuConnect] GeoJSON no vàlid:', err);
    }
}

// Actualitza l'input ocult amb la geometria del polígon
function actualitzarGeoJSON() {
    const dades = drawnItems.toGeoJSON();
    inputGeoJSON.value = dades.features.length > 0
        ? JSON.stringify(dades.features[0].geometry)
        : '';
}

// Quan es crea un polígon nou → esborrar l'anterior (1 polígon per parcel·la)
map.on(L.Draw.Event.CREATED, function (e) {
    drawnItems.clearLayers();
    drawnItems.addLayer(e.layer);
    actualitzarGeoJSON();
});

map.on('draw:edited',  actualitzarGeoJSON);
map.on('draw:deleted', actualitzarGeoJSON);

})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>