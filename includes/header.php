        <?php
        // ============================================================
        //  includes/header.php — Barre de navigation commune
        //  À inclure en haut de chaque page protégée
        // ============================================================
        requireLogin();

        // ── Token CSRF global (généré une fois par session) ───────
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        require_once __DIR__ . '/Message.php';
        $_msgNonLus = 0;
        if (isset($_SESSION['user_id'])) {
            $_msgModel  = new Message();
            $_msgNonLus = $_msgModel->getNonLus($_SESSION['user_id']);
        }

        // ── Charger thème depuis BDD si pas en session ───────────────
        if (!isset($_SESSION['theme_couleur']) || !isset($_SESSION['theme_mode'])) {
            $_dbTheme = getDB();
            $__s = $_dbTheme->prepare("SELECT cle,valeur FROM parametres WHERE cle IN ('theme_couleur','theme_mode')");
            $__s->execute();
            foreach ($__s->fetchAll() as $__r) $_SESSION[$__r['cle']] = $__r['valeur'];
        }
        $_themeCouleurs = ['bleu'=>'#1F5C99','vert'=>'#16a34a','violet'=>'#7c3aed','orange'=>'#ea580c','rouge'=>'#dc2626'];
        $_couleurActive = $_themeCouleurs[$_SESSION['theme_couleur'] ?? 'bleu'] ?? '#1F5C99';
        $_modeSombre    = ($_SESSION['theme_mode'] ?? 'clair') === 'sombre';

        // ── Charger avatar depuis BDD si pas en session ──────────────
        if (!isset($_SESSION['user_avatar'])) {
            $_dbAv = getDB();
            $__av = $_dbAv->prepare("SELECT avatar FROM utilisateurs WHERE id=:id");
            $__av->execute([':id' => $_SESSION['user_id']]);
            $_SESSION['user_avatar'] = $_dbAv->prepare("SELECT avatar FROM utilisateurs WHERE id=:id") ? $__av->fetchColumn() : null;
            $__avRow = getDB()->prepare("SELECT avatar FROM utilisateurs WHERE id=:id");
            $__avRow->execute([':id' => $_SESSION['user_id']]);
            $_SESSION['user_avatar'] = $__avRow->fetchColumn() ?: null;
        }

        $flash = getFlash();
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= $pageTitle ?? APP_NAME ?></title>

            <link href="<?= APP_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
            <link href="<?= APP_URL ?>/assets/css/bootstrap-icons.min.css" rel="stylesheet">
            <link href="<?= APP_URL ?>/assets/css/design.css" rel="stylesheet">

            <style>
                :root {
                    --bleu:       <?= $_couleurActive ?>cc;
                    --bleu-fonce: <?= $_couleurActive ?>;
                    --sidebar-w: 250px;
                }
                <?php if ($_modeSombre): ?>
                /* ── Fond général ── */
                body, .main-content { background:#0f172a !important; color:#e2e8f0 !important; }
                /* ── Sidebar sombre ── */
                .sidebar { background:var(--bleu-fonce) !important; }
                .sidebar-footer { 
                    border-top-color: #334155 !important;
                    background: linear-gradient(135deg, rgba(30,41,59,.8) 0%, rgba(15,23,42,.6) 100%) !important;
                }
                
                .sidebar-footer .user-info {
                    background: rgba(51,65,85,.6) !important;
                }
                
                .sidebar-footer .user-info:hover {
                    background: rgba(51,65,85,.8) !important;
                }
                
                .sidebar-footer .user-avatar {
                    background: rgba(100,116,139,.3) !important;
                }
                
                .sidebar-footer .user-avatar::after {
                    border: 2px solid #1e293b !important;
                    box-shadow: 0 0 0 1px rgba(255,255,255,.1) !important;
                }
                
                .sidebar-footer .footer-btn {
                    background: rgba(51,65,85,.5) !important;
                    color: #e2e8f0 !important;
                }
                
                .sidebar-footer .footer-btn:hover {
                    background: rgba(51,65,85,.7) !important;
                    color: #f1f5f9 !important;
                }
                
                .sidebar-footer .footer-btn.logout:hover {
                    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%) !important;
                }
                /* ── Topbar sombre ── */
                .topbar { background:#1a2235 !important; border-color:#2d3748 !important; color:#e2e8f0 !important; }
                .page-title { color:#e2e8f0 !important; }
                /* ── Cards et panneaux ── */
                .card, .param-panel, .param-sidebar, .msg-panel, .msg-sidebar { background:#1e293b !important; border-color:#334155 !important; color:#e2e8f0 !important; }
                /* ── Tableaux lisibles ── */
                .table { background:#1e293b !important; color:#e2e8f0 !important; }
                .table th { background:#162032 !important; color:#94a3b8 !important; border-color:#334155 !important; }
                .table td { border-color:#2d3748 !important; color:#e2e8f0 !important; }
                .table-hover tbody tr:hover { background:#253347 !important; }
                .table-striped tbody tr:nth-of-type(odd) { background:#1a2a3a !important; }
                /* ── Formulaires ── */
                .form-control, .form-select, .input-group-text { background:#162032 !important; border-color:#334155 !important; color:#e2e8f0 !important; }
                .form-control::placeholder { color:#64748b !important; }
                /* ── Textes ── */
                h1,h2,h3,h4,h5,h6 { color:#f1f5f9 !important; }
                label, .form-label { color:#cbd5e1 !important; }
                .text-muted { color:#94a3b8 !important; }
                p, small, .small { color:#cbd5e1 !important; }
                /* ── Badges et alertes ── */
                .alert { border-color:#334155 !important; }
                /* ── Modals ── */
                .modal-content { background:#1e293b !important; border-color:#334155 !important; }
                .modal-header { border-color:#334155 !important; }
                .modal-footer { border-color:#334155 !important; }
                /* ── Dropdown ── */
                .dropdown-menu { background:#1e293b !important; border-color:#334155 !important; }
                .dropdown-item { color:#e2e8f0 !important; }
                .dropdown-item:hover { background:#334155 !important; }
                .user-profile .dropdown-menu { 
                    background: linear-gradient(135deg, #1e293b 0%, #162032 100%) !important; 
                    border: 1px solid #334155 !important; 
                }
                .user-profile .dropdown-item { color: #e2e8f0 !important; }
                .user-profile .dropdown-item:hover { 
                    background: linear-gradient(135deg, rgba(31,92,153,0.15) 0%, rgba(31,92,153,0.08) 100%) !important; 
                    color: #94a3b8 !important; 
                }
                .user-profile .dropdown-item.text-danger { 
                    color: #fca5a5 !important;
                    background: linear-gradient(135deg, rgba(220,38,38,0.1) 0%, rgba(220,38,38,0.05) 100%) !important;
                }
                .user-profile .dropdown-item.text-danger:hover { 
                    background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%) !important; 
                    color: #fca5a5 !important; 
                }
                .user-profile .dropdown-header {
                    color: #94a3b8 !important;
                    border-bottom-color: #334155 !important;
                    background: linear-gradient(135deg, rgba(31,92,153,0.08) 0%, rgba(31,92,153,0.04) 100%) !important;
                }
                .user-profile .dropdown-divider {
                    background: linear-gradient(90deg, transparent 0%, #334155 20%, #334155 80%, transparent 100%) !important;
                }
                /* ── Divers ── */
                hr { border-color:#334155 !important; }
                .border, [class*="border-"] { border-color:#334155 !important; }
                <?php endif; ?>

                body { background: <?= $_modeSombre ? '#0f172a' : '#f0f4f8' ?>; font-family: 'Segoe UI', sans-serif; }

                /* ── Sidebar ── */
                .sidebar {
                    width: var(--sidebar-w);
                    height: 100vh;        /* Hauteur fixe = tout l'écran */
                    background: var(--bleu-fonce);
                    position: fixed;
                    top: 0; left: 0;
                    z-index: 1000;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;     /* Empêche le scroll global de la sidebar */
                    transition: transform .3s;
                }

                .sidebar-brand {
                    padding: 1.5rem 1rem;
                    border-bottom: 1px solid rgba(255,255,255,.1);
                    text-align: center;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: .5rem;
                }

                .sidebar-brand .brand-info {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                }

                .sidebar-brand .brand-icon {
                    font-size: 2rem;
                    color: #fff;
                }

                .sidebar-brand .brand-name {
                    display: block;
                    color: #fff;
                    font-weight: 700;
                    font-size: 1.1rem;
                    margin-top: .3rem;
                }
                
                .sidebar-brand .brand-sub {
                    display: block;
                    color: rgba(255,255,255,.6);
                    font-size: .7rem;
                    margin-top: .2rem;
                    letter-spacing: .05em;
                }
                
                .sidebar-collapse-btn,
                .sidebar-close-btn {
                    width: 32px;
                    height: 32px;
                    border: none;
                    border-radius: 8px;
                    background: rgba(255,255,255,.12);
                    color: #fff;
                    cursor: pointer;
                    transition: all .3s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    font-size: 1rem;
                }
                .sidebar-collapse-btn:hover,
                .sidebar-close-btn:hover {
                    background: rgba(255,255,255,.2);
                    transform: scale(1.05);
                }

                .sidebar-menu {
                    flex: 1;
                    padding: 1rem 0;
                    overflow-y: auto;
                    min-height: 0;
                    /* Barre de défilement fine et visible */
                    scrollbar-width: thin;
                    scrollbar-color: rgba(255,255,255,.25) transparent;
                }
                .sidebar-menu::-webkit-scrollbar {
                    width: 4px;
                }
                .sidebar-menu::-webkit-scrollbar-track {
                    background: transparent;
                }
                .sidebar-menu::-webkit-scrollbar-thumb {
                    background: rgba(255,255,255,.3);
                    border-radius: 4px;
                }
                .sidebar-menu::-webkit-scrollbar-thumb:hover {
                    background: rgba(255,255,255,.5);
                }
                .sidebar-footer {
                    padding: .75rem 1rem;
                    border-top: 1px solid rgba(255,255,255,.1);
                    flex-shrink: 0;
                    background: linear-gradient(135deg, var(--bleu-fonce), rgba(255,255,255,0.05));
                    filter: brightness(0.88);
                    border-radius: 12px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    margin: 0 0.5rem;
                }
                .sidebar-footer .nav-link {
                    background: linear-gradient(135deg, #ff6b6b, #ff4444);
                    color: #fff;
                    display: flex;
                    align-items: center;
                    gap: .6rem;
                    padding: .6rem .75rem;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: .88rem;
                    transition: all 0.3s ease;
                    text-decoration: none;
                    box-shadow: 0 2px 8px rgba(255,68,68,0.3);
                }
                .sidebar-footer .nav-link:hover {
                    background: linear-gradient(135deg, #ff4444, #ff2222);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(255,68,68,0.5);
                }

                .sidebar-menu .nav-section {
                    font-size: .7rem;
                    color: rgba(255,255,255,.4);
                    text-transform: uppercase;
                    letter-spacing: .08em;
                    padding: 1rem 1.2rem .5rem;
                    font-weight: 600;
                    margin-top: .5rem;
                }

                .sidebar-menu .nav-link {
                    color: rgba(255,255,255,.75);
                    padding: .75rem 1.2rem;
                    display: flex;
                    align-items: center;
                    gap: .7rem;
                    border-radius: 8px;
                    transition: all .2s;
                    font-size: .92rem;
                    margin: 0 .5rem .3rem .5rem;
                    border-left: 3px solid transparent;
                }

                .sidebar-menu .nav-link:hover,
                .sidebar-menu .nav-link.active {
                    background: rgba(255,255,255,.15);
                    color: #fff;
                    border-left: 3px solid #fff;
                }

                .sidebar-menu .nav-link i { font-size: 1.1rem; width: 20px; text-align: center; }
                
                /* Sidebar collapsed mode */
                .sidebar.collapsed {
                    width: 80px;
                }
                .sidebar.collapsed .brand-name,
                .sidebar.collapsed .brand-sub,
                .sidebar.collapsed .nav-section,
                .sidebar.collapsed .sidebar-menu span,
                .sidebar.collapsed .sidebar-footer .user-details,
                .sidebar.collapsed .sidebar-footer .footer-actions {
                    display: none;
                }
                .sidebar.collapsed .sidebar-menu .nav-link,
                .sidebar.collapsed .sidebar-footer .user-info {
                    padding: .75rem 0 !important;
                    justify-content: center;
                    margin: 0.3rem 0.5rem;
                }
                .sidebar.collapsed .sidebar-menu .nav-link i {
                    margin: 0;
                    font-size: 1.3rem;
                }
                .sidebar.collapsed .brand-info {
                    display: none;
                }

                /* ── Topbar ── */
                .topbar {
                    margin-left: var(--sidebar-w);
                    background: #fff;
                    padding: .8rem 1.5rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    box-shadow: 0 2px 8px rgba(0,0,0,.07);
                    position: sticky;
                    top: 0;
                    z-index: 900;
                    transition: margin-left .3s ease;
                }
                
                .sidebar.collapsed ~ .topbar {
                    margin-left: 80px;
                }
                
                /* ── Main content ── */
                .main-content {
                    margin-left: var(--sidebar-w);
                    padding: 1.5rem;
                    min-height: calc(100vh - 60px);
                    transition: margin-left .3s ease;
                }
                
                .sidebar.collapsed ~ * .main-content,
                .sidebar.collapsed ~ .main-content {
                    margin-left: 80px;
                }

                .topbar .page-title {
                    font-size: 1.2rem;
                    font-weight: 700;
                    color: var(--bleu);
                    margin: 0;
                }

                .topbar .user-info {
                    display: flex;
                    align-items: center;
                    gap: .8rem;
                }

                .topbar .user-profile {
                    display: flex;
                    align-items: center;
                    gap: .5rem;
                    padding: .4rem .8rem;
                    border-radius: 16px;
                    background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.9) 100%);
                    border: 1px solid rgba(31,92,153,0.1);
                    transition: all .3s cubic-bezier(0.4, 0, 0.2, 1);
                    cursor: pointer;
                    backdrop-filter: blur(10px);
                    box-shadow: 0 2px 20px rgba(31,92,153,0.08);
                }
                .topbar .user-profile:hover { 
                    background: linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(255,255,255,0.95) 100%);
                    box-shadow: 0 8px 32px rgba(31,92,153,0.15);
                    transform: translateY(-1px);
                    border-color: rgba(31,92,153,0.2);
                }
                
                /* ── Dropdown menu moderne ── */
                .user-profile .dropdown-menu {
                    border: none !important;
                    border-radius: 16px !important;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.15) !important;
                    padding: 1rem 0 !important;
                    margin-top: 1rem !important;
                    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
                    backdrop-filter: blur(20px) !important;
                    animation: dropdownFade .3s ease !important;
                    min-width: 240px !important;
                    overflow: hidden !important;
                }
                
                @keyframes dropdownFade {
                    from { opacity: 0; transform: translateY(-12px) scale(0.95); }
                    to { opacity: 1; transform: translateY(0) scale(1); }
                }
                
                .user-profile .dropdown-item {
                    padding: .8rem 1.2rem !important;
                    color: #475569 !important;
                    font-size: .95rem !important;
                    font-weight: 500 !important;
                    transition: all .25s cubic-bezier(0.4, 0, 0.2, 1) !important;
                    display: flex !important;
                    align-items: center !important;
                    gap: .6rem !important;
                    margin: 0 .5rem !important;
                    border-radius: 8px !important;
                    position: relative !important;
                }
                
                .user-profile .dropdown-item:hover {
                    background: linear-gradient(135deg, rgba(31,92,153,0.08) 0%, rgba(31,92,153,0.05) 100%) !important;
                    color: var(--bleu-fonce) !important;
                    transform: translateX(4px) !important;
                    box-shadow: 0 4px 12px rgba(31,92,153,0.1) !important;
                }
                
                .user-profile .dropdown-item i {
                    width: 20px !important;
                    text-align: center !important;
                    font-size: 1.1rem !important;
                    color: var(--bleu) !important;
                    transition: all .25s ease !important;
                }
                
                .user-profile .dropdown-item:hover i {
                    color: var(--bleu-fonce) !important;
                    transform: scale(1.1) !important;
                }
                
                .user-profile .dropdown-item.text-danger {
                    color: #dc2626 !important;
                    background: linear-gradient(135deg, rgba(220,38,38,0.05) 0%, rgba(220,38,38,0.03) 100%) !important;
                }
                
                .user-profile .dropdown-item.text-danger:hover {
                    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%) !important;
                    color: #b91c1c !important;
                    transform: translateX(4px) !important;
                    box-shadow: 0 4px 12px rgba(220,38,38,0.2) !important;
                }
                
                .user-profile .dropdown-item.text-danger i {
                    color: #dc2626 !important;
                }
                
                .user-profile .dropdown-item.text-danger:hover i {
                    color: #b91c1c !important;
                    transform: scale(1.1) !important;
                }
                
                .user-profile .dropdown-header {
                    padding: .5rem 1.2rem .3rem !important;
                    font-size: .8rem !important;
                    color: #64748b !important;
                    font-weight: 600 !important;
                    text-transform: uppercase !important;
                    letter-spacing: .5px !important;
                    border-bottom: 1px solid #e2e8f0 !important;
                    margin-bottom: .5rem !important;
                    background: linear-gradient(135deg, rgba(31,92,153,0.03) 0%, rgba(31,92,153,0.01) 100%) !important;
                }
                
                .user-profile .dropdown-header i {
                    color: var(--bleu) !important;
                    font-size: .75rem !important;
                }

                .topbar .user-profile .avatar {
                    width: 42px; height: 42px;
                    border-radius: 50%;
                    overflow: hidden;
                    border: 2px solid rgba(255,255,255,0.6);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
                    background: var(--bleu);
                }
                .topbar .user-profile .avatar img {
                    width: 100%; height: 100%; object-fit: cover;
                }

                .topbar .user-meta {
                    display: flex;
                    flex-direction: column;
                    line-height: 1.1;
                }
                .topbar .user-name {
                    font-weight: 700;
                    color: #1e293b;
                    font-size: .9rem;
                }
                .topbar .user-role {
                    font-size: .7rem;
                    padding: .15rem .5rem;
                    border-radius: 999px;
                    background: rgba(255,255,255,0.55);
                    color: #475569;
                    display: inline-flex;
                    align-items: center;
                    gap: .3rem;
                }
                .topbar .user-actions {
                    display: flex;
                    align-items: center;
                    gap: .65rem;
                }

                .topbar .avatar {
                    width: 36px; height: 36px;
                    background: var(--bleu);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                    font-weight: 700;
                    font-size: .85rem;
                }

                /* ── Sidebar Footer moderne ── */
                .sidebar-footer {
                    border-top: 1px solid rgba(255,255,255,.1);
                    background: linear-gradient(135deg, rgba(255,255,255,.05) 0%, rgba(255,255,255,.02) 100%);
                    padding: 1rem .75rem;
                    margin-top: auto;
                }
                
                .sidebar-footer .user-info {
                    display: flex;
                    align-items: center;
                    gap: .75rem;
                    padding: .5rem .75rem;
                    border-radius: 10px;
                    background: rgba(255,255,255,.08);
                    margin-bottom: .75rem;
                    transition: all .3s ease;
                }
                
                .sidebar-footer .user-info:hover {
                    background: rgba(255,255,255,.12);
                    transform: translateY(-1px);
                }
                
                .sidebar-footer .user-avatar {
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    background: rgba(255,255,255,.2);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                    font-weight: 700;
                    font-size: .7rem;
                    flex-shrink: 0;
                    position: relative;
                }
                
                .sidebar-footer .user-avatar::after {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    right: 0;
                    width: 10px;
                    height: 10px;
                    border-radius: 50%;
                    background: #10b981;
                    border: 2px solid var(--bleu-fonce);
                    box-shadow: 0 0 0 1px rgba(255,255,255,.2);
                    animation: pulse-green 2s infinite;
                }
                
                @keyframes pulse-green {
                    0%, 100% { 
                        opacity: 1; 
                        transform: scale(1);
                    }
                    50% { 
                        opacity: .7; 
                        transform: scale(1.1);
                    }
                }
                
                .sidebar-footer .user-details {
                    flex: 1;
                    min-width: 0;
                }
                
                .sidebar-footer .user-name {
                    color: #fff;
                    font-size: .8rem;
                    font-weight: 600;
                    margin-bottom: .1rem;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                
                .sidebar-footer .user-role {
                    color: rgba(255,255,255,.6);
                    font-size: .65rem;
                    display: flex;
                    align-items: center;
                    gap: .3rem;
                }
                
                .sidebar-footer .user-role i {
                    font-size: .6rem;
                }
                
                .sidebar-footer .footer-actions {
                    display: flex;
                    gap: .5rem;
                }
                
                .sidebar-footer .footer-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 36px;
                    height: 36px;
                    border-radius: 8px;
                    background: rgba(255,255,255,.1);
                    color: rgba(255,255,255,.8);
                    text-decoration: none;
                    transition: all .3s ease;
                    border: none;
                    cursor: pointer;
                }
                
                .sidebar-footer .footer-btn:hover {
                    background: rgba(255,255,255,.2);
                    color: #fff;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,.2);
                }
                
                .sidebar-footer .footer-btn.logout:hover {
                    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                    color: #fff;
                }
                
                .sidebar-footer .footer-btn i {
                    font-size: 1rem;
                }

                /* Overlay sombre derrière la sidebar mobile */
                .sidebar-overlay {
                    display: none;
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,.5);
                    z-index: 999;
                }
                .sidebar-overlay.show { display: block; }

                /* Responsive mobile */
                @media (max-width: 768px) {
                    .sidebar {
                        width: 85% !important;
                        max-width: 320px;
                        transform: translateX(-100%);
                        transition: transform .25s ease;
                        z-index: 1000;
                        position: fixed;
                    }
                    .sidebar.open { transform: translateX(0); }
                    .sidebar.open.collapsed { width: 85% !important; }
                    .topbar, .main-content { margin-left: 0 !important; }
                    .sidebar-collapse-btn { display: none !important; }
                    .sidebar-close-btn { display: flex !important; }

                    /* Footer mobile adapté */
                    .sidebar-footer {
                        position: sticky;
                        bottom: 0;
                        background: linear-gradient(135deg, rgba(21,63,110,.95) 0%, rgba(21,63,110,.9) 100%);
                        border-top: 1px solid rgba(255,255,255,.15);
                        padding-bottom: env(safe-area-inset-bottom, 12px);
                    }
                    
                    .sidebar-footer .user-info {
                        background: rgba(255,255,255,.1);
                    }
                    
                    .sidebar-footer .user-info:hover {
                        background: rgba(255,255,255,.15);
                    }
                    
                    .sidebar-footer .footer-btn {
                        background: rgba(255,255,255,.15);
                    }
                    
                    .sidebar-footer .footer-btn:hover {
                        background: rgba(255,255,255,.25);
                    }
                }
                
                /* Desktop collapse visible */
                @media (min-width: 769px) {
                    .sidebar-collapse-btn { display: flex !important; }
                    .sidebar-close-btn { display: none !important; }
                }
            </style>
        </head>
        <body>

        <!-- ── Overlay mobile ───────────────────────────────────────── -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- ── Sidebar ────────────────────────────────────────────── -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-info">
                    <i class="bi bi-shop-window brand-icon"></i>
                    <span class="brand-name"><?= APP_NAME ?></span>
                    <span class="brand-sub">Gestion · Stock · Caisse</span>
                </div>
                <button type="button" class="sidebar-collapse-btn" id="collapseSidebar" aria-label="Réduire la navigation" title="Réduire">
                    ⟨
                </button>
                <button type="button" class="sidebar-close-btn" id="closeSidebar" aria-label="Fermer la navigation">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="sidebar-menu">
                <span class="nav-section">Principal</span>
                <a href="<?= APP_URL ?>/dashboard.php"
                class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i> Tableau de bord
                </a>

                <span class="nav-section">Inventaire</span>
                <?php if(peutFaire('produits','voir')): ?>
                <a href="<?= APP_URL ?>/modules/produits/liste.php"
                class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'produits') !== false ? 'active' : '' ?>">
                    <i class="bi bi-box-seam"></i> Produits
                </a>
                <?php endif; ?>
                <?php if(peutFaire('stock','voir')): ?>
                <a href="<?= APP_URL ?>/modules/stock/mouvements.php"
                class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'stock') !== false ? 'active' : '' ?>">
                    <i class="bi bi-arrow-left-right"></i> Mouvements stock
                </a>
                <?php endif; ?>

                <span class="nav-section">Ventes</span>
                <?php if(peutFaire('ventes','creer')): ?>
                <a href="<?= APP_URL ?>/modules/ventes/caisse.php"
                class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'caisse') !== false ? 'active' : '' ?>">
                    <i class="bi bi-cash-register"></i> Point de vente
                </a>
                <?php endif; ?>
                <?php if(peutFaire('ventes','voir')): ?>
                <a href="<?= APP_URL ?>/modules/ventes/historique.php"
                class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'historique') !== false ? 'active' : '' ?>">
                    <i class="bi bi-clock-history"></i> Historique ventes
                </a>
                <?php endif; ?>
                <?php if(peutFaire('fournisseurs','voir')): ?>
                <a href="<?= APP_URL ?>/modules/fournisseurs/index.php"
                class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'fournisseurs') !== false ? 'active' : '' ?>">
                    <i class="bi bi-truck"></i> Fournisseurs
                </a>
                <?php endif; ?>

                <?php if(peutFaire('messagerie','voir')): ?>
                <a href="<?= APP_URL ?>/modules/messagerie/index.php"
                class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'messagerie') !== false ? 'active' : '' ?>">
                    <i class="bi bi-chat-dots"></i> Messagerie
                    <?php if ($_msgNonLus > 0): ?>
                    <span class="badge rounded-pill ms-auto" style="background:#ef4444;font-size:.65rem;"><?= $_msgNonLus ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>

                <?php if(peutFaire('rapports','voir')): ?>
                <?php if ($_SESSION['user_role'] === 'administrateur'): ?>
                <span class="nav-section">Administration</span>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/modules/rapports/index.php"
                class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'rapports') !== false ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart-line"></i> Rapports
                </a>
                <?php endif; ?>

                <?php if(peutFaire('utilisateurs','voir')): ?>
                <a href="<?= APP_URL ?>/modules/auth/utilisateurs.php"
                class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'utilisateurs') !== false ? 'active' : '' ?>">
                    <i class="bi bi-people"></i> Utilisateurs
                </a>
                <?php endif; ?>
            </div>

            <!-- Footer moderne avec infos utilisateur et actions -->
            <div class="sidebar-footer">
                <div class="user-info">
                    <?php if (!empty($_SESSION['user_avatar'])): ?>
                    <div class="user-avatar">
                        <img src="<?= APP_URL ?>/uploads/avatars/<?= $_SESSION['user_avatar'] ?>?v=<?= time() ?>" 
                            alt="Avatar" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    </div>
                    <?php else: ?>
                    <div class="user-avatar">
                        <?= strtoupper(substr(($_SESSION['user_prenom'] ?? ''), 0, 1) . substr($_SESSION['user_nom'], 0, 1)) ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="user-details">
                        <div class="user-name">
                            <?= clean(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? '')) ?>
                        </div>
                        <div class="user-role">
                            <i class="bi <?= match($_SESSION['user_role']) {
                                'administrateur' => 'bi-shield-fill',
                                'caissier' => 'bi-cash-register',
                                'magasinier' => 'bi-boxes',
                                default => 'bi-person'
                            } ?>"></i>
                            <?= ucfirst($_SESSION['user_role'] ?? 'utilisateur') ?>
                        </div>
                    </div>
                </div>
                
                <div class="footer-actions">
                    <a href="<?= APP_URL ?>/modules/parametres/index.php" 
                    class="footer-btn" 
                    title="Paramètres"
                    data-bs-toggle="tooltip">
                        <i class="bi bi-gear-fill"></i>
                    </a>
                    
                    <a href="<?= APP_URL ?>/modules/auth/logout.php" 
                    class="footer-btn logout" 
                    title="Déconnexion"
                    data-bs-toggle="tooltip">
                        <i class="bi bi-power"></i>
                    </a>
                </div>
            </div>
        </nav>

        <!-- ── Topbar ─────────────────────────────────────────────── -->
        <div class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm d-md-none" id="toggleSidebar" style="color:var(--bleu);">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div>
                    <h1 class="page-title"><?= $pageTitle ?? 'Tableau de bord' ?></h1>
                    <div class="topbar-breadcrumb d-none d-md-block">
                        <i class="bi bi-house-fill me-1"></i><?= APP_NAME ?> &rsaquo; <?= $pageTitle ?? 'Accueil' ?>
                    </div>
                </div>
            </div>

            <div class="user-info">
                <?php
                    $role = $_SESSION['user_role'];
                    $badgeClass = match($role) {
                        'administrateur' => 'badge-role-admin',
                        'caissier'       => 'badge-role-caissier',
                        'magasinier'     => 'badge-role-magasinier',
                        default          => 'bg-secondary text-white'
                    };
                    $roleIcon = match($role) {
                        'administrateur' => 'bi-shield-fill admin-icon',
                        'caissier'       => 'bi-cash-register',
                        'magasinier'     => 'bi-boxes',
                        default          => 'bi-person'
                    };
                    $displayName = clean(($_SESSION['user_prenom'] ?? '').' '.($_SESSION['user_nom'] ?? ''));
                ?>

                <div class="user-profile dropend">
                    <button class="btn btn-sm" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" 
                            style="background:none; border:none; padding:0; display:flex; align-items:center; gap:.6rem; width:100%;">
                        <?php if (!empty($_SESSION['user_avatar'])): ?>
                        <div class="avatar">
                            <img src="<?= APP_URL ?>/uploads/avatars/<?= $_SESSION['user_avatar'] ?>?v=<?= time() ?>" alt="Photo">
                        </div>
                        <?php else: ?>
                        <div class="avatar">
                            <?= strtoupper(substr(($_SESSION['user_prenom'] ?? ''), 0, 1) . substr($_SESSION['user_nom'], 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <div class="user-meta d-none d-md-flex flex-column">
                            <span class="user-name"><?= $displayName ?></span>
                            <span class="user-role">
                                <i class="bi <?= $roleIcon ?>" style="font-size:.65rem;"></i>
                                <span style="font-size:.7rem;"><?= ucfirst($role) ?></span>
                            </span>
                        </div>
                        <div class="dropdown-indicator d-none d-md-block">
                            <i class="bi bi-chevron-down" style="font-size:.7rem; color:#94a3b8; transition:transform .3s ease;"></i>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header" style="padding:.5rem 1.2rem .3rem; font-size:.8rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">
                            <i class="bi bi-person-circle me-1"></i>Mon compte
                        </li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/parametres/index.php?s=profil">
                            <i class="bi bi-person-badge"></i>
                            <span>Mon profil</span>
                            <i class="bi bi-chevron-right ms-auto" style="font-size:.8rem; opacity:.5;"></i>
                        </a></li>
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/parametres/index.php">
                            <i class="bi bi-sliders"></i>
                            <span>Paramètres</span>
                            <i class="bi bi-chevron-right ms-auto" style="font-size:.8rem; opacity:.5;"></i>
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/modules/auth/logout.php">
                            <i class="bi bi-power"></i>
                            <span>Déconnexion</span>
                            <i class="bi bi-box-arrow-right ms-auto" style="font-size:.8rem;"></i>
                        </a></li>
                    </ul>
                </div>

                <div class="user-actions">
                    <!-- Cloche notification messagerie -->
                    <a href="<?= APP_URL ?>/modules/messagerie/index.php" class="position-relative" style="color:#64748b; text-decoration:none;">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if ($_msgNonLus > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;">
                            <?= $_msgNonLus ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- ── Contenu de la page (ouverture div) ─────────────────── -->
        <div class="main-content">

        <!-- ── Flash message ─────────────────────────────────────── -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible fade show mb-3">
                <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
                <?= clean($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <script>
        // Animation de la flèche du dropdown utilisateur
        document.addEventListener('DOMContentLoaded', function() {
            const userDropdown = document.getElementById('userDropdown');
            const chevron = userDropdown?.querySelector('.dropdown-indicator i');
            
            if (userDropdown && chevron) {
                userDropdown.addEventListener('show.bs.dropdown', function() {
                    chevron.style.transform = 'rotate(180deg)';
                });
                
                userDropdown.addEventListener('hide.bs.dropdown', function() {
                    chevron.style.transform = 'rotate(0deg)';
                });
            }
            
            // Initialiser les tooltips pour la sidebar footer
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        </script>