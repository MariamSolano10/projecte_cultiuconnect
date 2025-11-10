<?php
// analisis_lab.php - Pàgina de Gestió d'Anàlisis de Laboratori (Sòl, Fulla, Aigua)

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA (Simulació de dades d'Anàlisis de Laboratori) ---
$llistat_analisis = [
    [
        'id' => 201,
        'sector' => 'P01 - Gala Jove',
        'tipus_analisi' => 'Sòl (Nutrients)',
        'data_recepcio' => '2025-09-20',
        'pH' => 6.5,
        'N' => 150, // Nitrogen (ppm)
        'K' => 400, // Potassi (ppm)
        'estat_nutricional' => 'OK (Equilibrat)'
    ],
    [
        'id' => 202,
        'sector' => 'P01 - Fuji Vella',
        'tipus_analisi' => 'Fulla (Micronutrients)',
        'data_recepcio' => '2025-08-15',
        'pH' => 7.1,
        'N' => 130,
        'K' => 320,
        'estat_nutricional' => 'DÈFICIT K'
    ],
    [
        'id' => 203,
        'sector' => 'P02 - Patrona',
        'tipus_analisi' => 'Sòl (Matèria Orgànica)',
        'data_recepcio' => '2025-01-10',
        'pH' => 5.8,
        'N' => 180,
        'K' => 450,
        'estat_nutricional' => 'RISC pH Baix'
    ],
];

// Lògica per obtenir la classe d'estat nutricional
function obtenirClasseEstatNutricional($estat): string
{
    return match (str_contains(strtolower($estat), 'dèficit') || str_contains(strtolower($estat), 'risc')) {
        true => 'estat-alerta',
        default => 'estat-ok',
    };
}
// -----------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Anàlisis de Laboratori</title>
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
            /* Taronja Accent (per buscar) */
            --color-accent-lila: #9b59b6;
            /* Lila per a Anàlisis */
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
        main.contingut-analisis {
            /* FIX: Creix per empènyer el footer cap avall */
            flex-grow: 1;

            max-width: 1400px;
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            /* Padding superior afegit */
            background-color: transparent;
            color: white;
            /* Per al text introductori */
        }

        /* Descripció */
        .contingut-analisis p {
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
        .taula-analisis {
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

        .taula-analisis th,
        .taula-analisis td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            color: #333;
            /* FORCEM EL TEXT FOSC */
        }

        .taula-analisis th {
            background-color: var(--color-principal, #1E4620);
            color: white;
            /* L'encapçalament ha de ser blanc sobre verd fosc */
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .taula-analisis tr {
            background-color: white;
        }

        .taula-analisis tr:hover {
            background-color: #f5f5f5;
            /* Hover clar sobre fons clar */
        }

        /* Estils dels estats nutricionals */
        .estat-ok {
            color: var(--color-secundari, #2ecc71);
            font-weight: bold;
        }

        .estat-alerta {
            color: #e74c3c;
            font-weight: bold;
            background-color: #f9e9ea;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            /* Per aplicar padding/bg correctament */
        }

        /* Botons d'Accions (Ajustats per colors definits) */
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

        .boto-nou-analisi {
            background-color: var(--color-accent-lila, #9b59b6);
            color: white;
        }

        .boto-nou-analisi:hover {
            background-color: #8e44ad;
        }

        /* 4. FOOTER (Sticky Footer) */
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

    <main class="contingut-analisis">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-flask" style="color: var(--color-principal);"></i>
            Anàlisis de Laboratori i Gestió Nutricional
        </h1>
        <p style="margin-bottom: 30px;">Registre històric de les analítiques de sòl, fulla i aigua. Informació essencial
            per a la fertilització de precisió.</p>

        <div class="botons-accions">
            <a href="nou_analisi.php" class="boto-nou-analisi">
                <i class="fas fa-plus"></i> Registrar Nova Analítica
            </a>
        </div>

        <table class="taula-analisis">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Sector</th>
                    <th>Tipus d'Anàlisi</th>
                    <th>Data Recepció</th>
                    <th>pH</th>
                    <th>Nitrogen (N ppm)</th>
                    <th>Potassi (K ppm)</th>
                    <th>Estat Nutricional</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($llistat_analisis)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                            No hi ha anàlisis de laboratori registrades.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($llistat_analisis as $analisi):
                        $classe_estat = obtenirClasseEstatNutricional($analisi['estat_nutricional']);
                        ?>
                        <tr>
                            <td><?= $analisi['id']; ?></td>
                            <td><?= htmlspecialchars($analisi['sector']); ?></td>
                            <td><?= htmlspecialchars($analisi['tipus_analisi']); ?></td>
                            <td><?= date('d/m/Y', strtotime($analisi['data_recepcio'])); ?></td>
                            <td><span style="font-weight: bold;"><?= number_format($analisi['pH'], 1); ?></span></td>
                            <td><?= $analisi['N']; ?></td>
                            <td><?= $analisi['K']; ?></td>
                            <td>
                                <span class="<?= $classe_estat; ?>">
                                    <?= htmlspecialchars($analisi['estat_nutricional']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="detall_analisi.php?id=<?= $analisi['id']; ?>" title="Veure Paràmetres Complet">
                                    <i class="fas fa-search" style="color: var(--color-accent-taronja);"></i>
                                </a>
                                <a href="pla_fertilitzacio.php?analisi_id=<?= $analisi['id']; ?>"
                                    title="Generar Pla de Fertilització" style="margin-left: 10px;">
                                    <i class="fas fa-leaf" style="color: var(--color-secundari);"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px; font-size: 0.85em;">
            * Les dades d'anàlisi s'utilitzen per calcular el balanç nutricional i optimitzar l'ús de fertilitzants.
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

</body>

</html>