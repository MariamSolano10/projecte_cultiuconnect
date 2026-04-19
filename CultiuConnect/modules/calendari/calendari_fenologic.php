<?php
/**
 * modules/calendari/calendari_fenologic.php
 *
 * Calendari fenològic interactiu que mostra les etapes de creixement del cultiu
 * al llarg de l'any. Permet visualitzar, afegir i modificar les etapes fenològiques
 * per a cada plantació/sector.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina = 'Calendari Fenològic';
$pagina_activa = 'calendari';

$sectors = [];
$plantes = [];
$etapes_fenologiques = [];
$error_db = null;

// Filtres
$any_sel = sanitize_int($_GET['any'] ?? date('Y'));
$sector_sel = sanitize_int($_GET['id_sector'] ?? null);

try {
    $pdo = connectDB();

    // Carregar sectors
    $sectors = $pdo->query("SELECT id_sector, nom FROM sector ORDER BY nom ASC")->fetchAll();

    // Carregar plantacions (si hi ha filtre de sector)
    $sql_plantes = "SELECT p.id_plantacio, p.id_sector, p.data_plantacio, v.nom_varietat 
                    FROM plantacio p 
                    JOIN varietat v ON v.id_varietat = p.id_varietat";
    $params = [];
    if ($sector_sel) {
        $sql_plantes .= " WHERE p.id_sector = ?";
        $params[] = $sector_sel;
    }
    $sql_plantes .= " ORDER BY p.data_plantacio DESC";
    $stmt = $pdo->prepare($sql_plantes);
    $stmt->execute($params);
    $plantes = $stmt->fetchAll();

    // Carregar etapes fenològiques existents
    $sql_etapes = "SELECT 
                    af.id_analisi_foliar,
                    af.id_sector,
                    af.id_plantacio,
                    af.data_analisi,
                    af.estat_fenologic,
                    s.nom AS nom_sector,
                    v.nom_varietat
                   FROM analisi_foliar af
                   LEFT JOIN sector s ON s.id_sector = af.id_sector
                   LEFT JOIN plantacio pl ON pl.id_plantacio = af.id_plantacio
                   LEFT JOIN varietat v ON v.id_varietat = pl.id_varietat
                   WHERE YEAR(af.data_analisi) = ?";
    $params_etapes = [$any_sel];
    
    if ($sector_sel) {
        $sql_etapes .= " AND af.id_sector = ?";
        $params_etapes[] = $sector_sel;
    }
    
    $sql_etapes .= " ORDER BY af.data_analisi ASC";
    $stmt = $pdo->prepare($sql_etapes);
    $stmt->execute($params_etapes);
    $etapes_fenologiques = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('[CultiuConnect] calendari_fenologic.php: ' . $e->getMessage());
    $error_db = 'No s\'han pogut carregar les dades del calendari fenològic.';
}

// Definició de les etapes fenològiques amb colors i descripcions
$definicio_etapes = [
    'repos_hivernal' => [
        'nom' => 'Repos Hivernal',
        'color' => '#6B8E23',
        'descripcio' => 'Període de dormància de la planta durant l\'hivern',
        'icona' => 'fa-snowflake'
    ],
    'brotacio' => [
        'nom' => 'Brotació',
        'color' => '#90EE90',
        'descripcio' => 'Inici del creixement actiu amb sortida de nous brots',
        'icona' => 'fa-seedling'
    ],
    'floracio' => [
        'nom' => 'Floració',
        'color' => '#FFB6C1',
        'descripcio' => 'Període de floració i pol·linització',
        'icona' => 'fa-spa'
    ],
    'creixement_fruit' => [
        'nom' => 'Fructificació',
        'color' => '#FFA500',
        'descripcio' => 'Desenvolupament dels fruits després de la pol·linització',
        'icona' => 'fa-apple-alt'
    ],
    'maduresa' => [
        'nom' => 'Maduració',
        'color' => '#FF6347',
        'descripcio' => 'Els fruits assoleixen el seu màxim desenvolupament i dolçor',
        'icona' => 'fa-apple-whole'
    ],
    'post_collita' => [
        'nom' => 'Collita',
        'color' => '#8B4513',
        'descripcio' => 'Període de recol·lecció dels fruits',
        'icona' => 'fa-basket-shopping'
    ],
    'caiguda_fulles' => [
        'nom' => 'Caiguda de Fulles',
        'color' => '#DAA520',
        'descripcio' => 'Process natural de pèrdua de les fulles a la tardor',
        'icona' => 'fa-leaf'
    ]
];

$css_addicional = [
    'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-calendari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-leaf" aria-hidden="true"></i>
            Calendari Fenològic
        </h1>
        <p class="descripcio-seccio">
            Visualitza i gestiona les etapes de creixement del cultiu al llarg de l'any.
            Segueix l'evolució fenològica de les teves plantacions.
        </p>
    </div>

    <?php if ($error_db): ?>
        <div class="flash flash--error" role="alert">
            <i class="fas fa-circle-xmark" aria-hidden="true"></i>
            <?= e($error_db) ?>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <form method="GET" class="formulari-filtres">
        <div class="form-fila-3">
            <div class="form-grup">
                <label for="any" class="form-label">Any</label>
                <select id="any" name="any" class="form-select">
                    <?php for ($a = (int)date('Y'); $a >= (int)date('Y') - 3; $a--): ?>
                        <option value="<?= $a ?>" <?= $a === $any_sel ? 'selected' : '' ?>>
                            <?= $a ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-grup">
                <label for="id_sector" class="form-label">Sector (opcional)</label>
                <select id="id_sector" name="id_sector" class="form-select">
                    <option value="">Tots els sectors</option>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?= (int)$s['id_sector'] ?>" 
                                <?= $sector_sel === (int)$s['id_sector'] ? 'selected' : '' ?>>
                            <?= e($s['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup form-grup--accio">
                <button type="submit" class="boto-principal">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrar
                </button>
                <button type="button" class="boto-secundari" id="btn-nova-etapa">
                    <i class="fas fa-plus" aria-hidden="true"></i> Nova Etapa
                </button>
            </div>
        </div>
    </form>

    <!-- Llegenda d'etapes fenològiques -->
    <div class="detall-bloc detall-bloc--separat">
        <h3 class="detall-bloc__titol">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            Etapes Fenològiques
        </h3>
        <div class="etapes-llegenda">
            <?php foreach ($definicio_etapes as $key => $etapa): ?>
                <div class="etapa-item">
                    <div class="etapa-color" data-color="<?= e($etapa['color']) ?>">
                        <i class="fas <?= $etapa['icona'] ?>"></i>
                    </div>
                    <div class="etapa-info">
                        <strong><?= e($etapa['nom']) ?></strong>
                        <small><?= e($etapa['descripcio']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Calendari -->
    <div class="calendari-contenidor">
        <div id="calendar-fenologic"></div>
    </div>

    <!-- Formulari modal per afegir/editar etapa fenològica -->
    <div id="modal-etapa" class="popup-overlay" role="dialog" aria-modal="true" hidden>
        <div class="popup-caixa popup-caixa--gran">
            <div class="popup-cap">
                <h2 class="popup-titol">Gestionar Etapa Fenològica</h2>
                <button type="button" class="popup-tancar" id="modal-tancar">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            
            <form id="form-etapa" class="formulari-card">
                <input type="hidden" id="id_analisi_foliar" name="id_analisi_foliar">
                
                <div class="form-fila-2">
                    <div class="form-grup">
                        <label for="data_analisi" class="form-label form-label--requerit">
                            Data de l'etapa
                        </label>
                        <input type="date" id="data_analisi" name="data_analisi" class="form-input camp-requerit" required>
                    </div>
                    
                    <div class="form-grup">
                        <label for="id_sector" class="form-label form-label--requerit">
                            Sector
                        </label>
                        <select id="sector_modal" name="id_sector" class="form-select camp-requerit" required>
                            <option value="">Selecciona un sector</option>
                            <?php foreach ($sectors as $s): ?>
                                <option value="<?= (int)$s['id_sector'] ?>"><?= e($s['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-fila-2">
                    <div class="form-grup">
                        <label for="id_plantacio" class="form-label">
                            Plantació (opcional)
                        </label>
                        <select id="id_plantacio" name="id_plantacio" class="form-select">
                            <option value="">Selecciona una plantació</option>
                        </select>
                    </div>
                    
                    <div class="form-grup">
                        <label for="estat_fenologic" class="form-label form-label--requerit">
                            Etapa Fenològica
                        </label>
                        <select id="estat_fenologic" name="estat_fenologic" class="form-select camp-requerit" required>
                            <?php foreach ($definicio_etapes as $key => $etapa): ?>
                                <option value="<?= $key ?>">
                                    <?= e($etapa['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grup">
                    <label for="observacions" class="form-label">
                        Observacions
                    </label>
                    <textarea id="observacions" name="observacions" class="form-textarea" rows="3"
                              placeholder="Notes sobre aquesta etapa fenològica..."></textarea>
                </div>

                <div class="form-botons">
                    <button type="submit" class="boto-principal">
                        <i class="fas fa-save" aria-hidden="true"></i> Guardar Etapa
                    </button>
                    <button type="button" class="boto-secundari" id="btn-cancelar">
                        <i class="fas fa-xmark" aria-hidden="true"></i> Cancel·lar
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE_URL = '<?= BASE_URL ?>';
    const calendarEl = document.getElementById('calendar-fenologic');
    const modal = document.getElementById('modal-etapa');
    const form = document.getElementById('form-etapa');
    const sectorSelect = document.getElementById('sector_modal');
    const plantacioSelect = document.getElementById('id_plantacio');

    // Dades de les etapes fenològiques
    const definicioEtapes = <?= json_encode($definicio_etapes) ?>;
    const etapesExistents = <?= json_encode($etapes_fenologiques) ?>;
    const plantacions = <?= json_encode($plantes) ?>;

    // Convertir etapes a esdeveniments FullCalendar
    function convertirEsdeveniments() {
        return etapesExistents.map(etapa => {
            const definicio = definicioEtapes[etapa.estat_fenologic] || {};
            return {
                id: etapa.id_analisi_foliar,
                title: `${definicio.nom || etapa.estat_fenologic} - ${etapa.nom_sector || ''}`,
                start: etapa.data_analisi,
                backgroundColor: definicio.color || '#6B8E23',
                borderColor: definicio.color || '#6B8E23',
                extendedProps: {
                    tipus: 'fenologic',
                    estat: etapa.estat_fenologic,
                    sector: etapa.nom_sector,
                    varietat: etapa.nom_varietat,
                    id_sector: etapa.id_sector,
                    id_plantacio: etapa.id_plantacio
                }
            };
        });
    }

    // Carregar plantacions segons sector seleccionat
    sectorSelect.addEventListener('change', function() {
        const idSector = this.value;
        plantacioSelect.innerHTML = '<option value="">Selecciona una plantació</option>';
        
        if (idSector) {
            const plantacionsSector = plantacions.filter(p => p.id_sector == idSector);
            plantacionsSector.forEach(planta => {
                const option = document.createElement('option');
                option.value = planta.id_plantacio;
                option.textContent = `${planta.nom_varietat} - ${formatData(planta.data_plantacio)}`;
                plantacioSelect.appendChild(option);
            });
        }
    });

    // Configuració FullCalendar
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'ca',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        buttonText: {
            today: 'Avui',
            month: 'Mes',
            week: 'Setmana',
            list: 'Llista'
        },
        events: convertirEsdeveniments(),
        
        eventContent: function(arg) {
            const definicio = definicioEtapes[arg.event.extendedProps.estat] || {};
            return {
                html: `
                    <div class="fc-event-fenologic" data-color="${definicio.color || '#6B8E23'}">
                        <div class="fc-event-title">
                            <i class="fas ${definicio.icona || 'fa-leaf'}"></i>
                            ${arg.event.title}
                        </div>
                    </div>
                `
            };
        },

        eventDidMount: function(info) {
            const eventEl = info.el.querySelector('.fc-event-fenologic');
            if (eventEl?.dataset.color) {
                eventEl.style.borderColor = eventEl.dataset.color;
            }
        },

        eventClick: function(info) {
            // Carregar dades de l'esdeveniment al formulari
            const props = info.event.extendedProps;
            document.getElementById('id_analisi_foliar').value = info.event.id;
            document.getElementById('data_analisi').value = info.event.startStr.split('T')[0];
            document.getElementById('sector_modal').value = props.id_sector || '';
            
            // Disparar event per carregar plantacions
            sectorSelect.dispatchEvent(new Event('change'));
            
            // Seleccionar plantació si existeix
            setTimeout(() => {
                document.getElementById('id_plantacio').value = props.id_plantacio || '';
            }, 100);
            
            document.getElementById('estat_fenologic').value = props.estat || '';
            
            modal.hidden = false;
        },

        dateClick: function(info) {
            // Preparar formulari per nova etapa
            form.reset();
            document.getElementById('id_analisi_foliar').value = '';
            document.getElementById('data_analisi').value = info.dateStr;
            modal.hidden = false;
        }
    });

    document.querySelectorAll('.etapa-color[data-color]').forEach(el => {
        el.style.backgroundColor = el.dataset.color;
    });

    calendar.render();

    // Gestió del modal
    document.getElementById('btn-nova-etapa').addEventListener('click', function() {
        form.reset();
        document.getElementById('id_analisi_foliar').value = '';
        document.getElementById('data_analisi').value = new Date().toISOString().split('T')[0];
        modal.hidden = false;
    });

    document.getElementById('modal-tancar').addEventListener('click', function() {
        modal.hidden = true;
    });

    document.getElementById('btn-cancelar').addEventListener('click', function() {
        modal.hidden = true;
    });

    // Enviar formulari
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const idAnalisi = formData.get('id_analisi_foliar');
        const url = idAnalisi ? 
            `${BASE_URL}api/api_analisis_foliar.php?id=${idAnalisi}` : 
            `${BASE_URL}api/api_analisis_foliar.php`;
        
        const method = idAnalisi ? 'PUT' : 'POST';
        
        fetch(url, {
            method: method,
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modal.hidden = true;
                location.reload(); // Recarregar per veure els canvis
            } else {
                alert('Error: ' + (data.error || 'No s\'ha pogut guardar l\'etapa'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de connexió. Torna-ho a intentar.');
        });
    });

    // Funció auxiliar per formatar data
    function formatData(dataStr) {
        const data = new Date(dataStr);
        return data.toLocaleDateString('ca-ES');
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

