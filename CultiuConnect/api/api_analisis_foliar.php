<?php
/**
 * api/api_analisis_foliar.php
 *
 * API REST per gestionar registres d'analisi_foliar utilitzats
 * com a etapes fenologiques.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit(0);
}

function resposta(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function estats_fenologics_permesos(): array
{
    return [
        'repos_hivernal',
        'brotacio',
        'floracio',
        'creixement_fruit',
        'maduresa',
        'post_collita',
    ];
}

try {
    $pdo = connectDB();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $id = sanitize_int($_GET['id'] ?? null);
        $idSector = sanitize_int($_GET['id_sector'] ?? null);
        $any = sanitize_int($_GET['any'] ?? null);
        $estat = sanitize($_GET['estat_fenologic'] ?? '');

        $sql = "SELECT
                    af.id_analisi_foliar,
                    af.id_sector,
                    af.id_plantacio,
                    af.data_analisi,
                    af.estat_fenologic,
                    af.N, af.P, af.K, af.Ca, af.Mg, af.Fe, af.Mn, af.Zn, af.Cu, af.B,
                    af.deficiencies_detectades,
                    af.recomanacions,
                    af.observacions,
                    s.nom AS nom_sector,
                    v.nom_varietat,
                    pl.data_plantacio
                FROM analisi_foliar af
                LEFT JOIN sector s ON s.id_sector = af.id_sector
                LEFT JOIN plantacio pl ON pl.id_plantacio = af.id_plantacio
                LEFT JOIN varietat v ON v.id_varietat = pl.id_varietat
                WHERE 1=1";
        $params = [];

        if ($id) {
            $sql .= " AND af.id_analisi_foliar = ?";
            $params[] = $id;
        }
        if ($idSector) {
            $sql .= " AND af.id_sector = ?";
            $params[] = $idSector;
        }
        if ($any) {
            $sql .= " AND YEAR(af.data_analisi) = ?";
            $params[] = $any;
        }
        if ($estat !== '') {
            $sql .= " AND af.estat_fenologic = ?";
            $params[] = $estat;
        }

        $sql .= " ORDER BY af.data_analisi DESC, af.id_analisi_foliar DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        resposta(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($method === 'POST' || $method === 'PUT') {
        $id = sanitize_int($_GET['id'] ?? $_POST['id_analisi_foliar'] ?? null);
        $idSector = sanitize_int($_POST['id_sector'] ?? null);
        $idPlantacio = sanitize_int($_POST['id_plantacio'] ?? null);
        $dataAnalisi = sanitize($_POST['data_analisi'] ?? '');
        $estat = sanitize($_POST['estat_fenologic'] ?? '');
        $observacions = sanitize($_POST['observacions'] ?? '') ?: null;

        if (!$idSector || !$dataAnalisi || !strtotime($dataAnalisi) || !$estat) {
            resposta(['success' => false, 'error' => 'Falten dades obligatories.'], 422);
        }
        if (!in_array($estat, estats_fenologics_permesos(), true)) {
            resposta(['success' => false, 'error' => 'L\'estat fenologic no es valid.'], 422);
        }

        $stmt = $pdo->prepare("SELECT id_sector FROM sector WHERE id_sector = ?");
        $stmt->execute([$idSector]);
        if (!$stmt->fetchColumn()) {
            resposta(['success' => false, 'error' => 'El sector no existeix.'], 404);
        }

        if ($idPlantacio) {
            $stmt = $pdo->prepare("SELECT id_plantacio FROM plantacio WHERE id_plantacio = ? AND id_sector = ?");
            $stmt->execute([$idPlantacio, $idSector]);
            if (!$stmt->fetchColumn()) {
                resposta(['success' => false, 'error' => 'La plantacio no pertany al sector indicat.'], 422);
            }
        }

        if ($method === 'POST') {
            $stmt = $pdo->prepare("
                INSERT INTO analisi_foliar (
                    id_sector, id_plantacio, data_analisi, estat_fenologic, N, P, K, observacions
                ) VALUES (
                    :id_sector, :id_plantacio, :data_analisi, :estat_fenologic, :N, :P, :K, :observacions
                )
            ");
            $stmt->execute([
                ':id_sector' => $idSector,
                ':id_plantacio' => $idPlantacio,
                ':data_analisi' => date('Y-m-d', strtotime($dataAnalisi)),
                ':estat_fenologic' => $estat,
                ':N' => sanitize_decimal($_POST['N'] ?? null),
                ':P' => sanitize_decimal($_POST['P'] ?? null),
                ':K' => sanitize_decimal($_POST['K'] ?? null),
                ':observacions' => $observacions,
            ]);

            resposta(['success' => true, 'data' => ['id_analisi_foliar' => (int)$pdo->lastInsertId()]]);
        }

        if (!$id) {
            resposta(['success' => false, 'error' => 'ID d\'analisi no especificat.'], 422);
        }

        $stmt = $pdo->prepare("SELECT id_analisi_foliar FROM analisi_foliar WHERE id_analisi_foliar = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) {
            resposta(['success' => false, 'error' => 'L\'analisi no existeix.'], 404);
        }

        $stmt = $pdo->prepare("
            UPDATE analisi_foliar SET
                id_sector = :id_sector,
                id_plantacio = :id_plantacio,
                data_analisi = :data_analisi,
                estat_fenologic = :estat_fenologic,
                N = :N,
                P = :P,
                K = :K,
                observacions = :observacions
            WHERE id_analisi_foliar = :id
        ");
        $stmt->execute([
            ':id' => $id,
            ':id_sector' => $idSector,
            ':id_plantacio' => $idPlantacio,
            ':data_analisi' => date('Y-m-d', strtotime($dataAnalisi)),
            ':estat_fenologic' => $estat,
            ':N' => sanitize_decimal($_POST['N'] ?? null),
            ':P' => sanitize_decimal($_POST['P'] ?? null),
            ':K' => sanitize_decimal($_POST['K'] ?? null),
            ':observacions' => $observacions,
        ]);

        resposta(['success' => true, 'data' => ['id_analisi_foliar' => $id]]);
    }

    if ($method === 'DELETE') {
        $id = sanitize_int($_GET['id'] ?? null);
        if (!$id) {
            resposta(['success' => false, 'error' => 'ID d\'analisi no especificat.'], 422);
        }

        $stmt = $pdo->prepare("DELETE FROM analisi_foliar WHERE id_analisi_foliar = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) {
            resposta(['success' => false, 'error' => 'L\'analisi no existeix.'], 404);
        }

        resposta(['success' => true, 'data' => ['id_analisi_foliar' => $id]]);
    }

    resposta(['success' => false, 'error' => 'Metode no permes.'], 405);
} catch (Throwable $e) {
    error_log('[CultiuConnect] api_analisis_foliar.php: ' . $e->getMessage());
    resposta(['success' => false, 'error' => 'Error intern del servidor.'], 500);
}
