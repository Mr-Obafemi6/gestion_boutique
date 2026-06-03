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

function getProduitImageUrl(array $produit): ?string {
    if (!empty($produit['image'])) {
        return APP_URL . '/uploads/produits/' . htmlspecialchars($produit['image']);
    }

    $map = [
        'coca'      => 'imgProduits/stock-photo-chisinau-moldova-march-coca-cola-glass-bottle-with-original-taste-label-showing-2761158803.jpg',
        'carotte'   => 'imgProduits/stock-photo-orange-carrots-green-leafy-tops-isolated-on-white-background-2755395061.jpg',
        'piment'    => 'imgProduits/stock-photo-red-hot-natural-chili-pepper-with-leaves-hot-chili-pepper-clipping-path-isolated-on-a-white-2732512111.jpg',
        'tomate'    => 'imgProduits/stock-photo-tomatoes-isolated-tomato-on-white-background-tomatoes-and-a-half-side-view-with-clipping-path-2194308345.jpg',
        'oignon'    => 'imgProduits/stock-photo-fresh-red-onion-isolated-on-white-background-vibrant-purple-allium-cepa-bulb-concept-2754470607.jpg',
        'boeuf'     => 'imgProduits/stock-photo-juicy-medium-rare-beef-steak-on-wooden-board-beef-steak-juicy-medium-rare-expertly-grilled-2710541057.jpg',
        'steak'     => 'imgProduits/stock-photo-juicy-medium-rare-beef-steak-on-wooden-board-beef-steak-juicy-medium-rare-expertly-grilled-2710541057.jpg',
        'banane'    => 'imgProduits/stock-photo-beautiful-banana-bunch-and-banana-skin-isolated-on-white-background-organic-banana-fruit-isolated-2655160397.jpg',
        'pomme'     => 'imgProduits/stock-photo-piece-of-green-apple-isolated-on-white-background-clipping-path-2525274515.jpg',
        'poisson'   => 'imgProduits/stock-photo-single-blue-mackerel-fish-isolated-2378172633.jpg',
        'maquereau' => 'imgProduits/stock-photo-single-blue-mackerel-fish-isolated-2378172633.jpg',
        'ail'       => 'imgProduits/stock-photo-two-garlic-and-garlic-cloves-isolated-on-a-white-background-2470608621.jpg',
        'poulet'    => 'imgProduits/stock-photo-raw-chicken-legs-drumsticks-perfect-for-roasting-or-frying-offering-a-delicious-and-juicy-flavor-2748624711.jpg',
        'savon'     => 'imgProduits/stock-photo-piece-of-natural-orange-soap-on-white-background-1878042115.jpg',
        'orange'    => 'imgProduits/stock-photo-orange-fruit-with-orange-slices-and-leaves-isolated-on-white-background-orange-with-clipping-path-2726276721.jpg',
        'lait'      => 'imgProduits/stock-photo-johannesburg-south-africa-january-a-box-of-long-life-full-cream-milk-from-dewfresh-2572636691.jpg',
        'avocat'    => 'imgProduits/stock-photo-many-whole-and-cut-avocados-isolated-on-white-2603537765.jpg',
        'mangue'    => 'imgProduits/stock-photo-juicy-fresh-mango-with-slices-and-leaves-2564957889.jpg',
        'poivron'   => 'imgProduits/stock-photo-colorful-assortment-of-red-yellow-orange-and-green-bell-peppers-isolated-on-white-background-2725735591.jpg',
        'couscous'  => 'imgProduits/stock-photo-udine-italy-august-carrefour-bio-couscous-bags-are-neatly-arranged-on-supermarket-2673422619.jpg',
        'haricot'   => 'imgProduits/stock-photo--kidney-beans-on-the-market-2514233031.jpg',
        'crevette'  => 'imgProduits/stock-photo-a-stunning-display-of-delicious-shrimp-on-ice-ideal-for-seafood-dishes-and-raw-bars-for-any-2526043795.jpg',
        'maïs'      => '',
        'arachide'  => 'imgProduits/stock-photo-natural-peanut-oil-isolated-on-white-background-aromatic-cooking-oil-derived-from-peanuts-for-deep-2763867095.jpg',
        'riz'       => '',
        'pain'      => '',
        'pate'      => '',
        'cereales'  => '',
        'farine'    => '',
        'jus'       => 'imgProduits/stock-photo-orange-juice-bottle-mockup-front-view-of-orange-juice-glass-bottle-isolated-on-white-pineapple-2496005241.jpg',
        'biere'     => 'imgProduits/stock-photo-beer-bottle-in-metal-bucket-full-of-ice-cubes-on-white-table-horizontal-composition-top-view-1436292794.jpg',
        'vin'       => '',
        'cafe'      => '',
        'the'       => '',
        'limonade'  => '',
        'sirop'     => '',
        'huile'     => '',
        'sel'       => 'imgProduits/stock-photo-salt-shaker-and-pile-of-spilled-salt-on-concrete-stone-2437938651.jpg',
        'sucre'     => 'imgProduits/stock-photo-cubes-of-white-and-cane-sugar-in-boxes-on-a-brown-background-2689611759.jpg',
        'miel'      => '',
        'vinaigre'  => '',
        'sauce'     => '',
        'mayonnaise'=> 'imgProduits/stock-photo-tasty-mayonnaise-sauce-in-glass-jar-with-blank-label-isolated-on-white-2579060541.jpg',
        'moutarde'  => '',
        'viande'    => '',
        'mouton'    => '',
        'porc'      => '',
        'thon'      => 'imgProduits/stock-photo-raw-fresh-red-tuna-fillet-fish-steak-2739180383.jpg',
        'sardine'   => '',
        'oeuf'      => 'imgProduits/stock-photo-close-up-of-white-and-brown-eggs-in-a-carton-showcasing-texture-and-color-contrast-2706831887.jpg',
        'fromage'   => 'imgProduits/stock-photo-maasdam-cheese-isolated-on-white-background-2609465083.jpg',
        'shampooing'=> '',
        'dentifrice'=> 'imgProduits/stock-photo-cairo-egypt-september-cavity-and-oral-protection-clove-and-basil-dabur-herb-l-toothpaste-2675959303.jpg',
        'brosse'    => '',
        'lessive'   => '',
        'javel'     => 'imgProduits/stock-photo-toilet-cleaner-green-bottle-isolated-on-white-background-unlabeled-plastic-container-of-liquid-2023096814.jpg',
        'detergent' => 'imgProduits/stock-photo-toilet-cleaner-green-bottle-isolated-on-white-background-unlabeled-plastic-container-of-liquid-2023096814.jpg',
        'desinfectant'=> 'imgProduits/stock-photo-toilet-cleaner-green-bottle-isolated-on-white-background-unlabeled-plastic-container-of-liquid-2023096814.jpg',
        'papier'    => '',
        'essuie'    => '',
        'serviette' => '',
        'couche'    => 'imgProduits/stock-photo-stack-of-disposable-baby-diapers-lsolated-on-clean-white-background-2624754769.jpg',
        'telephone' => '',
        'chargeur'  => '',
        'batterie'  => '',
        'cable'     => '',
        'lampe'     => '',
        'pile'      => '',
        'ampoule'   => '',
        'tissu'     => '',
        'vetement'  => '',
        'chemise'   => '',
        'pantalon'  => '',
        'chaussure' => '',
        'medicament'=> '',
        'masque'    => '',
        'gant'      => '',
        'sac'       => '',
        'bougie'    => '',
        'allumette' => '',
        'briquet'   => '',
        'carburant' => '',
        'ciment'    => '',
        'fer'       => '',
        'clou'      => '',
    ];

    $nom = strtolower($produit['nom'] ?? '');
    foreach ($map as $mot => $img) {
        if (str_contains($nom, $mot)) {
            return APP_URL . '/' . $img;
        }
    }

    return null;
}
