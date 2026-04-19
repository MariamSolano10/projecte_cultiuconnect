<?php
/**
 * modules/calendari/calendari.php — Calendari interactiu de tasques i tractaments.
 */

require_once __DIR__ . '/../../includes/helpers.php';
cc_session_start();
cc_enforce_auth_i_rols();

$titol_pagina = 'Calendari de Tasques i Tractaments';
$pagina_activa = 'calendari';

$css_addicional = [
    'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="contingut-calendari">

    <div class="capcalera-seccio">
        <h1 class="titol-pagina">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            Calendari de Tasques i Tractaments
        </h1>
        <p class="descripcio-seccio">
            Visualitza i planifica tractaments fitosanitaris, tasques de camp i més.
        </p>
<div class="form-ajuda--mb">
            <a href="<?= BASE_URL ?>modules/tractaments/nou_tractament_programat.php" class="boto-principal">
                <i class="fas fa-plus"></i> Programar Tractament
            </a>
        </div>
    </div>

    <div class="calendari-contenidor">
        <div id="calendar"></div>
    </div>

    <div id="popup-esdeveniment" class="popup-overlay" role="dialog" aria-modal="true" aria-labelledby="popup-titol" hidden>
        <div class="popup-caixa">
            <button class="popup-tancar" id="popup-tancar" aria-label="Tancar detall">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
            <h2 id="popup-titol" class="popup-titol"></h2>
            <dl class="popup-dades" id="popup-dades"></dl>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
    // 1. DEFINICIÓ DE VARIABLES GLOBALS PER A JS
    const BASE_URL = '<?= BASE_URL ?>';

    document.addEventListener('DOMContentLoaded', function () {
        const calendarEl = document.getElementById('calendar');
        if (!calendarEl) return;

        // --- Lògica del Popup ---
        const popup = document.getElementById('popup-esdeveniment');
        const popupTitol = document.getElementById('popup-titol');
        const popupDades = document.getElementById('popup-dades');
        const popupTancar = document.getElementById('popup-tancar');

        function obrirPopup(titol, props) {
            popupTitol.textContent = titol;
            popupDades.innerHTML = '';

            const camps = {
                'Tipus': props.tipus || null,
                'Sector': props.sector || null,
                'Descripció': props.description || null,
                'Responsable': props.responsable || null,
                'Estat': props.estat || null,
            };

            Object.entries(camps).forEach(([etiqueta, valor]) => {
                if (!valor) return;
                const dt = document.createElement('dt');
                dt.textContent = etiqueta;
                const dd = document.createElement('dd');
                dd.textContent = valor;
                popupDades.appendChild(dt);
                popupDades.appendChild(dd);
            });

            popup.hidden = false;
        }

        function tancarPopup() { popup.hidden = true; }
        popupTancar.addEventListener('click', tancarPopup);

        // --- Configuració FullCalendar ---
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'ca',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek',
            },
            buttonText: {
                today: 'Avui',
                month: 'Mes',
                week: 'Setmana',
                list: 'Llista',
            },
            // Fem servir la constant BASE_URL que hem definit a la línia 60
            events: BASE_URL + 'api/api_esdeveniments.php',

            eventContent: function (arg) {
                let container = document.createElement('div');
                container.style.display = 'flex';
                container.style.alignItems = 'center';
                container.style.gap = '4px';
                container.style.fontSize = '0.85em';

                let icona = 'fa-calendar-check';
                const tipus = arg.event.extendedProps.tipus;
                
                // Icones segons tipus d'esdeveniment
                if (tipus === 'tractament') icona = 'fa-spray-can';
                else if (tipus === 'tasca') icona = 'fa-tasks';
                else if (tipus === 'previsio_collita') icona = 'fa-basket-shopping';
                else if (tipus === 'collita') icona = 'fa-apple-alt';
                else if (tipus === 'permis') icona = 'fa-user-clock';
                else if (tipus === 'fenologic') icona = 'fa-seedling';
                else if (tipus === 'monitoratge') icona = 'fa-bug';
                else if (tipus === 'analisis_sol') icona = 'fa-vial';
                else if (tipus === 'preventiu') icona = 'fa-shield-halved';
                else if (tipus === 'correctiu') icona = 'fa-virus-slash';
                else if (tipus === 'fertilitzacio') icona = 'fa-flask';

                container.innerHTML = `<i class="fas ${icona}"></i> <span>${arg.event.title}</span>`;
                return { domNodes: [container] };
            },

            eventClick: function (info) {
                obrirPopup(info.event.title, info.event.extendedProps);
            }
        });

        calendar.render();
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
