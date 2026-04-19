<?php
/**
 * modules/sectors/files_arbre.php
 *
 * Llistat de les files d'arbres d'un sector concret.
 * Accessible des de detall_sector.php?id=X
 *
 * GET ?id_sector=X
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$id_sector = sanitize_int($_GET['id_sector'] ?? null);

if (!$id_sector) {
    set_flash('error', 'ID de sector no vàlid.');
    header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
    exit;
}

$sector       = null;
$files        = [];
$error_db     = null;

try {
    $pdo = connectDB();

    // Dades bàsiques del sector per a la capçalera
    $stmt = $pdo->prepare("
        SELECT s.id_sector, s.nom AS nom_sector,
               pl.marc_fila, pl.marc_arbre, pl.num_arbres_plantats
        FROM sector s
        LEFT JOIN plantacio pl ON pl.id_sector = s.id_sector
                               AND pl.data_arrencada IS NULL
        WHERE s.id_sector = ?
    ");
    $stmt->execute([$id_sector]);
    $sector = $stmt->fetch();

    if (!$sector) {
        set_flash('error', 'El sector sol·licitat no existeix.');
        header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
        exit;
    }

    // Llistat de files del sector
    $stmt_files = $pdo->prepare("
        SELECT
            fa.id_fila,
            fa.numero,
            fa.descripcio,
            fa.num_arbres,
            ST_AsGeoJSON(fa.coordenades_geo) AS geojson
        FROM fila_arbre fa
        WHERE fa.id_sector = ?
        ORDER BY fa.numero ASC
    ");
    $stmt_files->execute([$id_sector]);
    $files = $stmt_files->fetchAll();

    // Estadístiques ràpides
    $total_arbres_files = array_sum(array_column($files, 'num_arbres'));

} catch (Exception $e) {
    error_log('[CultiuConnect] files_arbre.php: ' . $e->getMessage());
    $error_db = 'Error en carregar les files d\'arbres.';
}

$titol_pagina  = 'Files d\'Arbres — ' . ($sector['nom_sector'] ?? '');
$pagina_activa = 'sectors';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-files-arbre">

    <div class="capcalera-seccio capcalera-seccio--amb-accions">
        <div>
            <h1 class="titol-pagina">
                <i class="fas fa-grip-lines" aria-hidden="true"></i>
                Files d'Arbres
                <span class="titol-pagina__sub">— <?= e($sector['nom_sector']) ?></span>
            </h1>
            <p class="descripcio-seccio">
                Gestió de les files de plantació del sector. Cada fila registra el nombre
                d'arbres i la seva traça geolocalitzada.
            </p>
        </div>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/sectors/detall_sector.php?id=<?= $id_sector ?>"
               class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar al sector
            </a>
            <a href="<?= BASE_URL ?>modules/sectors/nou_fila_arbre.php?id_sector=<?= $id_sector ?>"
               class="boto-principal">
                <i class="fas fa-plus" aria-hidden="true"></i> Nova Fila
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- TARGETES RESUM -->
    <div class="resum-cards resum-cards--grid-llarg">

        <div class="stat-card stat-card--verd">
            <div class="stat-card__icon"><i class="fas fa-grip-lines"></i></div>
            <div class="stat-card__valor"><?= count($files) ?></div>
            <div class="stat-card__label">Files registrades</div>
        </div>

        <div class="stat-card stat-card--blau">
            <div class="stat-card__icon"><i class="fas fa-tree"></i></div>
            <div class="stat-card__valor"><?= $total_arbres_files ?: '—' ?></div>
            <div class="stat-card__label">Arbres en files</div>
        </div>

        <?php if ($sector['marc_fila'] && $sector['marc_arbre']): ?>
        <div class="stat-card stat-card--lila">
            <div class="stat-card__icon"><i class="fas fa-ruler-combined"></i></div>
            <div class="stat-card__valor"><?= e($sector['marc_fila']) ?> × <?= e($sector['marc_arbre']) ?></div>
            <div class="stat-card__label">Marc (m)</div>
        </div>
        <?php endif; ?>

    </div>

    <!-- TAULA DE FILES -->
    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-files"
               placeholder="Cerca per número o descripció..."
               class="input-cerca"
               aria-label="Cercar files">
    </div>

    <table class="taula-simple" id="taula-files">
        <thead>
            <tr>
                <th>Nº Fila</th>
                <th>Descripció</th>
                <th class="text-dreta">Arbres</th>
                <th>Geolocalització</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($files)): ?>
                <tr>
                    <td colspan="5" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha files registrades per a aquest sector.
                        <a href="<?= BASE_URL ?>modules/sectors/nou_fila_arbre.php?id_sector=<?= $id_sector ?>">
                            Afegeix la primera fila.
                        </a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($files as $f): ?>
                <tr>
                    <td>
                        <strong class="num-fila"><?= (int)$f['numero'] ?></strong>
                    </td>
                    <td data-cerca>
                        <?= $f['descripcio'] ? e($f['descripcio']) : '<em class="text-suau">—</em>' ?>
                    </td>
                    <td class="text-dreta">
                        <?= $f['num_arbres'] !== null
                            ? (int)$f['num_arbres']
                            : '<em class="text-suau">—</em>' ?>
                    </td>
                    <td>
                        <?php if ($f['geojson']): ?>
                            <span class="badge badge--verd">
                                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                Geolocalitzada
                            </span>
                        <?php else: ?>
                            <span class="badge badge--gris">
                                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                Sense coordenades
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="cel-accions">
                        <a href="<?= BASE_URL ?>modules/sectors/nou_fila_arbre.php?editar=<?= (int)$f['id_fila'] ?>&id_sector=<?= $id_sector ?>"
                           title="Editar fila"
                           class="btn-accio btn-accio--editar">
                            <i class="fas fa-pen" aria-hidden="true"></i>
                        </a>

                        <form method="POST"
                              action="<?= BASE_URL ?>modules/sectors/processar_fila_arbre.php"
                              class="form-inline-display"
                              onsubmit="return confirm('Eliminar la fila <?= (int)$f['numero'] ?>? Aquesta acció no es pot desfer.')">
                            <input type="hidden" name="accio"      value="eliminar">
                            <input type="hidden" name="id_fila"    value="<?= (int)$f['id_fila'] ?>">
                            <input type="hidden" name="id_sector"  value="<?= $id_sector ?>">
                            <button type="submit"
                                    class="btn-accio btn-accio--eliminar"
                                    title="Eliminar fila">
                                <i class="fas fa-trash" aria-hidden="true"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($files)): ?>
    <!-- MAPA DE LES FILES -->
    <div class="detall-bloc detall-bloc--ample detall-bloc--mt-xl">
        <h2 class="detall-bloc__titol">
            <i class="fas fa-map" aria-hidden="true"></i>
            Mapa de files geolocalitzades
        </h2>
        <div id="mapa-files"
             class="mapa-detall"
             role="application"
             aria-label="Mapa de files d'arbres del sector <?= e($sector['nom_sector']) ?>">
        </div>
        <!-- Dades GeoJSON per a JS -->
        <script id="dades-files" type="application/json">
            <?= json_encode(
                array_values(array_filter($files, fn($f) => !empty($f['geojson']))),
                JSON_UNESCAPED_UNICODE
            ) ?>
        </script>
    </div>
    <?php endif; ?>

</div>

<?php if (!empty($files)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
'use strict';

const map = L.map('mapa-files').setView([41.6167, 0.6222], 15);

L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: '© Esri', maxZoom: 20 }
).addTo(map);

const dades = JSON.parse(document.getElementById('dades-files').textContent || '[]');
const bounds = [];

// Paleta de colors per distingir les files
const colors = ['#e74c3c','#3498db','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#e91e63'];

dades.forEach((fila, i) => {
    if (!fila.geojson) return;
    try {
        const geom = JSON.parse(fila.geojson);
        const color = colors[i % colors.length];
        const capa = L.geoJSON(geom, {
            style: { color, weight: 4, opacity: 0.9 },
            pointToLayer: (feature, latlng) => L.circleMarker(latlng, {
                radius: 6, fillColor: color, color: '#fff',
                weight: 2, opacity: 1, fillOpacity: 0.9
            })
        })
        .bindPopup(
            '<strong>Fila ' + (fila.numero ?? '?') + '</strong>' +
            (fila.descripcio ? '<br>' + fila.descripcio : '') +
            (fila.num_arbres ? '<br>' + fila.num_arbres + ' arbres' : '')
        )
        .addTo(map);

        try { bounds.push(...capa.getBounds().toArray()); } catch (_) {}
    } catch (err) {
        console.warn('[CultiuConnect] GeoJSON fila ' + fila.numero + ' no vàlid:', err);
    }
});

if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [30, 30] });
}

})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
