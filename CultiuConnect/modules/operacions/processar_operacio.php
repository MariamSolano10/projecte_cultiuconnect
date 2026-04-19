<?php
/**
 * modules/operacions/processar_operacio.php — Processador del formulari de tractament.
 *
 * Patró PRG (Post → Redirect → Get):
 *   Èxit  → redirigeix a quadern.php amb flash success
 *   Error → redirigeix a operacio_nova.php amb flash error
 *
 * Accions:
 *   1. Valida les dades del POST
 *   2. Insereix a `aplicacio`
 *   3. Insereix a `detall_aplicacio_producte` (si hi ha producte)
 *   4. Desconta `inventari_estoc` per FEFO (primer el que caduca abans)
 *   5. Redirigeix
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

// Només acceptem POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/operacions/operacio_nova.php');
    exit;
}

try {
    $pdo = connectDB();

    // -----------------------------------------------------------
    // 1. Recollida i validació de dades
    // -----------------------------------------------------------
    $id_sector   = sanitize_int($_POST['parcela_aplicacio'] ?? null);
    $id_producte = sanitize_int($_POST['producte_quimic']   ?? null);
    $data_hora   = sanitize($_POST['data_aplicacio']        ?? '');
    $dosi        = sanitize_decimal($_POST['dosi']          ?? null);
    $quantitat   = sanitize_decimal($_POST['quantitat_total'] ?? null);
    $tipus_event = sanitize($_POST['tipus_event']           ?? 'Tractament fitosanitari');
    $condicions  = sanitize($_POST['condicions_ambientals'] ?? '');
    $comentaris  = sanitize($_POST['comentaris']            ?? '');
    $id_maquinaria    = sanitize_int($_POST['id_maquinaria']       ?? null);
    $hores_maquinaria = sanitize_decimal($_POST['hores_maquinaria'] ?? null);

    // Validació
    if (!$id_sector || $id_sector <= 0) {
        throw new InvalidArgumentException('Cal seleccionar un sector d\'aplicació.');
    }
    if (empty($data_hora) || !strtotime($data_hora)) {
        throw new InvalidArgumentException('La data i hora d\'aplicació no és vàlida.');
    }
    if ($dosi === null || $dosi <= 0) {
        throw new InvalidArgumentException('La dosi ha de ser un valor positiu.');
    }
    if ($quantitat === null || $quantitat <= 0) {
        throw new InvalidArgumentException('La quantitat total ha de ser un valor positiu.');
    }

    // Separar data i hora per a la BD (necessari també per validacions normatives)
    $dt          = new DateTime($data_hora);
    $data_event  = $dt->format('Y-m-d');
    $hora_inici  = $dt->format('H:i:s');

    // -----------------------------------------------------------
    // 1b. Validació normativa server-side (no confiïs en l'AJAX)
    // -----------------------------------------------------------
    if ($id_producte && $id_producte > 0) {
        // Carregar dades del producte
        $stmtP = $pdo->prepare("
            SELECT nom_comercial, tipus, termini_seguretat_dies, dosi_max_ha, num_aplicacions_max
            FROM producte_quimic
            WHERE id_producte = :id
            LIMIT 1
        ");
        $stmtP->execute([':id' => $id_producte]);
        $prod = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new InvalidArgumentException('Producte no vàlid.');
        }

        // Dosi màxima per ha
        if ($prod['dosi_max_ha'] !== null && $dosi !== null) {
            $max = (float)$prod['dosi_max_ha'];
            if ($dosi > $max) {
                throw new InvalidArgumentException(
                    sprintf('Dosi superada: %.2f/ha > màxim legal %.2f/ha per a %s.', (float)$dosi, $max, (string)$prod['nom_comercial'])
                );
            }
        }

        // Màxim aplicacions per temporada (aplicades + planificades)
        if ($prod['num_aplicacions_max'] !== null) {
            $any = (int)(new DateTime($data_event))->format('Y');
            $maxA = (int)$prod['num_aplicacions_max'];

            $stmtA = $pdo->prepare("
                SELECT COUNT(DISTINCT a.id_aplicacio)
                FROM aplicacio a
                JOIN detall_aplicacio_producte dap ON dap.id_aplicacio = a.id_aplicacio
                JOIN inventari_estoc ie ON ie.id_estoc = dap.id_estoc
                WHERE a.id_sector = :sector
                  AND ie.id_producte = :producte
                  AND YEAR(a.data_event) = :any
            ");
            $stmtA->execute([':sector' => $id_sector, ':producte' => $id_producte, ':any' => $any]);
            $total_aplicades = (int)$stmtA->fetchColumn();

            $stmtPl = $pdo->prepare("
                SELECT COUNT(*)
                FROM tractament_programat
                WHERE id_sector = :sector
                  AND id_producte = :producte
                  AND estat = 'pendent'
                  AND YEAR(data_prevista) = :any
            ");
            $stmtPl->execute([':sector' => $id_sector, ':producte' => $id_producte, ':any' => $any]);
            $total_plan = (int)$stmtPl->fetchColumn();

            if (($total_aplicades + $total_plan) >= $maxA) {
                throw new InvalidArgumentException(
                    sprintf('Nombre màxim d\'aplicacions assolit (%d/%d) per a %s aquesta temporada.', $total_aplicades + $total_plan, $maxA, (string)$prod['nom_comercial'])
                );
            }
        }

        // Termini seguretat vs collita prevista (mateixa lògica que l'endpoint)
        if ($prod['termini_seguretat_dies'] !== null) {
            $stmtC = $pdo->prepare("
                SELECT pc.data_inici_collita_estimada
                FROM previsio_collita pc
                JOIN plantacio pl ON pl.id_plantacio = pc.id_plantacio
                WHERE pl.id_sector = :sector
                  AND pc.data_inici_collita_estimada IS NOT NULL
                  AND pc.data_inici_collita_estimada >= CURDATE()
                ORDER BY pc.data_inici_collita_estimada ASC
                LIMIT 1
            ");
            $stmtC->execute([':sector' => $id_sector]);
            $coll = $stmtC->fetch(PDO::FETCH_ASSOC);
            if ($coll && !empty($coll['data_inici_collita_estimada'])) {
                $termini = (int)$prod['termini_seguretat_dies'];
                $limit = (new DateTime($data_event))->modify("+{$termini} days");
                $dataColl = new DateTime((string)$coll['data_inici_collita_estimada']);
                if ($limit > $dataColl) {
                    throw new InvalidArgumentException('Termini de seguretat incomplit respecte la collita prevista del sector.');
                }
            }
        }

        // Finestra floració (proxy: analisi_foliar floracio) amb termini
        if ($prod['termini_seguretat_dies'] !== null) {
            $stmtF = $pdo->prepare("
                SELECT data_analisi
                FROM analisi_foliar
                WHERE id_sector = :sector
                  AND estat_fenologic = 'floracio'
                  AND YEAR(data_analisi) = YEAR(:d)
                ORDER BY data_analisi DESC
                LIMIT 1
            ");
            $stmtF->execute([':sector' => $id_sector, ':d' => $data_event]);
            $fl = $stmtF->fetch(PDO::FETCH_ASSOC);
            if ($fl && !empty($fl['data_analisi'])) {
                $termini = (int)$prod['termini_seguretat_dies'];
                $inici = new DateTime($data_event);
                $fi    = (new DateTime($data_event))->modify("+{$termini} days");
                $df    = new DateTime((string)$fl['data_analisi']);
                $iniFl = (clone $df)->modify('-7 days');
                $fiFl  = (clone $df)->modify('+7 days');
                $solapa = ($inici <= $fiFl) && ($fi >= $iniFl);
                $riscAlt = in_array(($prod['tipus'] ?? ''), ['Fitosanitari','Herbicida'], true);
                if ($solapa && $riscAlt) {
                    throw new InvalidArgumentException('Finestra de floració: aquest tractament solapa floració (segons analítiques foliars).');
                }
            }
        }
    }

    // -----------------------------------------------------------
    // 2. Transacció
    // -----------------------------------------------------------
    $pdo->beginTransaction();

    // Inserció a `aplicacio`
    $stmt = $pdo->prepare("
        INSERT INTO aplicacio
            (id_sector, data_event, hora_inici_planificada, tipus_event, condicions_ambientals)
        VALUES
            (:id_sector, :data_event, :hora_inici, :tipus_event, :condicions)
    ");
    $stmt->execute([
        ':id_sector'   => $id_sector,
        ':data_event'  => $data_event,
        ':hora_inici'  => $hora_inici,
        ':tipus_event' => $tipus_event,
        ':condicions'  => $condicions ?: null,
    ]);
    $id_aplicacio = (int)$pdo->lastInsertId();

    // Inserció de maquinària associada (si n'hi ha)
    if ($id_maquinaria && $id_maquinaria > 0) {
        $stmtMaq = $pdo->prepare("
            INSERT INTO maquinaria_aplicacio
                (id_maquinaria, id_aplicacio, hores_utilitzades)
            VALUES
                (:id_maq, :id_app, :hores)
        ");
        $stmtMaq->execute([
            ':id_maq' => $id_maquinaria,
            ':id_app' => $id_aplicacio,
            ':hores'  => $hores_maquinaria ?: null,
        ]);
    }

    // Gestió de producte i estoc (si s'ha seleccionat un producte)
    if ($id_producte && $id_producte > 0) {

        // FEFO: agafa el lot que caduca abans amb estoc suficient
        $stmt_lot = $pdo->prepare("
            SELECT id_estoc, quantitat_disponible
            FROM inventari_estoc
            WHERE id_producte        = :id_producte
              AND quantitat_disponible > 0
            ORDER BY data_caducitat ASC
            LIMIT 1
        ");
        $stmt_lot->execute([':id_producte' => $id_producte]);
        $lot = $stmt_lot->fetch();

        if (!$lot) {
            throw new RuntimeException(
                'No hi ha cap lot d\'aquest producte disponible a l\'inventari. ' .
                'Afegeix estoc abans de registrar el tractament.'
            );
        }

        if ((float)$lot['quantitat_disponible'] < $quantitat) {
            throw new RuntimeException(sprintf(
                'Estoc insuficient. Disponible: %s u. — Necessari: %s u.',
                number_format((float)$lot['quantitat_disponible'], 2, ',', '.'),
                number_format($quantitat, 2, ',', '.')
            ));
        }

        // Inserció al detall de l'aplicació
        $stmt_det = $pdo->prepare("
            INSERT INTO detall_aplicacio_producte
                (id_aplicacio, id_estoc, dosi_aplicada, quantitat_consumida_total)
            VALUES
                (:id_aplicacio, :id_estoc, :dosi, :quantitat)
        ");
        $stmt_det->execute([
            ':id_aplicacio' => $id_aplicacio,
            ':id_estoc'     => $lot['id_estoc'],
            ':dosi'         => $dosi,
            ':quantitat'    => $quantitat,
        ]);

        // Descomptar de l'estoc
        $stmt_upd = $pdo->prepare("
            UPDATE inventari_estoc
            SET quantitat_disponible = quantitat_disponible - :consum
            WHERE id_estoc = :id_estoc
        ");
        $stmt_upd->execute([
            ':consum'   => $quantitat,
            ':id_estoc' => $lot['id_estoc'],
        ]);

        // Crear moviment d'estoc automàtic vinculant-lo a l'aplicació
        $stmt_mov = $pdo->prepare("
            INSERT INTO moviment_estoc
                (id_producte, tipus_moviment, quantitat, data_moviment, motiu)
            VALUES
                (:id_producte, 'Sortida', :quantitat, :data_moviment, :motiu)
        ");
        $stmt_mov->execute([
            ':id_producte' => $id_producte,
            ':quantitat'   => $quantitat,
            ':data_moviment' => $data_event,
            ':motiu' => sprintf(
                'Aplicació #%d - Sector #%d (%s)',
                $id_aplicacio,
                $id_sector,
                $tipus_event
            )
        ]);
    }

    $pdo->commit();

    // Registrar acció al log
    registrar_accio(
        'TRACTAMENT REGISTRAT: ' . $tipus_event . ' a sector #' . $id_sector,
        'aplicacio',
        $id_aplicacio,
        'Producte: ' . ($prod['nom_comercial'] ?? 'Sense producte') . ', Quantitat: ' . number_format($quantitat, 2, ',', '.') . ' ' . ($prod['unitat_mesura'] ?? 'u')
    );

    set_flash('success',
        'Tractament registrat correctament al quadern d\'explotació. ' .
        'L\'estoc s\'ha actualitzat.'
    );
    header('Location: ' . BASE_URL . 'modules/quadern/quadern.php');
    exit;

} catch (InvalidArgumentException $e) {
    // Errors de validació → tornem al formulari
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Dades no vàlides. Revisa els camps del formulari.');
    header('Location: ' . BASE_URL . 'modules/operacions/operacio_nova.php');
    exit;

} catch (RuntimeException $e) {
    // Errors de negoci (estoc insuficient, etc.) → tornem al formulari
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'No s\'ha pogut registrar el tractament. Revisa les dades i l\'estoc.');
    header('Location: ' . BASE_URL . 'modules/operacions/operacio_nova.php');
    exit;

} catch (Exception $e) {
    // Errors inesperats → log intern, missatge genèric
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[CultiuConnect] processar_operacio.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en registrar el tractament. Torna-ho a intentar.');
    header('Location: ' . BASE_URL . 'modules/operacions/operacio_nova.php');
    exit;
}
