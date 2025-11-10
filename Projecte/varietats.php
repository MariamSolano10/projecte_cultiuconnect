<?php
// varietats.php - Pàgina de Gestió de Varietats de Cultiu

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- CÀRREGA DE DADES DEL CATÀLEG DE VARIETATS (CORREGIT) ---
$cataleg_varietats = [];
$error_connexio = null;

try {
    // 1. Establir la connexió a la BD
    $pdo = connectDB();

    // 2. Consulta SQL CORREGIDA:
    // - S'ha canviat la taula 'varietats_cultiu' per 'Varietat'.
    // - S'han corregit els noms de columna.
    // - S'ha afegit un JOIN amb Especie per mostrar el nom comú.
    $sql = "SELECT 
                V.id_varietat AS id, 
                V.nom_varietat AS nom, 
                E.nom_comu AS especie, 
                V.caracteristiques_agronomiques AS origen, 
                V.cicle_vegetatiu AS collita_aproximada, 
                V.qualitats_comercials AS sensibilitat_clau
            FROM 
                Varietat V
            JOIN 
                Especie E ON V.id_especie = E.id_especie
            ORDER BY V.nom_varietat ASC";

    // 3. Preparar i executar la consulta
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // 4. Obtenir tots els resultats com un array associatiu
    $cataleg_varietats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Gestió de l'error de BD
    $error_connexio = "Error en carregar les dades: " . htmlspecialchars($e->getMessage());
    $cataleg_varietats = []; // Buidem les dades
}

// Lògica per obtenir la classe de sensibilitat (basada en el contingut de la BD)
if (!function_exists('obtenirClasseSensibilitat')) {
    function obtenirClasseSensibilitat($sensibilitat): string
    {
        $sensibilitat_lower = strtolower($sensibilitat);
        if (strpos($sensibilitat_lower, 'foc bacterià') !== false || strpos($sensibilitat_lower, 'critical') !== false) {
            return 'sensibilitat-critica';
        }
        return 'sensibilitat-normal';
    }
}
// -----------------------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Catàleg de Varietats</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color (reforçades per consistència) */
        :root {
            --color-principal: #1E4620;
            /* Verd Fosc */
            --color-secundari: #4CAF50;
            /* Verd Mitjà */
            --color-accent-taronja: #FF9800;
            /* Taronja Accent (Calendari) */
            --color-accent-blau: #3498db;
            /* Blau Accent (Nou) */
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;

            /* Fons Fosc amb Imatge */
            background-color: #333;
            background-image: url('fons_sectors.jpg');
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
            color: #ddd;
            /* Text per defecte clar */
        }

        /* Contenidor Principal: Ajustat per l'efecte de fons d'imatge i Flexbox */
        main.contingut-varietats {
            flex-grow: 1;

            max-width: 1400px;
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            /* Padding superior afegit */
            background-color: transparent;
        }

        /* Descripció i altres elements sobre el fons fosc */
        .contingut-varietats p {
            color: #ccc !important;
        }

        /* 2. ESTIL DEL TÍTOL (Rectangular clar amb ombra) */
        .títol-pàgina {
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            color: var(--color-principal, #1E4620);
            text-align: center;
        }

        .títol-pàgina i {
            color: var(--color-principal) !important;
        }

        /* 3. TAULA (Fons Blanc Sòlid) */
        .taula-varietats {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            /* Fons SÒLID BLANC */
            background-color: white;
            color: var(--color-text-fosc);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            overflow: hidden;
        }

        .taula-varietats th,
        .taula-varietats td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .taula-varietats th {
            background-color: var(--color-principal, #1E4620);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .taula-varietats tr:hover {
            background-color: #f5f5f5;
        }

        /* Estils de Sensibilitat */
        .sensibilitat-normal {
            color: #2ecc71;
        }

        .sensibilitat-critica {
            color: #e74c3c;
            /* Vermell per a sensibilitat crítica */
            font-weight: bold;
        }

        /* 4. BOTONS D'ACCIÓ */
        .botons-accions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
            gap: 10px;
        }

        .botons-accions a {
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9em;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }

        .boto-nova-varietat {
            background-color: var(--color-accent-blau);
            color: white;
        }

        .boto-nova-varietat:hover {
            background-color: #2980b9;
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
                <li><a href="operacio_nova.php"><i class="fas fa-spray-can-sparkles"></i> Nou Tractament</a></li>
                <li class="actiu"><a href="#"><i class="fas fa-tree"></i> Varietats</a></li>
                <li><a href="quadern.php"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
                <li><a href="seguiment_anual.php"><i class="fas fa-calendar-check"></i> Seguiment Anual</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-varietats">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-seedling"></i>
            Catàleg de Varietats de Cultiu
        </h1>
        <p style="margin-bottom: 30px;">Informació de les varietats actives a l'explotació, incloent dades de fenologia
            i sensibilitat fitosanitària.</p>

        <div class="botons-accions">
            <a href="nova_varietat.php" class="boto-nova-varietat">
                <i class="fas fa-plus"></i> Registrar Nova Varietat
            </a>
        </div>

        <table class="taula-varietats">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom Varietat</th>
                    <th>Espècie</th>
                    <th>Origen</th>
                    <th>Collita Aprox.</th>
                    <th>Sensibilitats Clau</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cataleg_varietats)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                            <?php
                            if ($error_connexio)
                                echo 'No s\'han pogut carregar les dades. Revisa la connexió a la BD.';
                            else
                                echo 'El catàleg de varietats de cultiu està buit.';
                            ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($cataleg_varietats as $varietat):
                        $classe_sensibilitat = obtenirClasseSensibilitat($varietat['sensibilitat_clau']);
                        ?>
                        <tr>
                            <td><?= $varietat['id']; ?></td>
                            <td style="font-weight: bold;"><?= htmlspecialchars($varietat['nom']); ?></td>
                            <td><?= htmlspecialchars($varietat['especie']); ?></td>
                            <td><?= htmlspecialchars($varietat['origen']); ?></td>
                            <td><?= htmlspecialchars($varietat['collita_aproximada']); ?></td>
                            <td>
                                <span class="<?= $classe_sensibilitat; ?>">
                                    <?= htmlspecialchars($varietat['sensibilitat_clau']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="calendari_fenologic.php?varietat_id=<?= $varietat['id']; ?>"
                                    title="Veure Calendari Fenològic">
                                    <i class="fas fa-calendar-alt" style="color: var(--color-accent-taronja);"></i>
                                </a>
                                <a href="recomanacions_fito.php?varietat_id=<?= $varietat['id']; ?>"
                                    title="Recomanacions Fitosanitàries" style="margin-left: 10px;">
                                    <i class="fas fa-shield-virus" style="color: var(--color-secundari);"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px; font-size: 0.85em;">
            * La gestió per varietats permet ajustar els calendaris de tractament al cicle vital de cada fruit.
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