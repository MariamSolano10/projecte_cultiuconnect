<?php
// documentacio.php - Pàgina de Compliment Normatiu i Documentació

// Inclusió de la lògica de connexió (només per coherència).
include 'db_connect.php';

// --- MOCK DATA (Simulació de Documents i Informes per a l'Exportació) ---

// 1. Informes Legals i de Traçabilitat
$informes_legals = [
    ['nom' => 'Quadern d\'Explotació (QDE) Oficial - Any ' . date('Y'), 'tipus' => 'PDF', 'data_generacio' => '2025-10-06 14:00', 'enllac' => 'generar_qde.php?any=' . date('Y'), 'icona' => 'file-pdf'],
    ['nom' => 'Resum de Traçabilitat: Lots Poma Gala', 'tipus' => 'Excel', 'data_generacio' => '2025-09-30 09:30', 'enllac' => 'exportar_traca.php?lot=gala', 'icona' => 'file-excel'],
    ['nom' => 'Fitxa de Compliment LMR - Últims 12 mesos', 'tipus' => 'PDF', 'data_generacio' => '2025-08-01 11:00', 'enllac' => 'veure_lmr.php', 'icona' => 'shield-virus'],
];

// 2. Documents Operatius (Estàtics)
$documents_operatius = [
    ['nom' => 'Protocol de Seguretat en Aplicacions (EPIs)', 'tipus' => 'PDF', 'data_actualitzacio' => '2024-01-20', 'enllac' => 'manuals/protocol_seguretat.pdf', 'icona' => 'user-shield'],
    ['nom' => 'Certificació GlobalGAP - Estat Actual', 'tipus' => 'Certificat', 'data_actualitzacio' => '2025-07-01', 'enllac' => 'certificats/globalgap.pdf', 'icona' => 'certificate'],
    ['nom' => 'Fitxes Tècniques Productes Fitos. (Catàleg)', 'tipus' => 'Índex', 'data_actualitzacio' => '2025-10-01', 'enllac' => 'productes_quimics.php', 'icona' => 'vials'],
];

// -----------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Compliment Normatiu i Documentació</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color (Assumint el verd fosc, verd mitjà, taronja i blau) */
        :root {
            --color-principal: #1e4620ff;
            /* Verd Fosc */
            --color-secundari: #4CAF50;
            /* Verd Mitjà */
            --color-accent-taronja: #FF9800;
            /* Taronja Accent */
            --color-accent-blau: #3498db;
            /* Blau Accent */
            /* Afegim fons per compatibilitat amb el fons de la taula/targeta */
            --color-card-fons: #ffffffff;
            --color-text-fosc: #333333ff;
            /* NOVES VARIABLES DEL FOOTER */
            --color-footer-fosc: #1e4620e6;
            /* Fons més opac per al footer */
            --color-footer-text: #ddddddff;
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
        main.contingut-documentacio {
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
        .contingut-documentacio p {
            /* Sobreescriptura per llegibilitat sobre el fons d'imatge */
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

        /* 3. ESTILS DE LES TARGETES (Document-item) - APLICACIÓ DE FONS BLANC */
        .document-item {
            /* FIX: Fons Blanc Sòlid per màxima llegibilitat */
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            /* Ombra ajustada */
            border-left: 5px solid var(--color-accent-taronja);
            transition: box-shadow 0.3s;
        }

        .document-item:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .document-item h3 {
            margin-top: 0;
            font-size: 1.1em;
            color: var(--color-text-fosc, #333);
            /* Assegurem text fosc */
            display: flex;
            align-items: center;
        }

        .document-item h3 i {
            margin-right: 10px;
            color: var(--color-principal);
            /* Verd fosc per la icona principal */
            font-size: 1.2em;
        }

        .document-meta {
            font-size: 0.85em;
            color: #777;
            margin-top: 10px;
            padding-left: 30px;
        }

        /* Estils per a la capçalera de les seccions */
        .header-seccio {
            padding-bottom: 10px;
            /* Eliminem el border per la llegibilitat sobre el fons d'imatge */
            /* border-bottom: 2px solid var(--color-fons); */
            margin-bottom: 25px;
            color: white;
            /* Fem la capçalera de secció blanca per llegibilitat */
            font-size: 1.4em;
        }

        .header-seccio i {
            color: var(--color-accent-taronja);
            /* Destaquem la icona de secció */
            margin-right: 10px;
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
                <li class="actiu"><a href="#"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
                <li><a href="informes.php"><i class="fas fa-chart-line"></i> Anàlisi</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-documentacio">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-stamp"></i>
            Compliment Normatiu i Documentació
        </h1>
        <p style="margin-bottom: 30px;">Punt d'accés unificat per a la generació d'informes legals, Quadern d'Explotació
            (QDE) i documentació operativa.</p>

        <div class="seccio-documentacio">
            <h2 class="header-seccio">
                <i class="fas fa-gavel"></i> Informes Legals i Certificats
            </h2>
            <ul class="llista-documents">
                <?php foreach ($informes_legals as $doc): ?>
                    <li class="document-item">
                        <h3><i class="fas fa-<?= $doc['icona']; ?>"></i> <?= htmlspecialchars($doc['nom']); ?></h3>
                        <div class="document-meta">
                            Tipus: <strong><?= $doc['tipus']; ?></strong><br>
                            Última Generació: <strong><?= date('d/m/Y H:i', strtotime($doc['data_generacio'])); ?></strong>
                        </div>
                        <a href="<?= $doc['enllac']; ?>" class="boto-descarrega <?= strtolower($doc['tipus']); ?>">
                            <i class="fas fa-download"></i> Descarregar
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="seccio-documentacio">
            <h2 class="header-seccio">
                <i class="fas fa-book-open"></i> Protocols i Documentació Operativa
            </h2>
            <ul class="llista-documents">
                <?php foreach ($documents_operatius as $doc): ?>
                    <li class="document-item">
                        <h3><i class="fas fa-<?= $doc['icona']; ?>"></i> <?= htmlspecialchars($doc['nom']); ?></h3>
                        <div class="document-meta">
                            Tipus: <strong><?= $doc['tipus']; ?></strong><br>
                            Darrera Revisió: <strong><?= date('d/m/Y', strtotime($doc['data_actualitzacio'])); ?></strong>
                        </div>
                        <a href="<?= $doc['enllac']; ?>" class="boto-descarrega <?= strtolower($doc['tipus']); ?>">
                            <i class="fas fa-eye"></i> Veure / Accedir
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <p style="margin-top: 20px; font-size: 0.85em;">
            * Tots els informes generats s'emmagatzemen amb signatura digital per complir amb els requisits del Quadern
            d'Explotació Digital (CUE).
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