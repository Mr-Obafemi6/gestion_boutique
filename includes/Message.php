<?php
// ============================================================
//  includes/Message.php — Classe messagerie interne
// ============================================================

class Message {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    // Envoyer un message
    public function envoyer(int $expediteur, int $destinataire, string $sujet, string $contenu): bool {
        $stmt = $this->db->prepare("
            INSERT INTO messages (expediteur_id, destinataire_id, sujet, contenu)
            VALUES (:exp, :dest, :sujet, :contenu)
        ");
        return $stmt->execute([
            ':exp'     => $expediteur,
            ':dest'    => $destinataire,
            ':sujet'   => clean($sujet),
            ':contenu' => clean($contenu),
        ]);
    }

    // Messages reçus
    public function getRecus(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT m.*, 
                u.nom AS exp_nom, u.prenom AS exp_prenom
            FROM messages m
            JOIN utilisateurs u ON m.expediteur_id = u.id
            WHERE m.destinataire_id = :uid
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    // Messages envoyés
    public function getEnvoyes(int $userId): array {
        $stmt = $this->db->prepare("
                SELECT m.*,
                    u.nom AS dest_nom, u.prenom AS dest_prenom
                FROM messages m
                JOIN utilisateurs u ON m.destinataire_id = u.id
                WHERE m.expediteur_id = :uid
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll();
        }

        // Detail d'un message
        public function getById(int $id): ?array {
            $stmt = $this->db->prepare("
                SELECT m.*,
                    e.nom AS exp_nom, e.prenom AS exp_prenom,
                    d.nom AS dest_nom, d.prenom AS dest_prenom
                FROM messages m
                JOIN utilisateurs e ON m.expediteur_id = e.id
                JOIN utilisateurs d ON m.destinataire_id = d.id
                WHERE m.id = :id
            ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // Marquer comme lu
    public function marquerLu(int $id, int $userId): void {
        $this->db->prepare("
            UPDATE messages SET lu = 1
            WHERE id = :id AND destinataire_id = :uid
        ")->execute([':id' => $id, ':uid' => $userId]);
    }

    // Supprimer un message
    public function supprimer(int $id, int $userId): bool {
        $stmt = $this->db->prepare("
            DELETE FROM messages 
            WHERE id = :id 
            AND (expediteur_id = :uid OR destinataire_id = :uid2)
        ");
        return $stmt->execute([':id' => $id, ':uid' => $userId, ':uid2' => $userId]);
    }

    // Nombre de messages non lus
    public function getNonLus(int $userId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM messages 
            WHERE destinataire_id = :uid AND lu = 0
        ");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    // Liste tous les utilisateurs sauf soi-même
    public function getDestinataires(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT u.id, u.nom, u.prenom, r.nom AS role
            FROM utilisateurs u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id != :uid AND u.statut = 'actif'
            ORDER BY u.nom, u.prenom
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }
}
