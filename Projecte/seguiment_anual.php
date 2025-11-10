<?php
// seguiment_anual.php - Pàgina de Planificació Agronòmica i Seguiment Anual

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA (Simulació de dades de la Campanya Actual i Històrica) ---

$any_actual = date('Y');
$any_anterior = $any_actual - 1;
$sector_seleccionat = 'P01 - Gala Jove'; // Simulació de sector a seguir
$superficie_sector_ha = 5.2; // DADA DE SUPERFÍCIE SIMULADA (Obtinguda de sectors.php)

// 1. Dades de Seguiment Fenològic (Campanya Actual)
$fenologia_actual = [
    'Brotació' => '2025-03-15',
    'Floració' => '2025-04-10',
    'Quallat' => '2025-04-25',
    'Aclarida' => '2025-05-20',
    'Engreix de fruit' => '2025-07-01',
    'Collita Inici' => '2025-08-28', // Data clau
    'Collita Fi' => '2025-09-05',
];

// 2. Dades de Producció (Collita)
$produccio_total_kg = 35700.0;
$rendiment_kg_ha = $produccio_total_kg / $superficie_sector_ha; // CÀLCUL DINÀMIC

$produccio_actual = [
    'Producció Total (Kg)' => $produccio_total_kg,
    'Rendiment (Kg/ha)' => $rendiment_kg_ha,
    'Qualitat Mitjana (mm)' => '75-80 mm',
    'Coloració (escala)' => '85%',
    'Pèrdues Post-collita (%)' => 2.5
];

// 3. Comparativa Històrica (Simulació amb l'any anterior)
$produccio_historica = [
    $any_actual => 35700,
    $any_anterior => 38100,
    ($any_anterior - 1) => 34500,
];

