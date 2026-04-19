<?php
/**
 * modules/quadern/quadern_normativa.php — Estat de compliment normatiu de l'explotació.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Normativa i Compliment';
$pagina_activa = 'quadern';

$alertes        = [];
$certificacions = [];
$error_db       = null;

try {
    $pdo = connectDB();

    // --- 1. Tractaments sense producte associat (Taules 35 i 36) ---
    $sense_producte = $pdo->query("
        SELECT COUNT(*) FROM aplicacio a
        WHERE NOT EXISTS (
            SELECT 1 FROM detall_aplicacio_producte dap
            WHERE dap.id_aplicacio = a.id_aplicacio
        )
        AND YEAR(a.data_event) = YEAR(CURDATE())
    ")->fetchColumn();

    if ((int)$sense_producte > 0) {
        $alertes[] = [
            'nivell' => 'error',
            'titol'  => 'Tractaments sense producte registrat',
            'text'   => (int)$sense_producte . ' tractament(s) d\'aquest any no tenen cap producte associat. La normativa exigeix especificar el producte fitosanitari utilitzat.',
            'link'   => BASE_URL . 'modules/operacions/operacio_nova.php',
            'link_text' => 'Revisar aplicacions',
        ];
    }

    // --- 2. Sectors sense anàlisi de sòl en els últims 2 anys (Taules 8 i 11) ---
    $sense_analisi = $pdo->query("
        SELECT s.id_sector, s.nom
        FROM sector s
        WHERE NOT EXISTS (
            SELECT 1 FROM caracteristiques_sol cs
            WHERE cs.id_sector = s.id_sector
              AND cs.data_analisi >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
        )
        ORDER BY s.nom ASC
    ")->fetchAll();

    if (!empty($sense_analisi)) {
        $noms = implode(', ', array_column($sense_analisi, 'nom'));
        $alertes[] = [
            'nivell' => 'avis',
            'titol'  => 'Sectors sense anàlisi de sòl recent',
            'text'   => count($sense_analisi) . ' sector(s) no tenen anàlisi de sòl els últims 2 anys: ' . e($noms) . '. Recomanat per a una fertilització de precisió.',
            'link'   => BASE_URL . 'modules/analisis/analisis_lab.php',
            'link_text' => 'Gestionar anàlisis',
        ];
    }

    // --- 3. Productes fitosanitaris caducats a l'estoc (Taules 15 i 17) ---
    $productes_caducats = $pdo->query("
        SELECT COUNT(DISTINCT pq.id_producte) AS n
        FROM inventari_estoc ie
        JOIN producte_quimic pq ON pq.id_producte = ie.id_producte
        WHERE ie.quantitat_disponible > 0
          AND ie.data_caducitat < CURDATE()
    ")->fetchColumn();

    if ((int)$productes_caducats > 0) {
        $alertes[] = [
            'nivell' => 'error',
            'titol'  => 'Productes fitosanitaris caducats a l\'estoc',
            'text'   => (int)$productes_caducats . ' producte(s) amb lots caducats encara figuren com a existents. L\'ús de productes caducats pot comportar sancions.',
            'link'   => BASE_URL . 'modules/estoc/estoc.php',
            'link_text' => 'Revisar inventari',
        ];
    }

    // --- 4. Treballadors actius sense data d'alta (Taula 27) ---
    $sense_alta = $pdo->query("
        SELECT COUNT(*) FROM treballador
        WHERE estat = 'actiu' AND data_alta IS NULL
    ")->fetchColumn();

    if ((int)$sense_alta > 0) {
        $alertes[] = [
            'nivell' => 'avis',
            'titol'  => 'Treballadors sense data d\'alta',
            'text'   => (int)$sense_alta . ' treballador(s) actiu(s) no tenen la data d\'alta registrada al sistema.',
            'link'   => BASE_URL . 'modules/personal/personal.php',
            'link_text' => 'Revisar personal',
        ];
    }

    // --- 5. Maquinària amb revisió caducada (Lectura JSON - Taula 34) ---
    $totes_maquines = $pdo->query("
        SELECT nom_maquina, manteniment_json FROM maquinaria
    ")->fetchAll();

    $maq_caducades = [];
    foreach ($totes_maquines as $maq) {
        $json = json_decode($maq['manteniment_json'] ?? '{}', true);
        if (empty($json['darrera_revisio'])) {
            $maq_caducades[] = $maq['nom_maquina'];
            continue;
        }
        try {
            $revisio = new DateTime($json['darrera_revisio']);
            $avui    = new DateTime('today');
            if ((int)$avui->diff($revisio)->days > 365) {
                $maq_caducades[] = $maq['nom_maquina'];
            }
        } catch (Exception) {}
    }

    if (!empty($maq_caducades)) {
        $alertes[] = [
            'nivell' => 'avis',
            'titol'  => 'Maquinària amb revisió pendent',
            'text'   => count($maq_caducades) . ' màquina/es sense registre de revisió en l\'últim any: ' . e(implode(', ', $maq_caducades)) . '.',
            'link'   => BASE_URL . 'modules/maquinaria/maquinaria.php',
            'link_text' => 'Revisar maquinària',
        ];
    }

    // --- 6. Certificacions dels treballadors (Taules 27 i 28) ---
    $certificacions = $pdo->query("
        SELECT c.id_certificacio, 
               CONCAT(t.nom, ' ', COALESCE(t.cognoms, '')) AS nom_treballador, 
               c.tipus_certificacio AS nom, 
               c.entitat_emissora AS organisme, 
               c.data_obtencio, 
               c.data_caducitat
        FROM certificacio_treballador c
        JOIN treballador t ON c.id_treballador = t.id_treballador
        ORDER BY c.data_caducitat ASC
    ")->fetchAll();

    foreach ($certificacions as $cert) {
        if (empty($cert['data_caducitat'])) continue;
        
        $caducitat = new DateTime($cert['data_caducitat']);
        $avui      = new DateTime('today');
        $dies      = (int)$avui->diff($caducitat)->format('%r%a'); // respecte signes
        $es_passat = $dies < 0;

        if ($es_passat) {
            $alertes[] = [
                'nivell' => 'error',
                'titol'  => 'Certificació de personal caducada',
                'text'   => 'La certificació «' . $cert['nom'] . '» de ' . $cert['nom_treballador'] . ' va caducar el ' . format_data($cert['data_caducitat'], curta: true) . '.',
                'link'   => BASE_URL . 'modules/personal/personal.php',
                'link_text' => 'Anar a Personal',
            ];
        } elseif ($dies >= 0 && $dies <= 60) {
            $alertes[] = [
                'nivell' => 'avis',
                'titol'  => 'Certificació caduca aviat',
                'text'   => 'La certificació «' . $cert['nom'] . '» de ' . $cert['nom_treballador'] . ' caduca d\'aquí a ' . $dies . ' dies (' . format_data($cert['data_caducitat'], curta: true) . ').',
                'link'   => BASE_URL . 'modules/personal/personal.php',
                'link_text' => 'Anar a Personal',
            ];
        }
    }

} catch (Exception $e) {
    error_log('[CultiuConnect] quadern_normativa.php: ' . $e->getMessage());
    $error_db = 'No s\'ha pogut carregar el resum de normativa.';
}

// Comptadors per nivell
$n_errors = count(array_filter($alertes, fn($a) => $a['nivell'] === 'error'));
$n_avisos = count(array_filter($alertes, fn($a) => $a['nivell'] === 'avis'));

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-normativa">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-scale-balanced" aria-hidden="true"></i>
            Normativa i Compliment Legal
        </h1>
        <p class="descripcio-seccio">
            Indicadors automàtics de compliment basats en la Llei d'ús sostenible de plaguicides, quadern de camp i RD 1702/2011.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php else: ?>

        <div class="kpi-grid">
            <div class="kpi-card <?= $n_errors > 0 ? 'kpi-card--vermell' : 'kpi-card--verd' ?>">
                <div class="kpi-card__valor"><?= $n_errors ?></div>
                <div class="kpi-card__etiqueta">Incompliments crítics</div>
            </div>
            <div class="kpi-card <?= $n_avisos > 0 ? 'kpi-card--taronja' : 'kpi-card--verd' ?>">
                <div class="kpi-card__valor"><?= $n_avisos ?></div>
                <div class="kpi-card__etiqueta">Avisos de revisió</div>
            </div>
            <div class="kpi-card <?= ($n_errors + $n_avisos) === 0 ? 'kpi-card--verd' : '' ?>">
                <div class="kpi-card__valor">
                    <?= ($n_errors + $n_avisos) === 0
                        ? '<i class="fas fa-check-circle"></i>'
                        : ($n_errors + $n_avisos)
                    ?>
                </div>
                <div class="kpi-card__etiqueta">Total incidències</div>
            </div>
        </div>

        <div class="botons-accions mb-l">
            <a href="<?= BASE_URL ?>modules/quadern/quadern.php" class="boto-secundari">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Tornar al Quadern de Camp
            </a>
        </div>

        <?php if (empty($alertes)): ?>
            <div class="flash flash--success" role="status">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <strong>Tot en ordre.</strong> No s'han detectat incompliments ni avisos pendents en la documentació de l'explotació.
            </div>

        <?php else: ?>
            <div class="normativa-alertes normativa-alertes--llista">
                <?php foreach ($alertes as $alerta):
                    $is_error = $alerta['nivell'] === 'error';
                    $bg_color = $is_error ? '#ffebee' : '#fff3e0';
                    $border_color = $is_error ? '#f44336' : '#ff9800';
                    $icona = $is_error ? 'fa-circle-xmark' : 'fa-triangle-exclamation';
                    $color_icona = $is_error ? '#d32f2f' : '#e65100';
                ?>
                    <div class="normativa-bloc <?= $is_error ? 'normativa-bloc--error' : 'normativa-bloc--avis' ?>">
                        <div class="normativa-bloc__cap <?= $is_error ? 'normativa-bloc__cap--error' : 'normativa-bloc__cap--avis' ?>">
                            <i class="fas <?= $icona ?>" aria-hidden="true"></i>
                            <strong><?= e($alerta['titol']) ?></strong>
                        </div>
                        <p class="normativa-bloc__text"><?= $alerta['text'] ?></p>
                        <?php if (!empty($alerta['link'])): ?>
                            <a href="<?= e($alerta['link']) ?>" class="normativa-link">
                                <?= e($alerta['link_text']) ?> <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($certificacions)): ?>
            <section class="quadern-seccio mt-l">
                <h2 class="quadern-seccio__titol">
                    <i class="fas fa-id-card" aria-hidden="true"></i>
                    Acreditacions del Personal
                </h2>
                <table class="taula-simple taula-simple--normativa">
                    <thead>
                        <tr>
                            <th>Treballador</th>
                            <th>Tipus Acreditació</th>
                            <th>Data obtenció</th>
                            <th>Data caducitat</th>
                            <th>Estat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($certificacions as $cert):
                            $avui = new DateTime('today');
                            $cad  = !empty($cert['data_caducitat']) ? new DateTime($cert['data_caducitat']) : null;
                            $dies = $cad ? (int)$avui->diff($cad)->format('%r%a') : null;
                            $caducada = $cad && $dies < 0;

                            if ($caducada)             $badge_color = '#d32f2f'; // Vermell
                            elseif ($dies !== null && $dies <= 60) $badge_color = '#f57c00'; // Taronja
                            else                       $badge_color = '#388e3c'; // Verd
                            
                            $text_estat = $caducada ? 'Caducada' : ($dies !== null && $dies <= 60 ? 'Pròxima a caducar' : 'Vigent');
                        ?>
                            <tr>
                                <td><strong><?= e($cert['nom_treballador']) ?></strong></td>
                                <td>
                                    <?= e($cert['nom']) ?><br>
                                    <small class="text-suau"><?= e($cert['organisme'] ?? '') ?></small>
                                </td>
                                <td><?= $cert['data_obtencio'] ? format_data($cert['data_obtencio'], curta: true) : '—' ?></td>
                                <td>
                                    <?= $cad ? format_data($cert['data_caducitat'], curta: true) : 'Sense caducitat' ?>
                                    <?php if ($cad && !$caducada && $dies <= 60): ?>
                                        <br><span class="text-suau">Caduca en <?= $dies ?> dies</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-estat-doc <?= $caducada ? 'badge-estat-doc--vermell' : ($dies !== null && $dies <= 60 ? 'badge-estat-doc--taronja' : 'badge-estat-doc--verd') ?>">
                                        <?= $text_estat ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <section class="quadern-seccio mt-l">
            <h2 class="quadern-seccio__titol">
                <i class="fas fa-gavel" aria-hidden="true"></i>
                Marc Normatiu de Referència
            </h2>
            <table class="taula-simple taula-simple--normativa">
                <thead>
                    <tr>
                        <th>Normativa</th>
                        <th>Àmbit</th>
                        <th>Obligació principal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>RD 1311/2012</strong></td>
                        <td>Estatal</td>
                        <td>Ús sostenible dels productes fitosanitaris i Registre d'Explotacions (REGEPA/SIEX).</td>
                    </tr>
                    <tr>
                        <td><strong>RD 1702/2011</strong></td>
                        <td>Estatal</td>
                        <td>Inspeccions periòdiques (ITEAF) dels equips d'aplicació fitosanitària.</td>
                    </tr>
                    <tr>
                        <td><strong>Decret 24/2013 (Cat.)</strong></td>
                        <td>Catalunya</td>
                        <td>Obligació del compliment del quadern d'explotació agrícola.</td>
                    </tr>
                </tbody>
            </table>
        </section>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
