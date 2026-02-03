<?php
// quadern.php - Pàgina de Registre d'Aplicacions i Quadern d'Explotació

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// --- MOCK DATA (Simulació de l'històric d'aplicacions) ---
$registres_quadern = [];
$error_connexio = null;
$total_registres = 0;

try {
    $pdo = connectDB();

    // --------------------------------------------------------------------------------
    // PART 1: APLICACIONS (Tractaments Fitosanitaris, Fertilització)
    // Utilitzem un UNION amb Collita_Operacio per tenir un Quadern complert
    // --------------------------------------------------------------------------------
    $sql_aplicacions = "
        SELECT 
            A.id_aplicacio AS id_event, 
            A.data_event AS data_base, 
            'Aplicació' AS tipus_event,
            S.nom AS nom_sector,
            A.tipus_event AS operacio_breu, 
            P.nom_comercial AS detall_producte,
            CONCAT(A.dosi_aplicada, ' (', A.quantitat_consumida_total, ')') AS dosi_resum,
            A.operari_carnet AS operari,
            'detall_aplicacio.php' AS url_detall
        FROM 
            Aplicacio A
        JOIN 
            Sector S ON A.id_sector = S.id_sector
        JOIN 
            Inventari_Estoc E ON A.id_estoc = E.id_estoc
        JOIN 
            Producte_Quimic P ON E.id_producte = P.id_producte
    ";

    // --------------------------------------------------------------------------------
    // PART 2: COLLITES (Recol·leccions de Producció)
    // --------------------------------------------------------------------------------
    $sql_collites = "
        SELECT 
            C.id_collita AS id_event, 
            C.data_inici AS data_base, 
            'Collita' AS tipus_event,
            S.nom AS nom_sector,
            CONCAT('Recol·lecció ', V.nom_varietat) AS operacio_breu,
            ER.nom_equip AS detall_producte,
            '' AS dosi_resum, -- No s'aplica dosi a la collita
            NULL AS operari, -- Utilitzem el nom de l'Equip/Colla
            'detall_collita.php' AS url_detall
        FROM 
            Collita_Operacio C
        JOIN 
            Plantacio PL ON C.id_plantacio = PL.id_plantacio
        JOIN 
            Sector S ON PL.id_sector = S.id_sector
        JOIN 
            Varietat V ON PL.id_varietat = V.id_varietat
        LEFT JOIN
            Equip_Recollector ER ON C.id_equip = ER.id_equip
    ";

    // Consulta final combinada, ordenada per data de forma cronològica inversa
    $sql_final = "($sql_aplicacions) UNION ALL ($sql_collites) ORDER BY data_base DESC";

    $stmt = $pdo->prepare($sql_final);
    $stmt->execute();
    $registres_quadern = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_registres = count($registres_quadern);

} catch (Exception $e) {
    // Si la connexió falla, emmagatzemem l'error i el mostrem de forma controlada
    $error_connexio = htmlspecialchars($e->getMessage());
    $registres_quadern = [];
    $total_registres = 0;
}
?>
// -----------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Quadern d'Explotació</title>
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
            /* Taronja Accent (Exportar) */
            --color-accent-blau: #3498db;
            /* Blau Accent (Detall) */
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
            background-size: cover;
            background-position: center top;
            background-attachment: fixed;
        }

        /* Contenidor Principal: Ajustat per l'efecte de fons d'imatge i Flexbox */
        main.contingut-quadern {
            /* FIX: Creix per empènyer el footer cap avall */
            flex-grow: 1;

            max-width: 1400px;
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            /* Padding superior afegit */
            background-color: transparent;
            color: white;
        }

        /* Descripció i altres elements fora de la taula */
        .contingut-quadern p {
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
            color: var(--color-principal) !important;
        }

        /* Estils dels controls (Filtre, Total Registres) */
        .contingut-quadern div[style*="justify-content: space-between"] {
            color: white;
            padding: 10px 0;
            background-color: transparent;
        }

        .contingut-quadern div[style*="justify-content: space-between"] div:first-child {
            color: var(--color-accent-taronja);
        }

        .contingut-quadern input#filtreInput {
            /* Ajustar el color del border i fons per llegir-se bé */
            background-color: rgba(255, 255, 255, 0.9);
            color: var(--color-text-fosc);
            border: 1px solid var(--color-primary);
        }


        /* 3. TAULA (Fons Blanc Sòlid) */
        .taula-registres {
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

        .taula-registres th,
        .taula-registres td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            color: var(--color-text-fosc);
            /* Color de text per a les cel·les */
        }

        .taula-registres th {
            background-color: var(--color-principal, #1E4620);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .taula-registres tr:hover {
            background-color: #f5f5f5;
        }

        /* Afegir estils per diferenciar Collita vs Aplicació */
        .taula-registres tr.fila-collita {
            background-color: #fffaf0;
            /* Color per a les collites */
        }

        .taula-registres tr.fila-collita:hover {
            background-color: #ffecc6;
        }

        /* Estil per al missatge d'error de connexió */
        .missatge-alerta-fatal {
            /* Afegeix això si no ho tens ja (per gestionar errors de BD sota el header) */
            position: relative;
            z-index: 10;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
            font-weight: bold;
        }

        /* 4. FOOTER (Sticky Footer) */
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
                <li><a href="monitoratge.php"><i class="fas fa-bug"></i> Monitoratge</a></li>
                <li class="actiu"><a href="#"><i class="fas fa-file-invoice"></i> Quadern Explotació</a></li>
                <li><a href="estoc.php"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-quadern">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-file-invoice"></i>
            Quadern d'Explotació: Històric d'Aplicacions
        </h1>
        <p style="margin-bottom: 30px;">Registre cronològic de tots els tractaments fitosanitaris i fertilitzacions
            realitzades a l'explotació.</p>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div style="font-size: 1.1em; font-weight: bold;">
                Total Registres: <?= $total_registres; ?>
            </div>
            <div>
                <label for="filtreInput" style="margin-right: 10px; font-weight: bold;"><i class="fas fa-search"></i>
                    Cerca Ràpida:</label>
                <input type="text" id="filtreInput" onkeyup="filtrarQuadern()"
                    placeholder="Filtrar per Sector o Producte..."
                    style="padding: 10px; border-radius: 5px; width: 250px;">
            </div>
        </div>

        <table class="taula-registres" id="taula-quadern">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data Aplicació</th>
                    <th>Sector</th>
                    <th>Operació</th>
                    <th>Producte Utilitzat</th>
                    <th>Dosi Aplicada</th>
                    <th>Operari</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registres_quadern)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #999;">
                            No hi ha registres d'aplicacions al Quadern.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($registres_quadern as $registre): ?>
                        <tr class="<?= ($registre['tipus_event'] == 'Collita') ? 'fila-collita' : 'fila-aplicacio'; ?>">
                            <td><?= $registre['id_event']; ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($registre['data_base'])); ?></td>
                            <td style="font-weight: bold;"><?= htmlspecialchars($registre['nom_sector']); ?></td>

                            <td
                                style="font-weight: bold; color: <?= ($registre['tipus_event'] == 'Collita') ? '#FF9800' : '#3498db'; ?>;">
                                <i class="fas fa-<?= ($registre['tipus_event'] == 'Collita') ? 'seedling' : 'flask'; ?>"></i>
                                <?= htmlspecialchars($registre['tipus_event']); ?>
                            </td>

                            <td><?= htmlspecialchars($registre['detall_producte']); ?></td>
                            <td style="color: var(--color-secundari); font-weight: bold;">
                                <?= htmlspecialchars($registre['dosi_resum']); ?>
                            </td>
                            <td><?= htmlspecialchars($registre['operari'] ?: 'Sense operari fix'); ?></td>
                            <td>
                                <a href="<?= $registre['url_detall']; ?>?id=<?= $registre['id_event']; ?>" title="Veure Detall">
                                    <i class="fas fa-magnifying-glass" style="color: var(--color-accent-blau);"></i>
                                </a>
                                <a href="documentacio.php?type=<?= $registre['tipus_event']; ?>&id=<?= $registre['id_event']; ?>"
                                    title="Exportar Fitxa" style="margin-left: 10px;">
                                    <i class="fas fa-file-export" style="color: var(--color-accent-taronja);"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px; font-size: 0.85em;">
            * Aquest quadern és la base per al Quadern d'Explotació Digital (CUE) i la certificació GlobalGAP.
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