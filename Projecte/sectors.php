<?php
// sectors.php - Pàgina de Gestió Geoespacial i Detall de Sectors/Plantacions

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA (Simulació de dades de la taula Sectors/Plantacions) ---
$llistat_sectors = [
    [
        'id_sector' => 10,
        'nom_intern' => 'P01 - Gala Jove',
        'parcela_fisica' => 'Parcel·la Est (P-01)',
        'varietat' => 'Gala',
        'superficie_ha' => 5.2,
        'any_plantacio' => 2018,
        'marc_plantacio' => '4x1.5m',
        'fenologia_actual' => 'Caiguda fulla',
        'estat_sanitari' => 'OK'
    ],
    [
        'id_sector' => 11,
        'nom_intern' => 'P01 - Fuji Vella',
        'parcela_fisica' => 'Parcel·la Est (P-01)',
        'varietat' => 'Fuji',
        'superficie_ha' => 3.5,
        'any_plantacio' => 2015,
        'marc_plantacio' => '5x2m',
        'fenologia_actual' => 'Floració',
        'estat_sanitari' => 'Alerta (Pugó)'
    ],
    [
        'id_sector' => 12,
        'nom_intern' => 'P02 - Patrona',
        'parcela_fisica' => 'Sector Nord (P-02)',
        'varietat' => 'Patrona (Peral)',
        'superficie_ha' => 6.1,
        'any_plantacio' => 2020,
        'marc_plantacio' => '4.5x1.2m',
        'fenologia_actual' => 'Quallat',
        'estat_sanitari' => 'OK'
    ],
];

// Lògica per obtenir la classe d'estat (simulació)
function obtenirClasseEstatSanitari($estat): string
{
    return match (str_contains(strtolower($estat), 'alerta')) {
        true => 'estat-alerta',
        default => 'estat-ok',
    };
}
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Sectors i Plantacions</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color (Assumint el verd fosc, verd mitjà, taronja i blau) */
        :root {
            --color-principal: #1E4620;
            /* Verd Fosc */
            --color-secundari: #4CAF50;
            /* Verd Mitjà */
            --color-accent-taronja: #FF9800;
            /* Taronja Accent */
            --color-accent-blau: #3498db;
            /* Blau Accent */
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

            /* Manté la imatge de fons: */
            background-color: #333;
            background-image: url('fons_sectors.jpg');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
        }

        /* Contenidor Principal: Ajustat per l'efecte de fons d'imatge */
        main.contingut-sectors {
            /* FIX: Creix per empènyer el footer cap avall */
            flex-grow: 1;

            max-width: 1400px;
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            background-color: transparent;
            color: white;
        }

        /* Descripció */
        .contingut-sectors p {
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

        /* 3. ESTILS DE TAULA: APLICACIÓ DE L'OPCIÓ 1 (FONS BLANC SÒLID) */
        .taula-sectors {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            /* Fons SÒLID BLANC per màxima llegibilitat */
            background-color: white;
            color: #333;
            /* Text fosc general per defecte */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            overflow: hidden;
        }

        .taula-sectors th,
        .taula-sectors td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            color: #333;
            /* FORCEM EL TEXT FOSC */
        }

        .taula-sectors th {
            background-color: var(--color-principal, #1E4620);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .taula-sectors tr {
            background-color: white;
        }

        .taula-sectors tr:hover {
            background-color: #f5f5f5;
        }

        .taula-sectors td:nth-child(2) {
            font-weight: bold;
        }

        .taula-sectors td:nth-child(5),
        .taula-sectors td:nth-child(6) {
            color: #666;
        }

        /* Estils dels estats */
        .estat-ok {
            color: var(--color-secundari, #2ecc71);
            font-weight: bold;
        }

        .estat-alerta {
            color: #e74c3c;
            font-weight: bold;
        }

        /* Estils per Accions */
        .taula-sectors a i {
            font-size: 1.2em;
        }

        .taula-sectors a[title="Veure Històric Tractaments"] i {
            color: var(--color-accent-blau) !important;
        }

        .taula-sectors a[title="Monitoratge de Plagues"] i {
            color: #e67e22 !important;
        }

        /* Botons d'Accions */
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

        .boto-nou-sector {
            background-color: var(--color-principal, #1E4620);
            color: white;
        }

        .boto-nou-sector:hover {
            background-color: #2980b9;
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
                <li class="actiu"><a href="#"><i class="fas fa-tree"></i> Sectors</a></li>
                <li><a href="quadern.php"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-sectors">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-tree" style="color: var(--color-primary);"></i>
            Gestió de Sectors i Plantacions
        </h1>
        <p style="margin-bottom: 30px;">Detall de les unitats de cultiu (varietat, any, marc de plantació) utilitzades
            com a referència per a totes les operacions.</p>

        <div class="botons-accions">
            <a href="nou_sector.php" class="boto-nou-sector">
                <i class="fas fa-plus"></i> Registrar Nou Sector/Plantació
            </a>
        </div>

        <table class="taula-sectors">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom Intern</th>
                    <th>Parcel·la Física</th>
                    <th>Varietat / Cultiu</th>
                    <th>Superfície (ha)</th>
                    <th>Any Plantació</th>
                    <th>Marc (m)</th>
                    <th>Fenologia Actual</th>
                    <th>Estat Sanitari</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($llistat_sectors)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 30px; color: #999;">
                            No hi ha sectors de plantació definits.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($llistat_sectors as $sector):
                        $classe_estat = obtenirClasseEstatSanitari($sector['estat_sanitari']);
                        ?>
                        <tr>
                            <td><?= $sector['id_sector']; ?></td>
                            <td><?= htmlspecialchars($sector['nom_intern']); ?></td>
                            <td><?= htmlspecialchars($sector['parcela_fisica']); ?></td>
                            <td><?= htmlspecialchars($sector['varietat']); ?></td>
                            <td><?= number_format($sector['superficie_ha'], 2) . ' ha'; ?></td>
                            <td><?= $sector['any_plantacio']; ?></td>
                            <td><?= htmlspecialchars($sector['marc_plantacio']); ?></td>
                            <td><?= htmlspecialchars($sector['fenologia_actual']); ?></td>
                            <td><span class="<?= $classe_estat; ?>"><?= htmlspecialchars($sector['estat_sanitari']); ?></span>
                            </td>
                            <td>
                                <a href="detall_sector.php?id=<?= $sector['id_sector']; ?>" title="Veure Històric Tractaments">
                                    <i class="fas fa-file-invoice"></i>
                                </a>
                                <a href="monitoratge.php?sector_id=<?= $sector['id_sector']; ?>" title="Monitoratge de Plagues"
                                    style="margin-left: 10px;">
                                    <i class="fas fa-bug"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px; font-size: 0.85em;">
            * La informació del sector s'utilitza per aplicar correctament la dosi i verificar els màxims legals de
            matèria activa.
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