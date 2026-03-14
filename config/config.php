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
