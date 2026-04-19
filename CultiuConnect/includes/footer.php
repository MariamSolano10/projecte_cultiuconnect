<?php
/**
 * includes/footer.php — Peu de pàgina i tancament HTML.
 *
 * IMPORTANT: Tanca el <main> i l'<div class="app-shell"> oberts per header.php.
 * Inclou el JS del sidebar toggle per a mòbil.
 */
?>

    </main><!-- /.contingut-principal -->

    <footer class="peu-app">
        <div class="contingut-footer">

            <div class="columna-footer info-app">
                <h4 class="footer-titol">CultiuConnect</h4>
                <p>Eina de gestió agronòmica per a una agricultura més eficient i sostenible.</p>
                <p>&copy; <?= date('Y') ?> Tots els drets reservats.</p>
            </div>

            <div class="columna-footer">
                <h4>Ajuda i Legal</h4>
                <ul>
                    <li>
                        <a href="<?= BASE_URL ?>docs/privacitat.php">
                            <i class="fas fa-shield-halved" aria-hidden="true"></i>
                            Política de Privacitat
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>docs/termes.php">
                            <i class="fas fa-file-lines" aria-hidden="true"></i>
                            Termes d'Ús
                        </a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>README.md" target="_blank" rel="noopener">
                            <i class="fas fa-circle-question" aria-hidden="true"></i>
                            Documentació
                        </a>
                    </li>
                </ul>
            </div>

            <div class="columna-footer">
                <h4>Contacte</h4>
                <p>
                    <i class="fas fa-envelope" aria-hidden="true"></i>
                    <a href="mailto:info@cultiuconnect.cat">info@cultiuconnect.cat</a>
                </p>
                <div class="social-links">
                    <a href="#" title="Instagram" aria-label="Instagram" rel="noopener noreferrer">
                        <i class="fab fa-instagram" aria-hidden="true"></i>
                    </a>
                    <a href="#" title="TikTok" aria-label="TikTok" rel="noopener noreferrer">
                        <i class="fab fa-tiktok" aria-hidden="true"></i>
                    </a>
                    <a href="#" title="Twitter / X" aria-label="Twitter (X)" rel="noopener noreferrer">
                        <i class="fab fa-x-twitter" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

        </div>
    </footer>

</div><!-- /.app-shell -->

<!-- scripts.js (formularis, cercadors, flash, data-confirma) -->
<script src="<?= BASE_URL ?>js/scripts.js"></script>

<!-- Sidebar toggle (mòbil ≤ 1024px) -->
<script>
(function () {
    'use strict';

    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    const btns     = document.querySelectorAll('#sidebar-toggle, #sidebar-toggle-top');
    const mainContent = document.getElementById('main-content');

    if (!sidebar) return;

    // Tots els elements focusables dins la sidebar
    function getFocusables() {
        return Array.from(sidebar.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
        ));
    }

    function obrirSidebar() {
        sidebar.classList.add('obert');
        if (overlay) overlay.classList.add('visible');
        document.body.style.overflow = 'hidden';

        // Actualitzar aria-expanded en tots els botons hamburguesa
        btns.forEach(b => { b.setAttribute('aria-expanded', 'true'); });

        // Moure el focus al primer element de la sidebar
        requestAnimationFrame(() => {
            const primer = getFocusables()[0];
            primer?.focus();
        });
    }

    function tancarSidebar(retornarFocus = true) {
        sidebar.classList.remove('obert');
        if (overlay) overlay.classList.remove('visible');
        document.body.style.overflow = '';

        btns.forEach(b => { b.setAttribute('aria-expanded', 'false'); });

        // Retornar focus al botó que va obrir la sidebar
        if (retornarFocus) {
            btns[0]?.focus();
        }
    }

    // Focus trap dins la sidebar quan és oberta en mòbil
    sidebar.addEventListener('keydown', function (e) {
        if (!sidebar.classList.contains('obert') || window.innerWidth > 1024) return;

        const focusables = getFocusables();
        const primer = focusables[0];
        const darrer = focusables[focusables.length - 1];

        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === primer) {
                    e.preventDefault();
                    darrer?.focus();
                }
            } else {
                if (document.activeElement === darrer) {
                    e.preventDefault();
                    primer?.focus();
                }
            }
        }
    });

    btns.forEach(btn => {
        btn?.addEventListener('click', () => {
            sidebar.classList.contains('obert') ? tancarSidebar() : obrirSidebar();
        });
    });

    overlay?.addEventListener('click', () => tancarSidebar());

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && sidebar.classList.contains('obert')) {
            tancarSidebar();
        }
    });

    // Tancar en navegar (UX mòbil) — retornar focus al contingut
    sidebar.querySelectorAll('.sidebar__link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 1024) tancarSidebar(false);
        });
    });
})();
</script>

</body>
</html>