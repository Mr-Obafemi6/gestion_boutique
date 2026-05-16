<?php
// ============================================================
//  config/config.php — Configuration générale
// ============================================================

define('APP_NAME',    'GestionBoutique');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/gestion_boutique'); // ← adapte selon ton serveur

// Durée de session (en secondes) : 8 heures
define('SESSION_LIFETIME', 28800);

// Fuseau horaire
date_default_timezone_set('Africa/Lome');

// ── Configuration session AVANT session_start() ──────────────
// Ces 3 lignes sont OBLIGATOIRES sous XAMPP qui impose 24min par défaut
ini_set('session.gc_maxlifetime',    SESSION_LIFETIME); // Durée de vie côté serveur
ini_set('session.cookie_lifetime',   SESSION_LIFETIME); // Durée de vie du cookie
ini_set('session.gc_probability',    1);
ini_set('session.gc_divisor',        100);

// Démarrage sécurisé de la session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,   // Mettre true en production HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Chargement de la connexion BDD
require_once __DIR__ . '/database.php';

// ── Fonctions utilitaires globales ──────────────────────────

/**
 * Redirige vers une URL et stoppe l'exécution.
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Nettoie et sécurise une valeur entrée par l'utilisateur.
 */
function clean(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Formate un montant en FCFA.
 */
function formatMontant(float $montant): string {
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

/**
 * Alias court pour formatMontant().
 */
function fmt(float $montant): string {
    return formatMontant($montant);
}

/**
 * Vérifie si l'utilisateur est connecté, sinon redirige.
 */
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        redirect(APP_URL . '/modules/auth/login.php');
    }

    // Vérification expiration session
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        redirect(APP_URL . '/modules/auth/login.php?expire=1');
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique.
 * @param string|array $roles
 */
function requireRole($roles): void {
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        redirect(APP_URL . '/includes/acces_refuse.php');
    }
}

/**
 * Charge toutes les permissions du rôle courant en session (cache).
 */
