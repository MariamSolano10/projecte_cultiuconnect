<?php
// nou_sector.php - Formulari de registre d'una nova parcel·la (sector)

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// Inicialització de missatges
$missatge_estat = '';
$error_connexio = null;

// --- DADES PER ALS DROPDOWNS (Simulació de consulta a la BD) ---
$dades_select = [
    'varietats' => [], // De la taula Varietat
    'patrons' => [],   // De la taula Patro
    'tipus_sol' => ['Franc', 'Argilós', 'Calcari', 'Sorra'], // De la taula TipusSol
];

try {
    $pdo = connectDB();

    // 1. Càrrega de Varietats
    $stmt_v = $pdo->query("SELECT id_varietat, nom_varietat FROM Varietat ORDER BY nom_varietat");
    $dades_select['varietats'] = $stmt_v->fetchAll(PDO::FETCH_KEY_PAIR); // [ID => Nom]

    // 2. Càrrega de Patrons (Patró és la base de l'arbre)
    $stmt_p = $pdo->query("SELECT id_patro, nom_patro FROM Patro ORDER BY nom_patro");
    $dades_select['patrons'] = $stmt_p->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (Exception $e) {
    $error_connexio = "Error en carregar dades de selecció: " . htmlspecialchars($e->getMessage());
}


// --- LÒGICA DE PROCESSAMENT DEL FORMULARI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error_connexio) {
    // 1. Validació i sanejament bàsic
    $id_sector = htmlspecialchars(trim($_POST['id_sector']));
    $superficie = filter_var($_POST['superficie'], FILTER_VALIDATE_FLOAT);
    $varietat_id = filter_var($_POST['varietat_id'], FILTER_VALIDATE_INT);
    $patro_id = filter_var($_POST['patro_id'], FILTER_VALIDATE_INT);
    $any_plantacio = filter_var($_POST['any_plantacio'], FILTER_VALIDATE_INT);
    $tipus_sol = htmlspecialchars(trim($_POST['tipus_sol']));
    $coordenades_geojson = trim($_POST['coordenades_geojson']); // Cadena GeoJSON/WKT

    // 2. Comprovació de camps obligatoris
    if (empty($id_sector) || $superficie === false || $varietat_id === false || $patro_id === false || $any_plantacio === false || empty($coordenades_geojson)) {
        $missatge_estat = "<div class='alerta alerta-error'><i class='fas fa-times-circle'></i> Si us plau, omple tots els camps obligatoris correctament.</div>";
    } else {
        try {
            // 3. Inserció a la taula Sector
            $sql_insert = "INSERT INTO Sector (
                id_sector_client, 
                superficie_ha, 
                id_varietat, 
                id_patro, 
                any_plantacio, 
                tipus_sol,
                coordenades_geo
            ) VALUES (?, ?, ?, ?, ?, ?, ST_GeomFromText(?))"; // Assumint funció espacial per GeoJSON/WKT

            $stmt_insert = $pdo->prepare($sql_insert);
            
            // Simulem que la BD accepta WKT o GeoJSON en el camp de coordenades
            $stmt_insert->execute([
                $id_sector, 
                $superficie, 
                $varietat_id, 
                $patro_id, 
                $any_plantacio, 
                $tipus_sol,
                $coordenades_geojson
            ]);

            $missatge_estat = "<div class='alerta alerta-exit'><i class='fas fa-check-circle'></i> Sector **" . $id_sector . "** registrat amb èxit! Pots veure'l a <a href='sectors.php'>Sectors</a>.</div>";

            // Netejar les dades del formulari després de l'èxit si es vol
            $_POST = []; 

        } catch (PDOException $e) {
            // Error específic de la BD (p. ex. ID duplicat)
            $missatge_estat = "<div class='alerta alerta-error'><i class='fas fa-times-circle'></i> Error de base de dades: El sector potser ja existeix o hi ha un problema amb les dades introduïdes.</div>";
            // Per debug: $missatge_estat .= "<p>" . $e->getMessage() . "</p>"; 
        }
    }
}
// -----------------------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Nou Sector</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Variables de color de CultiuConnect (les mantindrem consistents) */
        :root {
            --color-principal: #1E4620;
            /* Verd Fosc */
            --color-secundari: #4CAF50;
            /* Verd Mitjà */
            --color-accent-taronja: #FF9800;
            /* Taronja Accent */
            --color-accent-blau: #3498db;
            /* Blau Accent */
            --color-card-fons: white;
            /* Fons de panell/targeta (BLANC) */
            --color-text-fosc: #333;
            /* NOVES VARIABLES DEL FOOTER */
            --color-footer-fosc: rgba(30, 70, 32, 0.9);
            /* Fons més opac per al footer */
            --color-footer-text: #ddd;
        }

        /* Estil de la pàgina */
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #f4f4f4;
            color: var(--color-text-fosc);
        }

        main.contingut-formulari {
            flex-grow: 1;
            max-width: 900px;
            margin: 105px auto 40px auto;
            /* Margin superior per deixar espai al header fix */
            padding: 20px;
            width: 100%;
        }

        .títol-pàgina {
            padding: 15px;
            background-color: var(--color-principal);
            color: white;
            border-radius: 5px 5px 0 0;
            text-align: center;
            margin-bottom: 0;
        }

        /* Contenidor del formulari */
        .panell-formulari {
            background-color: var(--color-card-fons);
            padding: 30px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .panell-formulari h2 {
            color: var(--color-principal);
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        /* Estils de camps */
        .grup-camp {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }

        .grup-camp label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
            display: block;
        }

        .grup-camp input[type="text"],
        .grup-camp input[type="number"],
        .grup-camp select,
        .grup-camp textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .grup-camp input:focus,
        .grup-camp select:focus,
        .grup-camp textarea:focus {
            border-color: var(--color-accent-blau);
            outline: none;
        }
        
        /* Secció de coordenades (es pot simular un camp de text gran per GeoJSON) */
        .grup-camp.geo textarea {
            min-height: 100px;
            font-family: monospace;
            font-size: 0.9em;
        }

        /* Botó d'enviament */
        .boto-enviar {
            display: block;
            width: 100%;
            padding: 15px;
            background-color: var(--color-secundari);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .boto-enviar:hover {
            background-color: #449d48;
        }

        /* Alertes */
        .alerta {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .alerta-error {
            background-color: #fdd;
            color: #c0392b;
            border: 1px solid #c0392b;
        }

        .alerta-exit {
            background-color: #ddf;
            color: var(--color-principal);
            border: 1px solid var(--color-principal);
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
                <li class="actiu"><a href="sectors.php"><i class="fas fa-tree"></i> Sectors</a></li>
                <li><a href="quadern.php"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
                <li><a href="seguiment_anual.php"><i class="fas fa-calendar-check"></i> Seguiment Anual</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-formulari">
        <h1 class="títol-pàgina">
            <i class="fas fa-map-marked-alt"></i> Registre de Nova Parcel·la / Sector
        </h1>

        <div class="panell-formulari">
            <?= $missatge_estat; ?>
            <?php if ($error_connexio) : ?>
                <div class='alerta alerta-error'><?= $error_connexio; ?></div>
            <?php endif; ?>

            <form method="POST" action="nou_sector.php">
                <h2>1. Identificació i Dades Agronòmiques</h2>
                <div class="grup-camp">
                    <label for="id_sector">Identificador Únic del Sector *</label>
                    <input type="text" id="id_sector" name="id_sector" placeholder="Ex: P05_Gala_Nord"
                        value="<?= $_POST['id_sector'] ?? ''; ?>" required>
                </div>

                <div class="grup-camp">
                    <label for="superficie">Superfície (Ha) *</label>
                    <input type="number" id="superficie" name="superficie" step="0.01" min="0.01"
                        placeholder="Ex: 3.5" value="<?= $_POST['superficie'] ?? ''; ?>" required>
                </div>

                <div class="grup-camp">
                    <label for="varietat_id">Varietat Cultivada *</label>
                    <select id="varietat_id" name="varietat_id" required>
                        <option value="">Selecciona la varietat...</option>
                        <?php foreach ($dades_select['varietats'] as $id => $nom) : ?>
                            <option value="<?= $id; ?>" <?= (isset($_POST['varietat_id']) && $_POST['varietat_id'] == $id) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($nom); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grup-camp">
                    <label for="patro_id">Patró (Base de l'arbre) *</label>
                    <select id="patro_id" name="patro_id" required>
                        <option value="">Selecciona el patró...</option>
                        <?php foreach ($dades_select['patrons'] as $id => $nom) : ?>
                            <option value="<?= $id; ?>" <?= (isset($_POST['patro_id']) && $_POST['patro_id'] == $id) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($nom); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grup-camp">
                    <label for="any_plantacio">Any de Plantació *</label>
                    <input type="number" id="any_plantacio" name="any_plantacio" min="1950" max="<?= date('Y'); ?>"
                        placeholder="<?= date('Y'); ?>" value="<?= $_POST['any_plantacio'] ?? date('Y'); ?>" required>
                </div>

                <div class="grup-camp">
                    <label for="tipus_sol">Tipus de Sòl *</label>
                    <select id="tipus_sol" name="tipus_sol" required>
                        <option value="">Selecciona el tipus de sòl...</option>
                        <?php foreach ($dades_select['tipus_sol'] as $sol) : ?>
                            <option value="<?= $sol; ?>" <?= (isset($_POST['tipus_sol']) && $_POST['tipus_sol'] == $sol) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($sol); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;">

                <h2>2. Informació Geoespacial (Cadastre Digital)</h2>
                <p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">
                    Per complir amb el cadastre digital, cal delimitar el perímetre. Normalment,
                    aquesta informació s'obtindria d'un mapa interactiu, però la introduirem manualment
                    en format GeoJSON o WKT.
                </p>

                <div class="grup-camp geo">
                    <label for="coordenades_geojson">Coordenades Geoespacials (GeoJSON o WKT) *</label>
                    <textarea id="coordenades_geojson" name="coordenades_geojson" 
                        placeholder="Ex: POLYGON ((lon1 lat1, lon2 lat2, ...)) o un objecte GeoJSON complet."
                        required><?= $_POST['coordenades_geojson'] ?? ''; ?></textarea>
                </div>
                
                <input type="submit" value="Guardar Sector i Anar al Mapa" class="boto-enviar">
            </form>
        </div>
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
    <script src="scripts.js"></script>
</body>

</html>