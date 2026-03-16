<?php
// ============================================================
//  includes/Stock.php — Modèle Stock
// ============================================================
require_once __DIR__ . '/../config/config.php';

class Stock {
    private PDO $db;

    public function __construct() { $this->db = getDB(); }

    public function getMouvements(string $search = '', string $type = ''): array {
        $sql = "SELECT ms.*, p.nom AS produit_nom, p.reference, u.nom AS user_nom, u.prenom AS user_prenom,
                       f.nom AS fournisseur_nom
                FROM mouvements_stock ms
                JOIN produits p ON ms.produit_id = p.id
                JOIN utilisateurs u ON ms.utilisateur_id = u.id
                LEFT JOIN fournisseurs f ON ms.fournisseur_id = f.id
                WHERE 1=1";
        $params = [];
        if ($search) { $sql .= " AND (p.nom LIKE :s OR p.reference LIKE :s2)"; $params[':s'] = "%$search%"; $params[':s2'] = "%$search%"; }
        if ($type)   { $sql .= " AND ms.type_mvt = :type"; $params[':type'] = $type; }
        $sql .= " ORDER BY ms.created_at DESC LIMIT 100";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function entreeStock(array $d): bool|string {
        if (empty($d['produit_id']) || empty($d['quantite']) || (int)$d['quantite'] <= 0)
            return "Produit et quantité sont obligatoires.";

        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO mouvements_stock (produit_id, type_mvt, quantite, prix_unitaire, motif, fournisseur_id, utilisateur_id)
                VALUES (:pid, 'entree', :qte, :pu, :motif, :fid, :uid)")
                ->execute([
                    ':pid'   => (int)$d['produit_id'],
                    ':qte'   => (int)$d['quantite'],
                    ':pu'    => (float)($d['prix_unitaire'] ?? 0),
                    ':motif' => clean($d['motif'] ?? 'Approvisionnement'),
                    ':fid'   => !empty($d['fournisseur_id']) ? (int)$d['fournisseur_id'] : null,
                    ':uid'   => $_SESSION['user_id'],
                ]);
            $this->db->prepare("UPDATE produits SET stock_actuel = stock_actuel + :qte WHERE id = :id")
                ->execute([':qte' => (int)$d['quantite'], ':id' => (int)$d['produit_id']]);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }

    public function sortieStock(array $d): bool|string {
        if (empty($d['produit_id']) || (int)$d['quantite'] <= 0) return "Données invalides.";

        // Vérifier stock disponible
        $stmt = $this->db->prepare("SELECT stock_actuel FROM produits WHERE id=:id");
        $stmt->execute([':id' => (int)$d['produit_id']]);
        $produit = $stmt->fetch();
        if (!$produit || $produit['stock_actuel'] < (int)$d['quantite'])
            return "Stock insuffisant pour cette opération.";

        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO mouvements_stock (produit_id, type_mvt, quantite, motif, utilisateur_id)
                VALUES (:pid, :type, :qte, :motif, :uid)")
                ->execute([
                    ':pid'   => (int)$d['produit_id'],
                    ':type'  => in_array($d['type_mvt'], ['sortie','perte','retour']) ? $d['type_mvt'] : 'sortie',
                    ':qte'   => (int)$d['quantite'],
                    ':motif' => clean($d['motif'] ?? ''),
                    ':uid'   => $_SESSION['user_id'],
                ]);
            $this->db->prepare("UPDATE produits SET stock_actuel = stock_actuel - :qte WHERE id=:id")
                ->execute([':qte' => (int)$d['quantite'], ':id' => (int)$d['produit_id']]);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return "Erreur : " . $e->getMessage();
        }
    }

    public function getStockCritique(): array {
        try {
            $stmt = $this->db->prepare("SELECT * FROM v_stock_critique");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            // Fallback si la vue n'existe pas encore
            $stmt = $this->db->prepare("
                SELECT p.id, p.reference, p.nom, c.nom AS categorie,
                       p.stock_actuel, p.seuil_alerte,
                       (p.seuil_alerte - p.stock_actuel) AS ecart
                FROM produits p
                JOIN categories c ON p.categorie_id = c.id
                WHERE p.stock_actuel <= p.seuil_alerte
                  AND p.statut = 'actif'
                ORDER BY ecart DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        }
    }

    public function getFournisseurs(): array {
        return $this->db->query("SELECT * FROM fournisseurs ORDER BY nom")->fetchAll();
    }

    public function addFournisseur(array $d): bool {
        $this->db->prepare("INSERT INTO fournisseurs (nom, telephone, email, adresse) VALUES (:nom,:tel,:email,:adr)")
            ->execute([':nom'=>clean($d['nom']),':tel'=>clean($d['telephone']??''),':email'=>clean($d['email']??''),':adr'=>clean($d['adresse']??'')]);
        return true;
    }

    public function getFournisseurById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM fournisseurs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function updateFournisseur(int $id, array $d): bool|string {
        if (empty($d['nom'])) return "Le nom est obligatoire.";
        $this->db->prepare("UPDATE fournisseurs SET nom=:nom, telephone=:tel, email=:email, adresse=:adr WHERE id=:id")
            ->execute([':nom'=>clean($d['nom']),':tel'=>clean($d['telephone']??''),':email'=>clean($d['email']??''),':adr'=>clean($d['adresse']??''),':id'=>$id]);
        return true;
    }

    public function deleteFournisseur(int $id): bool|string {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM mouvements_stock WHERE fournisseur_id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            return "Impossible de supprimer : ce fournisseur est lié à des mouvements de stock.";
        }
        $this->db->prepare("DELETE FROM fournisseurs WHERE id = :id")->execute([':id' => $id]);
        return true;
    }
}
