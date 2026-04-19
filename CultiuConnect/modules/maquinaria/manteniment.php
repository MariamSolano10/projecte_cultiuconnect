<?php
/**
 * modules/maquinaria/manteniment.php
 *
 * Gestió de manteniment de maquinària agrícola.
 * Permet llistar, crear, editar i marcar com a fets els manteniments.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Manteniment de Maquinària';
$pagina_activa = 'manteniment';

$manteniments = [];
$maquinaries   = [];
$error_db      = null;
$missatge      = null;

// Processament d'accions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accio = sanitize($_POST['accio'] ?? '');
    
    if ($accio === 'marcar_fet') {
        $id_manteniment = sanitize_int($_POST['id_manteniment'] ?? null);
        $data_realitzada = sanitize($_POST['data_realitzada'] ?? date('Y-m-d'));
        
        if ($id_manteniment) {
            try {
                $pdo = connectDB();
                
                $stmt = $pdo->prepare("
                    UPDATE manteniment_maquinaria 
                    SET realitzat = TRUE, data_realitzada = ?
                    WHERE id_manteniment = ?
                ");
                $stmt->execute([$data_realitzada, $id_manteniment]);
                
                if ($stmt->rowCount() > 0) {
                    set_flash('success', 'Manteniment marcat com a realitzat correctament.');
                } else {
                    $missatge = 'El manteniment no existeix o ja estava realitzat.';
                }
                
                header('Location: ' . BASE_URL . 'modules/maquinaria/manteniment.php');
                exit;
                
            } catch (Exception $e) {
                error_log('[CultiuConnect] manteniment.php (marcar_fet): ' . $e->getMessage());
                $missatge = 'Error marcant el manteniment com a realitzat.';
            }
        }
    }
    
    if ($accio === 'eliminar') {
        $id_manteniment = sanitize_int($_POST['id_manteniment'] ?? null);
        
        if ($id_manteniment) {
            try {
                $pdo = connectDB();
                
                $stmt = $pdo->prepare("DELETE FROM manteniment_maquinaria WHERE id_manteniment = ?");
                $stmt->execute([$id_manteniment]);
                
                if ($stmt->rowCount() > 0) {
                    set_flash('success', 'Manteniment eliminat correctament.');
                } else {
                    $missatge = 'El manteniment no existeix.';
                }
                
                header('Location: ' . BASE_URL . 'modules/maquinaria/manteniment.php');
                exit;
                
            } catch (Exception $e) {
                error_log('[CultiuConnect] manteniment.php (eliminar): ' . $e->getMessage());
                $missatge = 'Error eliminant el manteniment.';
            }
        }
    }
}

try {
    $pdo = connectDB();
    
    // Carregar màquines per al selector
    $maquinaries = $pdo->query("
        SELECT id_maquinaria, nom, tipus, marca, model
        FROM maquinaria
        ORDER BY nom ASC
    ")->fetchAll();
    
    // Carregar manteniments
    $sql = "
        SELECT 
            mm.id_manteniment,
            mm.id_maquinaria,
            mm.data_programada,
            mm.data_realitzada,
            mm.tipus_manteniment,
            mm.descripcio,
            mm.cost,
            mm.realitzat,
            mm.observacions,
            m.nom AS nom_maquinaria,
            m.tipus AS tipus_maquinaria
        FROM manteniment_maquinaria mm
        JOIN maquinaria m ON m.id_maquinaria = mm.id_maquinaria
        ORDER BY 
            CASE WHEN mm.realitzat = FALSE THEN 0 ELSE 1 END,
            mm.data_programada ASC,
            mm.id_manteniment DESC
    ";
    
    $manteniments = $pdo->query($sql)->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] manteniment.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades de manteniment.';
}

function classeTipusManteniment($tipus): array
{
    return match($tipus) {
        'preventiu' => ['badge--blau', 'fa-shield-halved', 'Preventiu'],
        'correctiu' => ['badge--vermell', 'fa-wrench', 'Correctiu'],
        'inspeccio' => ['badge--groc', 'fa-search', 'Inspecció'],
        'reparacio' => ['badge--taronja', 'fa-tools', 'Reparació'],
        default => ['badge--gris', 'fa-cog', 'Desconegut']
    };
}

function estatManteniment(bool $realitzat, ?string $data_programada, ?string $data_realitzada): array
{
    if ($realitzat) {
        return ['badge--verd', 'Realitzat', 'fa-check-circle'];
    }
    
    $avui = new DateTime('today');
    $programada = new DateTime($data_programada);
    $dies = $avui->diff($programada)->days;
    
    if ($dies < 0) {
        return ['badge--vermell', 'Retardat', 'fa-exclamation-triangle'];
    } elseif ($dies <= 7) {
        return ['badge--groc', 'Proper', 'fa-clock'];
    } else {
        return ['badge--gris', 'Pendent', 'fa-calendar'];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-manteniment">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-wrench" aria-hidden="true"></i>
            Manteniment de Maquinària
        </h1>
        <p class="descripcio-seccio">
            Gestiona el manteniment preventiu i correctiu de la maquinària agrícola.
            Registra tasques, costos iSegueix l'estat de cada manteniment programat.
        </p>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/maquinaria/nou_manteniment.php" class="boto-principal">
                <i class="fas fa-plus" aria-hidden="true"></i> Nou Manteniment
            </a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <?php if ($missatge): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            <?= e($missatge) ?>
        </div>
    <?php endif; ?>

    <!-- Estadístiques -->
    <div class="kpi-grid kpi-grid--petit">
        <?php 
        $total = count($manteniments);
        $pendents = count(array_filter($manteniments, fn($m) => !$m['realitzat']));
        $realitzats = $total - $pendents;
        $retardats = count(array_filter($manteniments, fn($m) => 
            !$m['realitzat'] && new DateTime($m['data_programada']) < new DateTime('today')
        ));
        ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-tools"></i>
            </div>
            <span class="kpi-card__valor"><?= $total ?></span>
            <span class="kpi-card__etiqueta">Total manteniments</span>
        </div>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-clock"></i>
            </div>
            <span class="kpi-card__valor"><?= $pendents ?></span>
            <span class="kpi-card__etiqueta">Pendents</span>
        </div>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-check-circle"></i>
            </div>
            <span class="kpi-card__valor"><?= $realitzats ?></span>
            <span class="kpi-card__etiqueta">Realitzats</span>
        </div>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <span class="kpi-card__valor"><?= $retardats ?></span>
            <span class="kpi-card__etiqueta">Retardats</span>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="formulari-filtres">
        <div class="form-fila-3">
            <div class="form-grup">
                <label for="id_maquinaria" class="form-label">Màquina (opcional)</label>
                <select id="id_maquinaria" name="id_maquinaria" class="form-select">
                    <option value="">Totes les màquines</option>
                    <?php foreach ($maquinaries as $m): ?>
                        <option value="<?= (int)$m['id_maquinaria'] ?>">
                            <?= e($m['nom']) ?> (<?= e($m['tipus']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup">
                <label for="estat" class="form-label">Estat</label>
                <select id="estat" name="estat" class="form-select">
                    <option value="">Tots</option>
                    <option value="pendents">Pendents</option>
                    <option value="realitzats">Realitzats</option>
                    <option value="retardats">Retardats</option>
                </select>
            </div>
            <div class="form-grup form-grup--accio">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
                </button>
            </div>
        </div>
    </form>

    <!-- Taula de manteniments -->
    <div class="taula-container">
        <table class="taula-simple">
            <thead>
                <tr>
                    <th>Data Programada</th>
                    <th>Data Realitzada</th>
                    <th>Màquina</th>
                    <th>Tipus</th>
                    <th>Descripció</th>
                    <th>Cost</th>
                    <th>Estat</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($manteniments)): ?>
                    <tr>
                        <td colspan="8" class="sense-dades">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            No hi ha manteniments registrats.
                            <a href="<?= BASE_URL ?>modules/maquinaria/nou_manteniment.php">Afegeix-ne un.</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($manteniments as $m): 
                        [$classe_tipus, $icona_tipus, $text_tipus] = classeTipusManteniment($m['tipus_manteniment']);
                        [$classe_estat, $text_estat, $icona_estat] = estatManteniment($m['realitzat'], $m['data_programada'], $m['data_realitzada']);
                    ?>
                        <tr>
                            <td>
                                <?= format_data($m['data_programada']) ?>
                            </td>
                            <td>
                                <?= $m['data_realitzada'] ? format_data($m['data_realitzada']) : '---' ?>
                            </td>
                            <td>
                                <div>
                                    <strong><?= e($m['nom_maquinaria']) ?></strong>
                                    <br><small class="text-suau"><?= e($m['tipus_maquinaria']) ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $classe_tipus ?>">
                                    <i class="fas <?= $icona_tipus ?>"></i>
                                    <?= $text_tipus ?>
                                </span>
                            </td>
                            <td class="cel-text-llarg">
                                <?= e(substr($m['descripcio'], 0, 100)) ?>
                                <?= strlen($m['descripcio']) > 100 ? '...' : '' ?>
                            </td>
                            <td class="text-dreta">
                                <?= $m['cost'] ? number_format((float)$m['cost'], 2, ',', '.') . ' EUR' : '---' ?>
                            </td>
                            <td>
                                <span class="badge <?= $classe_estat ?>">
                                    <i class="fas <?= $icona_estat ?>"></i>
                                    <?= $text_estat ?>
                                </span>
                            </td>
                            <td class="cel-accions">
                                <?php if (!$m['realitzat']): ?>
                                    <button type="button" 
                                            class="btn-accio btn-accio--veure" 
                                            onclick="marcarComFet(<?= (int)$m['id_manteniment'] ?>)"
                                            title="Marcar com a realitzat">
                                        <i class="fas fa-check" aria-hidden="true"></i>
                                        <span class="sr-only">Marcar fet</span>
                                    </button>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>modules/maquinaria/nou_manteniment.php?editar=<?= (int)$m['id_manteniment'] ?>" 
                                   class="btn-accio btn-accio--editar"
                                   title="Editar manteniment">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                    <span class="sr-only">Editar</span>
                                </a>
                            <form method="POST" class="form-inline-display"
                                      onsubmit="return confirm('Estàs segur que vols eliminar aquest manteniment?');">
                                    <input type="hidden" name="accio" value="eliminar">
                                    <input type="hidden" name="id_manteniment" value="<?= (int)$m['id_manteniment'] ?>">
                                    <button type="submit" 
                                            title="Eliminar manteniment"
                                            class="btn-accio btn-accio--eliminar">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                        <span class="sr-only">Eliminar</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Formulari ocult per marcar com a fet -->
                <form id="formMarcarFet" method="POST" class="amagat">
    <input type="hidden" name="accio" value="marcar_fet">
    <input type="hidden" name="id_manteniment" id="idMantenimentFet">
    <input type="hidden" name="data_realitzada" id="dataRealitzadaFet">
</form>

<script>
function marcarComFet(idManteniment) {
    if (confirm('Vols marcar aquest manteniment com a realitzat avui?')) {
        document.getElementById('idMantenimentFet').value = idManteniment;
        document.getElementById('dataRealitzadaFet').value = '<?= date('Y-m-d') ?>';
        document.getElementById('formMarcarFet').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
