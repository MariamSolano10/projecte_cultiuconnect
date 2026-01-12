<?php
// monitoratge.php - Formulari de Registre de Nou Monitoratge

include 'db_connect.php'; 

// --- LÒGICA PER REBRE EL MISSATGE DE RETORN (Només es manté per si el vols reutilitzar) ---
$missatge_estat = null;
$estat_classe = null; 

if (isset($_GET['missatge']) && isset($_GET['estat'])) {
    $missatge_estat = htmlspecialchars($_GET['missatge']);
    $estat_classe = htmlspecialchars($_GET['estat']);
}
// -----------------------------------------------------------------

$llistat_sectors = [];
$error_connexio = null;
$data_actual = date('Y-m-d\TH:i'); // Format DateTime local HTML5

try {
    $pdo = connectDB();

    // 1. OBTENIR LLISTAT DE SECTORS ACTIUS
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
    $error_connexio = "❌ Error de connexió o consulta a la base de dades: " . htmlspecialchars($e->getMessage());
    $llistat_sectors = [];
}
?>

<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Nou Monitoratge</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --color-principal: #1E4620;
            --color-secundari: #4CAF50;
            --color-primary-fosc: #143116;
            --color-accent-taronja: #FF9800;
            --color-accent-blau: #3498db;
            --color-card-fons: white;
            --color-text-fosc: #333;
            --color-footer-fosc: rgba(30, 70, 32, 0.9);
            --color-footer-text: #ddd;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #333;
            background-image: url('fons_monitoratge.jpg');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
        }

        main.contingut-monitoratge {
            flex-grow: 1;
            max-width: 1000px;
            padding: 105px 40px 40px 40px;
            margin: 0 auto;
            min-height: calc(100vh - 40px);
            background-color: transparent;
            box-shadow: none;
            color: white;
        }

        .títol-pàgina {
            max-width: 600px;
            margin: 0 auto 20px auto;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            color: var(--color-principal, #1E4620);
            text-align: center;
            border-bottom: none;
        }

        .títol-pàgina i {
            color: var(--color-principal) !important;
        }

        .contenidor-formulari-bloc {
            max-width: 900px;
            margin: 0 auto 30px auto;
            padding: 40px;
            background-color: var(--color-card-fons);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            color: var(--color-text-fosc);
        }

        .formulari-monitoratge {
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

        input[type="text"],
        input[type="datetime-local"],
        input[type="number"],
        select,
        textarea {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .grup-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .grup-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }

        .boto-enviar {
            background-color: var(--color-accent-taronja);
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
            background-color: #e68900;
        }

        .alerta-resposta {
            max-width: 900px;
            margin: 20px auto;
            padding: 15px 20px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            color: var(--color-text-fosc);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .alerta-resposta i {
            margin-right: 10px;
        }

        .alerta-exit {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alerta-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

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
                <li class="actiu"><a href="#"><i class="fas fa-bug"></i> Monitoratge</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-monitoratge">
        <h1 class="títol-pàgina">
            <i class="fas fa-bug"></i>
            Registre de Monitoratge i Plagues
        </h1>
        <p style="margin-bottom: 30px; color: #ccc">Registra una nova observació de plaga, malaltia, deficiència o
            mala herba per fer el seguiment.</p>

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

            <form action="nou_monitoratge.php" method="POST" class="formulari-monitoratge">
                <div class="grup-camp col-span-2">
                    <label for="sector_observat">Sector d'Observació <span style="color: red;">*</span></label>
                    <select id="sector_observat" name="sector_observat" required>
                        <option value="" selected disabled>--- Selecciona un sector ---</option>
                        <?php foreach ($llistat_sectors as $sector): ?>
                            <option value="<?= $sector['id']; ?>">
                                <?= htmlspecialchars($sector['codi_sector'] . ' - ' . $sector['nom_varietat'] . ' (' . number_format((float)$sector['superficie_ha'], 2, ',', '.') . ' ha)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grup-camp">
                    <label for="data_observacio">Data i Hora de l'Observació <span style="color: red;">*</span></label>
                    <input type="datetime-local" id="data_observacio" name="data_observacio"
                        value="<?= $data_actual; ?>" required>
                </div>

                <div class="grup-camp">
                    <label for="tipus_problema">Tipus de Problema <span style="color: red;">*</span></label>
                    <select id="tipus_problema" name="tipus_problema" required>
                        <option value="" selected disabled>--- Selecciona un tipus ---</option>
                        <option value="Plaga">Plaga (Insecte, Àcar, Nematode)</option>
                        <option value="Malaltia">Malaltia (Fong, Virus, Bactèria)</option>
                        <option value="Deficiencia">Deficiència Nutricional/Estrès</option>
                        <option value="Mala Herba">Mala Herba</option>
                        <option value="Altres">Altres Observacions</option>
                    </select>
                </div>

                <div class="grup-camp">
                    <label for="descripcio_breu">Element Observat (Nom) <span style="color: red;">*</span></label>
                    <input type="text" id="descripcio_breu" name="descripcio_breu"
                        placeholder="Ex: Mosca Blanca, Oïdi, Manca de Nitrogen..." required>
                </div>
                
                <div class="grup-camp">
                    <label for="nivell_poblacio">Nivell / % Danys (Opcional)</label>
                    <input type="number" step="0.01" id="nivell_poblacio" name="nivell_poblacio"
                        placeholder="Ex: 10 (per cent de dany, o unitats/trampa...)">
                </div>

                <div class="grup-camp col-span-2">
                    <label for="llindar_assolit">Llindar d'Intervenció Assolit?</label>
                    <div class="grup-checkbox">
                        <input type="checkbox" id="llindar_assolit" name="llindar_assolit" value="1">
                        <label for="llindar_assolit" style="font-weight: normal; color: #666;">Marcar si l'observació
                            requereix acció immediata.</label>
                    </div>
                </div>

                <div class="grup-camp col-span-2">
                    <button type="submit" class="boto-enviar">
                        <i class="fas fa-cloud-upload"></i> Registrar Monitoratge
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