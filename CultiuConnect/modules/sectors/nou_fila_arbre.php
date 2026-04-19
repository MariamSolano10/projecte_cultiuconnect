<?php
/**
 * modules/sectors/nou_fila_arbre.php
 *
 * Formulari per crear o editar una fila d'arbres dins d'un sector.
 *
 * GET  ?id_sector=X          → crear nova fila per al sector X
 * GET  ?editar=ID&id_sector=X → editar la fila ID del sector X
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_sector = sanitize_int($_GET['id_sector'] ?? null);
$id_editar = sanitize_int($_GET['editar']    ?? null);
$es_edicio = $id_editar !== null;

// Validació mínima: necessitem id_sector sempre
if (!$id_sector) {
    set_flash('error', 'Cal especificar un sector.');
    header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
    exit;
}

$sector   = null;
$fila     = null;
$error_db = null;

// Número suggerit per a la nova fila (màxim actual + 1)
$numero_suggerit = 1;

try {
    $pdo = connectDB();

    // Dades del sector
    $stmt = $pdo->prepare("SELECT id_sector, nom AS nom_sector FROM sector WHERE id_sector = ?");
    $stmt->execute([$id_sector]);
    $sector = $stmt->fetch();

    if (!$sector) {
        set_flash('error', 'El sector especificat no existeix.');
        header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
        exit;
    }

    // Número suggerit = max(numero) + 1
    $stmt_max = $pdo->prepare("SELECT COALESCE(MAX(numero), 0) + 1 FROM fila_arbre WHERE id_sector = ?");
    $stmt_max->execute([$id_sector]);
    $numero_suggerit = (int)$stmt_max->fetchColumn();

    // Si estem editant, carreguem la fila
    if ($es_edicio) {
        $stmt_f = $pdo->prepare("
            SELECT id_fila, id_sector, numero, descripcio, num_arbres,
                   ST_AsGeoJSON(coordenades_geo) AS geojson
            FROM fila_arbre
            WHERE id_fila = ? AND id_sector = ?
        ");
        $stmt_f->execute([$id_editar, $id_sector]);
        $fila = $stmt_f->fetch();

        if (!$fila) {
            set_flash('error', 'La fila sol·licitada no existeix o no pertany a aquest sector.');
            header('Location: ' . BASE_URL . 'modules/sectors/files_arbre.php?id_sector=' . $id_sector);
            exit;
        }
    }

    // Números ja usats al sector (per validació JS en temps real)
    $stmt_nums = $pdo->prepare("SELECT numero FROM fila_arbre WHERE id_sector = ? ORDER BY numero ASC");
    $stmt_nums->execute([$id_sector]);
    $numeros_usats = $stmt_nums->fetchAll(PDO::FETCH_COLUMN);
    // En mode edició, el número actual no és "usat" des del punt de vista de validació
    if ($es_edicio) {
        $numeros_usats = array_values(array_filter($numeros_usats, fn($n) => (int)$n !== (int)$fila['numero']));
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] nou_fila_arbre.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar el formulari.';
}

$titol_pagina  = $es_edicio ? 'Editar Fila d\'Arbres' : 'Nova Fila d\'Arbres';
$pagina_activa = 'sectors';
$css_addicional = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-formulari">

    <div class="capcalera-seccio capcalera-seccio--amb-accions">
        <div>
            <h1 class="titol-pagina">
                <i class="fas fa-<?= $es_edicio ? 'pen' : 'plus' ?>" aria-hidden="true"></i>
                <?= $es_edicio ? 'Editar Fila d\'Arbres' : 'Nova Fila d\'Arbres' ?>
                <span class="titol-pagina__sub">— <?= $sector ? e($sector['nom_sector']) : '' ?></span>
            </h1>
        </div>
        <a href="<?= BASE_URL ?>modules/sectors/files_arbre.php?id_sector=<?= $id_sector ?>"
           class="boto-secundari">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar a les files
        </a>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= BASE_URL ?>modules/sectors/processar_fila_arbre.php"
          class="formulari-card"
          novalidate>

        <input type="hidden" name="accio"     value="<?= $es_edicio ? 'editar' : 'crear' ?>">
        <input type="hidden" name="id_sector" value="<?= $id_sector ?>">
        <?php if ($es_edicio): ?>
            <input type="hidden" name="id_fila" value="<?= (int)$fila['id_fila'] ?>">
        <?php endif; ?>

        <!-- ================================================
             BLOC 1: Identificació de la fila
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-list-ol" aria-hidden="true"></i>
                Identificació
            </legend>

            <div class="form-fila-2">
                <div class="form-grup">
                    <label for="numero" class="form-label form-label--requerit">
                        Número de fila
                    </label>
                    <input type="number"
                           id="numero"
                           name="numero"
                           class="form-input camp-requerit"
                           data-etiqueta="El número de fila"
                           min="1"
                           max="9999"
                           required
                           value="<?= $es_edicio ? (int)$fila['numero'] : $numero_suggerit ?>">
                    <span class="form-ajuda">
                        Ha de ser únic dins del sector.
                        <?php if (!empty($numeros_usats)): ?>
                            Números en ús: <?= implode(', ', $numeros_usats) ?>.
                        <?php endif; ?>
                    </span>
                    <span id="avis-numero-duplicat"
                          class="form-error"
                         class="amagat">
                        <i class="fas fa-triangle-exclamation"></i>
                        Aquest número ja existeix en aquest sector.
                    </span>
                </div>

                <div class="form-grup">
                    <label for="num_arbres" class="form-label">
                        Nombre d'arbres
                    </label>
                    <input type="number"
                           id="num_arbres"
                           name="num_arbres"
                           class="form-input"
                           data-tipus="positiu"
                           data-etiqueta="El nombre d'arbres"
                           min="1"
                           max="9999"
                           placeholder="Ex: 45"
                           value="<?= $es_edicio && $fila['num_arbres'] !== null ? (int)$fila['num_arbres'] : '' ?>">
                    <span class="form-ajuda">Nombre real d'arbres presents a la fila (opcional).</span>
                </div>
            </div>

            <div class="form-grup">
                <label for="descripcio" class="form-label">
                    Descripció / Identificador visual
                </label>
                <input type="text"
                       id="descripcio"
                       name="descripcio"
                       class="form-input"
                       maxlength="100"
                       placeholder="Ex: Fila A, Fila Est, Fila Sud-1"
                       value="<?= e($es_edicio ? ($fila['descripcio'] ?? '') : '') ?>">
                <span class="form-ajuda">
                    Nom o etiqueta que faciliti la identificació visual al camp (opcional).
                </span>
            </div>
        </fieldset>

        <!-- ================================================
             BLOC 2: Geolocalització
        ================================================ -->
        <fieldset class="form-bloc">
            <legend class="form-bloc__titol">
                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                Traça geolocalitzada
                <span class="form-bloc__opcional">(opcional)</span>
            </legend>

            <p class="form-ajuda form-ajuda--mb">
                Dibuixa la traça de la fila sobre el mapa (línia) o marca el punt d'inici.
                Les dades es desaran automàticament al camp ocult.
            </p>

            <!-- Mapa interactiu per dibuixar la fila -->
            <div id="mapa-fila"
                 class="mapa-detall mapa-detall--formulari"
                 class="mapa-editor"
                 role="application"
                 aria-label="Mapa per geolocalitzar la fila">
            </div>

            <div class="accions-inline--wrap">
                <button type="button" id="btn-dibuixar-linia" class="boto-secundari boto-secundari--petit">
                    <i class="fas fa-pencil-ruler" aria-hidden="true"></i> Dibuixar traça (línia)
                </button>
                <button type="button" id="btn-dibuixar-punt" class="boto-secundari boto-secundari--petit">
                    <i class="fas fa-map-pin" aria-hidden="true"></i> Marcar punt
                </button>
                <button type="button" id="btn-netejar-mapa" class="boto-secundari boto-secundari--petit boto-secundari--perill"
                        class="amagat">
                    <i class="fas fa-trash" aria-hidden="true"></i> Esborrar dibuix
                </button>
            </div>

            <div id="avis-mapa" class="flash flash--info flash--stack-s">
                <i class="fas fa-circle-info" aria-hidden="true"></i>
                <span id="avis-mapa-text"></span>
            </div>

            <!-- Camp ocult que rep el GeoJSON generat al mapa -->
            <input type="hidden"
                   id="coordenades_geo_json"
                   name="coordenades_geo_json"
                   value="<?= e($es_edicio ? ($fila['geojson'] ?? '') : '') ?>">

            <span class="form-ajuda">
                Si no s'introdueix cap geometria, la fila es desarà sense coordenades
                i es podrà geolocalitzar més endavant.
            </span>
        </fieldset>

        <!-- Botons -->
        <div class="form-botons">
            <button type="submit" class="boto-principal">
                <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                <?= $es_edicio ? 'Desar canvis' : 'Crear fila' ?>
            </button>
            <a href="<?= BASE_URL ?>modules/sectors/files_arbre.php?id_sector=<?= $id_sector ?>"
               class="boto-secundari">
                <i class="fas fa-xmark" aria-hidden="true"></i> Cancel·lar
            </a>
        </div>

    </form>
</div>

<!-- Scripts de mapa -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
'use strict';

// -------------------------------------------------------
// Inicialització del mapa
// -------------------------------------------------------
const map = L.map('mapa-fila').setView([41.6167, 0.6222], 15);

L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: '© Esri', maxZoom: 20 }
).addTo(map);

// Intentem centrar el mapa per geolocalització del navegador (si l'usuari ho permet)
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
        pos => map.setView([pos.coords.latitude, pos.coords.longitude], 16),
        ()  => {} // silenciós si es denega
    );
}

// -------------------------------------------------------
// Estat intern
// -------------------------------------------------------
let capa_actual    = null;   // L.Polyline o L.Marker actual
let mode_dibuix    = null;   // 'linia' | 'punt' | null
let punts_linia    = [];     // array de LatLng mentre es dibuixa la línia
let linia_temporal = null;   // polilínia en construcció

const camp_geo = document.getElementById('coordenades_geo_json');
const btnLinia = document.getElementById('btn-dibuixar-linia');
const btnPunt  = document.getElementById('btn-dibuixar-punt');
const btnNet   = document.getElementById('btn-netejar-mapa');
const avis     = document.getElementById('avis-mapa');
const avisText = document.getElementById('avis-mapa-text');

// -------------------------------------------------------
// Carrega geometria existent (mode edició)
// -------------------------------------------------------
const geojsonExistent = camp_geo.value.trim();
if (geojsonExistent) {
    try {
        const geom = JSON.parse(geojsonExistent);
        capa_actual = L.geoJSON(geom, {
            style:        { color: '#2ecc71', weight: 4 },
            pointToLayer: (f, ll) => L.circleMarker(ll, {
                radius: 8, fillColor: '#2ecc71', color: '#fff',
                weight: 2, fillOpacity: 1
            })
        }).addTo(map);
        try { map.fitBounds(capa_actual.getBounds(), { padding: [40, 40] }); } catch (_) {}
        btnNet.style.display = 'inline-flex';
    } catch (e) {
        console.warn('[CultiuConnect] GeoJSON existent no vàlid:', e);
    }
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function mostrarAvis(text) {
    avisText.textContent = text;
    avis.style.display   = 'flex';
}
function amagarAvis() {
    avis.style.display = 'none';
}
function desarGeoJSON(geom) {
    camp_geo.value = JSON.stringify(geom);
}
function netejarCapa() {
    if (capa_actual)    { map.removeLayer(capa_actual);    capa_actual    = null; }
    if (linia_temporal) { map.removeLayer(linia_temporal); linia_temporal = null; }
    punts_linia    = [];
    camp_geo.value = '';
    btnNet.style.display = 'none';
    amagarAvis();
}
function activarModeLinea() {
    mode_dibuix = 'linia';
    punts_linia = [];
    map.getContainer().style.cursor = 'crosshair';
    mostrarAvis('Fes clic al mapa per afegir punts a la traça. Doble clic per finalitzar.');
    btnLinia.classList.add('boto-actiu');
    btnPunt.classList.remove('boto-actiu');
}
function activarModePunt() {
    mode_dibuix = 'punt';
    map.getContainer().style.cursor = 'crosshair';
    mostrarAvis('Fes clic al mapa per marcar el punt d\'inici de la fila.');
    btnPunt.classList.add('boto-actiu');
    btnLinia.classList.remove('boto-actiu');
}
function desactivarModeDisbuix() {
    mode_dibuix = null;
    map.getContainer().style.cursor = '';
    btnLinia.classList.remove('boto-actiu');
    btnPunt.classList.remove('boto-actiu');
}

// -------------------------------------------------------
// Botons
// -------------------------------------------------------
btnLinia.addEventListener('click', () => {
    netejarCapa();
    activarModeLinea();
});
btnPunt.addEventListener('click', () => {
    netejarCapa();
    activarModePunt();
});
btnNet.addEventListener('click', () => {
    netejarCapa();
    desactivarModeDisbuix();
});

// -------------------------------------------------------
// Interacció al mapa
// -------------------------------------------------------
map.on('click', function (e) {
    if (!mode_dibuix) return;

    if (mode_dibuix === 'punt') {
        // Un sol punt → Guardem com a GeoJSON Point
        if (capa_actual) map.removeLayer(capa_actual);
        capa_actual = L.circleMarker(e.latlng, {
            radius: 8, fillColor: '#e74c3c', color: '#fff',
            weight: 2, fillOpacity: 1
        }).addTo(map);

        desarGeoJSON({
            type: 'Point',
            coordinates: [e.latlng.lng, e.latlng.lat]
        });

        btnNet.style.display = 'inline-flex';
        mostrarAvis('Punt marcat. Pots mou-te\'l tornant a clicar al mapa o esborrar-lo.');
    }

    if (mode_dibuix === 'linia') {
        punts_linia.push(e.latlng);

        // Actualitzem la polilínia temporal
        if (linia_temporal) map.removeLayer(linia_temporal);
        linia_temporal = L.polyline(punts_linia, {
            color: '#e74c3c', weight: 3, dashArray: '6 4'
        }).addTo(map);

        mostrarAvis('Punts afegits: ' + punts_linia.length + '. Doble clic per finalitzar la traça.');
    }
});

map.on('dblclick', function (e) {
    if (mode_dibuix !== 'linia' || punts_linia.length < 2) return;
    e.originalEvent.preventDefault(); // evitem zoom doble clic

    // Finalitzem la línia
    if (linia_temporal) { map.removeLayer(linia_temporal); linia_temporal = null; }
    if (capa_actual)    { map.removeLayer(capa_actual); }

    capa_actual = L.polyline(punts_linia, { color: '#2ecc71', weight: 4 }).addTo(map);

    desarGeoJSON({
        type: 'LineString',
        coordinates: punts_linia.map(ll => [ll.lng, ll.lat])
    });

    btnNet.style.display = 'inline-flex';
    mostrarAvis('Traça desada amb ' + punts_linia.length + ' punts. Pots editar-la esborrant i tornant a dibuixar.');
    desactivarModeDisbuix();
    punts_linia = [];
});

})();
</script>

<!-- Validació número duplicat en temps real -->
<script>
(function () {
    const numeros_usats = <?= json_encode(array_map('intval', $numeros_usats ?? [])) ?>;
    const inputNum      = document.getElementById('numero');
    const avisNum       = document.getElementById('avis-numero-duplicat');

    inputNum.addEventListener('input', function () {
        const val = parseInt(this.value, 10);
        if (numeros_usats.includes(val)) {
            avisNum.style.display = 'flex';
            this.setCustomValidity('Número duplicat');
        } else {
            avisNum.style.display = 'none';
            this.setCustomValidity('');
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
