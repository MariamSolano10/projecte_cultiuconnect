<?php
/**
 * modules/comandes/comandes.php
 *
 * Gestió de comandes de clients.
 * Permet llistar, crear, editar i gestionar l'estat de les comandes.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina  = 'Gestió de Comandes';
$pagina_activa = 'comandes';

$comandes    = [];
$clients     = [];
$error_db    = null;
$missatge     = null;

// Processament d'accions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accio = sanitize($_POST['accio'] ?? '');
    
    if ($accio === 'actualitzar_estat') {
        $id_comanda = sanitize_int($_POST['id_comanda'] ?? null);
        $estat_nou = sanitize($_POST['estat_comanda'] ?? null);
        
        if ($id_comanda && $estat_nou) {
            try {
                $pdo = connectDB();
                
                $stmt = $pdo->prepare("
                    UPDATE comanda 
                    SET estat_comanda = ?
                    WHERE id_comanda = ?
                ");
                $stmt->execute([$estat_nou, $id_comanda]);
                
                if ($stmt->rowCount() > 0) {
                    set_flash('success', 'Estat de la comanda actualitzat correctament.');
                } else {
                    $missatge = 'La comanda no existeix.';
                }
                
                header('Location: ' . BASE_URL . 'modules/comandes/comandes.php');
                exit;
                
            } catch (Exception $e) {
                error_log('[CultiuConnect] comandes.php (actualitzar_estat): ' . $e->getMessage());
                $missatge = 'Error actualitzant l\'estat de la comanda.';
            }
        }
    }
    
    if ($accio === 'eliminar') {
        $id_comanda = sanitize_int($_POST['id_comanda'] ?? null);
        
        if ($id_comanda) {
            try {
                $pdo = connectDB();
                
                // Verificar si té factura associada
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM factura WHERE id_comanda = ?");
                $stmt->execute([$id_comanda]);
                $te_factura = (int)$stmt->fetchColumn() > 0;
                
                if ($te_factura) {
                    $missatge = 'No es pot eliminar la comanda perquè té una factura associada.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM comanda WHERE id_comanda = ?");
                    $stmt->execute([$id_comanda]);
                    
                    if ($stmt->rowCount() > 0) {
                        set_flash('success', 'Comanda eliminada correctament.');
                    } else {
                        $missatge = 'La comanda no existeix.';
                    }
                }
                
                header('Location: ' . BASE_URL . 'modules/comandes/comandes.php');
                exit;
                
            } catch (Exception $e) {
                error_log('[CultiuConnect] comandes.php (eliminar): ' . $e->getMessage());
                $missatge = 'Error eliminant la comanda.';
            }
        }
    }
}

try {
    $pdo = connectDB();
    
    // Carregar clients per al selector
    $clients = $pdo->query("
        SELECT id_client, nom_client, tipus_client
        FROM client
        WHERE estat = 'actiu'
        ORDER BY nom_client ASC
    ")->fetchAll();
    
    // Carregar totes les comandes
    $comandes = $pdo->query("
        SELECT 
            c.id_comanda,
            c.num_comanda,
            c.data_comanda,
            c.data_entrega_prevista,
            c.estat_comanda,
            c.forma_pagament,
            c.subtotal,
            c.iva_import,
            c.total,
            cl.nom_client,
            cl.tipus_client,
            COUNT(dc.id_detall) AS num_productes
        FROM comanda c
        JOIN client cl ON cl.id_client = c.id_client
        LEFT JOIN detall_comanda dc ON dc.id_comanda = c.id_comanda
        GROUP BY c.id_comanda
        ORDER BY c.data_comanda DESC, c.id_comanda DESC
    ")->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] comandes.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les comandes.';
}

function classeEstatComanda($estat): array
{
    return match($estat) {
        'pendent' => ['badge--groc', 'Pendent'],
        'preparacio' => ['badge--blau', 'Preparació'],
        'enviat' => ['badge--cyan', 'Enviat'],
        'entregat' => ['badge--verd', 'Entregat'],
        'cancelat' => ['badge--vermell', 'Cancel·lat'],
        default => ['badge--gris', 'Desconegut']
    };
}

function classeFormaPagament($forma): array
{
    return match($forma) {
        'transferencia' => ['badge--blau', 'fa-university'],
        'targeta' => ['badge--verd', 'fa-credit-card'],
        'efectiu' => ['badge--groc', 'fa-money-bill-wave'],
        'poder' => ['badge--taronja', 'fa-file-invoice'],
        'altres' => ['badge--gris', 'fa-ellipsis-h'],
        default => ['badge--gris', 'fa-ellipsis-h']
    };
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-comandes">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
            Gestió de Comandes
        </h1>
        <p class="descripcio-seccio">
            Administra les comandes de productes agrícoles. Gestiona l'estat,
            els detalls i la facturació de cada comanda.
        </p>
        <div class="capcalera-seccio__accions">
            <a href="<?= BASE_URL ?>modules/comandes/nova_comanda.php" class="boto-principal">
                <i class="fas fa-plus" aria-hidden="true"></i> Nova Comanda
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
        $total = count($comandes);
        $pendents = count(array_filter($comandes, fn($c) => $c['estat_comanda'] === 'pendent'));
        $entregades = count(array_filter($comandes, fn($c) => $c['estat_comanda'] === 'entregat'));
        $import_total = array_sum(array_column($comandes, 'total'));
        ?>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <span class="kpi-card__valor"><?= $total ?></span>
            <span class="kpi-card__etiqueta">Total comandes</span>
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
            <span class="kpi-card__valor"><?= $entregades ?></span>
            <span class="kpi-card__etiqueta">Entregades</span>
        </div>
        <div class="kpi-card">
            <div class="kpi-card__icona">
                <i class="fas fa-coins"></i>
            </div>
            <span class="kpi-card__valor"><?= number_format($import_total, 2, ',', '.') ?> EUR</span>
            <span class="kpi-card__etiqueta">Import total</span>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="formulari-filtres">
        <div class="form-fila-3">
            <div class="form-grup">
                <label for="id_client" class="form-label">Client (opcional)</label>
                <select id="id_client" name="id_client" class="form-select">
                    <option value="">Tots els clients</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= (int)$c['id_client'] ?>">
                            <?= e($c['nom_client']) ?> (<?= e($c['tipus_client']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup">
                <label for="estat_comanda" class="form-label">Estat</label>
                <select id="estat_comanda" name="estat_comanda" class="form-select">
                    <option value="">Tots els estats</option>
                    <option value="pendent">Pendent</option>
                    <option value="preparacio">Preparació</option>
                    <option value="enviat">Enviat</option>
                    <option value="entregat">Entregat</option>
                    <option value="cancelat">Cancel·lat</option>
                </select>
            </div>
            <div class="form-grup form-grup--accio">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
                </button>
            </div>
        </div>
    </form>

    <!-- Taula de comandes -->
    <div class="taula-container">
        <table class="taula-simple">
            <thead>
                <tr>
                    <th>Núm. Comanda</th>
                    <th>Data</th>
                    <th>Client</th>
                    <th>Estat</th>
                    <th>Productes</th>
                    <th>Total</th>
                    <th>Pagament</th>
                    <th>Accions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($comandes)): ?>
                    <tr>
                        <td colspan="8" class="sense-dades">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            No hi ha comandes registrades.
                            <a href="<?= BASE_URL ?>modules/comandes/nova_comanda.php">Crea una.</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($comandes as $comanda): 
                        [$classe_estat, $text_estat] = classeEstatComanda($comanda['estat_comanda']);
                        [$classe_pagament, $icona_pagament] = classeFormaPagament($comanda['forma_pagament']);
                    ?>
                        <tr>
                            <td>
                                <strong><?= e($comanda['num_comanda']) ?></strong>
                            </td>
                            <td>
                                <?= format_data($comanda['data_comanda'], curta: true) ?>
                                <?php if ($comanda['data_entrega_prevista']): ?>
                                    <br><small class="text-suau">Entrega: <?= format_data($comanda['data_entrega_prevista'], curta: true) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>
                                    <strong><?= e($comanda['nom_client']) ?></strong>
                                    <br><small class="text-suau"><?= ucfirst($comanda['tipus_client']) ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $classe_estat ?>">
                                    <?= $text_estat ?>
                                </span>
                            </td>
                            <td>
                                <?= (int)$comanda['num_productes'] ?> productes
                            </td>
                            <td class="text-dreta">
                                <strong><?= number_format((float)$comanda['total'], 2, ',', '.') ?> EUR</strong>
                                <br><small class="text-suau">Base: <?= number_format((float)$comanda['subtotal'], 2, ',', '.') ?></small>
                            </td>
                            <td>
                                <span class="badge <?= $classe_pagament ?>">
                                    <i class="fas <?= $icona_pagament ?>"></i>
                                    <?= ucfirst($comanda['forma_pagament']) ?>
                                </span>
                            </td>
                            <td class="cel-accions">
                                <a href="<?= BASE_URL ?>modules/comandes/veure_comanda.php?id=<?= (int)$comanda['id_comanda'] ?>" 
                                   class="btn-accio btn-accio--veure"
                                   title="Veure detall">
                                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                    <span class="sr-only">Veure</span>
                                </a>
                                <a href="<?= BASE_URL ?>modules/comandes/editar_comanda.php?id=<?= (int)$comanda['id_comanda'] ?>" 
                                   class="btn-accio btn-accio--editar"
                                   title="Editar comanda">
                                    <i class="fas fa-pen" aria-hidden="true"></i>
                                    <span class="sr-only">Editar</span>
                                </a>
                                <div class="dropdown-accio">
                                    <button type="button" class="btn-accio btn-accio--estat" title="Canviar estat">
                                        <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                                        <span class="sr-only">Canviar estat</span>
                                    </button>
                                    <div class="dropdown-menu">
                                        <form method="POST">
                                            <input type="hidden" name="accio" value="actualitzar_estat">
                                            <input type="hidden" name="id_comanda" value="<?= (int)$comanda['id_comanda'] ?>">
                                            <input type="hidden" name="estat_comanda" value="preparacio">
                                            <button type="submit" class="dropdown-item">Preparació</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="accio" value="actualitzar_estat">
                                            <input type="hidden" name="id_comanda" value="<?= (int)$comanda['id_comanda'] ?>">
                                            <input type="hidden" name="estat_comanda" value="enviat">
                                            <button type="submit" class="dropdown-item">Enviat</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="accio" value="actualitzar_estat">
                                            <input type="hidden" name="id_comanda" value="<?= (int)$comanda['id_comanda'] ?>">
                                            <input type="hidden" name="estat_comanda" value="entregat">
                                            <button type="submit" class="dropdown-item">Entregat</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="accio" value="actualitzar_estat">
                                            <input type="hidden" name="id_comanda" value="<?= (int)$comanda['id_comanda'] ?>">
                                            <input type="hidden" name="estat_comanda" value="cancelat">
                                            <button type="submit" class="dropdown-item">Cancel·lat</button>
                                        </form>
                                    </div>
                                </div>
                                <form method="POST" class="form-inline" 
                                      onsubmit="return confirm('Estàs segur que vols eliminar aquesta comanda? Aquesta acció no es pot desfer.');">
                                    <input type="hidden" name="accio" value="eliminar">
                                    <input type="hidden" name="id_comanda" value="<?= (int)$comanda['id_comanda'] ?>">
                                    <button type="submit" 
                                            title="Eliminar comanda"
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tancar dropdowns quan es fa clic fora
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-accio')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
