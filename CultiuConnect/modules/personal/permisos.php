<?php
/**
 * modules/personal/permisos.php — Gestió de permisos i absències.
 *
 * - Tothom pot veure les seves sol·licituds (si està vinculat a treballador).
 * - Admin/Tècnic poden veure i aprovar sol·licituds de tothom.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();
$usuari = usuari_actiu();

function es_gestor(?array $usuari): bool
{
    $rol = strtolower($usuari['rol'] ?? 'operari');
    return in_array($rol, ['admin', 'tecnic', 'responsable'], true);
}

function usuari_treballador_id(PDO $pdo, int $usuari_id): ?int
{
    $stmt = $pdo->prepare("SELECT id_treballador FROM usuari WHERE id_usuari = :id LIMIT 1");
    $stmt->execute([':id' => $usuari_id]);
    $id = $stmt->fetchColumn();
    return $id !== false && $id !== null ? (int)$id : null;
}

$titol_pagina  = 'Permisos i absències';
$pagina_activa = 'permisos';

$error_db = null;
$permisos = [];
$mode = in_array($_GET['mode'] ?? '', ['pendents','aprovats','tots']) ? $_GET['mode'] : 'pendents';

try {
    $pdo = connectDB();
    $gestor = es_gestor($usuari);
    $id_treballador = $usuari ? usuari_treballador_id($pdo, (int)$usuari['id']) : null;

    $where = [];
    $params = [];

    if (!$gestor) {
        if (!$id_treballador) {
            // Usuari sense vinculació: no pot consultar permisos d'altres
            $where[] = '1=0';
        } else {
            $where[] = 'pa.id_treballador = :id_treballador';
            $params[':id_treballador'] = $id_treballador;
        }
    }

    if ($mode === 'pendents') {
        $where[] = 'pa.aprovat = 0';
    } elseif ($mode === 'aprovats') {
        $where[] = 'pa.aprovat = 1';
    }

    $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("
        SELECT
            pa.id_permis,
            pa.id_treballador,
            pa.tipus,
            pa.data_inici,
            pa.data_fi,
            pa.motiu,
            pa.aprovat,
            pa.document_pdf,
            t.nom,
            t.cognoms,
            t.rol AS rol_treballador
        FROM permis_absencia pa
        JOIN treballador t ON t.id_treballador = pa.id_treballador
        $sqlWhere
        ORDER BY pa.aprovat ASC, pa.data_inici DESC, pa.id_permis DESC
    ");
    $stmt->execute($params);
    $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('[CultiuConnect] permisos.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar els permisos.';
}

function etiquetaTipus(string $t): string
{
    return match ($t) {
        'vacances'       => 'Vacances',
        'permis'         => 'Permís',
        'baixa_malaltia' => 'Baixa malaltia',
        'baixa_accident' => 'Baixa accident',
        'curs'           => 'Curs / Formació',
        default          => 'Altres',
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-personal">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-calendar-xmark" aria-hidden="true"></i>
            Permisos i absències
        </h1>
        <p class="descripcio-seccio">
            Sol·licituds de vacances, permisos i baixes. Les aprovades apareixen al calendari.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <div class="botons-accions">
        <a href="<?= BASE_URL ?>modules/personal/nou_permis.php" class="boto-principal">
            <i class="fas fa-plus" aria-hidden="true"></i> Sol·licitar permís
        </a>
    </div>

    <div class="filtres-container">
        <?php foreach (['pendents' => 'Pendents', 'aprovats' => 'Aprovats', 'tots' => 'Tots'] as $val => $lbl): ?>
            <a href="?mode=<?= $val ?>"
               class="filtre-boto <?= $mode === $val ? 'filtre-boto--actiu' : '' ?>">
                <?= $lbl ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="cerca-container">
        <input type="search"
               data-filtre-taula="taula-permisos"
               placeholder="Cerca per treballador, tipus o motiu..."
               class="input-cerca"
               aria-label="Cercar permisos">
    </div>

    <table class="taula-simple" id="taula-permisos">
        <thead>
            <tr>
                <th>ID</th>
                <th>Treballador</th>
                <th>Tipus</th>
                <th>Inici</th>
                <th>Fi</th>
                <th>Motiu</th>
                <th>Estat</th>
                <th>Accions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($permisos)): ?>
                <tr>
                    <td colspan="8" class="sense-dades">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        No hi ha sol·licituds en aquesta vista.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($permisos as $p): ?>
                    <tr>
                        <td><?= (int)$p['id_permis'] ?></td>
                        <td data-cerca>
                            <strong><?= e(trim($p['nom'] . ' ' . ($p['cognoms'] ?? ''))) ?></strong>
                            <div class="text-suau"><?= e($p['rol_treballador'] ?? '') ?></div>
                        </td>
                        <td data-cerca><?= e(etiquetaTipus((string)$p['tipus'])) ?></td>
                        <td><?= format_data((string)$p['data_inici'], curta: true) ?></td>
                        <td><?= $p['data_fi'] ? format_data((string)$p['data_fi'], curta: true) : '<em class="text-suau">Oberta</em>' ?></td>
                        <td data-cerca><?= $p['motiu'] ? e($p['motiu']) : '—' ?></td>
                        <td>
                            <?php if ((int)$p['aprovat'] === 1): ?>
                                <span class="badge badge--verd">Aprovat</span>
                            <?php else: ?>
                                <span class="badge badge--groc">Pendent</span>
                            <?php endif; ?>
                        </td>
                        <td class="cel-accions">
                            <?php if (!empty($p['document_pdf'])): ?>
                                <a href="<?= e($p['document_pdf']) ?>"
                                   class="btn-accio btn-accio--veure"
                                   title="Obrir PDF"
                                   target="_blank" rel="noopener">
                                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>

                            <?php if (es_gestor($usuari) && (int)$p['aprovat'] === 0): ?>
                                <form method="POST"
                                      action="<?= BASE_URL ?>modules/personal/processar_permis.php"
                        class="form-inline-display"
                                      onsubmit="return confirm('Aprovar aquesta sol·licitud?')">
                                    <input type="hidden" name="accio" value="aprovar">
                                    <input type="hidden" name="id_permis" value="<?= (int)$p['id_permis'] ?>">
                                    <button type="submit" class="btn-accio btn-accio--editar" title="Aprovar">
                                        <i class="fas fa-check" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (es_gestor($usuari)): ?>
                                <form method="POST"
                                      action="<?= BASE_URL ?>modules/personal/processar_permis.php"
                        class="form-inline-display"
                                      onsubmit="return confirm('Eliminar aquesta sol·licitud?')">
                                    <input type="hidden" name="accio" value="eliminar">
                                    <input type="hidden" name="id_permis" value="<?= (int)$p['id_permis'] ?>">
                                    <button type="submit" class="btn-accio btn-accio--eliminar" title="Eliminar">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
