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
</script>
</body>
</html>
