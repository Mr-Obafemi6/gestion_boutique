<?php
// ============================================================
//  includes/Vente.php — Modèle Vente
// ============================================================
require_once __DIR__ . '/../config/config.php';

class Vente {
    private PDO $db;

    public function __construct() { $this->db = getDB(); }

    /**
     * Enregistre une vente complète avec ses lignes.
     * $panier = [['produit_id'=>X, 'quantite'=>Y, 'prix_unitaire'=>Z], ...]
     */
    public function enregistrer(array $panier, float $remise = 0, float $montantRecu = 0, string $note = ''): array|string {
        if (empty($panier)) return "Le panier est vide.";

        // Calcul des totaux
        $totalHT = 0;
        foreach ($panier as $ligne) $totalHT += $ligne['quantite'] * $ligne['prix_unitaire'];
        $totalNet = max(0, $totalHT - $remise);
        $monnaie  = max(0, $montantRecu - $totalNet);

        // Nettoyer la note
        $note = trim($note);
        $note = $note !== '' ? mb_substr($note, 0, 500) : null;

        // Référence unique
        $ref = 'VTE-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $this->db->beginTransaction();
        try {
            // Insérer la vente
            $this->db->prepare("INSERT INTO ventes (reference, total_ht, remise, total_net, montant_recu, monnaie_rendue, note, utilisateur_id)
                VALUES (:ref,:ht,:remise,:net,:recu,:monnaie,:note,:uid)")
                ->execute([':ref'=>$ref,':ht'=>$totalHT,':remise'=>$remise,':net'=>$totalNet,
                        ':recu'=>$montantRecu,':monnaie'=>$monnaie,':note'=>$note,':uid'=>$_SESSION['user_id']]);
            $venteId = $this->db->lastInsertId();

            // Insérer les lignes et mettre à jour le stock
            foreach ($panier as $ligne) {
                $sousTotal = $ligne['quantite'] * $ligne['prix_unitaire'];
                $this->db->prepare("INSERT INTO lignes_vente (vente_id, produit_id, quantite, prix_unitaire, sous_total) VALUES (:vid,:pid,:qte,:pu,:st)")
                    ->execute([':vid'=>$venteId,':pid'=>$ligne['produit_id'],':qte'=>$ligne['quantite'],':pu'=>$ligne['prix_unitaire'],':st'=>$sousTotal]);

                // Décrémenter le stock
                $this->db->prepare("UPDATE produits SET stock_actuel = stock_actuel - :qte WHERE id=:id")
                    ->execute([':qte'=>$ligne['quantite'],':id'=>$ligne['produit_id']]);

                // Log mouvement stock
                $this->db->prepare("INSERT INTO mouvements_stock (produit_id, type_mvt, quantite, prix_unitaire, motif, utilisateur_id)
                    VALUES (:pid,'sortie',:qte,:pu,:motif,:uid)")
                    ->execute([':pid'=>$ligne['produit_id'],':qte'=>$ligne['quantite'],':pu'=>$ligne['prix_unitaire'],':motif'=>'Vente #'.$ref,':uid'=>$_SESSION['user_id']]);
            }

            $this->db->commit();
            return ['vente_id'=>$venteId, 'reference'=>$ref, 'total_net'=>$totalNet, 'monnaie'=>$monnaie, 'note'=>$note];
        } catch (Exception $e) {
            $this->db->rollBack();
            return "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT v.*, u.nom, u.prenom FROM ventes v JOIN utilisateurs u ON v.utilisateur_id=u.id WHERE v.id=:id");
        $stmt->execute([':id'=>$id]);
        $vente = $stmt->fetch();
        if (!$vente) return null;

        $stmt2 = $this->db->prepare("SELECT lv.*, p.nom AS produit_nom, p.reference FROM lignes_vente lv JOIN produits p ON lv.produit_id=p.id WHERE lv.vente_id=:id");
        $stmt2->execute([':id'=>$id]);
        $vente['lignes'] = $stmt2->fetchAll();
        return $vente;
    }

    public function getHistorique(string $dateDebut = '', string $dateFin = '', int $userId = 0): array {
        $sql = "SELECT v.*, u.nom, u.prenom, COUNT(lv.id) AS nb_articles
                FROM ventes v
                JOIN utilisateurs u ON v.utilisateur_id=u.id
                LEFT JOIN lignes_vente lv ON lv.vente_id=v.id
                WHERE v.statut='validee'";
        $params = [];
        if ($dateDebut) { $sql .= " AND DATE(v.created_at) >= :dd"; $params[':dd']=$dateDebut; }
        if ($dateFin)   { $sql .= " AND DATE(v.created_at) <= :df"; $params[':df']=$dateFin; }
        if ($userId > 0){ $sql .= " AND v.utilisateur_id=:uid"; $params[':uid']=$userId; }
        $sql .= " GROUP BY v.id ORDER BY v.created_at DESC LIMIT 200";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function annuler(int $id): bool|string {
        $vente = $this->getById($id);
        if (!$vente || $vente['statut'] === 'annulee') return "Vente introuvable ou déjà annulée.";

        $this->db->beginTransaction();
        try {
            foreach ($vente['lignes'] as $l) {
                $this->db->prepare("UPDATE produits SET stock_actuel = stock_actuel + :qte WHERE id=:id")
                    ->execute([':qte'=>$l['quantite'],':id'=>$l['produit_id']]);
            }
            $this->db->prepare("UPDATE ventes SET statut='annulee' WHERE id=:id")->execute([':id'=>$id]);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return "Erreur : " . $e->getMessage();
        }
    }

    public function getStatsMois(): array {
        $stmt = $this->db->prepare("SELECT * FROM v_ventes_jour ORDER BY jour DESC LIMIT 30");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTopProduits(int $limit = 10): array {
        // prepare() obligatoire — PDO n'accepte pas :limit dans LIMIT
        // On valide et caste en int pour éviter toute injection
        $limit = max(1, min((int)$limit, 100));
        $stmt  = $this->db->prepare("SELECT * FROM v_top_produits LIMIT " . $limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
