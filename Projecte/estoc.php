<?php
// estoc.php - Pàgina de Gestió de l'Inventari i Estocs (Control de Magatzem)

// Inclusió de la lògica de connexió.
include 'db_connect.php';

// Inicialització de missatges d'alerta general
$alerta_general = '';
$inventari_lots = [];
$error_connexio = null;

try {
    // Intent de connexió a la Base de Dades
    $pdo = connectDB();

    // --------------------------------------------------------------------------------
    // Consulta SQL per obtenir l'inventari actiu.
    // CORRECCIÓ: S'ha ELIMINAT 'E.nivell_minim' per adaptar-se a l'estructura de la BBDD.
    // CORRECCIÓ: Ús d'àlies per 'unitat_mesura' i 'data_caducitat'.
    // --------------------------------------------------------------------------------
    $sql = "
        SELECT 
            E.id_estoc, 
            P.nom_comercial AS nom_producte, 
            E.num_lot,                              
            E.quantitat_disponible, 
            E.unitat_mesura AS unitat,              
            E.data_caducitat AS caducitat           -- Nom de columna real de la BBDD
        FROM 
            Inventari_Estoc E
        JOIN 
            Producte_Quimic P ON E.id_producte = P.id_producte
        WHERE 
            E.quantitat_disponible > 0 
        ORDER BY 
            FIELD(E.unitat_mesura, 'L', 'Kg', 'Unitat'), 
            P.nom_comercial ASC;
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $inventari_lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Captura d'error de connexió i gestió
    $error_connexio = "❌ Error de connexió a la base de dades: " . htmlspecialchars($e->getMessage());
    $inventari_lots = [];
}

// Lògica de detecció d'alertes i assignació de classe CSS
// LÒGICA DE REPOSICIÓ IGNORADA: Ja que no tenim la dada 'nivell_minim' a la BBDD.
function obtenirClasseEstatLot(float $quantitat, string $data_caducitat, float $nivell_minim): string
{
    // Fix: Si la data_caducitat és NULL o buida, ignorem la comprovació de caducitat.
    if (empty($data_caducitat)) {
        $data_caducitat_dt = null;
    } else {
        $data_caducitat_dt = new DateTime($data_caducitat);
        $avui = new DateTime();
        
        // 1. Alerta si ja ha caducat
        if ($data_caducitat_dt < $avui) {
            return 'estat-caducat-urgent';
        }

        // 2. Alerta si caduca en menys de 6 mesos
        $data_limit_caducitat = (new DateTime())->modify('+6 months');
        if ($data_caducitat_dt < $data_limit_caducitat) {
            return 'estat-caducitat'; // Caducitat pròxima
        }
    }


    // 3. Alerta si està per sota del mínim (AQUESTA LÒGICA ESTÀ DESACTIVADA)
    if ($quantitat < $nivell_minim) {
         // Com que cridem amb $nivell_minim = 999999.0, aquesta condició és falsa.
        return 'estat-reposicio'; 
    }

    return '';
}

// Generació de missatges d'alerta general
$alerta_text = [];

// 1. Caducats Urgents
$lots_caducats_urgents = array_filter($inventari_lots, function ($lot) {
    // 999999 desactiva la comprovació de nivell mínim a la funció
    return obtenirClasseEstatLot($lot['quantitat_disponible'], $lot['caducitat'] ?? '', 999999) === 'estat-caducat-urgent';
});
if (count($lots_caducats_urgents) > 0) {
    $alerta_text[] = "🛑 **URGENT!** Hi ha " . count($lots_caducats_urgents) . " lots que **ja estan CADUCATS** i s'han de retirar de l'ús.";
}

// 2. Caducitat Pròxima
$lots_caducitat_proxima = array_filter($inventari_lots, function ($lot) use ($lots_caducats_urgents) {
    // 999999 desactiva la comprovació de nivell mínim
    $estat = obtenirClasseEstatLot($lot['quantitat_disponible'], $lot['caducitat'] ?? '', 999999);
    // Assegurar que no es compti doble si ja és urgent
    return $estat === 'estat-caducitat' && !in_array($lot, $lots_caducats_urgents); 
});
if (count($lots_caducitat_proxima) > 0) {
    $alerta_text[] = "⚠️ Hi ha " . count($lots_caducitat_proxima) . " lots amb **data de caducitat pròxima** (menys de 6 mesos).";
}
// Alerta de reposició eliminada per falta de dada a la BBDD.

if (!empty($alerta_text)) {
    $alerta_general = implode('<br>', $alerta_text);
}

// -----------------------------------------------------------------------

