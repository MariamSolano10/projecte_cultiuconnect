<?php
// informes.php - Pàgina d'Anàlisi i Informes (Presa de Decisions)

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA (Simulació de dades per a l'anàlisi) ---

// 1. Dades per al GRÀFIC de Consum de Productes Fitosanitaris (darrers 12 mesos)
$consum_mensual = [
    'Oct-24' => 120.5,
    'Nov-24' => 85.0,
    'Des-24' => 15.0,
    'Gen-25' => 10.0,
    'Feb-25' => 45.0,
    'Mar-25' => 180.0,
    'Abr-25' => 250.0,
    'Mai-25' => 320.0,
    'Jun-25' => 190.0,
    'Jul-25' => 95.0,
    'Ago-25' => 50.0,
    'Set-25' => 110.0,
];

// 2. Taula de RESUM DE COSTOS PER SECTOR
// (Això es calcularia agregant tots els consums i preus de lot per sector)
$resum_costos_sectors = [
    ['sector' => 'Parcel·la Est (Pomes Gala)', 'superficie_ha' => 10.5, 'cost_total' => 4500.50, 'cost_ha' => 428.62],
    ['sector' => 'Parcel·la Oest (Pomes Fuji)', 'superficie_ha' => 8.0, 'cost_total' => 3100.00, 'cost_ha' => 387.50],
    ['sector' => 'Sector Nord (Peral)', 'superficie_ha' => 5.2, 'cost_total' => 2800.75, 'cost_ha' => 538.61],
];

// Càlculs resum
$cost_total_general = array_sum(array_column($resum_costos_sectors, 'cost_total'));
// -----------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Anàlisi i Informes</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color */
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
        }

        /* Contenidor Principal: Ajustat per l'efecte de fons d'imatge i Flexbox */
        main.contingut-informes {
            /* FIX: Creix per empènyer el footer cap avall */
            flex-grow: 1;

            max-width: 1400px;
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            /* Padding superior afegit */
            background-color: transparent;
            color: white;
        }

        /* Descripció */
        .contingut-informes p {
            color: #ccc !important;
        }

        /* 2. ESTIL DEL TÍTOL (Rectangular clar amb ombra) */
        .títol-pàgina {
            margin-bottom: 20px;
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

        /* 3. ESTILS DELS PANELLS (Panell Gràfic) - APLICACIÓ DE FONS BLANC SÒLID */
        .quadricula-informes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .panell-grafic {
            /* FIX: Fons Blanc Sòlid */
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            height: 450px;
            overflow: hidden;
            color: var(--color-text-fosc);
            /* Text principal fosc dins del panell */
        }

        .panell-grafic h2 {
            color: var(--color-text-fosc) !important;
        }

        /* Estils per a la simulació del gràfic */
        .simulacio-grafic {
            height: 300px;
            border-bottom: 2px solid #ccc;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 0 10px;
        }

        .barra-grafic {
            width: 5%;
            background-color: var(--color-accent-blau);
            margin: 0 5px;
            transition: height 0.5s ease-out;
            border-top-left-radius: 3px;
            border-top-right-radius: 3px;
            position: relative;
        }

        .barra-grafic span {
            position: absolute;
            top: -20px;
            font-size: 0.75em;
            color: var(--color-text-fosc);
            font-weight: bold;
            transform: translateX(-50%);
        }

        .etiquetes-x {
            display: flex;
            justify-content: space-between;
            padding: 5px 10px 0;
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
        }

        /* Estils per a la taula de resum */
        .taula-resum {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            /* El fons blanc ja ve del panell-grafic pare */
        }

        .taula-resum th,
        .taula-resum td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            color: var(--color-text-fosc);
        }

        .taula-resum th {
            /* Ús de color principal per a la capçalera de la taula */
            background-color: var(--color-principal);
            color: white;
            font-weight: 600;
        }

        .taula-resum tr:hover:not(.resum-total) {
            background-color: #f9f9f9;
        }

        .resum-total {
            font-weight: bold;
            background-color: var(--color-secundari);
            color: white;
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
                <li><a href="quadern.php"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
                <li><a href="#" class="actiu"><i class="fas fa-chart-line"></i> Anàlisi</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-informes">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-chart-line"></i>
            Anàlisi de Tractaments i Rendes
        </h1>
        <p style="margin-bottom: 30px;">Informes clau per avaluar l'eficiència dels tractaments, optimitzar costos i
            planificar la propera campanya.</p>

        <div class="quadricula-informes">

            <div class="panell-grafic">
                <h2 style="margin-bottom: 20px;">Consum de Producte Químic (Volum Total L/Kg)</h2>

                <div class="simulacio-grafic">
                    <?php
                    // Trobem el valor màxim per escalar el gràfic (300px d'alçada)
                    $max_consum = max($consum_mensual);
                    $altura_base = 300; // Alçada màxima del gràfic en px
                    
                    foreach ($consum_mensual as $mes => $consum):
                        // Càlcul de l'alçada relativa de la barra
                        $altura_barra = ($consum / $max_consum) * $altura_base;
                        // Format del text del consum
                        $consum_text = number_format($consum, 0);
                        ?>
                        <div class="barra-grafic" style="height: <?= $altura_barra; ?>px;"
                            title="<?= $mes; ?>: <?= $consum; ?> L/Kg">
                            <span style="left: 50%;"><?= $consum_text; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="etiquetes-x">
                    <?php
                    // Mostrar només l'etiqueta de cada mes
                    foreach (array_keys($consum_mensual) as $mes): ?>
                        <span style="width: 5%; text-align: center;"><?= $mes; ?></span>
                    <?php endforeach; ?>
                </div>

            </div>

            <div class="panell-grafic">
                <h2 style="margin-bottom: 20px;">Costos Agregats (Fitosanitaris i Fertilització) per Sector</h2>

                <table class="taula-resum">
                    <thead>
                        <tr>
                            <th>Sector</th>
                            <th>Superfície (ha)</th>
                            <th>Cost Total (€)</th>
                            <th>Cost Mitjà (€/ha)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resum_costos_sectors as $resum): ?>
                            <tr>
                                <td><?= htmlspecialchars($resum['sector']); ?></td>
                                <td><?= number_format($resum['superficie_ha'], 2) . ' ha'; ?></td>
                                <td><?= number_format($resum['cost_total'], 2) . ' €'; ?></td>
                                <td style="font-weight: bold; color: var(--color-accent-taronja);">
                                    <?= number_format($resum['cost_ha'], 2) . ' €'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="resum-total">
                            <td>TOTAL GENERAL</td>
                            <td>-</td>
                            <td><?= number_format($cost_total_general, 2) . ' €'; ?></td>
                            <td>-</td>
                        </tr>
                    </tbody>
                </table>
                <p style="margin-top: 15px; font-size: 0.9em; color: #999;">
                    * Costos calculats utilitzant el preu d'adquisició dels lots consumits a la base de dades.
                </p>
            </div>

        </div>

        <div style="margin-top: 40px; text-align: center;">
            <a href="generar_informe.php" class="boto-exportar"
                style="background-color: var(--color-secundari); color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                <i class="fas fa-download"></i> Generar Informe Detallat (Excel/PDF)
            </a>
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