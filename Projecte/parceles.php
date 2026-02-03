<?php
// parceles.php - Pàgina de Gestió Geoespacial i Llistat de Parcel·les/Sectors

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA (Simulació de dades que vindrien de la Base de Dades) ---
// En un entorn real, es faria un SELECT de les taules Parceles i Sectors
$llistat_parceles_sectors = [
    [
        'id' => 1,
        'nom' => 'Parcel·la P-01 (Est)',
        'cultiu' => 'Pomera - Varietat Gala',
        'superficie' => 5.2,
        'data_plantacio' => '2018',
        'estat' => 'Producció',
        'requeriment_risc' => 'Alt'
    ],
    [
        'id' => 2,
        'nom' => 'Parcel·la P-02 (Oest)',
        'cultiu' => 'Pomera - Varietat Fuji',
        'superficie' => 4.8,
        'data_plantacio' => '2019',
        'estat' => 'Producció',
        'requeriment_risc' => 'Mitjà'
    ],
    [
        'id' => 3,
        'nom' => 'Sector N-03 (Nord)',
        'cultiu' => 'Peral - Varietat Patrona',
        'superficie' => 3.5,
        'data_plantacio' => '2020',
        'estat' => 'Producció',
        'requeriment_risc' => 'Baix'
    ],
    [
        'id' => 4,
        'nom' => 'Parcel·la P-04 (Nova)',
        'cultiu' => 'Sense plantar',
        'superficie' => 2.0,
        'data_plantacio' => 'N/A',
        'estat' => 'En preparació',
        'requeriment_risc' => 'N/A'
    ],
];

