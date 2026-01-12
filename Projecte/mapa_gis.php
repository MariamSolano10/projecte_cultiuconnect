<?php
// mapa_gis.php → Versió amb creació de noves parcel·les (Leaflet-Geoman)

require_once 'db_connect.php';

try {
    $pdo = connectDB();
} catch (Exception $e) {
    die("No s'ha pogut connectar a la base de dades: " . $e->getMessage());
}

// Consulta amb totes les dades reals
$sql = "
SELECT 
    s.id_sector,
    s.nom AS nom_sector,
    s.descripcio,
    v.nom_varietat,
    p.data_plantacio,
    p.marc_fila,
    p.marc_arbre,
    COALESCE(ROUND(ps.superficie_m2 / 10000, 2), 0) AS superficie_ha,
    ST_AsGeoJSON(s.coordenades_geo) AS geojson
FROM Sector s
LEFT JOIN Plantacio p ON p.id_sector = s.id_sector AND p.data_arrencada IS NULL
LEFT JOIN Varietat v ON v.id_varietat = p.id_varietat
LEFT JOIN Parcela_Sector ps ON ps.id_sector = s.id_sector
WHERE s.coordenades_geo IS NOT NULL
ORDER BY s.nom";

$stmt = $pdo->query($sql);
$sectors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parcel·les CultiuConnect</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Leaflet-Geoman CSS (per dibuixar) -->
    <link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css" />
    
    <style>
        body { font-family: 'Segoe UI', sans-serif; background:#f4f4f9; padding:20px; margin:0; }
        h1 { text-align:center; color:#2c3e50; margin-bottom:25px; font-size:28px; }
        #map { height:680px; width:100%; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.18); }
        .pm-icon-draw { background-color: #27ae60 !important; }
    </style>
</head>
<body>

<h1>Parcel·les CultiuConnect</h1>
<div id="map"></div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Leaflet-Geoman JS -->
<script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const map = L.map('map').setView([41.5145, 0.857], 16);

    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri', maxZoom: 20
    }).addTo(map);

    const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    });

    L.control.layers({"Satèl·lit": satellite, "Mapa Normal": streets}, {}, {position:'topright'}).addTo(map);

    // === CONFIGURACIÓ DE DIBUIX AMB LEAFLET-GEOMAN ===
    map.pm.addControls({
        position: 'topleft',
        drawMarker: false,
        drawCircleMarker: false,
        drawPolyline: false,
        drawRectangle: false,
        drawCircle: false,
        cutPolygon: false,
        editMode: false,
        dragMode: false,
        removalMode: false,
        rotateMode: false
    });

    // Estil global dels polígons dibuixats
    map.pm.setGlobalOptions({
        pathOptions: {
            color: '#e74c3c',
            weight: 4,
            fillOpacity: 0.2
        },
        snappable: true,
        snapDistance: 20
    });

    // Quan l'usuari acaba de dibuixar un polígon
    map.on('pm:create', function(e) {
        const layer = e.layer;
        const geojson = layer.toGeoJSON();

        const popupContent = `
            <div style="min-width:200px;">
                <b>Nova parcel·la</b><br><br>
                <label>Nom: <input type="text" id="nom_sector" style="width:100%; padding:4px;" required /></label><br><br>
                <button id="guardar_parcela" style="background:#27ae60; color:white; padding:6px 12px; border:none; cursor:pointer;">Guardar</button>
                <button id="cancelar_parcela" style="margin-left:8px; padding:6px 12px; border:1px solid #999; background:#f9f9f9; cursor:pointer;">Cancel·lar</button>
            </div>
        `;

        layer.bindPopup(popupContent).openPopup();

        // Esperem que el popup es renderitzi per accedir als botons
        setTimeout(() => {
            document.getElementById('guardar_parcela')?.addEventListener('click', function() {
                const nom = document.getElementById('nom_sector').value.trim();
                if (!nom) {
                    alert("Has d'introduir un nom per la parcel·la!");
                    return;
                }

                fetch('guardar_parcela.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nom_sector: nom,
                        geojson: geojson.geometry
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Parcel·la guardada correctament!");
                        location.reload(); // Recarrega per veure la nova parcel·la
                    } else {
                        alert("Error: " + (data.error || "Desconegut"));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Error de connexió amb el servidor.");
                });
            });

            document.getElementById('cancelar_parcela')?.addEventListener('click', function() {
                map.removeLayer(layer);
                layer.closePopup();
            });
        }, 100);
    });
    // === FI DIBUIX ===

    // Carrega les parcel·les existents
    const colors = ["#e74c3c","#3498db","#2ecc71","#f39c12","#9b59b6","#1abc9c","#e67e22","#34495e"];
    const sectors = <?= json_encode($sectors, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
    let allPoints = [];

    sectors.forEach((sector, idx) => {
        if (!sector.geojson) return;

        const geo = JSON.parse(sector.geojson);
        const coords = geo.coordinates[0];
        const latlngs = coords.map(c => [c[1], c[0]]);
        const color = colors[idx % colors.length];

        L.polygon(latlngs, {
            color: color,
            weight: 4,
            fill: false,
            opacity: 1
        }).addTo(map)
          .bindPopup(`
            <b style="color:${color};font-size:16px;">${sector.nom_sector}</b><hr style="margin:6px 0">
            ${sector.nom_varietat ? 'Varietat: <b>' + sector.nom_varietat + '</b><br>' : ''}
            ${sector.data_plantacio ? 'Plantació: <b>' + sector.data_plantacio + '</b><br>' : ''}
            ${sector.superficie_ha > 0 ? 'Superfície: <b>' + sector.superficie_ha + ' ha</b><br>' : ''}
            ${sector.marc_fila && sector.marc_arbre ? 'Marc: <b>' + sector.marc_fila + ' × ' + sector.marc_arbre + ' m</b>' : ''}
          `);

        latlngs.forEach((latlng, i) => {
            L.marker(latlng, {
                icon: L.divIcon({
                    className: '',
                    html: `<div style="background:${color};width:12px;height:12px;border:2px solid white;border-radius:50%;box-shadow:0 0 8px #000;"></div>`,
                    iconSize: [16,16],
                    iconAnchor: [8,8]
                })
            }).addTo(map).bindPopup(`
                <b>${sector.nom_sector}</b><br>
                Punt ${i+1}<br>
                Lat ${latlng[0].toFixed(6)}, Lng ${latlng[1].toFixed(6)}
            `);
            allPoints.push(latlng);
        });
    });

    if (allPoints.length > 0) {
        map.fitBounds(L.latLngBounds(allPoints).pad(0.15));
    }

    setTimeout(() => map.invalidateSize(), 300);
});
</script>
</body>
</html>