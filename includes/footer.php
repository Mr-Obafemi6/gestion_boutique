<?php
// ============================================================
//  includes/footer.php — Pied de page commun
// ============================================================
?>
</div><!-- /.main-content -->

<!-- Barre de statut bas de page -->
<div style="
    margin-left: var(--sidebar-w, 250px);
    background: #fff;
    border-top: 1px solid #e8edf3;
    padding: .5rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: .73rem;
    color: #94a3b8;
" class="d-none d-md-flex">
    <span>
        <i class="bi bi-circle-fill me-1" style="color:#16a34a; font-size:.5rem;"></i>
        Système opérationnel &nbsp;·&nbsp; <?= APP_NAME ?> v<?= APP_VERSION ?>
    </span>
    <span>
        <i class="bi bi-clock me-1"></i><?= date('d/m/Y H:i') ?>
        &nbsp;·&nbsp;
        <i class="bi bi-person me-1"></i><?= clean(($_SESSION['user_prenom'] ?? '').' '.($_SESSION['user_nom'] ?? '')) ?>
    </span>
</div>

<script src="<?= APP_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script>
    // ── Toggle sidebar mobile ────────────────────────────────
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('toggleSidebar');
    const collapseBtn = document.getElementById('collapseSidebar');
    const closeBtn = document.getElementById('closeSidebar');

    function ouvrirSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function fermerSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (toggleBtn) toggleBtn.addEventListener('click', () => {
        sidebar.classList.contains('open') ? fermerSidebar() : ouvrirSidebar();
    });

    if (collapseBtn) {
        collapseBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
        // Restaurer l'état collapsed depuis le localStorage
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }
    
    if (closeBtn) closeBtn.addEventListener('click', fermerSidebar);

    if (overlay) overlay.addEventListener('click', fermerSidebar);

    if (sidebar) {
        sidebar.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 768) fermerSidebar();
            });
        });
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) fermerSidebar();
    });

    // ── Auto-dismiss alertes flash après 4s ──────────────────
    document.querySelectorAll('.alert.alert-success, .alert.alert-info').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });

    // ── Tooltip Bootstrap ─────────────────────────────────────
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });

    // ── Page Loader ──────────────────────────────────────────
    (function() {
        const loader  = document.getElementById('page-loader');
        const barFill = document.getElementById('loaderBarFill');
        const label   = document.getElementById('loaderLabel');
        if (!loader) return;

        function hideLoader() {
            if (barFill) { barFill.style.transition = 'width .15s'; barFill.style.width = '100%'; }
            if (label)   label.textContent = 'Prêt !';
            setTimeout(() => {
                loader.style.transition = 'opacity .25s';
                loader.style.opacity    = '0';
                setTimeout(() => loader.style.display = 'none', 260);
            }, 120);
        }

        // Disparaît dès que le DOM est prêt (rapide) + min 400ms visible
        const t0 = Date.now();
        const MIN_DISPLAY = 400; // ms minimum d'affichage

        function tryHide() {
            const elapsed = Date.now() - t0;
            const wait    = Math.max(0, MIN_DISPLAY - elapsed);
            setTimeout(hideLoader, wait);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', tryHide);
        } else {
            tryHide();
        }
        // Sécurité absolue : 2s max
        setTimeout(hideLoader, 2000);

        // ── Barre de navigation sur clic de lien ─────────────
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a[href]');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript')
                || link.hasAttribute('download') || link.target === '_blank'
                || link.hasAttribute('data-bs-toggle')) return;
            if (href.startsWith('http') && !href.includes(location.hostname)) return;
            // Barre fine en haut
            const bar = document.createElement('div');
            bar.style.cssText = 'position:fixed;top:0;left:0;height:3px;width:0;z-index:99999;background:linear-gradient(90deg,var(--bleu),var(--bleu-fonce));transition:width 1.2s ease;';
            document.body.appendChild(bar);
            requestAnimationFrame(() => { bar.style.width = '90%'; });
        });
    })();

</script>
</body>
</html>
