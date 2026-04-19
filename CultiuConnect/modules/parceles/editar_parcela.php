<?php
/**
 * modules/parceles/editar_parcela.php — Edició d'una parcel·la existent.
 *
 * GET  → carrega les dades actuals i mostra el formulari amb el polígon al mapa
 * POST → valida, actualitza, redirigeix (PRG)
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$errors     = [];
$dades      = [];
$id_parcela = sanitize_int($_GET['id'] ?? null);

// -----------------------------------------------------------
// Validar ID i carregar dades existents
// -----------------------------------------------------------
if (!$id_parcela) {
    set_flash('error', 'ID de parcel·la invàlid o no proporcionat.');
    header('Location: ' . BASE_URL . 'modules/parceles/parceles.php');
    exit;
}

try {
    $pdo  = connectDB();
    $stmt = $pdo->prepare("
        SELECT id_parcela, nom, superficie_ha, pendent, orientacio,
               documentacio_pdf, ST_AsGeoJSON(coordenades_geo) AS geojson
        FROM parcela
        WHERE id_parcela = ?
    ");
    $stmt->execute([$id_parcela]);
    $dades = $stmt->fetch();

    if (!$dades) {
        set_flash('error', 'La parcel·la sol·licitada no existeix.');
        header('Location: ' . BASE_URL . 'modules/parceles/parceles.php');
        exit;
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] editar_parcela.php (SELECT): ' . $e->getMessage());
    set_flash('error', 'Error en carregar les dades de la parcel·la.');
    header('Location: ' . BASE_URL . 'modules/parceles/parceles.php');
    exit;
}

// -----------------------------------------------------------
// Processament POST
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sobreescribim $dades amb els valors del POST per preservar-los si hi ha errors
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
        $errors[] = 'Has de definir el perímetre de la parcel·la al mapa.';
    } elseif (json_decode($dades['geojson']) === null) {
        $errors[] = 'El perímetre dibuixat no és vàlid. Torna a dibuixar-lo.';
        $dades['geojson'] = '';
    }

    if (empty($errors)) {
        try {
            // Comprovar nom únic excloent la parcel·la actual
            $check = $pdo->prepare(
                "SELECT id_parcela FROM parcela WHERE nom = ? AND id_parcela != ?"
            );
            $check->execute([$dades['nom'], $id_parcela]);
            if ($check->fetch()) {
                $errors[] = 'Ja existeix una altra parcel·la amb aquest nom.';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE parcela SET
                        nom              = :nom,
                        superficie_ha    = :superficie_ha,
                        pendent          = :pendent,
                        orientacio       = :orientacio,
                        documentacio_pdf = :doc_pdf,
                        coordenades_geo  = ST_GeomFromGeoJSON(:geojson)
                    WHERE id_parcela = :id
                ");
                $stmt->execute([
                    ':nom'           => $dades['nom'],
                    ':superficie_ha' => $sup,
                    ':pendent'       => $dades['pendent']          ?: null,
                    ':orientacio'    => $dades['orientacio']       ?: null,
                    ':doc_pdf'       => $dades['documentacio_pdf'] ?: null,
                    ':geojson'       => $dades['geojson'],
                    ':id'            => $id_parcela,
                ]);

                set_flash('success', "La parcel·la «{$dades['nom']}» s'ha actualitzat correctament.");
                header('Location: ' . BASE_URL . 'modules/parceles/parceles.php');
                exit;
            }
        } catch (Exception $e) {
            error_log('[CultiuConnect] editar_parcela.php (UPDATE): ' . $e->getMessage());
            $errors[] = 'Error intern en actualitzar la parcel·la. Torna-ho a intentar.';
        }
    }
}

// -----------------------------------------------------------
// Capçalera
// -----------------------------------------------------------
$titol_pagina  = 'Editar parcel·la';   // Sense dades de BD al títol
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
            <i class="fas fa-pen" aria-hidden="true"></i>
            Editar Parcel·la: <?= e($dades['nom']) ?>
        </h1>
        <p class="descripcio-seccio">
            Modifica les dades tècniques o ajusta el perímetre al mapa.
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

    <form action="<?= BASE_URL ?>modules/parceles/editar_parcela.php?id=<?= (int)$id_parcela ?>"
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
                <span class="form-ajuda">Enllaç a l'escriptura, plànol cadastral o nota simple.</span>
            </div>
        </fieldset>

        <!-- ================================================
             COLUMNA DRETA: Mapa d'edició
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-draw-polygon"></i>
                Perímetre al mapa
                <span class="form-label--requerit" aria-hidden="true">*</span>
            </legend>

            <p class="form-ajuda">
                Pots editar els vèrtexs del polígon actual o esborrar-lo i redibuixar-lo.
            </p>

            <div id="mapa-dibuix"
                 class="mapa-dibuix"
                 role="application"
                 aria-label="Mapa per editar el perímetre de la parcel·la"></div>

            <input type="hidden" id="geojson" name="geojson"
                   value="<?= e($dades['geojson']) ?>">
        </fieldset>

        <!-- Botons -->
        <div class="form-botons form-botons--span2">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                Actualitzar Parcel·la
            </button>
            <a href="<?= BASE_URL ?>modules/parceles/parceles.php" class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i>
                Cancel·lar
            </a>
        </div>

    </form>

</div>

<!-- Leaflet + Leaflet.draw -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<script>
(function () {
'use strict';

const map = L.map('mapa-dibuix').setView([41.6167, 0.6222], 13);

L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: '© Esri', maxZoom: 20 }
).addTo(map);

const drawnItems  = new L.FeatureGroup().addTo(map);
const inputGeoJSON = document.getElementById('geojson');

// Carregar el polígon existent (taronja per distingir mode edició)
if (inputGeoJSON.value) {
    try {
        const geoData = JSON.parse(inputGeoJSON.value);
        L.geoJSON(geoData, {
            style: { color: '#f39c12', weight: 3, fillOpacity: 0.35 }
        }).eachLayer(layer => drawnItems.addLayer(layer));
        map.fitBounds(drawnItems.getBounds(), { padding: [20, 20] });
    } catch (err) {
        console.warn('[CultiuConnect] GeoJSON no vàlid:', err);
    }
}

// Controls d'edició
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

function actualitzarGeoJSON() {
    const dades = drawnItems.toGeoJSON();
    inputGeoJSON.value = dades.features.length > 0
        ? JSON.stringify(dades.features[0].geometry)
        : '';
}

// Nou polígon → esborrar l'anterior (1 per parcel·la)
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