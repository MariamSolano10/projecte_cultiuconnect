<?php
// nou_producte.php - Formulari de Registre de Nou Producte

// Inclou la funció de connexió a la base de dades
include 'db_connect.php'; 

// --- LÒGICA PER REBRE EL MISSATGE DE RETORN DEL PROCESSAMENT ---
$missatge_estat = null;
$estat_classe = null; 

if (isset($_GET['missatge']) && isset($_GET['estat'])) {
    $missatge_estat = htmlspecialchars($_GET['missatge']);
    $estat_classe = htmlspecialchars($_GET['estat']);
}
$error_connexio = null; 
?>

<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Registrar Nou Producte</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color per a Inventari (Blau) */
        :root {
            --color-principal: #1E4620;
            --color-secundari: #4CAF50;
            --color-primary-fosc: #143116;
            --color-accent-blau: #3498db; 
            --color-card-fons: white;
            --color-text-fosc: #333;
            --color-footer-fosc: rgba(30, 70, 32, 0.9);
            --color-footer-text: #ddd;
            
            /* S'AUGMENTA L'ALÇADA ESTIMADA DEL FOOTER PER SEGURETAT */
            --footer-height: 250px; 
            --header-height: 80px; /* Assumint una alçada estàndard del header */
        }

        /* 1. Estils del Fons i Contingut General */
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #333;
            background-image: url('fons_sectors.jpg');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
            padding-bottom: 0; 
        }

        /* Ajustem main per forçar el creixement */
        main.contingut-nou-analisi {
            flex-grow: 1;
            max-width: 1100px;
            /* Assegurem que el MAIN sigui almenys l'alçada de la finestra menys el header i el footer */
            min-height: calc(100vh - var(--header-height) - 10px); 
            padding: 105px 40px 40px 40px; 
            margin: 0 auto;
            background-color: transparent;
            box-shadow: none;
            color: white;
            /* Marge inferior per assegurar que el footer no tapa el contingut */
            margin-bottom: var(--footer-height); 
        }
        
        /* Ajustos per a pantalles mòbils (on el footer ocupa més espai verticalment) */
        @media (max-width: 900px) {
            :root {
                --footer-height: 350px; /* Augmentem l'estimació del footer per a pantalles mitjanes */
            }
            main.contingut-nou-analisi {
                margin-bottom: var(--footer-height); 
            }
        }
        @media (max-width: 600px) {
            :root {
                --footer-height: 450px; /* Augmentem l'estimació del footer per a mòbils */
            }
            main.contingut-nou-analisi {
                margin-bottom: var(--footer-height); 
            }
        }

        /* 2. Estil del Títol */
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
        
        /* Icona del títol en color Blau */
        .títol-pàgina i {
            color: var(--color-accent-blau) !important;
        }

        /* 3. Contenidor Formulari */
        .contenidor-formulari-bloc {
            max-width: 900px; 
            margin: 0 auto 30px auto; 
            padding: 40px;
            background-color: var(--color-card-fons);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            color: var(--color-text-fosc);
        }

        /* Estils del Formulari (Grid de dues columnes) */
        .formulari-producte {
            display: grid;
            grid-template-columns: 1fr 1fr; 
            gap: 20px;
        }

        /* Grup de camps que ocupen tota la línia */
        .grup-camp.col-span-2 {
            grid-column: 1 / span 2;
        }
        
        /* Separadors */
        .separador {
            grid-column: 1 / span 2;
            margin: 10px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #ddd;
            color: var(--color-principal);
            font-weight: bold;
            font-size: 1.1em;
        }

        /* Estils de camps genèrics */
        input[type="text"],
        input[type="number"],
        select,
        textarea {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            width: 100%;
        }

        .grup-camp {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        label {
            font-weight: bold;
            color: var(--color-text-fosc);
        }
        
        /* Botó d'enviament en Blau */
        .boto-enviar {
            background-color: var(--color-accent-blau);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s;
            grid-column: 1 / span 2;
            margin-top: 15px;
        }

        .boto-enviar:hover {
            background-color: #2980b9;
        }
        
        /* Estils de missatge d'estat */
        .alerta-resposta {
            max-width: 900px;
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
        .alerta-exit {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        /* 5. FOOTER (Sempre fixat a la part inferior visual) */
        .peu-app {
            position: relative; /* Hem de treure 'fixed' si no volem que surti del flux */
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
                <li class="actiu"><a href="productes_quimics.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
                <li><a href="monitoratge.php"><i class="fas fa-bug"></i> Monitoratge</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-nou-analisi">
        <h1 class="títol-pàgina">
            <i class="fas fa-vial"></i>
            Registre de Nou Producte Químic/Fertilitzant
        </h1>
        <p style="margin-bottom: 30px; color: #ccc">Introdueix les dades d'un nou producte que desitgis utilitzar o gestionar en l'inventari.</p>

        <?php if ($missatge_estat): ?>
            <div class="alerta-resposta alerta-<?= $estat_classe; ?>">
                <i class="fas fa-<?= ($estat_classe === 'exit' ? 'check-circle' : 'circle-xmark'); ?>"></i>
                <?= $missatge_estat; ?>
            </div>
        <?php endif; ?>

        <div class="contenidor-formulari-bloc">

            <form action="processar_producte.php" method="POST" class="formulari-producte">

                <div class="separador">Identificació del Producte</div>

                <div class="grup-camp col-span-2">
                    <label for="nom_comercial">Nom Comercial <span style="color: red;">*</span></label>
                    <input type="text" id="nom_comercial" name="nom_comercial" placeholder="Ex: Fungicida SPRINT-750, Fertilitzant NPK 15-5-10" required>
                </div>

                <div class="grup-camp">
                    <label for="fabricant">Fabricant / Marca</label>
                    <input type="text" id="fabricant" name="fabricant" placeholder="Ex: Syngenta, FertiCrop">
                </div>

                <div class="grup-camp">
                    <label for="registre_oficial">Nº Registre Oficial (Fitosanitari)</label>
                    <input type="text" id="registre_oficial" name="registre_oficial" placeholder="Ex: ES-00001">
                </div>

                <div class="separador">Tipus i Composició</div>

                <div class="grup-camp">
                    <label for="tipus_producte">Tipus de Producte <span style="color: red;">*</span></label>
                    <select id="tipus_producte" name="tipus_producte" required>
                        <option value="" selected disabled>--- Selecciona un tipus ---</option>
                        <option value="Fitosanitari_Fungicida">Fitosanitari (Fungicida)</option>
                        <option value="Fitosanitari_Insecticida">Fitosanitari (Insecticida)</option>
                        <option value="Fitosanitari_Herbicida">Fitosanitari (Herbicida)</option>
                        <option value="Fertilitzant_Simple">Fertilitzant (Simple)</option>
                        <option value="Fertilitzant_Compost">Fertilitzant (Compost)</option>
                        <option value="Producte_Generic">Producte Genèric/Corrector</option>
                    </select>
                </div>

                <div class="grup-camp">
                    <label for="unitat_mesura">Unitat de Compra <span style="color: red;">*</span></label>
                    <select id="unitat_mesura" name="unitat_mesura" required>
                        <option value="L">Litres (L)</option>
                        <option value="Kg">Quilograms (Kg)</option>
                        <option value="Unitat">Unitat (Ud)</option>
                    </select>
                </div>
                
                <div class="grup-camp col-span-2">
                    <label for="composicio">Composició (Matèria activa o garantida)</label>
                    <textarea id="composicio" name="composicio" placeholder="Ex: 50% Abamectina, 10% Fosfat de Potassi, 15-5-10 NPK..."></textarea>
                </div>

                <div class="separador">Preu i Estoc Inicial</div>
                
                <div class="grup-camp">
                    <label for="preu_compra">Preu de Compra (€/Unitat)</label>
                    <input type="number" step="0.01" id="preu_compra" name="preu_compra" placeholder="Ex: 12.50">
                </div>
                
                <div class="grup-camp">
                    <label for="estoc_inicial">Estoc Inicial (Quantitat)</label>
                    <input type="number" step="0.01" id="estoc_inicial" name="estoc_inicial" placeholder="Ex: 200 (L/Kg/Ud)">
                </div>

                <div class="grup-camp col-span-2">
                    <button type="submit" class="boto-enviar">
                        <i class="fas fa-box-open"></i> Registrar i Afegir a Inventari
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