<?php
// monitoratge.php - Pàgina de Protecció Vegetal, Seguiment de Plagues i Monitoratge

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA (Simulació de dades de les Trampes i Registres de Monitoratge) ---
$punts_monitoratge = [
    [
        'id' => 1,
        'sector' => 'P01 - Gala Jove',
        'plaga' => 'Cydia Pomonella (Carpocapsa)',
        'tipus_trampa' => 'Feromona',
        'ultima_captura' => 20,
        'data_registre' => '2025-10-05 10:00',
        'llindar_tractament' => 15, // Si Captura > Llindar -> ALERTA
        'estat_alerta' => 'SUPERAT'
    ],
    [
        'id' => 2,
        'sector' => 'P01 - Fuji Vella',
        'plaga' => 'Pugó Verd del Marge',
        'tipus_trampa' => 'Visual/Observació',
        'ultima_captura' => 0.5, // % de brots afectats
        'data_registre' => '2025-10-06 08:30',
        'llindar_tractament' => 5.0,
        'estat_alerta' => 'OK'
    ],
    [
        'id' => 3,
        'sector' => 'P02 - Patrona',
        'plaga' => 'Mosca de la Fruita',
        'tipus_trampa' => 'Atractiu Alimentari',
        'ultima_captura' => 12,
        'data_registre' => '2025-10-04 17:00',
        'llindar_tractament' => 10,
        'estat_alerta' => 'RISC'
    ],
];

// Lògica per determinar l'estat d'alerta i la classe CSS
function obtenirClasseEstatAlerta($estat): string
{
    return match (strtolower($estat)) {
        'superat' => 'alerta-superat',
        'risc' => 'alerta-risc',
        default => 'alerta-ok',
    };
}
// -----------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Monitoratge de Plagues</title>
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
            /* Taronja Accent (Nou Registre) */
            --color-accent-blau: #3498db;
            /* Blau Accent (Tendència) */
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
        main.contingut-monitoratge {
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
        .contingut-monitoratge p {
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

        /* 3. ESTILS DE TAULA: APLICACIÓ DE FONS BLANC SÒLID */
        .taula-monitoratge {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            /* Fons SÒLID BLANC */
            background-color: white;
            color: var(--color-text-fosc);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            overflow: hidden;
        }

        .taula-monitoratge th,
        .taula-monitoratge td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            color: var(--color-text-fosc);
            /* Color de text per a les cel·les */
        }

        .taula-monitoratge th {
            background-color: var(--color-principal, #1E4620);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .taula-monitoratge tr:hover {
            background-color: #f5f5f5;
        }

        /* Estils dels estats d'alerta (mantinguts com a colors clars sobre fons blanc) */
        .alerta-ok {
            color: #1e4620;
            /* Verd Fosc (Principal) */
            font-weight: bold;
            background-color: #e8f8f5;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .alerta-risc {
            color: #e67e22;
            /* Taronja */
            font-weight: bold;
            background-color: #fcf3cf;
            padding: 5px 10px;
            border-radius: 4px;
        }

        .alerta-superat {
            color: #e74c3c;
            /* Vermell */
            font-weight: bold;
            background-color: #f9e9ea;
            padding: 5px 10px;
            border-radius: 4px;
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

        .boto-nou-registre {
            background-color: var(--color-accent-taronja);
            color: white;
        }

        .boto-nou-registre:hover {
            background-color: #d35400;
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
                <li><a href="#" class="actiu"><i class="fas fa-bug"></i> Monitoratge</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-monitoratge">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-bug"></i>
            Monitoratge de Plagues i Malalties
        </h1>
        <p style="margin-bottom: 30px;">Seguiment de les trampes de captura i els llindars d'acció per garantir una
            protecció vegetal eficient i minimitzar les aplicacions.</p>

        <div class="botons-accions">
            <a href="nou_monitoratge.php" class="boto-nou-registre">
                <i class="fas fa-plus"></i> Registrar Nova Observació/Captura
            </a>
        </div>

        <table class="taula-monitoratge">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Sector</th>
                    <th>Plaga Objectiu</th>
                    <th>Tipus de Mostreig</th>
                    <th>Llindar d'Acció</th>
                    <th>Últim Registre</th>
                    <th>Data de Registre</th>
                    <th>Estat d'Alerta</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($punts_monitoratge)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                            No hi ha punts de monitoratge actius registrats.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($punts_monitoratge as $punt):
                        $classe_estat = obtenirClasseEstatAlerta($punt['estat_alerta']);

                        // Determinar la unitat per a Captura i Llindar
                        $unitat = (strpos(strtolower($punt['tipus_trampa']), 'feromona') !== false || strpos(strtolower($punt['tipus_trampa']), 'alimentari') !== false) ? ' individus' : ' %';

                        // Icona d'alerta
                        $icona_alerta = match (strtolower($punt['estat_alerta'])) {
                            'superat' => '<i class="fas fa-exclamation-triangle"></i> ',
                            'risc' => '<i class="fas fa-bell"></i> ',
                            default => '<i class="fas fa-check-circle"></i> ',
                        };
                        ?>
                        <tr>
                            <td><?= $punt['id']; ?></td>
                            <td style="font-weight: bold;"><?= htmlspecialchars($punt['sector']); ?></td>
                            <td><?= htmlspecialchars($punt['plaga']); ?></td>
                            <td><?= htmlspecialchars($punt['tipus_trampa']); ?></td>
                            <td><?= number_format($punt['llindar_tractament'], 1) . $unitat; ?></td>
                            <td><?= number_format($punt['ultima_captura'], 1) . $unitat; ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($punt['data_registre'])); ?></td>
                            <td>
                                <span class="<?= $classe_estat; ?>">
                                    <?= $icona_alerta . htmlspecialchars($punt['estat_alerta']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="grafic_tendencia.php?id=<?= $punt['id']; ?>" title="Veure Tendència Històrica">
                                    <i class="fas fa-chart-line" style="color: var(--color-accent-blau);"></i>
                                </a>
                                <a href="operacio_nova.php?plaga=<?= urlencode($punt['plaga']); ?>" title="Crear Tractament"
                                    style="margin-left: 10px;">
                                    <i class="fas fa-spray-can-sparkles" style="color: var(--color-secundari);"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px; font-size: 0.85em; color: #ccc;">
            * El monitoratge permet l'ús de tractaments més dirigits, reduint costos i impacte ambiental.
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