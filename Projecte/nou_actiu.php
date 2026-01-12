<?php
// nou_actiu.php - Formulari per Registrar un Nou Actiu/Inversió

// Inclusió de la lògica de connexió.
include 'db_connect.php';

$missatge_error = '';
$missatge_succes = '';

// --- Lògica de Processament del Formulari (Simulada) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Recollida i sanitització de dades
    $nom_actiu = filter_input(INPUT_POST, 'nom_actiu', FILTER_SANITIZE_STRING);
    $data_compra = filter_input(INPUT_POST, 'data_compra', FILTER_SANITIZE_STRING);
    $valor_inicial = filter_input(INPUT_POST, 'valor_inicial', FILTER_VALIDATE_FLOAT);
    $vida_util_anys = filter_input(INPUT_POST, 'vida_util_anys', FILTER_VALIDATE_INT);
    $manteniment_anual = filter_input(INPUT_POST, 'manteniment_anual', FILTER_VALIDATE_FLOAT);

    // 2. Validació (mínima)
    if (empty($nom_actiu) || !$valor_inicial || !$vida_util_anys) {
        $missatge_error = "Error: Tots els camps obligatoris (*) han de ser omplerts i ser vàlids.";
    } elseif ($valor_inicial <= 0 || $vida_util_anys <= 0) {
        $missatge_error = "Error: El Valor Inicial i la Vida Útil han de ser positius.";
    } else {
        // 3. Simulació de Càlculs (Mètode Lineal)
        $amortitzacio_anual = $valor_inicial / $vida_util_anys;
        $manteniment_anual_real = $manteniment_anual ?: 0; // Si és null, posa 0

        // 4. Simulació d'Inserció a la BBDD
        // Aquí s'executaria un INSERT INTO Actius_Immobilitzat (nom, data, valor, vida_util...)
        
        $missatge_succes = "✅ Actiu **" . htmlspecialchars($nom_actiu) . "** registrat correctament. Amortització Anual calculada: **" . number_format($amortitzacio_anual, 2) . " €**.";

        // Reiniciar variables per netejar el formulari si l'èxit és sense redirecció
        unset($nom_actiu, $data_compra, $valor_inicial, $vida_util_anys, $manteniment_anual);
    }
}
// -----------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Registrar Nou Actiu</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color (reforçades) */
        :root {
            --color-principal: #1E4620;
            --color-secundari: #4CAF50;
            --color-accent-taronja: #FF9800;
            --color-accent-blau: #3498db;
            --color-card-fons: white;
            --color-text-fosc: #333;
            --color-error: #e74c3c;
            --color-succes: #4CAF50;
        }

        /* 1. ESTILS DEL FONS DE LA PÀGINA I CONTINGUT GENERAL */
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

        /* Contenidor Principal (Centrat i Compensació de Header) */
        main.contingut-nou-actiu {
            flex-grow: 1;
            max-width: 800px; /* Ample reduït per al formulari */
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            color: var(--color-text-fosc);
        }

        /* 2. ESTIL DEL TÍTOL RECTANGULAR BLANC */
        .títol-registre {
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            color: var(--color-principal, #1E4620);
            text-align: center;
            margin-bottom: 30px;
        }

        .títol-registre i {
            color: var(--color-principal) !important;
            margin-right: 10px;
        }
        
        /* 3. CONTENIDOR DEL FORMULARI (Targeta Blanca) */
        .contenidor-formulari {
            background-color: var(--color-card-fons);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        /* 4. ESTILS DEL FORMULARI */
        .quadricula-camps {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .camp-complet {
            grid-column: 1 / span 2;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--color-text-fosc);
        }

        input[type="text"],
        input[type="date"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
            margin-bottom: 10px;
        }

        input[type="number"] {
            /* Aliniació dreta per a valors monetaris */
            text-align: right; 
        }

        /* Botó Principal d'Enviament */
        .boto-enviar {
            width: 100%;
            padding: 15px;
            background-color: var(--color-secundari);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 15px;
        }

        .boto-enviar:hover {
            background-color: #449d48;
        }
        
        /* Missatges (Error/Succés) */
        .missatge {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
            border: 1px solid transparent;
        }

        .missatge-error {
            background-color: #fdd;
            color: var(--color-error);
            border-color: var(--color-error);
        }

        .missatge-succes {
            background-color: #dff0d8;
            color: var(--color-succes);
            border-color: var(--color-succes);
        }

        /* Enllaç de tornada */
        .enllac-tornar {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: var(--color-accent-blau);
            text-decoration: none;
            font-weight: bold;
        }
        
        /* Media Query: 1 columna en mòbils */
        @media (max-width: 600px) {
            .quadricula-camps {
                grid-template-columns: 1fr;
            }
            .camp-complet {
                grid-column: 1 / span 1;
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
                <li class="actiu"><a href="inversions.php"><i class="fas fa-euro-sign"></i> Finances</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-nou-actiu">
        <h1 class="títol-registre">
            <i class="fas fa-tractor"></i> Registrar Nou Actiu d'Immobilitzat
        </h1>

        <div class="contenidor-formulari">
            
            <?php if ($missatge_error): ?>
                <div class="missatge missatge-error">
                    <i class="fas fa-times-circle"></i> <?= $missatge_error; ?>
                </div>
            <?php endif; ?>

            <?php if ($missatge_succes): ?>
                <div class="missatge missatge-succes">
                    <i class="fas fa-check-circle"></i> <?= $missatge_succes; ?>
                </div>
            <?php endif; ?>

            <p style="margin-bottom: 25px; color: #666;">
                Introdueix les dades de l'actiu per calcular la seva càrrega d'amortització anual (mètode lineal).
            </p>

            <form method="POST" action="nou_actiu.php">
                <div class="quadricula-camps">
                    
                    <div class="camp-complet">
                        <label for="nom_actiu">Nom de l'Actiu *</label>
                        <input type="text" id="nom_actiu" name="nom_actiu" placeholder="Ex: Tractor John Deere 5090R o Sistema Reg Degoteig P01" 
                               value="<?= htmlspecialchars($nom_actiu ?? ''); ?>" required>
                    </div>

                    <div>
                        <label for="valor_inicial">Valor Inicial (sense IVA) (€) *</label>
                        <input type="number" id="valor_inicial" name="valor_inicial" step="0.01" min="0.01" 
                               placeholder="Ex: 65000.00" value="<?= htmlspecialchars($valor_inicial ?? ''); ?>" required>
                    </div>
                    
                    <div>
                        <label for="vida_util_anys">Vida Útil Estimada (anys) *</label>
                        <input type="number" id="vida_util_anys" name="vida_util_anys" min="1" step="1" 
                               placeholder="Ex: 10" value="<?= htmlspecialchars($vida_util_anys ?? ''); ?>" required>
                    </div>

                    <div>
                        <label for="data_compra">Data de Compra *</label>
                        <input type="date" id="data_compra" name="data_compra" 
                               value="<?= htmlspecialchars($data_compra ?? date('Y-m-d')); ?>" required>
                    </div>
                    
                    <div>
                        <label for="manteniment_anual">Cost Manteniment Anual Estimat (€)</label>
                        <input type="number" id="manteniment_anual" name="manteniment_anual" step="0.01" min="0" 
                               placeholder="Ex: 1800.00 (Opcional)" value="<?= htmlspecialchars($manteniment_anual ?? ''); ?>">
                    </div>
                    
                </div>
                
                <button type="submit" class="boto-enviar">
                    <i class="fas fa-save"></i> Registrar Actiu i Calcular Amortització
                </button>
            </form>
            
            <a href="inversions.php" class="enllac-tornar">
                <i class="fas fa-arrow-left"></i> Tornar a la Llista d'Actius
            </a>
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