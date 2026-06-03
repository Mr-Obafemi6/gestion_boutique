<?php
// ============================================================
//  modules/ventes/Scan.php — Recherche produit par code-barres
// ============================================================
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Vérification session et permission
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté.']);
    exit;
}
if (!peutFaire('ventes', 'creer') && !peutFaire('produits', 'voir')) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    exit;
}

$code = clean($_GET['code'] ?? '');

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Code vide.']);
    exit;
}

$db = getDB();

// Recherche par code-barres OU par référence produit
$stmt = $db->prepare("
    SELECT p.id, p.nom, p.prix_vente, p.prix_gros, p.qte_min_gros, p.stock_actuel, p.reference, p.code_barre
    FROM produits p
    WHERE p.statut = 'actif'
      AND p.stock_actuel > 0
      AND (p.code_barre = :code OR p.reference = :ref)
    LIMIT 1
");
$stmt->execute([':code' => $code, ':ref' => strtoupper($code)]);
$produit = $stmt->fetch();

if ($produit) {
    echo json_encode([
        'success' => true,
        'produit' => [
            'id'           => (int)$produit['id'],
            'nom'          => $produit['nom'],
            'prix_vente'   => (float)$produit['prix_vente'],
            'prix_gros'    => $produit['prix_gros'] !== null ? (float)$produit['prix_gros'] : null,
            'qte_min_gros' => (int)$produit['qte_min_gros'],
            'stock_actuel' => (int)$produit['stock_actuel'],
            'reference'    => $produit['reference'],
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Produit introuvable pour le code : ' . htmlspecialchars($code)
    ]);
}
