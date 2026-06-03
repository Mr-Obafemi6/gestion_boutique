<?php
// ============================================================
//  includes/Produit.php — Modèle Produit
// ============================================================
require_once __DIR__ . '/../config/config.php';

class Produit {
    private PDO $db;

    public function __construct() { $this->db = getDB(); }

    public function getCount(string $search = '', int $categorie = 0): int {
        $sql    = "SELECT COUNT(*) FROM produits p WHERE p.statut = 'actif'";
        $params = [];
        if ($search) {
            $sql .= " AND (p.nom LIKE :s OR p.reference LIKE :s2)";
            $params[':s'] = "%$search%"; $params[':s2'] = "%$search%";
        }
        if ($categorie > 0) {
            $sql .= " AND p.categorie_id = :cat";
            $params[':cat'] = $categorie;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getAll(string $search = '', int $categorie = 0, int $limit = 0, int $offset = 0): array {
        $sql = "SELECT p.*, c.nom AS categorie_nom
                FROM produits p
                JOIN categories c ON p.categorie_id = c.id
                WHERE p.statut = 'actif'";
        $params = [];
        if ($search) {
            $sql .= " AND (p.nom LIKE :s OR p.reference LIKE :s2)";
            $params[':s'] = "%$search%"; $params[':s2'] = "%$search%";
        }
        if ($categorie > 0) {
            $sql .= " AND p.categorie_id = :cat";
            $params[':cat'] = $categorie;
        }
        $sql .= " ORDER BY p.nom ASC";
        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT p.*, c.nom AS categorie_nom FROM produits p JOIN categories c ON p.categorie_id = c.id WHERE p.id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // ── Génère une référence unique automatique ─────────────
    private function genererReference(): string {
        $stmt = $this->db->query("SELECT MAX(CAST(SUBSTRING(reference, 6) AS UNSIGNED)) AS max_num FROM produits WHERE reference LIKE 'PROD-%'");
        $row  = $stmt->fetch();
        $num  = ($row['max_num'] ?? 0) + 1;
        return 'PROD-' . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    // ── Helper upload image ───────────────────────────────────
    private function uploadImage(?array $file, int $produitId = 0): ?string {
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) return null;
        if ($file['size'] > 2 * 1024 * 1024) return null; // max 2Mo
        $dir     = __DIR__ . '/../uploads/produits/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $nom     = 'prod_' . ($produitId ?: time()) . '_' . rand(100,999) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $nom);
        return $nom;
    }

    public function create(array $d): bool|string {
        if (empty($d['nom']) || empty($d['categorie_id'])) return "Champs obligatoires manquants.";
        if ((float)$d['prix_vente'] <= 0) return "Le prix de vente doit être supérieur à 0.";

        // Référence : utiliser celle saisie ou en générer une automatiquement
        $reference = !empty(trim($d['reference'] ?? ''))
            ? clean($d['reference'])
            : $this->genererReference();

        // Vérifier unicité
        $stmt = $this->db->prepare("SELECT id FROM produits WHERE reference = :ref");
        $stmt->execute([':ref' => $reference]);
        if ($stmt->fetch()) {
            if (empty(trim($d['reference'] ?? ''))) {
                $reference = 'PROD-' . date('ymd') . '-' . rand(10, 99);
            } else {
                return "Cette référence existe déjà.";
            }
        }

        $codeBarre = !empty(trim($d['code_barre'] ?? '')) ? clean($d['code_barre']) : null;

        $this->db->prepare("INSERT INTO produits (reference, code_barre, nom, description, categorie_id, unite_mesure, prix_achat, prix_vente, prix_gros, qte_min_gros, stock_actuel, seuil_alerte)
            VALUES (:ref, :cb, :nom, :desc, :cat, :unite, :pa, :pv, :pg, :qmg, :stock, :seuil)")
            ->execute([
                ':ref'   => $reference,               ':cb'    => $codeBarre,
                ':nom'   => clean($d['nom']),         ':desc'  => clean($d['description'] ?? ''),
                ':cat'   => (int)$d['categorie_id'],  ':unite' => clean($d['unite_mesure'] ?? 'pièce'),
                ':pa'    => (float)$d['prix_achat'],  ':pv'    => (float)$d['prix_vente'],
                ':pg'    => !empty($d['prix_gros']) ? (float)$d['prix_gros'] : null,
                ':qmg'   => (int)($d['qte_min_gros'] ?? 0),
                ':stock' => (int)($d['stock_initial'] ?? 0),
                ':seuil' => (int)($d['seuil_alerte'] ?? 5),
            ]);
        $newId = (int)$this->db->lastInsertId();

        // Upload image si fournie
        if (!empty($d['_image'])) {
            $img = $this->uploadImage($d['_image'], $newId);
            if ($img) {
                $this->db->prepare("UPDATE produits SET image=:img WHERE id=:id")
                    ->execute([':img'=>$img, ':id'=>$newId]);
            }
        }
        return true;
    }

    public function update(int $id, array $d): bool|string {
        if (empty($d['nom']) || empty($d['reference'])) return "Champs obligatoires manquants.";
        $stmt = $this->db->prepare("SELECT id FROM produits WHERE reference = :ref AND id != :id");
        $stmt->execute([':ref' => $d['reference'], ':id' => $id]);
        if ($stmt->fetch()) return "Cette référence est déjà utilisée par un autre produit.";

        $this->db->prepare("UPDATE produits SET reference=:ref, nom=:nom, description=:desc, categorie_id=:cat,
            unite_mesure=:unite, prix_achat=:pa, prix_vente=:pv, seuil_alerte=:seuil WHERE id=:id")
            ->execute([
                ':ref'=>clean($d['reference']), ':nom'=>clean($d['nom']),
                ':desc'=>clean($d['description']??''), ':cat'=>(int)$d['categorie_id'],
                ':unite'=>clean($d['unite_mesure']??'pièce'),
                ':pa'=>(float)$d['prix_achat'], ':pv'=>(float)$d['prix_vente'],
                ':seuil'=>(int)$d['seuil_alerte'], ':id'=>$id,
            ]);

        // Upload nouvelle image si fournie
        if (!empty($d['_image']) && $d['_image']['error'] === UPLOAD_ERR_OK) {
            $img = $this->uploadImage($d['_image'], $id);
            if ($img) {
                // Supprimer l'ancienne
                $ancien = $this->db->prepare("SELECT image FROM produits WHERE id=:id");
                $ancien->execute([':id'=>$id]);
                $ancienImg = $ancien->fetchColumn();
                if ($ancienImg) @unlink(__DIR__ . '/../uploads/produits/' . $ancienImg);
                $this->db->prepare("UPDATE produits SET image=:img WHERE id=:id")
                    ->execute([':img'=>$img, ':id'=>$id]);
            }
        }
        return true;
    }

    public function delete(int $id): bool|string {
        // Vérifier si le produit a des mouvements
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM mouvements_stock WHERE produit_id=:id");
        $stmt->execute([':id'=>$id]);
        if ($stmt->fetchColumn() > 0) {
            // Désactiver plutôt que supprimer
            $this->db->prepare("UPDATE produits SET statut='inactif' WHERE id=:id")->execute([':id'=>$id]);
            return true;
        }
        $this->db->prepare("DELETE FROM produits WHERE id=:id")->execute([':id'=>$id]);
        return true;
    }

    public function getCategories(): array {
        return $this->db->query("SELECT * FROM categories ORDER BY nom")->fetchAll();
    }

    public function createCategorie(string $nom, string $desc = ''): bool {
        $this->db->prepare("INSERT INTO categories (nom, description) VALUES (:nom, :desc)")
            ->execute([':nom'=>clean($nom), ':desc'=>clean($desc)]);
        return true;
    }

    public function getPourVente(): array {
        return $this->db->query("SELECT p.id, p.nom, p.reference, p.prix_vente,
                p.prix_gros, p.qte_min_gros,
                p.stock_actuel, p.seuil_alerte, p.unite_mesure, p.categorie_id,
                p.image,
                c.nom AS categorie_nom
                FROM produits p LEFT JOIN categories c ON p.categorie_id = c.id
                WHERE p.statut='actif' AND p.stock_actuel > 0 ORDER BY p.nom")->fetchAll();
    }

    /**
     * Calcule le prix applicable selon la quantité (détail ou gros).
     */
    public static function calculerPrix(array $produit, int $quantite): array {
        $estGros = !empty($produit['prix_gros'])
            && $produit['qte_min_gros'] > 0
            && $quantite >= $produit['qte_min_gros'];
        return [
            'prix'     => $estGros ? (float)$produit['prix_gros'] : (float)$produit['prix_vente'],
            'est_gros' => $estGros,
        ];
    }
}
