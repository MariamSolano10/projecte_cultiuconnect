<?php
// nou_lot.php - Formulari per registrar una nova entrada de Lot d'Inventari (Estoc)

include 'db_connect.php'; 

$productes_quimics = [];
$error_connexio = null;

try {
    $pdo = connectDB();

    // 1. Carregar els Productes Químics disponibles per al <select>
    $sql_productes = "
        SELECT 
            id_producte, 
            nom_comercial, 
            tipus, 
            unitat_mesura
        FROM 
            Producte_Quimic
        ORDER BY 
            nom_comercial ASC;
    ";
    
    $stmt_productes = $pdo->prepare($sql_productes);
    $stmt_productes->execute();
    $productes_quimics = $stmt_productes->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_connexio = "❌ Error en carregar les dades inicials: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Registrar Nou Lot</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estils específics per al formulari */
        :root {
            --color-principal: #1E4620;
            --color-secundari: #4CAF50;
            --color-fons-clar: #f4f4f4;
        }

        body {
            background-color: var(--color-fons-clar);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex-grow: 1;
            max-width: 900px;
            margin: 0 auto;
            padding: 105px 20px 40px 20px;
            width: 100%;
        }

        .contenidor-formulari {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .titol-formulari {
            color: var(--color-principal);
            border-bottom: 2px solid var(--color-secundari);
            padding-bottom: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        .grup-camp {
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .camp-simple {
            grid-column: 1 / 3;
            /* Ocupa les dues columnes */
        }
        
        .camp-doble {
            /* Ocupa una columna */
            display: flex;
            flex-direction: column;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }

        select {
            appearance: none;
            /* Elimina l'estil per defecte de fletxa en alguns navegadors */
            background-color: white;
            /* Per assegurar un fons blanc en el desplegable */
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236c757d'%3e%3cpath d='M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px top 50%;
            background-size: 12px;
        }

        .unitat-mesura-display {
            display: inline-block;
            margin-left: 10px;
            font-weight: bold;
            color: var(--color-principal);
        }

        .boto-submit {
            grid-column: 1 / 3;
            text-align: center;
            margin-top: 30px;
        }

        button[type="submit"] {
            background-color: var(--color-secundari);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        button[type="submit"]:hover {
            background-color: #388e3c;
        }

        @media (max-width: 600px) {
            .grup-camp {
                grid-template-columns: 1fr;
            }
            .camp-simple {
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
                <li class="actiu"><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main>
        <div class="contenidor-formulari">
            <h1 class="titol-formulari">
                <i class="fas fa-box-open"></i> Registrar Nova Entrada de Lot (Estoc)
            </h1>

            <?php if ($error_connexio): ?>
                <div class="alerta-error" style="padding: 15px; background-color: #f8d7da; color: #721c24; border-radius: 5px; margin-bottom: 20px;">
                    <?= $error_connexio; ?>
                </div>
            <?php elseif (empty($productes_quimics)): ?>
                 <div class="alerta-atencio" style="padding: 15px; background-color: #fff3cd; color: #856404; border-radius: 5px; margin-bottom: 20px; text-align: center;">
                    ⚠️ No hi ha **Productes Químics** registrats. Si us plau, registreu els productes bàsics al catàleg abans de registrar-ne l'estoc.
                </div>
            <?php else: ?>
                <form action="processar_nou_lot.php" method="POST">
                    
                    <div class="camp-simple">
                        <label for="id_producte">1. Producte Químic / Fertilitzant</label>
                        <select id="id_producte" name="id_producte" required>
                            <option value="">Selecciona un producte...</option>
                            <?php foreach ($productes_quimics as $prod): ?>
                                <option 
                                    value="<?= $prod['id_producte']; ?>"
                                    data-tipus="<?= htmlspecialchars($prod['tipus']); ?>"
                                    data-unitat="<?= htmlspecialchars($prod['unitat_mesura']); ?>"
                                >
                                    <?= htmlspecialchars($prod['nom_comercial']); ?> (<?= htmlspecialchars($prod['tipus']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display: block; margin-top: 5px; color: #777;">* El producte ha d'estar prèviament definit al catàleg.</small>
                    </div>

                    <div class="grup-camp">
                        <div class="camp-doble">
                            <label for="num_lot">2. Número de Lot</label>
                            <input type="text" id="num_lot" name="num_lot" placeholder="Ex: L202410-A" required maxlength="100">
                        </div>
                        
                        <div class="camp-doble">
                            <label for="quantitat_disponible">3. Quantitat Entrant</label>
                            <input type="number" id="quantitat_disponible" name="quantitat_disponible" step="0.01" min="0" placeholder="Ex: 50.00" required>
                            <small id="unitat_display" class="unitat-mesura-display" style="color: #388e3c; font-weight: bold; margin-top: 5px;">Unitat: L/Kg</small>
                        </div>
                    </div>

                    <div class="grup-camp">
                        <div class="camp-doble">
                            <label for="data_caducitat">4. Data de Caducitat (Opcional)</label>
                            <input type="date" id="data_caducitat" name="data_caducitat">
                        </div>
                        
                        <div class="camp-doble">
                            <label for="data_compra">5. Data de Compra / Entrada</label>
                            <input type="date" id="data_compra" name="data_compra" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="grup-camp">
                        <div class="camp-doble">
                            <label for="ubicacio_magatzem">6. Ubicació al Magatzem</label>
                            <input type="text" id="ubicacio_magatzem" name="ubicacio_magatzem" placeholder="Ex: Estanteria 3B" maxlength="100">
                        </div>
                        
                        <div class="camp-doble">
                            <label for="preu_adquisicio">7. Preu d'Adquisició (€)</label>
                            <input type="number" id="preu_adquisicio" name="preu_adquisicio" step="0.01" min="0" placeholder="Ex: 150.50">
                        </div>
                    </div>

                    <div class="camp-simple">
                        <label for="proveidor">8. Proveïdor</label>
                        <input type="text" id="proveidor" name="proveidor" placeholder="Nom de l'empresa proveïdora" maxlength="100">
                    </div>

                    <div class="boto-submit">
                        <button type="submit">
                            <i class="fas fa-save"></i> Registrar Lot al Magatzem
                        </button>
                    </div>

                </form>
            <?php endif; ?>
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
        // Script per actualitzar la unitat de mesura en funció del producte seleccionat
        document.addEventListener('DOMContentLoaded', function() {
            const selectProducte = document.getElementById('id_producte');
            const unitatDisplay = document.getElementById('unitat_display');

            function actualitzarUnitat() {
                const selectedOption = selectProducte.options[selectProducte.selectedIndex];
                const unitat = selectedOption.getAttribute('data-unitat');
                
                if (unitat) {
                    unitatDisplay.textContent = 'Unitat: ' + unitat;
                } else {
                    unitatDisplay.textContent = 'Unitat: (No Seleccionat)';
                }
            }

            selectProducte.addEventListener('change', actualitzarUnitat);
            // Crida inicial per si ja hi ha una opció seleccionada (encara que en aquest cas no n'hi ha)
            actualitzarUnitat();
        });
    </script>
</body>

</html>