// Lògica per obtenir la classe d'estat (simulació)
function obtenirClasseEstat($estat): string
{
    return match (strtolower($estat)) {
        'producció' => 'estat-ok',
        'en preparació' => 'estat-atencio',
        default => 'estat-normal',
    };
}
// -----------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Parcel·les i Sòl</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color (Assumint el verd fosc i taronja de la imatge) */
        :root {
            --color-principal: #1E4620;
            /* Verd Fosc */
            --color-secundari: #4CAF50;
            /* Verd Mitjà */
            --color-accent-taronja: #FF9800;
            /* Taronja Accent */
            --color-text-fosc: #333;
            /* NOVES VARIABLES DEL FOOTER */
            --color-footer-fosc: rgba(30, 70, 32, 0.9);
            /* Fons més opac per al footer */
            --color-footer-text: #ddd;
        }

        /* 1. CORRECCIÓ GENERAL DEL CONTINGUT PER MOSTRAR EL FONS D'IMATGE */
        body {
            /* Assumim que 'estils.css' defineix el fons amb la imatge de camp */
            background-color: #333;
            /* Fons de seguretat */
            background-image: url('Captura_de_pantalla_2025-10-27_130631_fons.jpg');
            /* Imatge de Fons (simulació) */
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
            min-height: 100vh;
        }

        /* Contenidor Principal: Manté el fons transparent per a l'efecte, però amb text clar */
        .contingut-parceles {
            max-width: 1400px;
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            min-height: calc(100vh - 40px);
            background-color: transparent;
            box-shadow: none;
            border-radius: 0;
            color: white;
            /* Assegurem que el text del <p> introductori sigui llegible */
        }

        /* Descripció */
        .contingut-parceles p {
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
            color: var(--color-principal, #1E4660) !important;
        }

        /* 3. ESTILS DE TAULA: **APLICACIÓ DE L'OPCIÓ 1 (FONS BLANC SÒLID)** */
        .taula-parceles {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            /* Fons SÒLID BLANC per màxima llegibilitat */
            background-color: white;
            color: #333;
            /* Text fosc general per defecte */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            overflow: hidden;
        }

        .taula-parceles th,
        .taula-parceles td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            /* Separador subtil i clar */
            color: #333;
            /* **FORCEM EL TEXT FOSC** */
        }

        .taula-parceles th {
            background-color: var(--color-principal, #1E4620);
            color: white;
            /* L'encapçalament ha de ser blanc sobre verd fosc */
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .taula-parceles tr {
            background-color: white;
        }

        .taula-parceles tr:hover {
            background-color: #f5f5f5;
            /* Hover clar sobre fons clar */
        }

        .taula-parceles td:nth-child(2) {
            /* Nom del Sector/Parcel·la */
            font-weight: bold;
        }

        .taula-parceles td:nth-child(3),
        /* Cultiu Actual */
        .taula-parceles td:nth-child(4),
        /* Superfície */
        .taula-parceles td:nth-child(5) {
            /* Any Plantació */
            color: #666;
            /* Text més clar per dades secundàries */
        }

        /* Estils dels estats (Assegurem colors vius sobre fons blanc) */
        .estat-ok {
            color: var(--color-secundari, #2ecc71);
            font-weight: bold;
        }

        .estat-atencio {
            color: var(--color-accent-taronja, #FF9800);
            font-weight: bold;
        }

        .estat-normal {
            color: #333;
        }

        /* Botons d'Accions (Mantenen l'estil per contrastar) */
        .botons-accions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            gap: 10px;
        }

        .botons-accions a {
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9em;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }

        .boto-nova-parcela {
            background-color: var(--color-principal, #1E4620);
            color: white;
        }

        .boto-nova-parcela:hover {
            background-color: #2980b9;
        }

        .boto-mapa {
            background-color: var(--color-secundari, #4CAF50);
            color: white;
        }

        .boto-mapa:hover {
            background-color: #449d48;
        }

        /* Estils per Accions */
        .taula-parceles a i {
            font-size: 1.2em;
        }

        .taula-parceles a[title="Veure Detall Agronòmic"] i {
            color: var(--color-accent-taronja, #FF9800) !important;
        }

        .taula-parceles a[title="Anàlisis de Sòl"] i {
            color: #9b59b6 !important;
        }

        /* 4. FOOTER (Sticky Footer) */
        */ .peu-app {
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
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-parceles">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-map-marked-alt" style="color: var(--color-primary);"></i>
            Gestió Geoespacial: Parcel·les i Sectors
        </h1>
        <p style="margin-bottom: 30px; color: #666;">Definició i paràmetres tècnics de cada unitat productiva de
            l'explotació (superfície, cultiu, any de plantació, etc.).</p>

        <div class="botons-accions">
            <a href="mapa_gis.php" class="boto-mapa">
                <i class="fas fa-globe-europe"></i> Veure Mapa GIS (Simulació)
            </a>
            <a href="nova_parcela.php" class="boto-nova-parcela">
                <i class="fas fa-plus"></i> Registrar Nova Parcel·la/Sector
            </a>
        </div>

        <table class="taula-parceles">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom del Sector/Parcel·la</th>
                    <th>Cultiu Actual</th>
                    <th>Superfície (ha)</th>
                    <th>Any Plantació</th>
                    <th>Requeriment Risc</th>
                    <th>Estat</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($llistat_parceles_sectors)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                            No hi ha parcel·les ni sectors definits.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($llistat_parceles_sectors as $parcela):
                        $classe_estat = obtenirClasseEstat($parcela['estat']);
                        ?>
                        <tr>
                            <td><?= $parcela['id']; ?></td>
                            <td style="font-weight: bold;"><?= htmlspecialchars($parcela['nom']); ?></td>
                            <td><?= htmlspecialchars($parcela['cultiu']); ?></td>
                            <td><?= number_format($parcela['superficie'], 2) . ' ha'; ?></td>
                            <td><?= htmlspecialchars($parcela['data_plantacio']); ?></td>
                            <td><?= htmlspecialchars($parcela['requeriment_risc']); ?></td>
                            <td><span class="<?= $classe_estat; ?>"><?= htmlspecialchars($parcela['estat']); ?></span></td>
                            <td>
                                <a href="detall_parcela.php?id=<?= $parcela['id']; ?>" title="Veure Detall Agronòmic">
                                    <i class="fas fa-magnifying-glass" style="color: var(--color-accent-taronja);"></i>
                                </a>
                                <a href="analisis_lab.php?parcela_id=<?= $parcela['id']; ?>" title="Anàlisis de Sòl"
                                    style="margin-left: 10px;">
                                    <i class="fas fa-flask" style="color: #9b59b6;"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px; font-size: 0.85em; color: #999;">
            * El control de les unitats productives (parcel·les) és el primer pas per a la traçabilitat completa.
        </p>
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