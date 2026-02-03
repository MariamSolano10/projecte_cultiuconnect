<?php
// productes_quimics.php - Gestió d'Inventari i Registre de Productes Fitosanitaris/Adobs

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA ---
$productes_inventari = [
    [
        'id' => 1,
        'nom' => 'Fungistar 500',
        'tipus' => 'Fungicida',
        'stock_actual' => 150.0,
        'unitat' => 'L',
        'dosi_recomanada' => 1.5,
        'dosi_unitat' => 'L/ha',
        'phi_dies' => 21,
    ],
    [
        'id' => 2,
        'nom' => 'Bio-Insect Natural',
        'tipus' => 'Insecticida Biològic',
        'stock_actual' => 85.5,
        'unitat' => 'L',
        'dosi_recomanada' => 2.5,
        'dosi_unitat' => 'L/ha',
        'phi_dies' => 3,
    ],
    [
        'id' => 3,
        'nom' => 'Fertiplús N20',
        'tipus' => 'Adob Nitrogenat',
        'stock_actual' => 5000.0,
        'unitat' => 'Kg',
        'dosi_recomanada' => 5.0,
        'dosi_unitat' => 'Kg/ha',
        'phi_dies' => 0,
    ],
];
// -----------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Inventari de Productes</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estils adaptats per al Fons Fosc i Taula Blanca */
        :root {
            --color-principal: #1E4620;
            --color-secundari: #4CAF50;
            --color-accent-taronja: #FF9800;
            --color-accent-blau: #3498db;
            --color-card-fons: white;
            --color-text-fosc: #333;
            /* NOVES VARIABLES DEL FOOTER */
            --color-footer-fosc: rgba(30, 70, 32, 0.9); /* Fons més opac per al footer */
            --color-footer-text: #ddd;
        }

        /* 1. ESTILS DE BASE */
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #333;
            background-image: url('fons_sectors.jpg');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
        }

        /* 2. CONTINGUT PRINCIPAL I TÍTOL */
        main.contingut-productes {
            flex-grow: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            background-color: transparent;
            color: white;
        }

        .contingut-productes p {
            color: #ccc !important;
        }

        .títol-pàgina {
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            color: var(--color-principal);
            text-align: center;
        }

        .títol-pàgina i {
            color: var(--color-principal) !important;
        }

        /* 3. TAULA (Fons Blanc Sòlid) */
        .taula-productes {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            color: var(--color-text-fosc);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            overflow: hidden;
        }

        .taula-productes th,
        .taula-productes td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .taula-productes th {
            background-color: var(--color-principal);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .taula-productes tr:hover {
            background-color: #f5f5f5;
        }

        /* Estils PHI */
        .phi-baix {
            color: #4CAF50;
            font-weight: bold;
        }

        .phi-mitja {
            color: #FF9800;
            font-weight: bold;
        }

        .phi-alt {
            color: #E74C3C;
            font-weight: bold;
        }

        /* 4. BOTONS */
        .botons-accions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            gap: 10px;
        }

        .boto-nou {
            background-color: var(--color-accent-blau);
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

        .boto-nou:hover {
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
                <li><a href="quadern.php"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li class="actiu"><a href="#"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
                <li><a href="monitoratge.php"><i class="fas fa-bug"></i> Monitoratge</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-productes">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-boxes-stacked"></i>
            Inventari de Productes Fitosanitaris i Adobs
        </h1>
        <p style="margin-bottom: 30px;">Gestió d'estocs, dosis estàndard i Terminis de Seguretat (PHI).</p>

        <div class="botons-accions">
            <a href="nou_producte.php" class="boto-nou">
                <i class="fas fa-plus"></i> Registrar Nou Producte
            </a>
        </div>

        <table class="taula-productes">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom Comercial</th>
                    <th>Tipus</th>
                    <th>Stock Actual</th>
                    <th>Dosi Recomanada</th>
                    <th>PHI (dies)</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($productes_inventari)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                            No hi ha productes registrats a l'inventari.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productes_inventari as $producte):
                        if ($producte['phi_dies'] > 15) {
                            $classe_phi = 'phi-alt';
                        } elseif ($producte['phi_dies'] > 5) {
                            $classe_phi = 'phi-mitja';
                        } else {
                            $classe_phi = 'phi-baix';
                        }
                        ?>
                        <tr>
                            <td><?= $producte['id']; ?></td>
                            <td style="font-weight: bold;"><?= htmlspecialchars($producte['nom']); ?></td>
                            <td><?= htmlspecialchars($producte['tipus']); ?></td>
                            <td><?= number_format($producte['stock_actual'], 2) . ' ' . $producte['unitat']; ?></td>
                            <td><?= number_format($producte['dosi_recomanada'], 2) . ' ' . $producte['dosi_unitat']; ?></td>
                            <td><span class="<?= $classe_phi; ?>"><?= $producte['phi_dies']; ?> dies</span></td>
                            <td>
                                <a href="editar_producte.php?id=<?= $producte['id']; ?>" title="Editar Producte">
                                    <i class="fas fa-edit" style="color: var(--color-accent-taronja);"></i>
                                </a>
                                <a href="moure_stock.php?id=<?= $producte['id']; ?>" title="Moviment d'Estoc"
                                    style="margin-left: 10px;">
                                    <i class="fas fa-arrow-right-arrow-left" style="color: var(--color-secundari);"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

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