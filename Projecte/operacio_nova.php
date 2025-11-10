<?php
// operacio_nova.php - Pàgina de Registre de Nou Tractament Fitosanitari/Fertilització

// Inclusió de la lògica de connexió.
include 'db_connect.php';

$llistat_sectors = [];
$productes_disponibles = [];
$error_connexio = null;

try {
    $pdo = connectDB();

    // 1. OBTENIR LLISTAT DE SECTORS AMB SUPERFÍCIE I DATA DE COLLITA PREVISTA
    // Suposant que la data de collita prevista es troba a la taula Explotacio_Sector o Cultiu_Varietat
    $sql_sectors = "
        SELECT 
            id_sector AS id, 
            CONCAT(codi_sector, ' - ', nom_varietat, ' (', superficie_ha, ' ha)') AS nom,
            superficie_ha, 
            collita_prevista AS collita_previsio
        FROM 
            Explotacio_Sector
        ORDER BY 
            id_sector;
    ";
    $stmt_sectors = $pdo->prepare($sql_sectors);
    $stmt_sectors->execute();
    $llistat_sectors = $stmt_sectors->fetchAll(PDO::FETCH_ASSOC);

    // 2. OBTENIR PRODUCTES QUÍMICS AMB PHI
    // Incloem un 'Sense Producte' o 'Fertilització' manualment com a ID 0 si cal,
    // o assumim que els fertilitzants estan a la BD amb PHI=0.
    // Aquí farem la consulta només dels registres reals amb PHI
    $sql_productes = "
        SELECT 
            id_producte AS id, 
            nom_comercial AS nom, 
            dosi_recomanada AS dosi, 
            unitat_dosi AS unitat, 
            termini_seguretat_dies AS phi_dies
        FROM 
            Producte_Quimic
        ORDER BY 
            nom_comercial ASC;
    ";
    $stmt_productes = $pdo->prepare($sql_productes);
    $stmt_productes->execute();
    $productes_disponibles = $stmt_productes->fetchAll(PDO::FETCH_ASSOC);

    // Afegir l'opció manual de "Sense Producte" per a altres operacions si no és a la BD (ID 0)
    array_unshift($productes_disponibles, [
        'id' => 0,
        'nom' => 'Sense Producte (Altres operacions / Fertilització simple)',
        'dosi' => 0,
        'unitat' => 'N/A',
        'phi_dies' => 0
    ]);


} catch (Exception $e) {
    $error_connexio = "❌ Error de connexió a la base de dades: " . htmlspecialchars($e->getMessage());
    $llistat_sectors = [];
    $productes_disponibles = [];
}


// Preparar les dades de sectors i productes per a JavaScript
$sectors_json = json_encode(array_column($llistat_sectors, null, 'id'));
// Si no hi ha sectors, assegurem que sigui un objecte buit per al JS
if (empty($llistat_sectors))
    $sectors_json = '{}';

$productes_json = json_encode(array_column($productes_disponibles, null, 'id'));
$data_actual = date('Y-m-d\TH:i'); // Format DateTime local HTML5

?>

