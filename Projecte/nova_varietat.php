<?php
// nova_varietat.php - Formulari d'Alta de Noves Varietats de Cultiu

// ATENCIÓ: ES NECESSITA QUE EL FITXER 'db_connect.php' ESTIGUI FUNCIONANT
include 'db_connect.php'; 

$missatge = '';
$classe_missatge = '';

// --- 1. PROCESSAMENT DEL FORMULARI (CRUD - Create) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Recollida de dades des del formulari (els noms dels inputs HTML)
    $nom_v = trim($_POST['nom_varietat']);
    // ATENCIÓ: Assumim que el camp 'especie' del formulari retorna l'ID numèric (id_especie)
    $id_especie = trim($_POST['especie']); 
    
    // Aquests camps del formulari s'ADAPTEN als noms de columna més llargs de la vostra taula (Varietat)
    $carac_agr = trim($_POST['origen']); // Utilitzem 'origen' per al camp 'caracteristiques_agronomiques'
    $cicle_veg = trim($_POST['collita_aproximada']); // Utilitzem 'collita_aproximada' per al camp 'cicle_vegetatiu'
    $qualitats_comercials = trim($_POST['sensibilitat_clau']); // Utilitzem 'sensibilitat_clau' per a 'qualitats_comercials'
    
    // Els camps 'estat', 'productivitat_mitjana_esperada' i 'requisits_pollinitzacio' no s'envien o s'ignoren si no estan al formulari.
    $requisits_pollin = NULL; // Assignem NULL per defecte si no es recull
    $prod_mitjana = NULL;

    // Validació bàsica de dades
    // Es valida que l'id_especie sigui un número (INT)
    if (empty($nom_v) || empty($id_especie) || !is_numeric($id_especie)) {
        $missatge = "Error: El nom de la varietat i l'ID de l'espècie són obligatoris i l'ID ha de ser numèric.";
        $classe_missatge = 'error';
    } else {
        try {
            $pdo = connectDB();

            // CONSULTA SQL CORREGIDA: 
            // - Nom de la Taula: Varietat 
            // - Noms de Columna: Coincideixen amb el vostre esquema (nom_varietat, id_especie, etc.)
            $sql = "INSERT INTO Varietat (
                nom_varietat, 
                id_especie, 
                caracteristiques_agronomiques, 
                cicle_vegetatiu, 
                requisits_pollinitzacio, 
                qualitats_comercials
            ) 
            VALUES (
                :nom_v, 
                :id_esp, 
                :carac_agr, 
                :cicle_veg, 
                :req_pollin, 
                :qualitats_c
            )";

            $stmt = $pdo->prepare($sql);

            // Vinculació dels paràmetres (bindParam utilitza les variables de recollida de dades)
            $stmt->bindParam(':nom_v', $nom_v); 
            $stmt->bindParam(':id_esp', $id_especie);
            $stmt->bindParam(':carac_agr', $carac_agr);
            $stmt->bindParam(':cicle_veg', $cicle_veg);
            $stmt->bindParam(':req_pollin', $requisits_pollin); // NULL per defecte
            $stmt->bindParam(':qualitats_c', $qualitats_comercials);

            // Execució
            if ($stmt->execute()) {
                $missatge = "✅ Varietat **" . htmlspecialchars($nom_v) . "** registrada correctament al catàleg.";
                $classe_missatge = 'exit';
            } else {
                $missatge = "Error al registrar la varietat. Intenta-ho de nou.";
                $classe_missatge = 'error';
            }

        } catch (PDOException $e) {
            $missatge = "Error de Base de Dades: " . htmlspecialchars($e->getMessage());
            $classe_missatge = 'error';
        } catch (Exception $e) {
            $missatge = "Error general: " . htmlspecialchars($e->getMessage());
            $classe_missatge = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Registrar Nova Varietat</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estils específics del formulari (CODI CSS OBLIGAT A OMETRE PER BREVETAT) */
        .contingut-formulari {
            max-width: 800px;
            margin: 100px auto 40px auto;
            padding: 40px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            color: var(--color-text-fosc, #333);
        }

        .contingut-formulari h1 {
            color: var(--color-principal, #1E4620);
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .grup-camp {
            margin-bottom: 20px;
        }

        .grup-camp label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 0.95em;
        }

        .grup-camp input[type="text"],
        .grup-camp select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .grup-camp input[type="text"]:focus,
        .grup-camp select:focus {
            border-color: var(--color-accent-blau, #3498db);
            outline: none;
        }

        .missatge-estat {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .missatge-estat.exit {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .missatge-estat.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .boto-enviar {
            background-color: var(--color-secundari, #4CAF50);
            color: white;
            padding: 15px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .boto-enviar:hover {
            background-color: #449d48;
        }
    </style>
</head>

<body style="background-image: none; background-color: #f4f4f4; color: var(--color-text-fosc);">
    <header class="capçalera-app">
        <div class="logo">
            <img src="LogoAppRetallatSenseNom.png" alt="Logo de CultiuConnect" class="logo-imatge">
            CultiuConnect
        </div>
        <nav class="navegacio-principal">
            <ul>
                <li><a href="index.html"><i class="fas fa-house"></i> Panell</a></li>
                <li><a href="operacio_nova.php"><i class="fas fa-spray-can-sparkles"></i> Nou Tractament</a></li>
                <li class="actiu"><a href="varietats.php"><i class="fas fa-tree"></i> Varietats</a></li>
                <li><a href="quadern.php"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
                <li><a href="seguiment_anual.php"><i class="fas fa-calendar-check"></i> Seguiment Anual</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-formulari">
        <h1><i class="fas fa-plus-circle"></i> Registre de Nova Varietat</h1>
        <p>Introdueix les dades bàsiques de la nova varietat per afegir-la al catàleg mestre de l'explotació.</p>

        <?php if ($missatge): ?>
            <div class="missatge-estat <?= $classe_missatge; ?>">
                <i class="fas <?= $classe_missatge == 'exit' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                <?= $missatge; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="nova_varietat.php">
            <div class="grup-camp">
                <label for="nom_varietat">Nom de la Varietat (*)</label>
                <input type="text" id="nom_varietat" name="nom_varietat" required
                    placeholder="Ex: Poma Fuji, Pera Conference">
            </div>

            <div class="grup-camp">
                <label for="especie">ID de l'Espècie / Cultiu (*)</label>
                <input type="text" id="especie" name="especie" required placeholder="Ex: 1 (Pomera), 2 (Nectarina). Ha de ser un número INT.">
            </div>

            <div class="grup-camp">
                <label for="origen">Característiques / Origen Geogràfic</label>
                <input type="text" id="origen" name="origen" placeholder="Ex: Japó (molt vigorós)">
            </div>

            <div class="grup-camp">
                <label for="collita_aproximada">Cicle Vegetatiu / Collita Aprox. (Mes/Setmana)</label>
                <input type="text" id="collita_aproximada" name="collita_aproximada"
                    placeholder="Ex: Setmana 36, Cicle Mitjà-Tardà">
            </div>

            <div class="grup-camp">
                <label for="sensibilitat_clau">Qualitats Comercials / Sensibilitats Clau</label>
                <input type="text" id="sensibilitat_clau" name="sensibilitat_clau"
                    placeholder="Ex: Calibre AA, Sensible a Mota">
            </div>

            <button type="submit" class="boto-enviar"><i class="fas fa-save"></i> Guardar Varietat</button>
            <a href="varietats.php" class="boto-descarrega"
                style="display: inline-block; width: auto; margin-left: 10px; background-color: #95a5a6;">
                <i class="fas fa-undo"></i> Tornar al Catàleg
            </a>
        </form>
    </main>

    <footer class="peu-app">
    </footer>
    <script src="scripts.js"></script>
</body>

</html>