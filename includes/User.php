<?php
// ============================================================
//  includes/User.php — Modèle Utilisateur
// ============================================================

require_once __DIR__ . '/../config/config.php';

class User {

    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ── AUTHENTIFICATION ─────────────────────────────────────

    /**
     * Vérifie les identifiants et connecte l'utilisateur.
     * Retourne true si succès, string d'erreur sinon.
     */
    public function login(string $email, string $password): bool|string {

        if (empty($email) || empty($password)) {
            return "Veuillez remplir tous les champs.";
        }

        $stmt = $this->db->prepare("
            SELECT u.*, r.nom AS role_nom
            FROM utilisateurs u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = :email
    AND u.statut = 'actif'
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return "Email ou mot de passe incorrect.";
        }

        if (!password_verify($password, $user['mot_de_passe'])) {
            return "Email ou mot de passe incorrect.";
        }

        // Régénération de l'ID de session (sécurité anti-fixation)
        session_regenerate_id(true);

        // Stockage en session
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_nom']    = $user['nom'] . ' ' . $user['prenom'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_role']   = $user['role_nom'];
        $_SESSION['last_activity'] = time();

        // Log de connexion
        $this->logAction($user['id'], 'login');

        return true;
    }

    /**
     * Déconnecte l'utilisateur.
     */
    public function logout(): void {
        if (!empty($_SESSION['user_id'])) {
            $this->logAction($_SESSION['user_id'], 'logout');
        }
        session_unset();
        session_destroy();
    }

    // ── GESTION DES UTILISATEURS ─────────────────────────────

    /**
     * Crée un nouvel utilisateur.
     */
    public function create(array $data): bool|string {

        // Validation
        if (empty($data['nom']) || empty($data['prenom']) ||
            empty($data['email']) || empty($data['password']) ||
            empty($data['role_id'])) {
            return "Tous les champs sont obligatoires.";
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return "L'adresse email est invalide.";
        }

        if (strlen($data['password']) < 8) {
            return "Le mot de passe doit contenir au moins 8 caractères.";
        }

        // Vérification email unique
        $stmt = $this->db->prepare("SELECT id FROM utilisateurs WHERE email = :email");
        $stmt->execute([':email' => $data['email']]);
        if ($stmt->fetch()) {
            return "Cette adresse email est déjà utilisée.";
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->db->prepare("
            INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id)
            VALUES (:nom, :prenom, :email, :mdp, :role_id)
        ");
        $stmt->execute([
            ':nom'     => clean($data['nom']),
            ':prenom'  => clean($data['prenom']),
            ':email'   => $data['email'],
            ':mdp'     => $hash,
            ':role_id' => (int) $data['role_id'],
        ]);

        return true;
    }

    /**
     * Retourne tous les utilisateurs avec leur rôle.
     */
    public function getAll(): array {
        $stmt = $this->db->query("
            SELECT u.id, u.nom, u.prenom, u.email, u.statut,
                   u.avatar, r.nom AS role, u.created_at
            FROM utilisateurs u
            JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Retourne un utilisateur par son ID.
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nom AS role_nom
            FROM utilisateurs u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Change le statut (actif / inactif) d'un utilisateur.
     */
    public function toggleStatut(int $id): void {
        $stmt = $this->db->prepare("
            UPDATE utilisateurs
            SET statut = IF(statut = 'actif', 'inactif', 'actif')
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur (par l'admin).
     */
    public function resetPassword(int $id, string $newPassword): bool|string {
        if (strlen($newPassword) < 8) {
            return "Le mot de passe doit contenir au moins 8 caractères.";
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare("
            UPDATE utilisateurs SET mot_de_passe = :mdp WHERE id = :id
        ");
        $stmt->execute([':mdp' => $hash, ':id' => $id]);
        return true;
    }

    /**
     * Retourne tous les rôles disponibles.
     */
    public function getRoles(): array {
        return $this->db->query("SELECT * FROM roles ORDER BY id")->fetchAll();
    }

    // ── LOGS ─────────────────────────────────────────────────

    private function logAction(int $userId, string $action): void {
        $stmt = $this->db->prepare("
            INSERT INTO sessions_log (utilisateur_id, ip_address, user_agent, action)
            VALUES (:uid, :ip, :ua, :action)
        ");
        $stmt->execute([
            ':uid'    => $userId,
            ':ip'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':ua'     => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ':action' => $action,
        ]);
    }
}