<?php
// nou_analisi.php - Formulari de Registre de Nova Anàlisi de Laboratori

// Inclou la funció de connexió a la base de dades
include 'db_connect.php'; 

// --- LÒGICA PER A OBTENIR SECTORS ---
$llistat_sectors = [];
$error_connexio = null;
$data_actual = date('Y-m-d'); // Format de Data simple HTML5

try {
    $pdo = connectDB();

    // Consulta per obtenir Sectors actius (amb dades de varietat i superfície)
    $sql_sectors = "
        SELECT 
            T7.id_sector AS id, 
            T7.nom AS codi_sector,
            T6.nom_varietat,
            SUM(T8.superficie_m2) / 10000 AS superficie_ha
        FROM 
            Sector T7
        INNER JOIN 
            Plantacio T10 ON T7.id_sector = T10.id_sector
        INNER JOIN 
            Varietat T6 ON T10.id_varietat = T6.id_varietat
        INNER JOIN 
            Parcela_Sector T8 ON T7.id_sector = T8.id_sector
        WHERE 
            T10.data_arrencada IS NULL 
        GROUP BY
            T7.id_sector, T7.nom, T6.nom_varietat
        ORDER BY 
            T7.nom ASC;
    ";
    $stmt_sectors = $pdo->prepare($sql_sectors);
    $stmt_sectors->execute();
    $llistat_sectors = $stmt_sectors->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Si hi ha un error de connexió, es captura
    $error_connexio = "❌ Error de connexió a la base de dades: " . htmlspecialchars($e->getMessage());
    $llistat_sectors = [];
}

// --- LÒGICA PER REBRE EL MISSATGE DE RETORN DEL PROCESSAMENT ---
$missatge_estat = null;
$estat_classe = null; 

if (isset($_GET['missatge']) && isset($_GET['estat'])) {
    $missatge_estat = htmlspecialchars($_GET['missatge']);
    $estat_classe = htmlspecialchars($_GET['estat']);
}
?>

