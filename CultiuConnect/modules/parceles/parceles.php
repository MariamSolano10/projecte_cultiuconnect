<?php
/**
 * modules/parceles/parceles.php — Llistat i mapa de parcel·les de l'explotació.
 *
 * Mostra:
 *   - Mapa interactiu Leaflet amb els polígons de cada parcel·la
 *   - Taula amb cultiu actiu, superfície, estat i accions
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$llistat_parceles = [];
$error_db = null;

try {
    $pdo = connectDB();

    $sql = "
        SELECT
            p.id_parcela,
            p.nom,
            p.superficie_ha,
            p.pendent,
            p.orientacio,
            ST_AsGeoJSON(p.coordenades_geo) AS geojson,

            -- Subconsulta per al cultiu actiu (segura amb LIMIT 1)
            (SELECT CONCAT(v.nom_varietat, ' (', e.nom_comu, ')')
             FROM parcela_sector ps
             JOIN plantacio pl ON pl.id_sector = ps.id_sector
             JOIN varietat v ON v.id_varietat = pl.id_varietat
             JOIN especie e ON e.id_especie = v.id_especie
             WHERE ps.id_parcela = p.id_parcela AND pl.data_arrencada IS NULL
             LIMIT 1) AS cultiu,

            -- Subconsulta per l'any de plantació
            (SELECT YEAR(pl.data_plantacio)
             FROM parcela_sector ps
             JOIN plantacio pl ON pl.id_sector = ps.id_sector
             WHERE ps.id_parcela = p.id_parcela AND pl.data_arrencada IS NULL
             LIMIT 1) AS any_plantacio,

            -- Subconsulta per a l'estat (amb COALESCE per defecte)
            COALESCE(
                (SELECT CASE
                            WHEN pl.previsio_entrada_produccio <= CURRENT_DATE() THEN 'Producció'
                            ELSE 'En preparació'
                        END
                 FROM parcela_sector ps
                 JOIN plantacio pl ON pl.id_sector = ps.id_sector AND pl.data_arrencada IS NULL
                 WHERE ps.id_parcela = p.id_parcela
                 LIMIT 1), 
            'Sense plantar') AS estat

        FROM parcela p
        ORDER BY p.nom ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $llistat_parceles = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_db = "Error carregant parcel·les: " . $e->getMessage();
}

// -----------------------------------------------------------
// Helpers locals (complementen els de helpers.php)
// -----------------------------------------------------------

/**
 * Classifica el pendent en risc baix/mitjà/alt per a la gestió de l'aigua.
 */
function obtenirNivellRisc(?string $pendent): string
{
    if (empty($pendent))
        return 'N/A';
    $p = strtolower($pendent);
    if (str_contains($p, 'pronunciat') || str_contains($p, 'fort') || str_contains($p, 'alt'))
        return 'Alt';
    if (str_contains($p, 'moderat') || str_contains($p, 'mitj'))
        return 'Mitjà';
    if (str_contains($p, 'suau') || str_contains($p, 'pla') || str_contains($p, 'baix'))
        return 'Baix';
    return 'Mitjà';
}

/**
 * Retorna la classe CSS corresponent a l'estat de la parcel·la.
 */
function classeEstat(?string $estat): string
{
    return match (strtolower($estat ?? '')) {
        'producció' => 'estat-ok',
        'en preparació' => 'estat-atencio',
        default => 'estat-normal',
    };
}