<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Nou Tractament</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color (reforçades per consistència) */
        :root {
            --color-principal: #1E4620;
            /* Verd Fosc */
            --color-secundari: #4CAF50;
            /* Verd Mitjà */
            --color-primary-fosc: #143116;
            /* Verd Fosc per al hover */
            --color-accent-taronja: #FF9800;
            --color-accent-blau: #3498db;
            --color-card-fons: white;
            /* Fons de panell/targeta (BLANC) */
            --color-text-fosc: #333;
            /* NOVES VARIABLES DEL FOOTER */
            --color-footer-fosc: rgba(30, 70, 32, 0.9);
            /* Fons més opac per al footer */
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
            /* CANVIA PER LA TEVA IMATGE REAL */
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
        }

        /* Contenidor Principal (Main): **HA DE SER TRANSPARENT** i centrat */
        main.contingut-operacio {
            /* FIX: Creix per empènyer el footer cap avall */
            flex-grow: 1;
            max-width: 1000px;
            /* Amplada màxima per centrar */

            /* Compensació per la capçalera FIXED */
            padding: 105px 40px 40px 40px;
            margin: 0 auto;
            min-height: calc(100vh - 40px);

            /* CLAU: Fons transparent per veure la imatge */
            background-color: transparent;
            box-shadow: none;
            border-radius: 0;
            color: white;
            /* Per assegurar text llegible si no està al contenidor blanc */
        }

        /* 2. ESTIL DEL TÍTOL (Rectangular clar amb ombra - FLOTANT) */
        .títol-pàgina {
            /* Aquest títol flotarà lliurement sobre el fons d'imatge */
            max-width: 600px;
            margin: 0 auto 20px auto;
            /* Centrat i separat del formulari */

            padding: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            color: var(--color-principal, #1E4620);
            text-align: center;
            border-bottom: none;
            /* Treiem la línia separadora */
        }

        .títol-pàgina i {
            color: var(--color-principal) !important;
        }

        /* 3. NOU CONTENIDOR: El BLOC BLANC que encapsula el formulari */
        .contenidor-formulari-bloc {
            max-width: 900px;
            margin: 0 auto 30px auto;
            /* Centrat i amb marge inferior */
            padding: 40px;

            /* Fons BLANC per aïllar el formulari */
            background-color: var(--color-card-fons);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            color: var(--color-text-fosc);
        }

        /* Descripció */
        .contenidor-formulari-bloc p {
            color: #666 !important;
        }


        .formulari-tractament {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .grup-camp {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .grup-camp.col-span-2 {
            grid-column: 1 / span 2;
        }

        label {
            font-weight: bold;
            color: var(--color-text-fosc);
        }

        input,
        select,
        textarea {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .camp-lectura {
            background-color: #f0f0f0;
            font-weight: bold;
            color: var(--color-accent-blau);
        }

        .alerta-container {
            grid-column: 1 / span 2;
            padding: 15px;
            border-radius: 5px;
            background-color: #f9e9ea;
            /* Fons d'alerta suau */
            color: #c0392b;
            /* Text vermell fosc */
            border: 1px solid #e74c3c;
            font-weight: bold;
            display: none;
        }

        .boto-enviar {
            background-color: var(--color-principal);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s;
            margin-top: 15px;
        }

        .boto-enviar:hover {
            background-color: var(--color-primary-fosc);
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
                <li class="actiu"><a href="#"><i class="fas fa-spray-can-sparkles"></i> Nou Tractament</a></li>
                <li><a href="quadern.php"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
                <li><a href="monitoratge.php"><i class="fas fa-bug"></i> Monitoratge</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-operacio">
        <h1 class="títol-pàgina">
            <i class="fas fa-spray-can-sparkles"></i>
            Registre de Nova Operació Agrícola
        </h1>
        <p style="margin-bottom: 30px; color: #666">Registra el tractament amb màxima precisió per al quadern
            d'explotació i el
            càlcul de les dosis.</p>
        <div class="contenidor-formulari-bloc">


            <form action="processar_operacio.php" method="POST" class="formulari-tractament"
                onsubmit="return validarTractament()">

                <div class="grup-camp col-span-2">
                    <label for="parcela_aplicacio">Sector d'Aplicació <span style="color: red;">*</span></label>
                    <select id="parcela_aplicacio" name="parcela_aplicacio" onchange="actualitzarInfo()" required>
                        <option value="0" selected disabled>--- Selecciona un sector ---</option>
                        <?php foreach ($llistat_sectors as $sector): ?>
                            <option value="<?= $sector['id']; ?>" data-superficie="<?= $sector['superficie_ha']; ?>"
                                data-collita="<?= $sector['collita_previsio']; ?>">
                                <?= htmlspecialchars($sector['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #999;">Superfície del sector: <span id="superficie_mostrada">--</span>
                        ha</small>
                </div>

                <div class="grup-camp">
                    <label for="data_aplicacio">Data i Hora Aplicació <span style="color: red;">*</span></label>
                    <input type="datetime-local" id="data_aplicacio" name="data_aplicacio" value="<?= $data_actual; ?>"
                        onchange="actualitzarInfo()" required>
                </div>

                <div class="grup-camp">
                    <label for="producte_quimic">Producte Fitosanitari / Adob <span style="color: red;">*</span></label>
                    <select id="producte_quimic" name="producte_quimic" onchange="actualitzarInfo()" required>
                        <option value="0" selected disabled>--- Selecciona un producte ---</option>
                        <?php foreach ($productes_disponibles as $producte): ?>
                            <option value="<?= $producte['id']; ?>" data-dosi-recomanada="<?= $producte['dosi']; ?>"
                                data-unitat="<?= $producte['unitat']; ?>" data-phi="<?= $producte['phi_dies']; ?>">
                                <?= htmlspecialchars($producte['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grup-camp">
                    <label for="dosi">Dosi Aplicada (Per Hectàrea) <span style="color: red;">*</span></label>
                    <input type="number" step="0.01" id="dosi" name="dosi" placeholder="Ex: 1.5"
                        oninput="actualitzarInfo()" required>
                    <small style="color: #999;">Unitat per hectàrea: <span id="unitat_dosi">L/ha</span></small>
                </div>

                <div class="grup-camp">
                    <label for="quantitat_total">Quantitat Total a Aplicar (Lectura)</label>
                    <input type="text" id="quantitat_total" name="quantitat_total" class="camp-lectura" readonly
                        placeholder="Calculant... (Dosi x Superfície)">
                </div>

                <div id="alerta-phi" class="alerta-container">
                    <i class="fas fa-triangle-exclamation"></i>
                    **ALERTA CRÍTICA: TERMINI DE SEGURETAT (PHI) NO COMPLERT.**
                    <br>El producte requereix **<span id="phi-requerit">X</span> dies** de PHI, però la collita és en
                    **<span id="dies-restants">Y</span> dies** des de l'aplicació.
                </div>

                <div class="grup-camp col-span-2">
                    <label for="comentaris">Comentaris / Condicions d'Aplicació (Opcional)</label>
                    <textarea id="comentaris" name="comentaris" rows="3"
                        placeholder="Ex: Humitat alta, aplicació amb poc vent..."></textarea>
                </div>

                <div class="grup-camp col-span-2">
                    <input type="hidden" name="operari" value="Encarregat de Camp">
                    <button type="submit" class="boto-enviar">
                        <i class="fas fa-check-circle"></i> Registrar Tractament
                    </button>
                </div>
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

    <script>
        const SECTORS_DATA = <?= $sectors_json; ?>;
        const PRODUCTES_DATA = <?= $productes_json; ?>;

        // Referències als elements del DOM
        const sectorSelect = document.getElementById('parcela_aplicacio');
        const producteSelect = document.getElementById('producte_quimic');
        const dosiInput = document.getElementById('dosi');
        const dataAplicacioInput = document.getElementById('data_aplicacio');
        const quantitatTotalInput = document.getElementById('quantitat_total');
        const superficieMostrada = document.getElementById('superficie_mostrada');
        const unitatDosiSpan = document.getElementById('unitat_dosi');
        const alertaPhiDiv = document.getElementById('alerta-phi');
        const phiRequeritSpan = document.getElementById('phi-requerit');
        const diesRestantsSpan = document.getElementById('dies-restants');


        /**
         * Funció que recalcula la quantitat total i realitza la validació PHI
         */
        function actualitzarInfo() {
            const sectorId = sectorSelect.value;
            const producteId = producteSelect.value;
            let dosi = parseFloat(dosiInput.value) || 0;

            // Reiniciar estats
            superficieMostrada.textContent = '--';
            quantitatTotalInput.value = 'Calculant...';
            unitatDosiSpan.textContent = 'L/ha';
            alertaPhiDiv.style.display = 'none';

            let superficie = 0;
            let collitaPrevista = null;
            let phiDies = 0;
            let unitat = 'L/ha';

            // 1. Obtenció de dades del Sector
            if (SECTORS_DATA[sectorId]) {
                superficie = SECTORS_DATA[sectorId].superficie_ha;
                collitaPrevista = new Date(SECTORS_DATA[sectorId].collita_previsio + 'T00:00:00');
                superficieMostrada.textContent = superficie.toFixed(2);
            }

            // 2. Obtenció de dades del Producte i pre-omplir Dosi si cal
            if (PRODUCTES_DATA[producteId]) {
                phiDies = parseInt(PRODUCTES_DATA[producteId].phi_dies, 10);
                unitat = PRODUCTES_DATA[producteId].unitat;
                unitatDosiSpan.textContent = unitat;

                const dosiRecomanada = parseFloat(PRODUCTES_DATA[producteId].dosi) || 0;

                // Només pre-omplim si l'usuari no ha escrit res encara
                if (dosiInput.value === "" && dosiRecomanada > 0) {
                    dosiInput.value = dosiRecomanada.toFixed(2);
                    dosi = dosiRecomanada; // Utilitzem el valor pre-omplert pel càlcul
                }
            }

            // 3. CÀLCUL DE QUANTITAT TOTAL (Aquesta part estava bé)
            if (dosi > 0 && superficie > 0) {
                const quantitatTotal = dosi * superficie;
                // Mostrem la quantitat total amb la unitat (només la part abans de '/')
                quantitatTotalInput.value = `${quantitatTotal.toFixed(2)} ${unitat.split('/')[0]}`;
            } else {
                quantitatTotalInput.value = '0.00';
            }


            // 4. Validació PHI (Termini de Seguretat) (Aquesta lògica és independent del càlcul)
            if (phiDies > 0 && collitaPrevista && dataAplicacioInput.value) {
                const dataAplicacio = new Date(dataAplicacioInput.value);
                const dataSegura = new Date(dataAplicacio);
                dataSegura.setDate(dataSegura.getDate() + phiDies);

                if (dataSegura >= collitaPrevista) {
                    const msPerDay = 1000 * 60 * 60 * 24;
                    const tempsRestantMS = collitaPrevista.getTime() - dataAplicacio.getTime();
                    const diesRestants = Math.ceil(tempsRestantMS / msPerDay);

                    phiRequeritSpan.textContent = phiDies;
                    diesRestantsSpan.textContent = diesRestants;
                    alertaPhiDiv.style.display = 'block';
                }
            }
        }

        // Funció de validació per al formulari
        function validarTractament() {
            actualitzarInfo(); // Recalculem/validem per última vegada
            const alertaVisible = document.getElementById('alerta-phi').style.display === 'block';

            if (alertaVisible) {
                alert("ALERTA: El Termini de Seguretat (PHI) no es compleix! No es pot registrar el tractament.");
                return false;
            }
            return true;
        }

        // Inicialització: Selecciona els primers valors vàlids i executa el càlcul.
        document.addEventListener('DOMContentLoaded', () => {
            // Selecciona Sector i Producte a l'índex 1 per iniciar els càlculs, si no hi ha cap valor escollit
            if (sectorSelect.selectedIndex === 0 && sectorSelect.options.length > 1) {
                sectorSelect.selectedIndex = 1;
            }
            if (producteSelect.selectedIndex === 0 && producteSelect.options.length > 1) {
                producteSelect.selectedIndex = 1;
            }

            // Afegir escoltadors d'esdeveniments per assegurar la reactivitat del camp 'dosi'
            dosiInput.addEventListener('input', actualitzarInfo);

            // Executar la funció inicialment
            actualitzarInfo();
        });
    </script>
    <script src="scripts.js"></script>
</body>

</html>