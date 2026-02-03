<?php
// seguiment_anual.php - Pàgina de Planificació Agronòmica i Seguiment Anual (Versió amb Parcel·les)

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA SIMULANT LA BASE DE DADES JERÀRQUICA ---

// Estructura: Sector_ID => Dades del Sector
$explotacio = [
    'S_GALA' => [
        'nom' => 'Sector Gala',
        'cultiu' => 'Poma Gala',
        'parceles' => [
            'P01' => ['nom' => 'P01 - Gala Jove', 'superficie' => 5.2, 'dades' => [
                'fenologia' => ['Brotació' => '2025-03-15', 'Floració' => '2025-04-10', 'Collita Inici' => '2025-08-28'],
                'produccio_actual' => ['Producció Total (Kg)' => 35700.0, 'Qualitat Mitjana (mm)' => '75-80 mm', 'Coloració (escala)' => '85%', 'Pèrdues Post-collita (%)' => 2.5],
                'produccio_historica' => [2023 => 34500, 2024 => 38100, 2025 => 35700],
            ]],
            'P02' => ['nom' => 'P02 - Gala Vella', 'superficie' => 2.9, 'dades' => [
                'fenologia' => ['Brotació' => '2025-03-18', 'Floració' => '2025-04-12', 'Collita Inici' => '2025-08-30'],
                'produccio_actual' => ['Producció Total (Kg)' => 20500.0, 'Qualitat Mitjana (mm)' => '80-85 mm', 'Coloració (escala)' => '92%', 'Pèrdues Post-collita (%)' => 1.9],
                'produccio_historica' => [2023 => 22000, 2024 => 20800, 2025 => 20500],
            ]],
        ],
    ],
    'S_PERA' => [
        'nom' => 'Sector Pera Ercolini',
        'cultiu' => 'Pera Ercolini',
        'parceles' => [
            'P03' => ['nom' => 'P03 - Ercolini 1', 'superficie' => 8.1, 'dades' => [
                'fenologia' => ['Brotació' => '2025-03-22', 'Floració' => '2025-04-15', 'Collita Inici' => '2025-08-10'],
                'produccio_actual' => ['Producció Total (Kg)' => 65000.0, 'Qualitat Mitjana (mm)' => '60-70 mm', 'Coloració (escala)' => '70%', 'Pèrdues Post-collita (%)' => 3.1],
                'produccio_historica' => [2023 => 60000, 2024 => 68000, 2025 => 65000],
            ]],
            // P04 no té dades per simular el cas de parcel·la nova
            'P04' => ['nom' => 'P04 - Ercolini Nova', 'superficie' => 4.0, 'dades' => []], 
        ],
    ],
];

// --- LÒGICA DE SELECCIÓ I CÀRREGA DE DADES (Sense canvis) ---

$any_actual = date('Y');

// 1. Determinar el Sector i la Parcel·la seleccionats
$sector_id_seleccionat = $_GET['sector_id'] ?? array_key_first($explotacio);
$parcela_id_seleccionada = $_GET['parcela_id'] ?? ''; 

// Validació de Sector
if (!isset($explotacio[$sector_id_seleccionat])) {
    $sector_id_seleccionat = array_key_first($explotacio);
}

$sector_actual = $explotacio[$sector_id_seleccionat];
$dades_a_mostrar = null;

// 2. Càrrega de dades segons la selecció (Parcel·la o Sector Agregat)
if (!empty($parcela_id_seleccionada) && isset($sector_actual['parceles'][$parcela_id_seleccionada])) {
    // Cas 2A: S'ha seleccionat una Parcel·la específica
    $unitat_seleccio = $sector_actual['parceles'][$parcela_id_seleccionada];
    $nom_visualitzacio = $unitat_seleccio['nom'];
    $superficie_ha = $unitat_seleccio['superficie'];
    $dades_a_mostrar = $unitat_seleccio['dades'];
    $tipus_seleccio = 'Parcel·la';

} else {
    // Cas 2B: S'ha seleccionat només el Sector (Agregat)
    
    $total_superficie = 0;
    $produccio_historica_agregada = [];
    
    foreach ($sector_actual['parceles'] as $id => $parcela) {
        $total_superficie += $parcela['superficie'];
        $prod_hist = $parcela['dades']['produccio_historica'] ?? [];

        foreach ($prod_hist as $any => $produccio) {
            $produccio_historica_agregada[$any] = ($produccio_historica_agregada[$any] ?? 0) + $produccio;
        }
    }

    $nom_visualitzacio = $sector_actual['nom'];
    $superficie_ha = $total_superficie;
    $tipus_seleccio = 'Sector (Agregat)';
    
    $primera_parcela_dades = array_values($sector_actual['parceles'])[0]['dades'] ?? [];
    
    if (isset($primera_parcela_dades['produccio_actual'])) {
        $dades_a_mostrar['produccio_actual'] = $primera_parcela_dades['produccio_actual'];
        $dades_a_mostrar['produccio_actual']['Producció Total (Kg)'] = array_sum($produccio_historica_agregada); 
    }
    $dades_a_mostrar['fenologia'] = $primera_parcela_dades['fenologia'] ?? [];
    $dades_a_mostrar['produccio_historica'] = $produccio_historica_agregada;
}

