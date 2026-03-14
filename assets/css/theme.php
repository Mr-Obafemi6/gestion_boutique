<?php
// ── theme.php — CSS dynamique selon le thème choisi ──────────
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: text/css');
header('Cache-Control: no-cache');

if (session_status() === PHP_SESSION_NONE) session_start();

// Charger depuis BDD si pas en session
if (!isset($_SESSION['theme_couleur'])) {
    try {
        $db = getDB();
        $s = $db->prepare("SELECT cle,valeur FROM parametres WHERE cle IN ('theme_couleur','theme_mode')");
        $s->execute();
        foreach ($s->fetchAll() as $r) $_SESSION[$r['cle']] = $r['valeur'];
    } catch(Exception $e) {}
}

$couleurs = [
    'bleu'   => '#1F5C99',
    'vert'   => '#16a34a',
    'violet' => '#7c3aed',
    'orange' => '#ea580c',
    'rouge'  => '#dc2626',
];
$c    = $couleurs[$_SESSION['theme_couleur'] ?? 'bleu'] ?? '#1F5C99';
$mode = $_SESSION['theme_mode'] ?? 'clair';
?>
:root {
    --bleu: <?= $c ?>;
    --bleu-fonce: <?= $c ?>;
    --theme-color: <?= $c ?>;
}

/* ── Sidebar ── */
.sidebar, .sidebar-footer { background: <?= $c ?> !important; }
.sidebar-footer { filter: brightness(0.88); }

/* ── Boutons & badges ── */
.btn-primary { background: <?= $c ?> !important; border-color: <?= $c ?> !important; }
.btn-primary:hover { filter: brightness(0.9); }
.badge.bg-primary, .badge-primary { background: <?= $c ?> !important; }
.text-primary { color: <?= $c ?> !important; }
.nav-link.active { background: rgba(255,255,255,.15) !important; }

/* ── Tableaux en-têtes ── */
thead { background: <?= $c ?> !important; color: #fff !important; }
thead th { color: #fff !important; }

/* ── Éléments divers ── */
.border-primary { border-color: <?= $c ?> !important; }
.bg-primary { background: <?= $c ?> !important; }
a.nav-link:hover { color: <?= $c ?> !important; }

<?php if ($mode === 'sombre'): ?>
/* ════════ MODE SOMBRE ════════ */
body, .main-content { background: #0f172a !important; color: #e2e8f0 !important; }
.topbar { background: #1a2235 !important; border-color: #2d3748 !important; }
.page-title, h1,h2,h3,h4,h5,h6 { color: #f1f5f9 !important; }
label, .form-label { color: #cbd5e1 !important; }
.text-muted { color: #94a3b8 !important; }
p, small, .small { color: #cbd5e1 !important; }
.card, .param-panel, .msg-panel { background: #1e293b !important; border-color: #334155 !important; color: #e2e8f0 !important; }
.table { background: #1e293b !important; color: #e2e8f0 !important; }
.table th { background: #0f172a !important; color: #94a3b8 !important; border-color: #334155 !important; }
.table td { border-color: #2d3748 !important; color: #e2e8f0 !important; }
.table-hover tbody tr:hover { background: #253347 !important; }
.table-striped tbody tr:nth-of-type(odd) { background: #1a2a3a !important; }
.form-control, .form-select, .input-group-text { background: #162032 !important; border-color: #334155 !important; color: #e2e8f0 !important; }
.form-control::placeholder { color: #64748b !important; }
.modal-content { background: #1e293b !important; border-color: #334155 !important; }
.modal-header, .modal-footer { border-color: #334155 !important; }
.dropdown-menu { background: #1e293b !important; border-color: #334155 !important; }
.dropdown-item { color: #e2e8f0 !important; }
.dropdown-item:hover { background: #334155 !important; }
hr { border-color: #334155 !important; }
<?php endif; ?>