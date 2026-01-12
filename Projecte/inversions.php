<?php
// inversions.php - Pàgina de Gestió d'Inversions, Actius i Amortització

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA (Simulació de dades d'Actius i Inversions) ---
// En un entorn real, es faria un SELECT de la taula Actius_Immobilitzat

$actius_inversions = [
    [
        'id' => 1,
        'nom_actiu' => 'Tractor John Deere 5090R',
        'data_compra' => '2022-03-15',
        'valor_inicial' => 65000.00,
        'vida_util_anys' => 10,
        'amortitzacio_anual' => 6500.00, // 65000 / 10
        'valor_comptable' => 45500.00, // (Valor inicial - 3 anys * Amortització Anual)
        'manteniment_anual' => 1800.00
    ],
    [
        'id' => 2,
        'nom_actiu' => 'Sistema de Reg per Degoteig P01',
        'data_compra' => '2019-06-01',
        'valor_inicial' => 12000.00,
        'vida_util_anys' => 8,
        'amortitzacio_anual' => 1500.00,
        'valor_comptable' => 7500.00,
        'manteniment_anual' => 350.00
    ],
    [
        'id' => 3,
        'nom_actiu' => 'Nebulitzador Fitop. Arrastrat (2000L)',
        'data_compra' => '2023-11-05',
        'valor_inicial' => 25000.00,
        'vida_util_anys' => 15,
        'amortitzacio_anual' => 1666.67,
        'valor_comptable' => 22222.21,
        'manteniment_anual' => 700.00
    ],
];

// Càlculs totals simulats
$total_manteniment = array_sum(array_column($actius_inversions, 'manteniment_anual'));
$total_amortitzacio = array_sum(array_column($actius_inversions, 'amortitzacio_anual'));
// -----------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Gestió d'Inversions i Actius</title>
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
            /* Taronja Accent (Nou Actiu / Manteniment) */
            --color-accent-blau: #3498db;
            /* Blau Accent (Amortització / Detall) */
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
        main.contingut-inversions {
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
        .contingut-inversions p {
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
        .taula-inversions {
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

        .taula-inversions th,
        .taula-inversions td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            color: var(--color-text-fosc);
        }

        .taula-inversions th {
            background-color: var(--color-principal, #1E4620);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .taula-inversions tr:hover {
            background-color: #f5f5f5;
        }

        /* Estils per a resums financers (Panell Blanc) */
        .resum-financier {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
            padding: 20px;
            background-color: var(--color-card-fons);
            /* FONS BLANC */
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            color: var(--color-text-fosc);
        }

        .resum-item {
            text-align: center;
            font-size: 1.1em;
        }

        .resum-item strong {
            display: block;
            font-size: 1.8em;
            color: var(--color-accent-blau);
            /* Utilitzar variables */
            margin-top: 5px;
        }

        .resum-item i {
            font-size: 1.4em;
            margin-bottom: 5px;
        }

        /* Botons d'Accions */
        .botons-accions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            gap: 10px;
        }

        .boto-nou-actiu {
            background-color: var(--color-accent-taronja);
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }

        .boto-nou-actiu:hover {
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
                <li class="actiu"><a href="#"><i class="fas fa-euro-sign"></i> Finances</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-inversions">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-dollar-sign"></i>
            Gestió d'Actius i Inversions (Amortització)
        </h1>
        <p style="margin-bottom: 30px;">Control del valor comptable de la maquinària i sistemes, calculant
            l'amortització anual i els costos de manteniment.</p>

        <div class="botons-accions">
            <a href="nou_actiu.php" class="boto-nou-actiu">
                <i class="fas fa-plus"></i> Registrar Nova Inversió/Actiu
            </a>
        </div>

        <table class="taula-inversions">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom de l'Actiu</th>
                    <th>Data Compra</th>
                    <th>Valor Inicial (€)</th>
                    <th>Vida Útil (anys)</th>
                    <th>Amortització Anual (€)</th>
                    <th>Valor Comptable Actual (€)</th>
                    <th>Cost Manteniment Anual (€)</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($actius_inversions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 30px; color: #999;">
                            No hi ha actius registrats.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($actius_inversions as $actiu): ?>
                        <tr>
                            <td><?= $actiu['id']; ?></td>
                            <td style="font-weight: bold;"><?= htmlspecialchars($actiu['nom_actiu']); ?></td>
                            <td><?= date('d/m/Y', strtotime($actiu['data_compra'])); ?></td>
                            <td><?= number_format($actiu['valor_inicial'], 2) . ' €'; ?></td>
                            <td><?= $actiu['vida_util_anys']; ?></td>
                            <td style="color: var(--color-accent-taronja); font-weight: bold;">
                                <?= number_format($actiu['amortitzacio_anual'], 2) . ' €'; ?></td>
                            <td style="color: var(--color-principal); font-weight: bold;">
                                <?= number_format($actiu['valor_comptable'], 2) . ' €'; ?></td>
                            <td><?= number_format($actiu['manteniment_anual'], 2) . ' €'; ?></td>
                            <td>
                                <a href="detall_actiu.php?id=<?= $actiu['id']; ?>" title="Veure Històric Manteniment">
                                    <i class="fas fa-tools" style="color: var(--color-accent-blau);"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="resum-financier">
            <div class="resum-item">
                <i class="fas fa-money-bill-wave" style="color: var(--color-accent-blau);"></i>
                Càrrega Anual per Amortització
                <strong><?= number_format($total_amortitzacio, 2) . ' €'; ?></strong>
            </div>
            <div class="resum-item">
                <i class="fas fa-screwdriver-wrench" style="color: var(--color-accent-taronja);"></i>
                Cost Total Manteniment Estimat
                <strong><?= number_format($total_manteniment, 2) . ' €'; ?></strong>
            </div>
        </div>

        <p style="margin-top: 30px; font-size: 0.85em; color: #ccc;">
            * Les dades d'amortització ajuden a calcular el cost real d'operació per hectàrea.
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