?>
<!DOCTYPE html>
<html lang="ca">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CultiuConnect - Inventari i Estocs</title>
    <link rel="stylesheet" href="estils.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Definició de variables de color */
        :root {
            --color-principal: #1E4620;
            /* Verd Fosc */
            --color-secundari: #4CAF50;
            /* Verd Mitjà */
            --color-accent-blau: #3498db;
            /* Blau Accent (Moure/Ajustar) */
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
        main.contingut-inventari {
            /* FIX: Creix per empènyer el footer cap avall */
            flex-grow: 1;

            max-width: 1400px;
            margin: 0 auto;
            padding: 105px 40px 40px 40px;
            /* Padding superior afegit */
            background-color: transparent;
            color: white;
        }

        /* Descripció */
        .contingut-inventari p {
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

        /* 3. ESTILS DE TAULA: APLICACIÓ DE FONS BLANC SÒLID */
        .taula-inventari {
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

        .taula-inventari th,
        .taula-inventari td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            color: var(--color-text-fosc);
        }

        .taula-inventari th {
            background-color: var(--color-principal, #1E4620);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .taula-inventari tr:not(.estat-reposicio):not(.estat-caducitat):hover {
            background-color: #f5f5f5;
        }

        /* Estats i Alertes */
        /* Alerta de reposició desactivada */
        /* .taula-inventari .estat-reposicio {
            background-color: #ffe0b2;
            font-weight: bold;
        } */

        .taula-inventari .estat-caducitat {
            background-color: #f8d7da;
            /* Vermell Clar */
            font-weight: bold;
        }

        .taula-inventari .estat-caducitat:hover {
            background-color: #f7c7cd;
            /* Mantenir hover en el mateix to */
        }

        .taula-inventari .estat-caducat-urgent {
            background-color: #f7a3ac;
            /* Vermell Fosc clar */
            color: #721c24;
            /* Text fosc */
            font-weight: bolder;
            border: 2px solid #dc3545;
        }

        .taula-inventari .estat-caducat-urgent:hover {
            background-color: #f5969f;
        }

        /* Missatge d'alerta superior */
        .alerta-inventari {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
            font-weight: bold;
            background-color: #fff3cd;
            /* Groc */
            color: #856404;
            border: 1px solid #ffeeba;
        }

        /* Botons d'Accions */
        .botons-accions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 15px;
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

        .boto-nou-lot {
            background-color: var(--color-principal);
            color: white;
        }

        .boto-nou-lot:hover {
            background-color: #006400;
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
                <li><a href="#" class="actiu"><i class="fas fa-boxes-stacked"></i> Inventari</a></li>
            </ul>
        </nav>
        <div class="informacio-usuari">
            <i class="fas fa-user-circle"></i> Encarregat de Camp
        </div>
    </header>

    <main class="contingut-inventari">
        <h1 class="títol-pàgina" style="margin-bottom: 20px;">
            <i class="fas fa-warehouse"></i>
            Gestió d'Inventari i Control d'Estocs
        </h1>
        <p style="margin-bottom: 30px;">Control d'existències, nivells de reposició i gestió de caducitats per a
            tots
            els lots de productes químics.</p>

        <?php if ($error_connexio): ?>
            <div class="alerta-inventari" style="background-color: #f8d7da; color: #721c24;">
                <?= $error_connexio; ?>
            </div>
        <?php endif; ?>

        <?php if ($alerta_general): ?>
            <div class="alerta-inventari">
                <?= $alerta_general; ?>
            </div>
        <?php endif; ?>

        <div class="botons-accions">
            <a href="nou_lot.php" class="boto-nou-lot">
                <i class="fas fa-box-open"></i> Registrar Entrada de Lot
            </a>
        </div>

        <table class="taula-inventari">
            <thead>
                <tr>
                    <th>ID Lot</th>
                    <th>Nom Producte</th>
                    <th>Codi de Lot</th>
                    <th>Quantitat Disponible</th>
                    <th>Data Caducitat</th>
                    <th>Estat</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventari_lots)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                            L'inventari està buit. Registra un nou lot per començar.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventari_lots as $lot):
                        // 999999.0 desactiva la comprovació de nivell mínim
                        $classe_estat = obtenirClasseEstatLot(
                            $lot['quantitat_disponible'],
                            $lot['caducitat'] ?? '', // Operador de coalescència per evitar warnings si és NULL
                            999999.0 
                        );

                        $text_estat = 'OK';
                        if ($classe_estat === 'estat-reposicio') {
                            $text_estat = 'REPOSICIÓ URGENT (Funcionalitat Desactivada)'; 
                        } elseif ($classe_estat === 'estat-caducitat') {
                            $text_estat = 'CADUCITAT PRÒXIMA';
                        } elseif ($classe_estat === 'estat-caducat-urgent') {
                            $text_estat = 'CADUCAT!';
                        }
                        ?>
                        <tr class="<?= $classe_estat; ?>">
                            <td><?= $lot['id_estoc']; ?></td>
                            <td><?= htmlspecialchars($lot['nom_producte']); ?></td>
                            <td><?= htmlspecialchars($lot['num_lot']); ?></td> 
                            <td style="font-weight: bold;">
                                <?= number_format($lot['quantitat_disponible'], 1) . ' ' . $lot['unitat']; ?>
                            </td>
                            <td><?= empty($lot['caducitat']) ? 'N/A' : date('d/m/Y', strtotime($lot['caducitat'])); ?></td>
                            <td style="font-weight: bold;"><?= $text_estat; ?></td>
                            <td>
                                <a href="moure_lot.php?id=<?= $lot['id_estoc']; ?>" title="Moure/Ajustar Estoc">
                                    <i class="fas fa-arrows-left-right" style="color: var(--color-accent-blau);"></i>
                                </a>
                                <a href="detall_lot.php?id=<?= $lot['id_estoc']; ?>" title="Traçabilitat del Lot">
                                    <i class="fas fa-link" style="color: var(--color-secundari); margin-left: 10px;"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px; font-size: 0.85em;">
            * Els lots amb caducitat pròxima es mostren amb fons de color. La comprovació de nivell mínim està
            desactivada per falta de dades a la taula d'estoc.
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