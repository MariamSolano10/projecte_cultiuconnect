<?php
/**
 * modules/tractaments/processar_tractament_programat.php
 *
 * Accions POST:
 *  - crear     → INSERT nou tractament programat
 *  - editar    → UPDATE tractament existent
 *  - completar → Canvia estat a 'completat' + desconta estoc (si hi ha protocol amb productes_json)
 *  - cancellar → Canvia estat a 'cancel·lat'
 *  - eliminar  → DELETE
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

/**
 * Valida si hi ha estoc suficient per a un producte
 * @param PDO $pdo
 * @param int $id_producte
 * @param float $quantitat_necessaria
 * @return array ['suficient' => bool, 'estoc_actual' => float, 'estoc_minim' => float]
 */
function validarEstocSuficient($pdo, $id_producte, $quantitat_necessaria) {
    $stmt = $pdo->prepare("
        SELECT estoc_actual, estoc_minim, nom_comercial, unitat_mesura
        FROM producte_quimic
        WHERE id_producte = :id_producte
    ");
    $stmt->execute([':id_producte' => $id_producte]);
    $producte = $stmt->fetch();
    
    if (!$producte) {
        return ['suficient' => false, 'error' => 'Producte no trobat'];
    }
    
    $estoc_actual = (float) $producte['estoc_actual'];
    $estoc_minim = (float) $producte['estoc_minim'];
    
    return [
        'suficient' => $estoc_actual >= $quantitat_necessaria,
        'estoc_actual' => $estoc_actual,
        'estoc_minim' => $estoc_minim,
        'nom_producte' => $producte['nom_comercial'],
        'unitat_mesura' => $producte['unitat_mesura']
    ];
}

/**
 * Genera una notificació d'estoc baix
 * @param PDO $pdo
 * @param int $id_producte
 * @param float $estoc_actual
 * @param float $estoc_minim
 */
function generarNotificacioEstocBaix($pdo, $id_producte, $estoc_actual, $estoc_minim) {
    // Obtenir informació del producte
    $stmt = $pdo->prepare("
        SELECT nom_comercial, unitat_mesura
        FROM producte_quimic
        WHERE id_producte = :id_producte
    ");
    $stmt->execute([':id_producte' => $id_producte]);
    $producte = $stmt->fetch();
    
    if (!$producte) return;
    
    // Crear entrada a log_accions com a notificació
    $stmt = $pdo->prepare("
        INSERT INTO log_accions
            (id_treballador, data_hora, tipus_accio, descripcio, id_element_relacionat, tipus_element)
        VALUES
            (1, NOW(), 'ALERTA_ESTOC', :descripcio, :id_producte, 'producte')
    ");
    $stmt->execute([
        ':descripcio' => 'Estoc baix: ' . $producte['nom_comercial'] . 
                        ' - Actual: ' . number_format($estoc_actual, 2) . ' ' . $producte['unitat_mesura'] .
                        ' / Mínim: ' . number_format($estoc_minim, 2) . ' ' . $producte['unitat_mesura'],
        ':id_producte' => $id_producte
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/tractaments/tractaments_programats.php');
    exit;
}

$accio = $_POST['accio'] ?? '';

// Reconstruïm la URL de retorn mantenint els filtres actius
$filtre_estat  = in_array($_POST['filtre_estat'] ?? '', ['pendent', 'completat', 'cancel·lat', 'tots'])
    ? $_POST['filtre_estat'] : 'pendent';
$filtre_tipus  = in_array($_POST['filtre_tipus'] ?? '', ['preventiu', 'correctiu', 'fertilitzacio', 'tots'])
    ? $_POST['filtre_tipus'] : 'tots';
$filtre_sector = is_numeric($_POST['filtre_sector'] ?? '') ? (int) $_POST['filtre_sector'] : 0;

$url_retorn = BASE_URL . 'modules/tractaments/tractaments_programats.php'
    . '?estat='     . urlencode($filtre_estat)
    . '&tipus='     . urlencode($filtre_tipus)
    . '&id_sector=' . $filtre_sector;

try {
    $pdo = connectDB();

    // ----------------------------------------------------------------
    // CREAR
    // ----------------------------------------------------------------
    if ($accio === 'crear') {

        $tipus         = in_array($_POST['tipus'] ?? '', ['preventiu', 'correctiu', 'fertilitzacio'])
            ? $_POST['tipus'] : null;
        $data_prevista = trim($_POST['data_prevista'] ?? '');
        // id_sector és NOT NULL a la BD — validem que vingui informat
        $id_sector     = !empty($_POST['id_sector']) && is_numeric($_POST['id_sector'])
            ? (int) $_POST['id_sector'] : null;
        $id_protocol   = !empty($_POST['id_protocol']) && is_numeric($_POST['id_protocol'])
            ? (int) $_POST['id_protocol'] : null;
        $motiu         = trim($_POST['motiu']        ?? '') ?: null;
        $observacions  = trim($_POST['observacions'] ?? '') ?: null;
        $dies_avis     = is_numeric($_POST['dies_avis'] ?? '') ? max(0, (int) $_POST['dies_avis']) : 3;

        if (!$tipus || !$data_prevista || !$id_sector) {
            set_flash('error', 'El tipus, el sector i la data prevista són obligatoris.');
            header('Location: ' . BASE_URL . 'modules/tractaments/nou_tractament_programat.php');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO tractament_programat
                (id_sector, id_protocol, data_prevista, tipus, motiu, estat, dies_avis, observacions)
            VALUES
                (:id_sector, :id_protocol, :data_prevista, :tipus, :motiu, 'pendent', :dies_avis, :observacions)
        ");
        $stmt->execute([
            ':id_sector'    => $id_sector,
            ':id_protocol'  => $id_protocol,
            ':data_prevista'=> $data_prevista,
            ':tipus'        => $tipus,
            ':motiu'        => $motiu,
            ':dies_avis'    => $dies_avis,
            ':observacions' => $observacions,
        ]);

        set_flash('success', 'Tractament programat correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // EDITAR
    // ----------------------------------------------------------------
    if ($accio === 'editar') {

        $id_programat = !empty($_POST['id_programat']) ? (int) $_POST['id_programat'] : 0;
        if (!$id_programat) {
            set_flash('error', 'Tractament no identificat.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $tipus         = in_array($_POST['tipus'] ?? '', ['preventiu', 'correctiu', 'fertilitzacio'])
            ? $_POST['tipus'] : null;
        $data_prevista = trim($_POST['data_prevista'] ?? '');
        $id_sector     = !empty($_POST['id_sector']) && is_numeric($_POST['id_sector'])
            ? (int) $_POST['id_sector'] : null;
        $id_protocol   = !empty($_POST['id_protocol']) && is_numeric($_POST['id_protocol'])
            ? (int) $_POST['id_protocol'] : null;
        $motiu         = trim($_POST['motiu']        ?? '') ?: null;
        $observacions  = trim($_POST['observacions'] ?? '') ?: null;
        $dies_avis     = is_numeric($_POST['dies_avis'] ?? '') ? max(0, (int) $_POST['dies_avis']) : 3;
        $estat         = in_array($_POST['estat'] ?? '', ['pendent', 'completat', 'cancel·lat'])
            ? $_POST['estat'] : 'pendent';

        if (!$tipus || !$data_prevista || !$id_sector) {
            set_flash('error', 'El tipus, el sector i la data prevista són obligatoris.');
            header('Location: ' . BASE_URL . 'modules/tractaments/nou_tractament_programat.php?editar=' . $id_programat);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE tractament_programat SET
                id_sector     = :id_sector,
                id_protocol   = :id_protocol,
                data_prevista = :data_prevista,
                tipus         = :tipus,
                motiu         = :motiu,
                estat         = :estat,
                dies_avis     = :dies_avis,
                observacions  = :observacions
            WHERE id_programat = :id_programat
        ");
        $stmt->execute([
            ':id_sector'    => $id_sector,
            ':id_protocol'  => $id_protocol,
            ':data_prevista'=> $data_prevista,
            ':tipus'        => $tipus,
            ':motiu'        => $motiu,
            ':estat'        => $estat,
            ':dies_avis'    => $dies_avis,
            ':observacions' => $observacions,
            ':id_programat' => $id_programat,
        ]);

        set_flash('success', 'Tractament actualitzat correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // COMPLETAR TRACTAMENT I ACTUALITZAR ESTOC
    // ----------------------------------------------------------------
    if ($accio === 'completar') {

        $id_programat = !empty($_POST['id_programat']) ? (int) $_POST['id_programat'] : 0;
        if (!$id_programat) {
            set_flash('error', 'Tractament no identificat.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $pdo->beginTransaction();

        try {
            // 1. Obtenim el tractament (amb el seu protocol i sector)
            $stmtTract = $pdo->prepare("
                SELECT tp.id_programat, tp.estat, tp.id_protocol, tp.id_sector
                FROM tractament_programat tp
                WHERE tp.id_programat = :id
                FOR UPDATE
            ");
            $stmtTract->execute([':id' => $id_programat]);
            $tractament = $stmtTract->fetch();

            if (!$tractament || $tractament['estat'] !== 'pendent') {
                $pdo->rollBack();
                set_flash('warning', 'El tractament ja estava completat, cancel·lat o no existeix.');
                header('Location: ' . $url_retorn);
                exit;
            }

            // 2. Marquem com a completat
            $pdo->prepare("
                UPDATE tractament_programat
                SET estat = 'completat'
                WHERE id_programat = :id
            ")->execute([':id' => $id_programat]);

            // 3. Si té protocol, intentem descomptar estoc dels productes del JSON
            //    Estructura esperada de productes_json:
            //    [{"id_producte": 1, "dosi_ha": 2.5}, {"id_producte": 3, "dosi_ha": 0.5}]
            if ($tractament['id_protocol']) {

                $stmtProt = $pdo->prepare("
                    SELECT productes_json
                    FROM protocol_tractament
                    WHERE id_protocol = :id_protocol
                ");
                $stmtProt->execute([':id_protocol' => $tractament['id_protocol']]);
                $protocol = $stmtProt->fetch();

                $productes = $protocol ? json_decode($protocol['productes_json'] ?? 'null', true) : null;

                if (is_array($productes) && count($productes) > 0) {

                    // Obtenim la superfície total del sector sumant les interseccions de parcel·les
                    // (parcela_sector.superficie_m2 és en m², convertim a ha)
                    $stmtSup = $pdo->prepare("
                        SELECT COALESCE(SUM(ps.superficie_m2) / 10000, 0) AS superficie_ha
                        FROM parcela_sector ps
                        WHERE ps.id_sector = :id_sector
                    ");
                    $stmtSup->execute([':id_sector' => $tractament['id_sector']]);
                    $superficie_ha = (float) $stmtSup->fetchColumn();

                    // Validar estoc suficient per a tots els productes abans de descomptar
                    $errors_estoc = [];
                    $consum_total = [];

                    foreach ($productes as $prod) {
                        $id_producte = (int) ($prod['id_producte'] ?? 0);
                        $dosi_ha     = (float) ($prod['dosi_ha']     ?? 0);

                        if (!$id_producte || $dosi_ha <= 0 || $superficie_ha <= 0) {
                            continue;
                        }

                        $total_consumit = round($dosi_ha * $superficie_ha, 4);
                        $consum_total[$id_producte] = $total_consumit;

                        // Validar estoc suficient
                        $validacio = validarEstocSuficient($pdo, $id_producte, $total_consumit);
                        
                        if (!$validacio['suficient']) {
                            $errors_estoc[] = sprintf(
                                'Estoc insuficient per %s. Necessari: %.2f %s, Disponible: %.2f %s',
                                $validacio['nom_producte'],
                                $total_consumit,
                                $validacio['unitat_mesura'],
                                $validacio['estoc_actual'],
                                $validacio['unitat_mesura']
                            );
                        }
                    }

                    // Si hi ha errors d'estoc, cancel·lar l'operació
                    if (!empty($errors_estoc)) {
                        $pdo->rollBack();
                        set_flash('error', 'No es pot completar el tractament per falta d\'estoc:<br>' . implode('<br>', $errors_estoc));
                        header('Location: ' . $url_retorn);
                        exit;
                    }

                    // Si tot l'estoc és suficient, procedir amb el descompte
                    foreach ($productes as $prod) {
                        $id_producte = (int) ($prod['id_producte'] ?? 0);
                        $dosi_ha     = (float) ($prod['dosi_ha']     ?? 0);

                        if (!$id_producte || $dosi_ha <= 0 || $superficie_ha <= 0) {
                            continue;
                        }

                        $total_consumit = round($dosi_ha * $superficie_ha, 4);

                        // 3a. Descomptem dels lots d'inventari_estoc (FIFO: primer els que caduquen abans)
                        $stmtLots = $pdo->prepare("
                            SELECT id_estoc, quantitat_disponible
                            FROM inventari_estoc
                            WHERE id_producte = :id_producte
                              AND quantitat_disponible > 0
                            ORDER BY data_caducitat ASC, id_estoc ASC
                        ");
                        $stmtLots->execute([':id_producte' => $id_producte]);
                        $lots = $stmtLots->fetchAll();

                        $pendent = $total_consumit;
                        foreach ($lots as $lot) {
                            if ($pendent <= 0) break;

                            $gastat = min($pendent, (float) $lot['quantitat_disponible']);

                            $pdo->prepare("
                                UPDATE inventari_estoc
                                SET quantitat_disponible = quantitat_disponible - :gastat
                                WHERE id_estoc = :id_estoc
                            ")->execute([
                                ':gastat'   => $gastat,
                                ':id_estoc' => $lot['id_estoc'],
                            ]);

                            $pendent -= $gastat;
                        }

                        // 3b. Actualitzem l'estoc agregat a producte_quimic
                        //     (mai per sota de 0, per seguretat)
                        $pdo->prepare("
                            UPDATE producte_quimic
                            SET estoc_actual = GREATEST(0, estoc_actual - :gastat)
                            WHERE id_producte = :id_producte
                        ")->execute([
                            ':gastat'      => $total_consumit,
                            ':id_producte' => $id_producte,
                        ]);

                        // 3c. Comprovar si l'estoc actualitzat està per sota del mínim i generar notificació
                        $stmtEstoc = $pdo->prepare("
                            SELECT estoc_actual, estoc_minim
                            FROM producte_quimic
                            WHERE id_producte = :id_producte
                        ");
                        $stmtEstoc->execute([':id_producte' => $id_producte]);
                        $estoc_info = $stmtEstoc->fetch();

                        if ($estoc_info && $estoc_info['estoc_actual'] < $estoc_info['estoc_minim']) {
                            generarNotificacioEstocBaix(
                                $pdo, 
                                $id_producte, 
                                $estoc_info['estoc_actual'], 
                                $estoc_info['estoc_minim']
                            );
                        }

                        // 3d. Registrem el moviment a l'historial
                        $pdo->prepare("
                            INSERT INTO moviment_estoc
                                (id_producte, tipus_moviment, quantitat, data_moviment, motiu)
                            VALUES
                                (:id_producte, 'Sortida', :quantitat, CURDATE(), :motiu)
                        ")->execute([
                            ':id_producte' => $id_producte,
                            ':quantitat'   => $total_consumit,
                            ':motiu'       => 'Tractament programat #' . $id_programat,
                        ]);
                    }
                }
                // Si productes_json és null o buit, completem el tractament igualment sense tocar estoc
            }

            $pdo->commit();
            set_flash('success', 'Tractament completat correctament.');

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[CultiuConnect] Error en completar tractament #' . $id_programat . ': ' . $e->getMessage());
            set_flash('error', 'Error intern en completar el tractament.');
        }

        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // CANCEL·LAR
    // ----------------------------------------------------------------
    if ($accio === 'cancellar') {

        $id_programat = !empty($_POST['id_programat']) ? (int) $_POST['id_programat'] : 0;
        if (!$id_programat) {
            set_flash('error', 'Tractament no identificat.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE tractament_programat
            SET estat = 'cancel·lat'
            WHERE id_programat = :id AND estat = 'pendent'
        ");
        $stmt->execute([':id' => $id_programat]);

        set_flash('success', 'Tractament cancel·lat.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // ELIMINAR
    // ----------------------------------------------------------------
    if ($accio === 'eliminar') {

        $id_programat = !empty($_POST['id_programat']) ? (int) $_POST['id_programat'] : 0;
        if (!$id_programat) {
            set_flash('error', 'Tractament no identificat.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $pdo->prepare("DELETE FROM tractament_programat WHERE id_programat = :id")
            ->execute([':id' => $id_programat]);

        set_flash('success', 'Tractament eliminat correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // Acció desconeguda
    set_flash('error', 'Acció no reconeguda.');
    header('Location: ' . $url_retorn);
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_tractament_programat.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en processar l\'acció.');
    header('Location: ' . $url_retorn);
    exit;
}