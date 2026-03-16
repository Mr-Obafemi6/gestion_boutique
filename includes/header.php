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
            /* ── Sidebar MÊME couleur que le thème (pas de conflit) ── */
            .sidebar { background:var(--bleu-fonce) !important; }
            .sidebar-footer { background:var(--bleu-fonce) !important; filter:brightness(0.88); }
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
                padding: .8rem 1.2rem .3rem;
            }

            .sidebar-menu .nav-link {
                color: rgba(255,255,255,.75);
                padding: .65rem 1.2rem;
                display: flex;
                align-items: center;
                gap: .7rem;
                border-radius: 0;
                transition: all .2s;
                font-size: .92rem;
            }

            .sidebar-menu .nav-link:hover,
            .sidebar-menu .nav-link.active {
                background: rgba(255,255,255,.12);
                color: #fff;
                border-left: 3px solid #fff;
            }

            .sidebar-menu .nav-link i { font-size: 1.1rem; width: 20px; text-align: center; }

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
                gap: .75rem;
                padding: .35rem .6rem;
                border-radius: 999px;
                background: rgba(31,92,153,0.08);
                transition: background .2s;
            }
            .topbar .user-profile:hover { background: rgba(31,92,153,0.12); }

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

            /* ── Contenu principal ── */
            .main-content {
                margin-left: var(--sidebar-w);
                padding: 1.5rem;
                min-height: calc(100vh - 60px);
            }

            /* Badge rôle */
            .badge-role {
                font-size: .7rem;
                padding: .25rem .6rem;
                border-radius: 20px;
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
                    transform: translateX(-100%);
                    transition: transform .25s ease;
                    z-index: 1000;
                    position: fixed;
                }
                .sidebar.open { transform: translateX(0); }
                .topbar, .main-content { margin-left: 0 !important; }

                /* Déconnexion toujours visible sur mobile */
                .sidebar-footer {
                    position: sticky;
                    bottom: 0;
                    background: #153F6E;
                    padding-bottom: env(safe-area-inset-bottom, 12px);
                }
            }
        </style>
    </head>
    <body>

    <!-- ── Overlay mobile ───────────────────────────────────────── -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ── Sidebar ────────────────────────────────────────────── -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="bi bi-shop-window brand-icon"></i>
            <span class="brand-name"><?= APP_NAME ?></span>
            <span class="brand-sub">Gestion · Stock · Caisse</span>
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

        <!-- Paramètres -->
        <div style="padding:.5rem .5rem 0; border-top:1px solid rgba(255, 255, 255, 0); margin-top:.5rem;">
            <a href="<?= APP_URL ?>/modules/parametres/index.php"
            class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'parametres') !== false ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Paramètres
            </a>
        </div>

        <!-- Déconnexion — toujours visible en bas -->
        <div class="sidebar-footer">
            <div style="font-size:.72rem; color:rgba(255,255,255,.4); margin-bottom:.4rem; padding-left:.75rem;">
                <i class="bi bi-person-circle" style="margin-right:.3rem;"></i>
                Connecté : <strong style="color:rgba(255,255,255,.6);"><?= $_SESSION['user_nom'] ?? '' ?></strong>
            </div>
            <a href="<?= APP_URL ?>/modules/auth/logout.php" class="nav-link">
                <i class="bi bi-box-arrow-left" style="font-size:1.1rem;"></i>
                Déconnexion
            </a>
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
                    'administrateur' => 'bi-shield-fill',
                    'caissier'       => 'bi-cash-register',
                    'magasinier'     => 'bi-boxes',
                    default          => 'bi-person'
                };
                $displayName = clean(($_SESSION['user_prenom'] ?? '').' '.($_SESSION['user_nom'] ?? ''));
            ?>

            <div class="user-profile dropend">
                <button class="btn btn-sm" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="background:rgba(255,255,255,0);border:none;padding:0;">
                    <div class="d-flex align-items-center gap-2">
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
                            <span class="user-role"><i class="bi <?= $roleIcon ?>"></i><?= ucfirst($role) ?></span>
                        </div>
                    </div>
                </button>
                <ul class="dropdown-menu" aria-labelledby="userDropdown" style="min-width:220px;">
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/parametres/index.php?s=profil"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/parametres/index.php"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/modules/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
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