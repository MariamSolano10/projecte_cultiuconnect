<?php
/**
 * modules/sectors/processar_fila_arbre.php
 *
 * Accions POST:
 *  - crear   → INSERT nova fila d'arbres
 *  - editar  → UPDATE fila existent
 *  - eliminar → DELETE fila
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'modules/sectors/sectors.php');
    exit;
}

$accio     = $_POST['accio']     ?? '';
$id_sector = sanitize_int($_POST['id_sector'] ?? null);

// Sempre necessitem id_sector per saber on tornar
$url_retorn = $id_sector
    ? BASE_URL . 'modules/sectors/files_arbre.php?id_sector=' . $id_sector
    : BASE_URL . 'modules/sectors/sectors.php';

if (!$id_sector) {
    set_flash('error', 'Sector no identificat.');
    header('Location: ' . $url_retorn);
    exit;
}

// ----------------------------------------------------------------
// Helper: converteix el GeoJSON del formulari a WKT per a MySQL
// Suporta Point i LineString.
// ----------------------------------------------------------------
function geojson_a_wkt(string $json): ?string
{
    $geom = json_decode($json, true);
    if (!$geom || empty($geom['type'])) return null;

    switch ($geom['type']) {
        case 'Point':
            [$lng, $lat] = $geom['coordinates'];
            return sprintf('POINT(%F %F)', (float)$lng, (float)$lat);

        case 'LineString':
            $parells = array_map(
                fn($c) => sprintf('%F %F', (float)$c[0], (float)$c[1]),
                $geom['coordinates']
            );
            return 'LINESTRING(' . implode(', ', $parells) . ')';

        default:
            return null;
    }
}

try {
    $pdo = connectDB();

    // ----------------------------------------------------------------
    // CREAR
    // ----------------------------------------------------------------
    if ($accio === 'crear') {

        $numero     = sanitize_int($_POST['numero']     ?? null);
        $descripcio = sanitize($_POST['descripcio']     ?? '') ?: null;
        $num_arbres = sanitize_int($_POST['num_arbres'] ?? null);
        $geo_json   = trim($_POST['coordenades_geo_json'] ?? '');

        // Validació
        if (!$numero || $numero < 1) {
            set_flash('error', 'El número de fila és obligatori i ha de ser positiu.');
            header('Location: ' . BASE_URL . 'modules/sectors/nou_fila_arbre.php?id_sector=' . $id_sector);
            exit;
        }

        // Comprovem que el número no estigui duplicat al sector
        $stmt_check = $pdo->prepare("
            SELECT COUNT(*) FROM fila_arbre WHERE id_sector = ? AND numero = ?
        ");
        $stmt_check->execute([$id_sector, $numero]);
        if ((int)$stmt_check->fetchColumn() > 0) {
            set_flash('error', "La fila número {$numero} ja existeix en aquest sector.");
            header('Location: ' . BASE_URL . 'modules/sectors/nou_fila_arbre.php?id_sector=' . $id_sector);
            exit;
        }

        // Construïm la part de coordenades
        $wkt = (!empty($geo_json)) ? geojson_a_wkt($geo_json) : null;

        if ($wkt) {
            $stmt = $pdo->prepare("
                INSERT INTO fila_arbre (id_sector, numero, descripcio, num_arbres, coordenades_geo)
                VALUES (:id_sector, :numero, :descripcio, :num_arbres, ST_GeomFromText(:wkt, 4326))
            ");
            $stmt->execute([
                ':id_sector'  => $id_sector,
                ':numero'     => $numero,
                ':descripcio' => $descripcio,
                ':num_arbres' => $num_arbres,
                ':wkt'        => $wkt,
            ]);
        } else {
            // Sense geometria: inserim un punt nul temporal (GEOMETRY NOT NULL a la BD)
            // Usem POINT(0 0) com a valor neutre — és el comportament habitual en sistemes
            // que permeten actualitzar la geo posteriorment.
            // IMPORTANT: Si la BD permet NULL a coordenades_geo, es pot canviar a NULL.
            $stmt = $pdo->prepare("
                INSERT INTO fila_arbre (id_sector, numero, descripcio, num_arbres, coordenades_geo)
                VALUES (:id_sector, :numero, :descripcio, :num_arbres, ST_GeomFromText('POINT(0 0)', 4326))
            ");
            $stmt->execute([
                ':id_sector'  => $id_sector,
                ':numero'     => $numero,
                ':descripcio' => $descripcio,
                ':num_arbres' => $num_arbres,
            ]);
        }

        set_flash('success', "Fila {$numero} creada correctament.");
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // EDITAR
    // ----------------------------------------------------------------
    if ($accio === 'editar') {

        $id_fila    = sanitize_int($_POST['id_fila']    ?? null);
        $numero     = sanitize_int($_POST['numero']     ?? null);
        $descripcio = sanitize($_POST['descripcio']     ?? '') ?: null;
        $num_arbres = sanitize_int($_POST['num_arbres'] ?? null);
        $geo_json   = trim($_POST['coordenades_geo_json'] ?? '');

        if (!$id_fila) {
            set_flash('error', 'Fila no identificada.');
            header('Location: ' . $url_retorn);
            exit;
        }
        if (!$numero || $numero < 1) {
            set_flash('error', 'El número de fila és obligatori i ha de ser positiu.');
            header('Location: ' . BASE_URL . 'modules/sectors/nou_fila_arbre.php?editar=' . $id_fila . '&id_sector=' . $id_sector);
            exit;
        }

        // Comprovem duplicat (excloent la fila actual)
        $stmt_check = $pdo->prepare("
            SELECT COUNT(*) FROM fila_arbre
            WHERE id_sector = ? AND numero = ? AND id_fila != ?
        ");
        $stmt_check->execute([$id_sector, $numero, $id_fila]);
        if ((int)$stmt_check->fetchColumn() > 0) {
            set_flash('error', "La fila número {$numero} ja existeix en aquest sector.");
            header('Location: ' . BASE_URL . 'modules/sectors/nou_fila_arbre.php?editar=' . $id_fila . '&id_sector=' . $id_sector);
            exit;
        }

        $wkt = (!empty($geo_json)) ? geojson_a_wkt($geo_json) : null;

        if ($wkt) {
            $stmt = $pdo->prepare("
                UPDATE fila_arbre SET
                    numero          = :numero,
                    descripcio      = :descripcio,
                    num_arbres      = :num_arbres,
                    coordenades_geo = ST_GeomFromText(:wkt, 4326)
                WHERE id_fila = :id_fila AND id_sector = :id_sector
            ");
            $stmt->execute([
                ':numero'     => $numero,
                ':descripcio' => $descripcio,
                ':num_arbres' => $num_arbres,
                ':wkt'        => $wkt,
                ':id_fila'    => $id_fila,
                ':id_sector'  => $id_sector,
            ]);
        } else {
            // Actualitzem tot excepte les coordenades (les deixem com estan)
            $stmt = $pdo->prepare("
                UPDATE fila_arbre SET
                    numero     = :numero,
                    descripcio = :descripcio,
                    num_arbres = :num_arbres
                WHERE id_fila = :id_fila AND id_sector = :id_sector
            ");
            $stmt->execute([
                ':numero'     => $numero,
                ':descripcio' => $descripcio,
                ':num_arbres' => $num_arbres,
                ':id_fila'    => $id_fila,
                ':id_sector'  => $id_sector,
            ]);
        }

        set_flash('success', "Fila {$numero} actualitzada correctament.");
        header('Location: ' . $url_retorn);
        exit;
    }

    // ----------------------------------------------------------------
    // ELIMINAR
    // ----------------------------------------------------------------
    if ($accio === 'eliminar') {

        $id_fila = sanitize_int($_POST['id_fila'] ?? null);

        if (!$id_fila) {
            set_flash('error', 'Fila no identificada.');
            header('Location: ' . $url_retorn);
            exit;
        }

        // Comprovem que la fila pertany al sector (protecció IDOR)
        $stmt_check = $pdo->prepare("
            SELECT numero FROM fila_arbre WHERE id_fila = ? AND id_sector = ?
        ");
        $stmt_check->execute([$id_fila, $id_sector]);
        $fila = $stmt_check->fetch();

        if (!$fila) {
            set_flash('error', 'La fila no existeix o no pertany a aquest sector.');
            header('Location: ' . $url_retorn);
            exit;
        }

        $pdo->prepare("DELETE FROM fila_arbre WHERE id_fila = ? AND id_sector = ?")
            ->execute([$id_fila, $id_sector]);

        set_flash('success', 'Fila ' . (int)$fila['numero'] . ' eliminada correctament.');
        header('Location: ' . $url_retorn);
        exit;
    }

    // Acció desconeguda
    set_flash('error', 'Acció no reconeguda.');
    header('Location: ' . $url_retorn);
    exit;

} catch (Exception $e) {
    error_log('[CultiuConnect] processar_fila_arbre.php: ' . $e->getMessage());
    set_flash('error', 'Error intern en processar l\'acció.');
    header('Location: ' . $url_retorn);
    exit;
}