function chargerPermissions(): void {
    if (isset($_SESSION['permissions']) || empty($_SESSION['user_role'])) return;
    // L'administrateur a toujours tout (court-circuit)
    if ($_SESSION['user_role'] === 'administrateur') {
        $_SESSION['permissions'] = 'admin';
        return;
    }
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT module, action, autorise FROM permissions WHERE role = :role");
        $stmt->execute([':role' => $_SESSION['user_role']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $perms = [];
        foreach ($rows as $r) {
            $perms[$r['module']][$r['action']] = (bool)$r['autorise'];
        }
        $_SESSION['permissions'] = $perms;
    } catch (Exception $e) {
        // Table pas encore créée → on accorde tout par sécurité
        $_SESSION['permissions'] = 'admin';
    }
}

/**
 * Vérifie si l'utilisateur courant peut effectuer une action sur un module.
 * Usage : peutFaire('produits', 'supprimer')
 */
function peutFaire(string $module, string $action): bool {
    if (empty($_SESSION['user_id'])) return false;
    chargerPermissions();
    // Admin = tout autorisé
    if ($_SESSION['permissions'] === 'admin') return true;
    return $_SESSION['permissions'][$module][$action] ?? false;
}

/**
 * Vérifie et redirige si pas autorisé (version stricte).
 */
function requirePermission(string $module, string $action): void {
    requireLogin();
    if (!peutFaire($module, $action)) {
        redirect(APP_URL . '/includes/acces_refuse.php');
    }
}

/**
 * Invalide le cache des permissions en session (à appeler après modif).
 */
function invaliderPermissions(): void {
    unset($_SESSION['permissions']);
}

/**
 * Vérifie le token CSRF d'une requête POST.
 * Redirige avec message d'erreur si invalide.
 */
function verifierCSRF(): void {
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        setFlash('error', 'Requête invalide (CSRF). Veuillez réessayer.');
        $referer = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/dashboard.php';
        redirect($referer);
    }
    // Régénérer après chaque POST validé
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Affiche et supprime le message flash.
 */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── Helpers visuels produits (caisse + liste) ──────────────
function getCategorieStyle(string $categorie): array {
    $cat = strtolower($categorie);
    $styles = [
        // Alimentation / épicerie
        'epicerie'    => ['#FFF7ED','#EA580C'],
        'alimentation'=> ['#FFF7ED','#EA580C'],
        'alimentaire' => ['#FFF7ED','#EA580C'],
        // Fruits & légumes
        'fruits'      => ['#F0FDF4','#16A34A'],
        'legumes'     => ['#F0FDF4','#16A34A'],
        'légumes'     => ['#F0FDF4','#16A34A'],
        'fruits et legumes' => ['#F0FDF4','#16A34A'],
        // Boissons
        'boissons'    => ['#EFF6FF','#2563EB'],
        'boisson'     => ['#EFF6FF','#2563EB'],
        // Hygiène / beauté
        'hygiene'     => ['#FDF4FF','#9333EA'],
        'hygiène'     => ['#FDF4FF','#9333EA'],
        'beaute'       => ['#FDF4FF','#9333EA'],
        'beauté'      => ['#FDF4FF','#9333EA'],
        // Electronique
        'electronique'=> ['#F0F9FF','#0284C7'],
        'électronique'=> ['#F0F9FF','#0284C7'],
        'telephones'  => ['#F0F9FF','#0284C7'],
        'téléphones'  => ['#F0F9FF','#0284C7'],
        // Vêtements / textile
        'vetements'   => ['#FFF1F2','#E11D48'],
        'vêtements'   => ['#FFF1F2','#E11D48'],
        'textile'     => ['#FFF1F2','#E11D48'],
        'habillement' => ['#FFF1F2','#E11D48'],
        // Céréales / farines
        'cereales'    => ['#FFFBEB','#D97706'],
        'céréales'    => ['#FFFBEB','#D97706'],
        'farines'     => ['#FFFBEB','#D97706'],
        // Viandes / poissons
        'viandes'     => ['#FEF2F2','#DC2626'],
        'poissons'    => ['#F0FDFA','#0D9488'],
        // Médicaments / santé
        'medicaments' => ['#F0FDF4','#15803D'],
        'médicaments' => ['#F0FDF4','#15803D'],
        'sante'       => ['#F0FDF4','#15803D'],
        'santé'       => ['#F0FDF4','#15803D'],
        // Quincaillerie / bricolage
        'quincaillerie'=> ['#F8FAFC','#475569'],
        'bricolage'   => ['#F8FAFC','#475569'],
    ];
    foreach ($styles as $mot => $style) {
        if (str_contains($cat, $mot)) return $style;
    }
    // Couleur par défaut basée sur l'ID de catégorie pour varier
    $defaults = [
        ['#EEF2FF','#4F46E5'],
        ['#FFF7ED','#EA580C'],
        ['#F0FDF4','#16A34A'],
        ['#FDF4FF','#9333EA'],
        ['#FFFBEB','#D97706'],
        ['#F0F9FF','#0284C7'],
    ];
    return $defaults[abs(crc32($categorie)) % count($defaults)];
}

// ── Fonction emoji automatique selon le nom du produit ───────
function getProduitEmoji(string $nom, int $cat = 0): string {
    $nom = strtolower($nom);

    $map = [
        // Fruits & légumes
        'tomate'      => '🍅', 'tomates'     => '🍅',
        'oignon'      => '🧅', 'oignons'     => '🧅',
        'ail'         => '🧄',
        'carotte'     => '🥕', 'carottes'    => '🥕',
        'pomme'       => '🍎', 'pommes'      => '🍎',
        'orange'      => '🍊', 'oranges'     => '🍊',
        'banane'      => '🍌', 'bananes'     => '🍌',
        'mangue'      => '🥭', 'mangues'     => '🥭',
        'ananas'      => '🍍',
        'citron'      => '🍋', 'citrons'     => '🍋',
        'avocat'      => '🥑', 'avocats'     => '🥑',
        'piment'      => '🌶️', 'piments'    => '🌶️',
        'poivron'     => '🫑',
        'gombo'       => '🥬',
        'igname'      => '🍠',
        'manioc'      => '🌿',
        'mais'        => '🌽', 'maïs'        => '🌽',
        'haricot'     => '🫘',
        'arachide'    => '🥜', 'arachides'   => '🥜',
        'noix'        => '🥜',

        // Céréales & féculents
        'riz'         => '🌾',
        'mil'         => '🌾', 'millet'      => '🌾',
        'sorgho'      => '🌾',
        'farine'      => '🌾',
        'pain'        => '🍞',
        'pate'        => '🍝', 'pâte'        => '🍝',
        'couscous'    => '🍚',

        // Boissons
        'eau'         => '💧',
        'lait'        => '🥛',
        'jus'         => '🧃',
        'coca'        => '🥤', 'cola'        => '🥤',
        'biere'       => '🍺', 'bière'       => '🍺',
        'vin'         => '🍷',
        'cafe'        => '☕', 'café'        => '☕',
        'the'         => '🍵', 'thé'         => '🍵',
        'boisson'     => '🥤', 'boissons'    => '🥤',
        'limonade'    => '🥤',
        'sirop'       => '🧴',

        // Huiles & condiments
        'huile'       => '🫙',
        'sel'         => '🧂',
        'sucre'       => '🍬',
        'miel'        => '🍯',
        'vinaigre'    => '🫙',
        'sauce'       => '🥫',
        'tomate concentree' => '🥫',
        'mayonnaise'  => '🫙',
        'moutarde'    => '🫙',

        // Viandes & poissons
        'poulet'      => '🍗',
        'viande'      => '🥩',
        'boeuf'       => '🥩',
        'mouton'      => '🐑',
        'porc'        => '🐷',
        'poisson'     => '🐟', 'poissons'    => '🐟',
        'thon'        => '🐟',
        'sardine'     => '🐟',
        'crevette'    => '🦐', 'crevettes'   => '🦐',
        'oeuf'        => '🥚', 'oeufs'       => '🥚',
        'fromage'     => '🧀',

        // Produits d'hygiène & ménage
        'savon'       => '🧼', 'savons'      => '🧼',
        'shampooing'  => '🧴', 'shampoing'   => '🧴',
        'dentifrice'  => '🪥',
        'brosse'      => '🪥',
        'lessive'     => '🧺',
        'javel'       => '🧹',
        'detergent'   => '🧹',
        'desinfectant'=> '🧴',
        'papier'      => '📄',
        'essuie'      => '🧻',
        'serviette'   => '🧻',
        'couche'      => '👶',

        // Electronique & autre
        'telephone'   => '📱', 'téléphone'   => '📱',
        'chargeur'    => '🔌',
        'batterie'    => '🔋',
        'cable'       => '🔌',
        'lampe'       => '💡',
        'pile'        => '🔋',
        'ampoule'     => '💡',

        // Vêtements & textile
        'tissu'       => '🧵',
        'vetement'    => '👕', 'vêtement'    => '👕',
        'chemise'     => '👕',
        'pantalon'    => '👖',
        'chaussure'   => '👟', 'chaussures'  => '👟',

        // Divers
        'medicament'  => '💊', 'médicament'  => '💊',
        'comprime'    => '💊',
        'sirop medicament' => '💊',
        'masque'      => '😷',
        'gant'        => '🧤',
        'sac'         => '👜',
        'bougie'      => '🕯️',
        'allumette'   => '🔥',
        'briquet'     => '🔥',
        'carburant'   => '⛽',
        'ciment'      => '🏗️',
        'fer'         => '⚙️',
        'clou'        => '🔨',
    ];

    // Cherche une correspondance dans le nom du produit
    foreach ($map as $mot => $emoji) {
        if (str_contains($nom, $mot)) {
            return $emoji;
        }
    }

    // Emoji par défaut selon la premiere lettre (pour varier)
    $defaults = ['📦','🛍️','🏪','🛒','🎁','📫'];
    return $defaults[abs(crc32($nom)) % count($defaults)];
}