<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Registrar Anàlisi</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color */
        :root {
            --color-principal: #1E4620;
            --color-secundari: #4CAF50;
            --color-primary-fosc: #143116;
            --color-accent-lila: #9b59b6;
            --color-card-fons: white;
            --color-text-fosc: #333;
            --color-footer-fosc: rgba(30, 70, 32, 0.9);
            --color-footer-text: #ddd;
        }

        /* 1. ESTILS DEL FONS DE LA PÀGINA I CONTINGUT GENERAL */
        body {
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            padding-bottom: 0; 
            background-color: #333;
            background-image: url('fons_sectors.jpg');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
        }

        /* Ajustem main per forçar el creixement */
        main.contingut-nou-analisi {
            flex-grow: 1; 
            max-width: 1100px;
            
            /* VALOR MÀXIM DE SEGURETAT: 1400px per garantir que el footer estigui al final */
            min-height: 1300px; 
            
            padding: 105px 40px 40px 40px; 
            margin: 0 auto;
            background-color: transparent;
            box-shadow: none;
            color: white;
        }

        /* 2. ESTIL DEL TÍTOL (Rectangular clar amb ombra) */
        .títol-pàgina {
            max-width: 700px;
            margin: 0 auto 20px auto;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            color: var(--color-principal, #1E4620);
            text-align: center;
        }
        
        .títol-pàgina i {
            color: var(--color-accent-lila) !important;
        }


        /* 3. CONTENIDOR: El BLOC BLANC que encapsula el formulari */
        .contenidor-formulari-bloc {
            max-width: 1000px;
            margin: 0 auto 30px auto; 
            padding: 40px;
            background-color: var(--color-card-fons);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            color: var(--color-text-fosc);
        }

        /* Estils del Formulari (Grid complex) */
        .formulari-analisi {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 20px;
        }
        
        /* Grup de Camps amb estil flexbox per defecte */
        .grup-camp {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        /* Camps que han d'ocupar el total o dues columnes */
        .grup-camp.col-span-3 {
            grid-column: 1 / span 3;
        }
        
        .grup-camp.col-span-2 {
            grid-column: 1 / span 2;
        }

        label {
            font-weight: bold;
            color: var(--color-text-fosc);
        }

        input[type="text"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            width: 100%;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Separadors */
        .separador {
            grid-column: 1 / span 3;
            margin: 10px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #ddd;
            color: var(--color-principal);
            font-weight: bold;
            font-size: 1.1em;
        }


        .boto-enviar {
            background-color: var(--color-accent-lila);
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
            background-color: #8e44ad;
        }
        
        /* 4. ESTILS DE MISSATGE D'ESTAT */
        .alerta-resposta {
            max-width: 1000px;
            margin: 20px auto;
            padding: 15px 20px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .alerta-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        /* 5. FOOTER: ESTIL NATURAL (Després del contingut) */
        .peu-app {
            position: relative; 
            
            background-color: var(--color-footer-fosc);
            color: var(--color-footer-text);
            padding: 30px 0 15px 0;
            width: 100%;
            font-size: 0.9em;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.5);
            margin-top: auto; 
            z-index: 1;
        }

        .contingut-footer {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 30px;
            text-align: left;
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
                <li class="actiu"><a href="analisis_lab.php"><i class="fas fa-flask"></i> Anàlisis</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-nou-analisi">
        <h1 class="títol-pàgina">
            <i class="fas fa-flask"></i>
            Registre de Nova Analítica de Laboratori
        </h1>
        <p style="margin-bottom: 30px; color: #ccc">Introdueix tots els paràmetres rellevants obtinguts de l'informe
            del laboratori.</p>

        <?php if ($missatge_estat): ?>
            <div class="alerta-resposta alerta-<?= $estat_classe; ?>">
                <i class="fas fa-<?= ($estat_classe === 'exit' ? 'check-circle' : 'circle-xmark'); ?>"></i>
                <?= $missatge_estat; ?>
            </div>
        <?php endif; ?>

        <div class="contenidor-formulari-bloc">

            <?php if ($error_connexio): ?>
                <div class="alerta-resposta alerta-error" style="display: block;">
                    <?= $error_connexio; ?>
                </div>
            <?php endif; ?>

            <form action="processar_analisi.php" method="POST" class="formulari-analisi">

                <div class="separador">Detalls Bàsics</div>

                <div class="grup-camp col-span-2">
                    <label for="id_sector">Sector Analitzat <span style="color: red;">*</span></label>
                    <select id="id_sector" name="id_sector" required>
                        <option value="" selected disabled>--- Selecciona un sector ---</option>
                        <?php foreach ($llistat_sectors as $sector): ?>
                            <option value="<?= $sector['id']; ?>">
                                <?= htmlspecialchars($sector['codi_sector'] . ' - ' . $sector['nom_varietat'] . ' (' . number_format((float)$sector['superficie_ha'], 2, ',', '.') . ' ha)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grup-camp">
                    <label for="tipus_mostra">Tipus de Mostra <span style="color: red;">*</span></label>
                    <select id="tipus_mostra" name="tipus_mostra" required>
                        <option value="Sòl">Sòl</option>
                        <option value="Fulla">Fulla (Folial)</option>
                        <option value="Aigua">Aigua (Reg)</option>
                    </select>
                </div>

                <div class="grup-camp">
                    <label for="data_mostreig">Data de Mostreig <span style="color: red;">*</span></label>
                    <input type="date" id="data_mostreig" name="data_mostreig" value="<?= $data_actual; ?>"
                        required>
                </div>

                <div class="grup-camp">
                    <label for="data_informe">Data de l'Informe (Recepció)</label>
                    <input type="date" id="data_informe" name="data_informe">
                </div>
                
                <div class="grup-camp">
                    <label for="laboratori">Laboratori</label>
                    <input type="text" id="laboratori" name="laboratori" placeholder="Ex: Agrolab S.L.">
                </div>

                <div class="separador">Paràmetres Clau (Unitats típiques)</div>

                <div class="grup-camp">
                    <label for="ph">pH (Unitats)</label>
                    <input type="number" step="0.01" id="ph" name="ph" placeholder="Ex: 6.8">
                </div>

                <div class="grup-camp">
                    <label for="mo_percent">Matèria Orgànica (%)</label>
                    <input type="number" step="0.01" id="mo_percent" name="mo_percent" placeholder="Ex: 2.5">
                </div>

                <div class="grup-camp">
                    <label for="ce_ds_m">Conductivitat Elèctrica (dS/m)</label>
                    <input type="number" step="0.01" id="ce_ds_m" name="ce_ds_m" placeholder="Ex: 0.95">
                </div>

                <div class="grup-camp">
                    <label for="n_ppm">Nitrogen (N total ppm)</label>
                    <input type="number" step="0.1" id="n_ppm" name="n_ppm" placeholder="Ex: 150">
                </div>

                <div class="grup-camp">
                    <label for="p_ppm">Fòsfor (P2O5 ppm)</label>
                    <input type="number" step="0.1" id="p_ppm" name="p_ppm" placeholder="Ex: 35">
                </div>

                <div class="grup-camp">
                    <label for="k_ppm">Potassi (K2O ppm)</label>
                    <input type="number" step="0.1" id="k_ppm" name="k_ppm" placeholder="Ex: 420">
                </div>
                
                <div class="separador">Resultats i Recomanacions</div>

                <div class="grup-camp col-span-3">
                    <label for="descripcio_nutricional">Resum / Estat Nutricional Final <span style="color: red;">*</span></label>
                    <input type="text" id="descripcio_nutricional" name="descripcio_nutricional"
                        placeholder="Ex: Lleuger dèficit de K, pH correcte, Matèria Orgànica baixa..." required>
                </div>

                <div class="grup-camp col-span-3">
                    <label for="recomanacions">Recomanacions del Tècnic</label>
                    <textarea id="recomanacions" name="recomanacions"
                        placeholder="Ex: Aplicar 50 kg/ha de Sulfat Potàssic al mes vinent..."></textarea>
                </div>


                <div class="grup-camp col-span-3">
                    <button type="submit" class="boto-enviar">
                        <i class="fas fa-floppy-disk"></i> Guardar Analítica
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
</body>

</html>