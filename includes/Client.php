<?php
// ============================================================
//  includes/Client.php — Modèle Client
//  Gère : CRUD clients, historique achats, crédit/dette, fidélité
// ============================================================
require_once __DIR__ . '/../config/config.php';

class Client {
    private PDO $db;

    // Points gagnés par tranche de 1 000 FCFA dépensés
    const POINTS_PAR_1000 = 1;
    // 1 point = X FCFA de remise automatique
    const VALEUR_POINT    = 10;

    public function __construct() { $this->db = getDB(); }

    // ── CRUD ─────────────────────────────────────────────────

    public function getAll(string $search = '', string $type = ''): array {
        $sql    = "SELECT * FROM clients WHERE statut = 'actif'";
        $params = [];
        if ($search) {
            $sql .= " AND (nom LIKE :s OR telephone LIKE :s2 OR email LIKE :s3)";
            $params[':s'] = $params[':s2'] = $params[':s3'] = "%$search%";
        }
        if ($type) {
            $sql .= " AND type_client = :type";
            $params[':type'] = $type;
        }
        $sql .= " ORDER BY nom ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $d): bool|string {
        if (empty(trim($d['nom'] ?? ''))) return "Le nom est obligatoire.";

        // Vérifier doublon téléphone
        if (!empty($d['telephone'])) {
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE telephone = :tel LIMIT 1");
            $stmt->execute([':tel' => clean($d['telephone'])]);
            if ($stmt->fetch()) return "Un client avec ce numéro existe déjà.";
        }

        $this->db->prepare("
            INSERT INTO clients (nom, telephone, email, adresse, type_client, credit_autorise)
            VALUES (:nom, :tel, :email, :adr, :type, :credit)
        ")->execute([
            ':nom'    => clean($d['nom']),
            ':tel'    => clean($d['telephone'] ?? ''),
            ':email'  => clean($d['email']     ?? ''),
            ':adr'    => clean($d['adresse']   ?? ''),
            ':type'   => in_array($d['type_client'] ?? '', ['particulier','grossiste']) ? $d['type_client'] : 'particulier',
            ':credit' => max(0, (float)($d['credit_autorise'] ?? 0)),
        ]);
        return true;
    }

    public function update(int $id, array $d): bool|string {
        if (empty(trim($d['nom'] ?? ''))) return "Le nom est obligatoire.";

        // Doublon téléphone (hors cet enregistrement)
        if (!empty($d['telephone'])) {
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE telephone = :tel AND id != :id LIMIT 1");
            $stmt->execute([':tel' => clean($d['telephone']), ':id' => $id]);
            if ($stmt->fetch()) return "Ce numéro est utilisé par un autre client.";
        }

        $this->db->prepare("
            UPDATE clients SET
                nom            = :nom,
                telephone      = :tel,
                email          = :email,
                adresse        = :adr,
                type_client    = :type,
                credit_autorise= :credit
            WHERE id = :id
        ")->execute([
            ':nom'    => clean($d['nom']),
            ':tel'    => clean($d['telephone'] ?? ''),
            ':email'  => clean($d['email']     ?? ''),
            ':adr'    => clean($d['adresse']   ?? ''),
            ':type'   => in_array($d['type_client'] ?? '', ['particulier','grossiste']) ? $d['type_client'] : 'particulier',
            ':credit' => max(0, (float)($d['credit_autorise'] ?? 0)),
            ':id'     => $id,
        ]);
        return true;
    }

    public function delete(int $id): bool|string {
        // Vérifier achats liés
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ventes WHERE client_id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            // Désactiver au lieu de supprimer
            $this->db->prepare("UPDATE clients SET statut='inactif' WHERE id=:id")->execute([':id'=>$id]);
            return true;
        }
        $this->db->prepare("DELETE FROM clients WHERE id=:id")->execute([':id'=>$id]);
        return true;
    }

    // ── Recherche rapide (pour select2 / autocomplete caisse) ─

    public function rechercher(string $q): array {
        $stmt = $this->db->prepare("
            SELECT id, nom, telephone, type_client, solde_credit, points_fidelite
            FROM clients
            WHERE statut = 'actif'
              AND (nom LIKE :q OR telephone LIKE :q2)
            ORDER BY nom
            LIMIT 10
        ");
        $stmt->execute([':q' => "%$q%", ':q2' => "%$q%"]);
        return $stmt->fetchAll();
    }

    // ── Historique achats ─────────────────────────────────────

    public function getHistorique(int $clientId): array {
        $stmt = $this->db->prepare("
            SELECT v.id, v.reference, v.total_net, v.remise, v.type_vente,
                   v.statut, v.created_at,
                   u.nom AS caissier_nom,
                   COUNT(lv.id) AS nb_articles
            FROM ventes v
            JOIN utilisateurs u ON v.utilisateur_id = u.id
            LEFT JOIN lignes_vente lv ON lv.vente_id = v.id
            WHERE v.client_id = :cid
            GROUP BY v.id
            ORDER BY v.created_at DESC
            LIMIT 100
        ");
        $stmt->execute([':cid' => $clientId]);
        return $stmt->fetchAll();
    }

    public function getStats(int $clientId): array {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(v.id)      AS nb_achats,
                COALESCE(SUM(v.total_net), 0)  AS total_depense,
                COALESCE(MAX(v.total_net), 0)  AS plus_grosse_vente,
                COALESCE(MIN(v.created_at), NULL) AS premier_achat,
                COALESCE(MAX(v.created_at), NULL) AS dernier_achat
            FROM ventes v
            WHERE v.client_id = :cid AND v.statut = 'validee'
        ");
        $stmt->execute([':cid' => $clientId]);
        return $stmt->fetch();
    }

    // ── Fidélité ──────────────────────────────────────────────

    /**
     * Ajoute des points après une vente.
     * Appelé par Vente::enregistrer() si client_id fourni.
     */
    public function ajouterPoints(int $clientId, float $montant): void {
        $points = (int)floor($montant / 1000) * self::POINTS_PAR_1000;
        if ($points <= 0) return;
        $this->db->prepare("
            UPDATE clients SET points_fidelite = points_fidelite + :pts WHERE id = :id
        ")->execute([':pts' => $points, ':id' => $clientId]);
    }

    /**
     * Calcule la remise fidélité en FCFA selon les points disponibles.
     * Ne consomme pas encore les points.
     */
    public function remiseFidelite(int $clientId): float {
        $client = $this->getById($clientId);
        if (!$client) return 0;
        return $client['points_fidelite'] * self::VALEUR_POINT;
    }

    /**
     * Consomme les points utilisés en remise.
     * $remiseUtilisee = montant remise fidélité effectivement appliqué
     */
    public function consommerPoints(int $clientId, float $remiseUtilisee): void {
        $pointsUtilises = (int)ceil($remiseUtilisee / self::VALEUR_POINT);
        $this->db->prepare("
            UPDATE clients
            SET points_fidelite = GREATEST(0, points_fidelite - :pts)
            WHERE id = :id
        ")->execute([':pts' => $pointsUtilises, ':id' => $clientId]);
    }

    // ── Crédit / dette ────────────────────────────────────────

    /**
     * Vérifie si le client peut acheter à crédit.
     * $montantCredit = montant supplémentaire à crédit pour cette vente
     */
    public function peutAcheterCredit(int $clientId, float $montantCredit): bool {
        $client = $this->getById($clientId);
        if (!$client) return false;
        return ($client['solde_credit'] + $montantCredit) <= $client['credit_autorise'];
    }

    /**
     * Enregistre un crédit (dette) sur la vente.
     */
    public function ajouterCredit(int $clientId, float $montant): void {
        $this->db->prepare("
            UPDATE clients SET solde_credit = solde_credit + :m WHERE id = :id
        ")->execute([':m' => $montant, ':id' => $clientId]);
    }

    /**
     * Enregistre un remboursement partiel ou total.
     */
    public function rembourser(int $clientId, float $montant, string $note = '', int $userId = 0): bool|string {
        $client = $this->getById($clientId);
        if (!$client) return "Client introuvable.";
        if ($montant <= 0) return "Montant invalide.";
        if ($montant > $client['solde_credit']) return "Montant supérieur à la dette ({$client['solde_credit']} FCFA).";

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                UPDATE clients SET solde_credit = GREATEST(0, solde_credit - :m) WHERE id = :id
            ")->execute([':m' => $montant, ':id' => $clientId]);

            $this->db->prepare("
                INSERT INTO paiements_credit (client_id, montant, note, utilisateur_id)
                VALUES (:cid, :m, :note, :uid)
            ")->execute([
                ':cid'  => $clientId,
                ':m'    => $montant,
                ':note' => clean($note),
                ':uid'  => $userId ?: ($_SESSION['user_id'] ?? 0),
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return "Erreur : " . $e->getMessage();
        }
    }

    /**
     * Historique des remboursements d'un client.
     */
    public function getPaiementsCredit(int $clientId): array {
        $stmt = $this->db->prepare("
            SELECT pc.*, u.nom AS caissier
            FROM paiements_credit pc
            JOIN utilisateurs u ON pc.utilisateur_id = u.id
            WHERE pc.client_id = :cid
            ORDER BY pc.created_at DESC
        ");
        $stmt->execute([':cid' => $clientId]);
        return $stmt->fetchAll();
    }
}