// 3. Extracció de variables finals
$cultiu_sector = $sector_actual['cultiu'];
$fenologia_actual = $dades_a_mostrar['fenologia'] ?? [];
$produccio_historica = $dades_a_mostrar['produccio_historica'] ?? [];
$produccio_actual = $dades_a_mostrar['produccio_actual'] ?? [];

$produccio_total_kg = $produccio_actual['Producció Total (Kg)'] ?? 0;
$rendiment_kg_ha = ($superficie_ha > 0) ? ($produccio_total_kg / $superficie_ha) : 0;
$produccio_actual['Rendiment (Kg/ha)'] = $rendiment_kg_ha; 

$hi_ha_dades = !empty($produccio_historica);

// Dades per al Chart.js
$labels_produccio = array_keys($produccio_historica);
$data_produccio = array_values($produccio_historica);

$labels_json = json_encode($labels_produccio);
$data_json = json_encode($data_produccio);

// -----------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Seguiment Anual: <?= htmlspecialchars($nom_visualitzacio); ?></title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style>
        /* (Mantenim les variables de color per brevetat) */
        :root {
            --color-principal: #1E4620;
            --color-secundari: #4CAF50;
            --color-accent-taronja: #FF9800;
            --color-accent-blau: #3498db;
            --color-card-fons: white;
            --color-text-fosc: #333;
            --color-footer-fosc: rgba(30, 70, 32, 0.9);
            --color-footer-text: #ddd;
            --footer-height: 250px;
            --header-height: 80px; /* Alçada de la capçalera (ajustar si cal) */
        }

        /* ************* FIX: CAPÇALERA AMB POSITION FIXED ************* */
        .capçalera-app {
            /* Assumim que aquest element té una posició fixa per la vostra descripció */
            position: fixed; 
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000; /* Assegura que estigui per sobre de tot */
            /* Afegiu aquí el vostre estil de fons de la capçalera si no és a estils.css */
            background-color: var(--color-principal); 
        }

        /* ------------------- ESTILS DEL CONTINGUT PRINCIPAL ------------------- */
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #333;
            background-image: url('fons_sectors.jpg');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
            color: #ddd;
        }

        /* *** PADDING-TOP AJUSTAT PER COMPENSAR EL HEADER FIX *** */
        main.contingut-seguiment {
            flex-grow: 1;
            max-width: 1400px;
            margin: 0 auto;
            /* Suma de l'alçada del header (80px) + marge (30px) */
            padding: calc(var(--header-height) + 30px) 40px 40px 40px; 
            margin-bottom: var(--footer-height);
            background-color: transparent;
        }

        /* ------------------- ESTILS DEL SELECTOR (MOGUT A DINS DEL MAIN) ------------------- */
        .selector-sector {
            /* Ja no necessita max-width ni margin: 0 auto, ja ho fa el main */
            padding: 0 0 20px 0; /* Espai inferior */
            display: flex;
            justify-content: flex-end;
            align-items: center;
            /* Afegim el fons clar per fer-lo destacar si el fons és fosc */
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .selector-sector label {
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        
        /* (Mantenim els estils de select i button) */
        .selector-sector select {
            padding: 8px 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: white;
            font-size: 1em;
            cursor: pointer;
            min-width: 150px;
            margin-right: 15px;
        }

        .selector-sector button {
            padding: 8px 15px;
            background-color: var(--color-accent-taronja);
            color: white;
            border: none;
            border-radius: 5px;
            margin-left: 10px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        /* ------------------- RESTA D'ESTILS ------------------- */
        .títol-pàgina {
            margin-bottom: 10px;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            color: var(--color-principal, #1E4620);
            text-align: center;
        }

        .quadricula-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        /* Media query per a mòbils */
        @media (max-width: 900px) {
            :root { --footer-height: 350px; }
            .quadricula-info { grid-template-columns: 1fr; }
            .selector-sector { 
                justify-content: center; 
                flex-wrap: wrap;
                padding-bottom: 30px; /* Més espai inferior per a mòbils */
            }
            .selector-sector select { margin-bottom: 10px; margin-right: 0; }
            .selector-sector button { margin-left: 0; }
        }
    </style>
</head>

<body>
    <header class="capçalera-app">
        <div class="logo">
            <img src="LogoAppRetallatSenseNom.png" alt="Logo de CultiuConnect" class="logo-imatge">
            CultiuConnect
        </div>
        <nav class="navegacio-principal">
            <ul>
                <li><a href="index.html"><i class="fas fa-house"></i> Panell</a></li>
                <li><a href="operacio_nova.php"><i class="fas fa-spray-can-sparkles"></i> Nou Tractament</a></li>
                <li><a href="sectors.php"><i class="fas fa-tree"></i> Sectors</a></li>
                <li><a href="quadern.php"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
                <li class="actiu"><a href="#"><i class="fas fa-calendar-check"></i> Seguiment Anual</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-seguiment">
        
        <div class="selector-sector">
            <form method="GET" action="seguiment_anual.php" id="form-selector">
                <label for="sector_select"><i class="fas fa-map-marked-alt"></i> Sector:</label>
                <select id="sector_select" name="sector_id">
                    <?php foreach ($explotacio as $id => $sector): ?>
                        <option value="<?= htmlspecialchars($id); ?>"
                            <?= ($id === $sector_id_seleccionat) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($sector['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="parcela_select"><i class="fas fa-tree"></i> Parcel·la:</label>
                <select id="parcela_select" name="parcela_id">
                    <option value="">-- Tot el Sector (Agregat) --</option>
                    <?php foreach ($sector_actual['parceles'] as $id => $parcela): ?>
                        <option value="<?= htmlspecialchars($id); ?>"
                            <?= ($id === $parcela_id_seleccionada) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($parcela['nom']) . " (" . number_format($parcela['superficie'], 2) . " ha)"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fas fa-eye"></i> Visualitzar</button>
            </form>
        </div>
        <h1 class="títol-pàgina" style="margin-bottom: 10px;">
            <i class="fas fa-seedling"></i>
            Seguiment Anual de **<?= htmlspecialchars($nom_visualitzacio); ?>**
        </h1>
        <p style="margin-bottom: 30px;">
            Dades de **<?= htmlspecialchars($tipus_seleccio); ?>** agregades de la campanya <?= $any_actual; ?>.
            (Cultiu: **<?= htmlspecialchars($cultiu_sector); ?>** | Superfície Total: **<?= number_format($superficie_ha, 2); ?> ha**)
        </p>

        <?php if (!$hi_ha_dades || empty($produccio_historica)): ?>
            <div class="alerta-sense-dades">
                <i class="fas fa-triangle-exclamation"></i>
                No s'han trobat dades de seguiment i producció per a **<?= htmlspecialchars($nom_visualitzacio); ?>**.
                Si us plau, seleccioneu una altra unitat o registreu les dades.
            </div>
        <?php else: ?>
            <div class="quadricula-info">
                <div class="panell-info">
                    <h2 style="margin-bottom: 15px;"><i class="fas fa-calendar-day"></i> FENOLOGIA CLAU (Campanya
                        <?= $any_actual; ?>)</h2>
                    <table class="taula-dades">
                        <?php foreach ($fenologia_actual as $fase => $data): ?>
                            <tr><th><?= htmlspecialchars($fase); ?></th><td style="color: var(--color-accent-taronja);"><?= date('d/m/Y', strtotime($data)); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                    <p style="margin-top: 15px; font-size: 0.8em;">Aquestes dates determinen la planificació d'aplicació de tractaments.</p>
                </div>

                <div class="panell-info">
                    <h2 style="margin-bottom: 15px;"><i class="fas fa-boxes-stacked"></i> PRODUCCIÓ I RENDIMENT</h2>
                    <table class="taula-dades">
                        <?php foreach ($produccio_actual as $dada => $valor): ?>
                            <tr><th><?= htmlspecialchars($dada); ?></th><td style="color: var(--color-secundari);"><?= is_numeric($valor) ? number_format($valor, 2) : htmlspecialchars($valor); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                    <p style="margin-top: 15px; font-size: 0.8em;">Rendiment basat en la superfície registrada de **<?= htmlspecialchars($nom_visualitzacio); ?>** (<?= number_format($superficie_ha, 2); ?> ha).</p>
                </div>
            </div>

            <div class="panell-info panell-comparativa" style="grid-column: 1 / span 2;">
                <h2 style="margin-bottom: 25px;"><i class="fas fa-chart-bar"></i> COMPARATIVA DE PRODUCCIÓ HISTÒRICA (Kg)
                </h2>

                <div style="max-width: 100%; margin: 0 auto;">
                    <canvas id="produccioChart"></canvas>
                </div>

                <p style="margin-top: 15px; font-size: 0.85em;">
                    Aquesta anàlisi ajuda a identificar tendències i correlacionar la producció amb la climatologia o els
                    tractaments aplicats.
                </p>
            </div>
        <?php endif; ?>

    </main>

    <footer class="peu-app">
        <div class="contingut-footer">
            <div class="columna-footer info-app">
                <h4 style="color: var(--color-secundari);">CultiuConnect</h4>
                <p>Eina de gestió agronòmica per a una agricultura més eficient i sostenible.</p>
                <p>&copy; 2025 Tots els drets reservats.</p>
            </div>

            <div class="columna-footer legal-ajuda">
                <h4>Ajuda i Legal</h4>
                <ul>
                    <li><a href="contacte.php">Contacte</a></li>
                    <li><a href="privacitat.php">Política de Privacitat</a></li>
                    <li><a href="termes.php">Termes d'Ús</a></li>
                </ul>
            </div>

            <div class="columna-footer contacte-social">
                <h4>Contacte</h4>
                <p><i class="fas fa-envelope"></i> info@cultiuconnect.cat</p>
                <div class="social-links">
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" title="TikTok"><i class="fab fa-tiktok"></i></a>
                    <a href="#" title="Twitter (X)"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <?php if ($hi_ha_dades && !empty($produccio_historica)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Dades extretes de PHP
                const labels = <?= $labels_json; ?>;
                const dataValues = <?= $data_json; ?>;
                const anyActual = <?= $any_actual; ?>;

                // Creació dels colors de les barres: L'any actual serà diferent
                const barColors = dataValues.map((value, index) => {
                    if (labels[index] == anyActual) {
                        return 'rgba(76, 175, 80, 0.8)'; // Color actual (Verd)
                    } else {
                        return 'rgba(52, 152, 219, 0.6)'; // Color històric (Blau)
                    }
                });

                const ctx = document.getElementById('produccioChart').getContext('2d');
                
                // Opció més robusta per forçar el fons blanc al canvas directament per sobreescriure possibles transparències
                ctx.canvas.style.backgroundColor = 'white'; 

                const produccioChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Producció Total (Kg)',
                            data: dataValues,
                            backgroundColor: barColors,
                            borderColor: barColors.map(color => color.replace('0.6', '1').replace('0.8', '1')),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            title: {
                                display: true,
                                text: 'Producció Anual (Kg)',
                                font: { size: 16 },
                                color: '#333'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        if (context.parsed.y !== null) {
                                            label += new Intl.NumberFormat('ca-ES').format(context.parsed.y) + ' Kg';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Quilograms (Kg)', color: '#555' }
                            },
                            x: {
                                title: { display: true, text: 'Any de Collita', color: '#555' }
                            }
                        }
                    }
                });
            });
        </script>
    <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sectorSelect = document.getElementById('sector_select');
            
            sectorSelect.onchange = function() {
                // Quan es canvia el sector, es neteja la parcel·la per forçar la vista agregada
                document.getElementById('parcela_select').value = ''; 
                // Envia el formulari per recarregar la pàgina amb el nou Sector
                document.getElementById('form-selector').submit();
            };
        });
    </script>
</body>

</html>