// -----------------------------------------------------------
// Capçalera — Leaflet CSS carregat via $css_addicional
// -----------------------------------------------------------
$titol_pagina = 'Parcel·les i Sòl';
$pagina_activa = 'parceles';
$css_addicional = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-parceles">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
            Parcel·les i Sectors
        </h1>
        <p class="descripcio-seccio">
            Definició i paràmetres tècnics de cada unitat productiva de l'explotació.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Mapa Leaflet -->
    <div id="mapa-parceles" class="mapa-parceles" aria-label="Mapa de parcel·les"></div>

    <!-- Botons d'acció -->
    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/parceles/nova_parcela.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Nova Parcel·la
        </a>
    </div>

    <!-- Cercador -->
    <div class="cerca-container">
        <input type="search" data-filtre-taula="taula-parceles" placeholder="Cerca per nom o cultiu..."
            class="input-cerca" aria-label="Cercar parcel·les">
    </div>

    <!-- Taula de parcel·les -->
    <table class="taula-simple" id="taula-parceles">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom de la Parcel·la</th>
                <th>Cultiu Actual</th>
                <th>Superfície</th>
                <th>Any Plantació</th>
                <th>Risc Pendent</th>
                <th>Estat</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($llistat_parceles)): ?>
                <tr>
                    <td colspan="8" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha parcel·les registrades.
                        <a href="<?= BASE_URL ?>modules/parceles/nova_parcela.php">Afegeix-ne una.</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($llistat_parceles as $p):
                    $risc = obtenirNivellRisc($p['pendent']);
                    $estat = $p['estat'] ?? 'Sense plantar';
                    ?>
                    <tr>
                        <td><?= (int) $p['id_parcela'] ?></td>
                        <td data-cerca><?= e($p['nom']) ?></td>
                        <td data-cerca>
                            <?= $p['cultiu']
                                ? e($p['cultiu'])
                                : '<em class="text-suau">Sense plantar</em>'
                                ?>
                        </td>
                        <td><?= format_ha((float) $p['superficie_ha']) ?></td>
                        <td><?= $p['any_plantacio'] ? (int) $p['any_plantacio'] : '—' ?></td>
                        <td>
                            <span class="badge badge--<?= strtolower($risc) ?>">
                                <?= e($risc) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= classeEstat($estat) ?>">
                                <?= e($estat) ?>
                            </span>
                        </td>
                        <td class="cel-accions">
                            <a href="<?= BASE_URL ?>modules/parceles/detall_parcela.php?id=<?= (int) $p['id_parcela'] ?>"
                                title="Veure detall agronòmic" class="btn-accio btn-accio--veure">
                                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                            </a>
                            <a href="<?= BASE_URL ?>modules/parceles/editar_parcela.php?id=<?= (int) $p['id_parcela'] ?>"
                                title="Editar parcel·la" class="btn-accio btn-accio--editar">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div><!-- /.contingut-parceles -->

<!-- Dades per al mapa (escapament complet per evitar XSS) -->
<script>
    const PARCELES_DATA = <?= json_encode(
        array_map(fn($p) => [
            'id' => (int) $p['id_parcela'],
            'nom' => $p['nom'],
            'superficie_ha' => (float) $p['superficie_ha'],
            'cultiu' => $p['cultiu'] ?? null,
            'estat' => $p['estat'] ?? 'Sense plantar',
            'geojson' => $p['geojson'] ? json_decode($p['geojson']) : null,
        ], $llistat_parceles),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    ) ?>;
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const map = L.map('mapa-parceles').setView([41.6167, 0.6222], 12);

        // Capa satèl·lit (adequada per a ús agrícola)
        L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                attribution: 'Tiles &copy; Esri &mdash; Esri, USDA, USGS, AEX, GeoEye',
                maxZoom: 20
            }
        ).addTo(map);

        const colors = {
            'Producció': '#27ae60',
            'En preparació': '#f39c12',
            'Sense plantar': '#e74c3c',
        };

        const grup = L.featureGroup().addTo(map);

        PARCELES_DATA.forEach(parcela => {
            if (!parcela.geojson) return;

            const color = colors[parcela.estat] ?? '#7f8c8d';

            const capa = L.geoJSON(parcela.geojson, {
                style: { color, weight: 3, opacity: 0.9, fillOpacity: 0.35 }
            });

            // Popup amb dades bàsiques (text escapat per evitar XSS)
            const nom = document.createTextNode(parcela.nom).textContent;
            const cultiu = parcela.cultiu
                ? document.createTextNode(parcela.cultiu).textContent
                : 'Sense plantar';
            const sup = parseFloat(parcela.superficie_ha).toLocaleString('ca-ES', { minimumFractionDigits: 2 }) + ' ha';

            capa.bindPopup(
                `<strong>${nom}</strong><br>
             Superfície: ${sup}<br>
             Cultiu: ${cultiu}<br>
             Estat: ${document.createTextNode(parcela.estat).textContent}`
            );

            grup.addLayer(capa);
        });

        if (grup.getLayers().length > 0) {
            map.fitBounds(grup.getBounds(), { padding: [20, 20] });
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>