// -----------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Seguiment Anual i Producció</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color (reforçades per consistència) */
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
            --color-footer-fosc: rgba(30, 70, 32, 0.9); /* Fons més opac per al footer */
            --color-footer-text: #ddd;
        }

        /* 1. ESTILS DEL FONS DE LA PÀGINA I CONTINGUT GENERAL - FIX PER A PEU DE PÀGINA */
        body {
            /* FIX: Flexbox per al peu de pàgina fix */
            min-height: 100vh;
            display: flex;
            flex-direction: column;

            /* Simulem el fons amb la imatge de camp */
            background-color: #333;
            background-image: url('fons_sectors.jpg');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
            color: #ddd;
            /* Color de text per defecte per a elements directes al main */
        }

        /* Contenidor Principal: Ajustat per l'efecte de fons d'imatge i Flexbox */
        main.contingut-seguiment {
            /* FIX: Creix per empènyer el footer cap avall */
            flex-grow: 1;

            max-width: 1400px;
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            /* Padding superior afegit */
            background-color: transparent;
        }

        /* Descripció del sector sobre el fons fosc */
        .contingut-seguiment p {
            color: #ccc;
        }

        /* 2. ESTIL DEL TÍTOL (Rectangular clar amb ombra) */
        .títol-pàgina {
            margin-bottom: 10px;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            color: var(--color-principal, #1E4620);
            /* Text fosc */
            text-align: center;
        }

        .títol-pàgina i {
            color: var(--color-principal) !important;
        }

        /* 3. PANELLS D'INFORMACIÓ (Fons Blanc Sòlid) */
        .quadricula-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .panell-info {
            /* Fons SÒLID BLANC */
            background-color: var(--color-card-fons);
            color: var(--color-text-fosc);
            /* Tot el text dins el panell és fosc */
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s;
        }

        .panell-info:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .panell-info h2 i {
            color: var(--color-principal);
            margin-right: 10px;
        }

        .panell-info p {
            color: #999 !important;
            /* Text de notes dins el panell */
        }

        /* 4. TAULA DE DADES DINS DELS PANELLS */
        .taula-dades {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .taula-dades th,
        .taula-dades td {
            padding: 10px 0;
            text-align: left;
            border-bottom: 1px dashed #eee;
        }

        .taula-dades th {
            width: 60%;
            font-weight: 500;
            color: #555;
            /* Etiquetes de les dades */
        }

        .taula-dades td {
            width: 40%;
            font-weight: bold;
            color: var(--color-principal);
            /* Valors de les dades (Fenologia i Producció) */
        }

        /* Estil per al panell de Comparativa */
        .panell-comparativa {
            border-left: 5px solid var(--color-accent-taronja);
            /* Línia d'accent */
        }

        .barra-comparativa {
            height: 25px;
            background-color: var(--color-accent-blau);
            margin-bottom: 8px;
            border-radius: 3px;
            color: white;
            line-height: 25px;
            padding-left: 10px;
            font-size: 0.9em;
            font-weight: bold;
            transition: width 0.5s;
        }

        /* 4. FOOTER (Sticky Footer) */*/
        .peu-app {
            position: relative;
            background-color: var(--color-footer-fosc);
            color: var(--color-footer-text);
            padding: 30px 0 15px 0;
            width: 100%;
            font-size: 0.9em;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.5);
            margin-top: auto;
        }

        .contingut-footer {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            /* 3 Columnes */
            gap: 30px;
            text-align: left;
        }

        .columna-footer h4 {
            color: white;
            margin-bottom: 15px;
            font-size: 1.1em;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 5px;
        }

        .columna-footer ul {
            list-style: none;
            padding: 0;
        }

        .columna-footer ul li {
            margin-bottom: 8px;
        }

        .columna-footer ul li a {
            color: var(--color-footer-text);
            text-decoration: none;
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .columna-footer ul li a:hover {
            color: var(--color-accent-taronja);
        }

        /* Estil d'enllaços socials */
        .social-links {
            margin-top: 15px;
            display: flex;
            gap: 15px;
        }

        .social-links a {
            color: white;
            font-size: 1.4em;
            transition: color 0.3s;
        }

        .social-links a:hover {
            color: var(--color-accent-blau);
        }

        /* Text final de drets d'autor */
        .info-app p:last-child {
            margin-top: 20px;
            font-size: 0.8em;
            color: #999;
        }

        /* Adaptació per a pantalles petites */
        @media (max-width: 900px) {
            .contingut-footer {
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }

            .info-app {
                grid-column: 1 / 3;
                /* Ocupa les dues columnes a tauleta */
                text-align: center;
            }

            .info-app h4,
            .info-app p {
                text-align: center;
            }
        }

        @media (max-width: 600px) {
            .contingut-footer {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .columna-footer h4 {
                border-bottom: none;
            }

            .social-links {
                justify-content: center;
            }

            .info-app {
                grid-column: 1 / 2;
            }
        }

        /* Altres estils existents */
        .boto-descarrega {
            display: block;
            margin-top: 15px;
            padding: 10px 15px;
            background-color: var(--color-secundari);
            color: white;
            text-align: center;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .boto-descarrega:hover {
            background-color: #449d48;
        }

        .boto-descarrega.pdf {
            background-color: #e74c3c;
        }

        .boto-descarrega.pdf:hover {
            background-color: #c0392b;
        }

        .boto-descarrega.excel {
            background-color: #27ae60;
        }

        .boto-descarrega.excel:hover {
            background-color: #229954;
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
        <h1 class="títol-pàgina" style="margin-bottom: 10px;">
            <i class="fas fa-seedling"></i>
            Seguiment Anual del Cicle de Cultiu
        </h1>
        <p style="margin-bottom: 30px;">
            Vista agregada i comparativa de la campanya <?= $any_actual; ?> per al sector:
            <span
                style="font-weight: bold; color: var(--color-accent-taronja);"><?= htmlspecialchars($sector_seleccionat); ?></span>
        </p>

        <div class="quadricula-info">
            <div class="panell-info">
                <h2 style="margin-bottom: 15px;"><i class="fas fa-calendar-day"></i> FENOLOGIA CLAU (Campanya
                    <?= $any_actual; ?>)</h2>

                <table class="taula-dades">
                    <?php foreach ($fenologia_actual as $fase => $data): ?>
                        <tr>
                            <th><?= htmlspecialchars($fase); ?></th>
                            <td style="color: var(--color-accent-taronja);"><?= date('d/m/Y', strtotime($data)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p style="margin-top: 15px; font-size: 0.8em;">
                    Aquestes dates determinen la planificació d'aplicació de tractaments.
                </p>
            </div>

            <div class="panell-info">
                <h2 style="margin-bottom: 15px;"><i class="fas fa-boxes-stacked"></i> PRODUCCIÓ I RENDIMENT</h2>

                <table class="taula-dades">
                    <?php foreach ($produccio_actual as $dada => $valor): ?>
                        <tr>
                            <th><?= htmlspecialchars($dada); ?></th>
                            <td style="color: var(--color-secundari);">
                                <?= is_numeric($valor) ? number_format($valor, 2) : htmlspecialchars($valor); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p style="margin-top: 15px; font-size: 0.8em;">
                    Rendiment basat en la superfície registrada del sector
                    (<?= number_format($superficie_sector_ha, 2); ?> ha).
                </p>
            </div>
        </div>

        <div class="panell-info panell-comparativa" style="grid-column: 1 / span 2;">
            <h2 style="margin-bottom: 25px;"><i class="fas fa-chart-bar"></i> COMPARATIVA DE PRODUCCIÓ (Kg/Sector)</h2>

            <?php
            $max_produccio = max($produccio_historica);
            foreach ($produccio_historica as $any => $produccio):
                // Càlcul de l'amplada relativa per al gràfic de barres (màxim 100%)
                $amplada_barra = ($produccio / $max_produccio) * 100;
                ?>
                <div style="margin-bottom: 15px;">
                    <div style="font-size: 0.9em; font-weight: bold; margin-bottom: 5px; color: #555;">
                        <?= $any; ?> (<?= number_format($produccio, 0); ?> Kg)
                    </div>
                    <!-- La barra d'aquest any és més verda/diferent -->
                    <div class="barra-comparativa"
                        style="width: <?= $amplada_barra; ?>%; 
                                background-color: <?= ($any == $any_actual) ? 'var(--color-secundari)' : 'var(--color-accent-blau)'; ?>;">
                    </div>
                </div>
            <?php endforeach; ?>

            <p style="margin-top: 15px; font-size: 0.85em;">
                Aquesta anàlisi ajuda a identificar tendències i correlacionar la producció amb la climatologia o els
                tractaments aplicats.
            </p>
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
