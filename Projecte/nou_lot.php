<?php
// nou_lot.php - Formulari i lògica per registrar l'entrada d'un nou lot a l'Inventari

include 'db_connect.php';

$missatge = '';
$missatge_error = '';
$productes_quimics = [];

try {
    $pdo = connectDB();

    // 1. OBTENIR PRODUCTES QUÍMICS PER AL DESPLEGABLE
    $sql_productes = "SELECT id_producte, nom_comercial, tipus FROM Producte_Quimic ORDER BY nom_comercial ASC";
    $stmt_productes = $pdo->prepare($sql_productes);
    $stmt_productes->execute();
    $productes_quimics = $stmt_productes->fetchAll(PDO::FETCH_ASSOC);

    // 2. PROCESSAMENT DEL FORMULARI (POST)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        
        // Validació i sanitització de les dades d'entrada
        $id_producte = filter_input(INPUT_POST, 'id_producte', FILTER_VALIDATE_INT);
        $num_lot = trim(filter_input(INPUT_POST, 'num_lot', FILTER_SANITIZE_STRING));
        $quantitat_disponible = filter_input(INPUT_POST, 'quantitat_disponible', FILTER_VALIDATE_FLOAT);
        $unitat_mesura = trim(filter_input(INPUT_POST, 'unitat_mesura', FILTER_SANITIZE_STRING));
        $data_caducitat = trim(filter_input(INPUT_POST, 'data_caducitat', FILTER_SANITIZE_STRING));
        $ubicacio_magatzem = trim(filter_input(INPUT_POST, 'ubicacio_magatzem', FILTER_SANITIZE_STRING));
        $data_compra = trim(filter_input(INPUT_POST, 'data_compra', FILTER_SANITIZE_STRING));
        $proveidor = trim(filter_input(INPUT_POST, 'proveidor', FILTER_SANITIZE_STRING));
        $preu_adquisicio = filter_input(INPUT_POST, 'preu_adquisicio', FILTER_VALIDATE_FLOAT);

        // Validació bàsica
        if (!$id_producte || empty($num_lot) || $quantitat_disponible === false || $quantitat_disponible <= 0 || empty($unitat_mesura) || empty($data_compra)) {
            $missatge_error = "❌ Si us plau, omple tots els camps obligatoris correctament (Producte, Lot, Quantitat, Unitat, Data de Compra).";
        } else {
            // Preparar la consulta d'inserció
            $sql_insert = "
                INSERT INTO Inventari_Estoc (
                    id_producte, num_lot, quantitat_disponible, unitat_mesura, data_caducitat, 
                    ubicacio_magatzem, data_compra, proveidor, preu_adquisicio
                ) VALUES (
                    :id_producte, :num_lot, :quantitat_disponible, :unitat_mesura, :data_caducitat, 
                    :ubicacio_magatzem, :data_compra, :proveidor, :preu_adquisicio
                )
            ";
            
            $stmt_insert = $pdo->prepare($sql_insert);

            // Ajustar la data de caducitat per acceptar NULL si està buida
            $caducitat_valor = empty($data_caducitat) ? NULL : $data_caducitat;

            // Executar la inserció
            $resultat_insercio = $stmt_insert->execute([
                ':id_producte' => $id_producte,
                ':num_lot' => $num_lot,
                ':quantitat_disponible' => $quantitat_disponible,
                ':unitat_mesura' => $unitat_mesura,
                ':data_caducitat' => $caducitat_valor,
                ':ubicacio_magatzem' => empty($ubicacio_magatzem) ? NULL : $ubicacio_magatzem,
                ':data_compra' => $data_compra,
                ':proveidor' => empty($proveidor) ? NULL : $proveidor,
                ':preu_adquisicio' => $preu_adquisicio === false ? NULL : $preu_adquisicio,
            ]);

            if ($resultat_insercio) {
                // Redirecció per evitar doble enviament
                header("Location: nou_lot.php?success=1");
                exit();
            } else {
                $missatge_error = "❌ Error en registrar el lot. Assegureu-vos que el Lot no existeixi ja per a aquest producte.";
            }
        }
    }

    // Comprovar si hi ha un missatge d'èxit després de la redirecció
    if (isset($_GET['success']) && $_GET['success'] == 1) {
        $missatge = "✅ Nou lot registrat correctament a l'inventari!";
    }

} catch (Exception $e) {
    $missatge_error = "❌ Error de connexió o processament: " . htmlspecialchars($e->getMessage());
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
        /* Estils CSS específics per a aquest formulari */
        :root {
            --color-principal: #1E4620;
            /* Verd Fosc */
            --color-secundari: #4CAF50;
            /* Verd Mitjà */
            --color-fons-formulari: rgba(255, 255, 255, 0.95);
            /* Blanc semitransparent */
            --color-sombra: rgba(0, 0, 0, 0.3);
            /* Ombra fosc */
        }

        /* 1. Disseny de la pàgina i contenidor principal */
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

        main.contingut-formulari {
            flex-grow: 1;
            max-width: 800px;
            margin: 105px auto 40px auto;
            padding: 40px;
            background-color: var(--color-fons-formulari);
            border-radius: 10px;
            box-shadow: 0 8px 20px var(--color-sombra);
            color: var(--color-text-fosc);
        }

        .titol-formulari {
            text-align: center;
            color: var(--color-principal);
            margin-bottom: 30px;
            border-bottom: 2px solid var(--color-secundari);
            padding-bottom: 10px;
        }

        /* 2. Estils del Formulari */
        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .camp-formulari {
            display: flex;
            flex-direction: column;
        }

        /* Camps que ocupen dues columnes (wide) */
        .camp-formulari.wide {
            grid-column: 1 / 3;
        }

        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--color-principal);
            font-size: 0.95em;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
            transition: border-color 0.3s;
            background-color: white;
            color: #333;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        select:focus {
            border-color: var(--color-secundari);
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.5);
            outline: none;
        }

        /* 3. Botó d'Enviar */
        .grup-boto {
            grid-column: 1 / 3;
            text-align: center;
            margin-top: 20px;
        }

        .boto-submit {
            padding: 12px 30px;
            background-color: var(--color-secundari);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            transition: background-color 0.3s, transform 0.1s;
        }

        .boto-submit:hover {
            background-color: var(--color-principal);
            transform: translateY(-1px);
        }

        /* 4. Missatges d'Estat */
        .missatge-estat {
            grid-column: 1 / 3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: bold;
            text-align: center;
        }

        .missatge-exit {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .missatge-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Estils per a pantalles petites */
        @media (max-width: 600px) {
            form {
                grid-template-columns: 1fr;
            }

            .camp-formulari.wide {
                grid-column: 1 / 2;
            }

            main.contingut-formulari {
                margin: 105px 20px 20px 20px;
                padding: 20px;
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

    <main class="contingut-formulari">
        <h1 class="titol-formulari">
            <i class="fas fa-box-open"></i> Registrar Entrada de Nou Lot
        </h1>

        <?php if ($missatge): ?>
            <div class="missatge-estat missatge-exit"><?= $missatge; ?></div>
        <?php endif; ?>

        <?php if ($missatge_error): ?>
            <div class="missatge-estat missatge-error"><?= $missatge_error; ?></div>
        <?php endif; ?>

        <form action="nou_lot.php" method="POST">
            <div class="camp-formulari wide">
                <label for="id_producte">Producte Químic *</label>
                <select id="id_producte" name="id_producte" required>
                    <option value="">-- Selecciona un producte --</option>
                    <?php if (empty($productes_quimics)): ?>
                         <option value="" disabled>No hi ha productes registrats. Afegeix-ne un primer!</option>
                    <?php else: ?>
                        <?php foreach ($productes_quimics as $producte): ?>
                            <option value="<?= $producte['id_producte']; ?>">
                                <?= htmlspecialchars($producte['nom_comercial']) . ' (' . $producte['tipus'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="camp-formulari">
                <label for="num_lot">Codi de Lot *</label>
                <input type="text" id="num_lot" name="num_lot" maxlength="100" required>
            </div>

            <div class="camp-formulari">
                <label for="quantitat_disponible">Quantitat *</label>
                <input type="number" id="quantitat_disponible" name="quantitat_disponible" step="0.01" min="0.01" required>
            </div>

            <div class="camp-formulari">
                <label for="unitat_mesura">Unitat de Mesura *</label>
                <select id="unitat_mesura" name="unitat_mesura" required>
                    <option value="">-- Selecciona --</option>
                    <option value="Kg">Kg (Quilograms)</option>
                    <option value="L">L (Litres)</option>
                    <option value="Unitat">Unitat/Paquet</option>
                </select>
            </div>

            <div class="camp-formulari">
                <label for="data_caducitat">Data de Caducitat</label>
                <input type="date" id="data_caducitat" name="data_caducitat">
            </div>
            
            <div class="camp-formulari">
                <label for="ubicacio_magatzem">Ubicació al Magatzem</label>
                <input type="text" id="ubicacio_magatzem" name="ubicacio_magatzem" maxlength="100">
            </div>

            <div class="camp-formulari">
                <label for="data_compra">Data de Compra *</label>
                <input type="date" id="data_compra" name="data_compra" required>
            </div>

            <div class="camp-formulari">
                <label for="proveidor">Proveïdor (Nom)</label>
                <input type="text" id="proveidor" name="proveidor" maxlength="100">
            </div>

            <div class="camp-formulari">
                <label for="preu_adquisicio">Preu d'Adquisició (€)</label>
                <input type="number" id="preu_adquisicio" name="preu_adquisicio" step="0.01" min="0">
            </div>
            
            <div class="camp-formulari wide">
                <p style="font-size: 0.9em; color: #555;">Els camps marcats amb * són obligatoris.</p>
            </div>

            <div class="grup-boto">
                <button type="submit" class="boto-submit">
                    <i class="fas fa-plus-circle"></i> Registrar Lot
                </button>
            </div>
        </form>

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