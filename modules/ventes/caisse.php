<?php
// ============================================================
//  modules/ventes/caisse.php — Point de vente (: gros + clients)
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Vente.php';
require_once __DIR__ . '/../../includes/Produit.php';
require_once __DIR__ . '/../../includes/Client.php';
requireLogin();
if (!peutFaire('ventes','creer')) redirect(APP_URL . '/includes/acces_refuse.php');

$venteModel   = new Vente();
$produitModel = new Produit();
$clientModel  = new Client();

// ── AJAX : recherche client ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search_client') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    echo json_encode($q ? $clientModel->rechercher($q) : []);
    exit;
}

// ── Traitement vente POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $panier         = json_decode($_POST['panier'] ?? '[]', true);
    $remise         = (float)($_POST['remise']          ?? 0);
    $recu           = (float)($_POST['montant_recu']    ?? 0);
    $note           = trim(strip_tags($_POST['note']    ?? ''));
    $clientId       = (int)($_POST['client_id']         ?? 0);
    $montantCredit  = (float)($_POST['montant_credit']  ?? 0);
    $remiseFidelite = (float)($_POST['remise_fidelite'] ?? 0);

    if (empty($panier)) { echo json_encode(['success'=>false,'message'=>'Le panier est vide.']); exit; }

    if ($montantCredit > 0 && $clientId <= 0) {
        echo json_encode(['success'=>false,'message'=>'Crédit client nécessite un client sélectionné.']); exit;
    }

    if ($remiseFidelite > 0) {
        if ($clientId <= 0) {
            echo json_encode(['success'=>false,'message'=>'Remise fidélité nécessite un client sélectionné.']); exit;
        }
        $client = $clientModel->getById($clientId);
        if (!$client) {
            echo json_encode(['success'=>false,'message'=>'Client introuvable.']); exit;
        }
        $maxFidelite = $client['points_fidelite'] * Client::VALEUR_POINT;
        if ($remiseFidelite > $maxFidelite) {
            echo json_encode(['success'=>false,'message'=>'Montant de remise fidélité trop élevé.']); exit;
        }
    }

    // ── VALIDATION : Vérifier les prix et recalculer si nécessaire ──────
    foreach ($panier as &$ligne) {
        $produit = $produitModel->getById($ligne['produit_id']);
        if (!$produit) {
            echo json_encode(['success'=>false,'message'=>'Produit introuvable : ' . $ligne['produit_id']]); exit;
        }

        // Recalculer le prix selon quantité
        $prixCalcule = Produit::calculerPrix($produit, (int)$ligne['quantite']);
        $prixAttendu = $prixCalcule['prix'];
        $estGrosAttendu = $prixCalcule['est_gros'];

        // Tolérer 1% de variation (arrondis floats)
        $tolerance = abs($prixAttendu * 0.01);
        $ecart = abs($ligne['prix_unitaire'] - $prixAttendu);

        if ($ecart > $tolerance) {
            // Prix incorrect rejeté pour sécurité
            echo json_encode(['success'=>false,'message'=>'Prix invalide pour ' . $produit['nom'] . ' (attendu ' . $prixAttendu . ', reçu ' . $ligne['prix_unitaire'] . ')']);
            exit;
        }

        // Corriger le prix et est_gros
        $ligne['prix_unitaire'] = $prixAttendu;
        $ligne['est_gros']      = $estGrosAttendu;
    }

    $total = array_sum(array_map(fn($l)=>$l['quantite']*$l['prix_unitaire'], $panier)) - $remise - $remiseFidelite;
    if (($recu + $montantCredit) < $total) {
        echo json_encode(['success'=>false,'message'=>'Montant reçu insuffisant.']); exit;
    }

    $result = $venteModel->enregistrer($panier, $remise, $recu, $note, $clientId, $montantCredit, $remiseFidelite);
    if (is_array($result)) {
        echo json_encode([
            'success'        => true,
            'vente_id'       => $result['vente_id'],
            'reference'      => $result['reference'],
            'total_net'      => $result['total_net'],
            'monnaie'        => $result['monnaie'],
            'note'           => $result['note'] ?? '',
            'type_vente'     => $result['type_vente'],
            'montant_credit' => $result['montant_credit'],
        ]);
    } else {
        echo json_encode(['success'=>false,'message'=>$result]);
    }
    exit;
}

$produits    = $produitModel->getPourVente();
$categories = $produitModel->getCategories();
$pageTitle   = "Point de vente";

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ─── Variables CSS pour thème réaliste ──────────────────────── */
:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --primary-light: #dbeafe;
    --success: #059669;
    --warning: #d97706;
    --danger: #dc2626;
    --dark: #1f2937;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    --shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

/* ─── Layout général ─────────────────────────────────────── */
.caisse-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1.5rem;
    height: calc(100vh - 120px);
    padding: 1rem;
    background: #f0f4f8;
}

/* ─── Panneau gauche ───────────────────────────────────────── */
.produits-panel {
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    overflow: hidden;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
}

/* ─── Barre de recherche simplifiée ───────────────────────────── */
.search-caisse {
    position: relative;
    margin-bottom: 1rem;
}
.search-caisse i {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400); font-size: .9rem; pointer-events: none;
}
.search-caisse input {
    padding-left: 2.5rem;
    padding-right: 1rem;
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-300);
    background: white;
    font-size: .9rem;
    height: 44px;
    transition: all 0.2s ease;
}
.search-caisse input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    outline: none;
}
.btn-scan {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    padding: 0 1.2rem;
    font-size: .85rem;
    font-weight: 600;
    white-space: nowrap;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    height: 44px;
}
.btn-scan:hover { 
    background: var(--primary-dark);
    transform: translateY(-1px);
}
.btn-scan.active { 
    background: var(--success);
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(5, 150, 105, 0); }
}
.scan-status {
    display: flex;
    align-items: center;
    gap: .75rem;
    background: #153F6E;
    color: #fff;
    padding: .6rem 1rem;
    border-radius: 10px;
    margin-bottom: .75rem;
    font-size: .85rem;
    font-weight: 600;
}
.scan-icon { font-size: 1.3rem; animation: blink 1s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
.scan-found {
    position: fixed;
    top: 72px; right: 20px;
    background: #16a34a;
    color: #fff;
    padding: .65rem 1.1rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: .85rem;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: .5rem;
    box-shadow: 0 4px 20px rgba(22,163,74,.35);
    animation: slideIn .25s ease;
    max-width: 280px;
}
.scan-found::before {
    content: '';
    display: inline-block;
    width: 18px; height: 18px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z'/%3E%3C/svg%3E") center/contain no-repeat;
    flex-shrink: 0;
}
.scan-notfound {
    position: fixed;
    top: 72px; right: 20px;
    background: #dc2626;
    color: #fff;
    padding: .65rem 1.1rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: .85rem;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: .5rem;
    box-shadow: 0 4px 20px rgba(220,38,38,.35);
    animation: slideIn .25s ease;
    max-width: 280px;
}
.scan-notfound::before {
    content: '';
    display: inline-block;
    width: 18px; height: 18px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z'/%3E%3C/svg%3E") center/contain no-repeat;
    flex-shrink: 0;
}
@keyframes slideIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }

/* ─── Grille produits moderne ───────────────────────────────── */
.produits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    overflow-y: auto;
    padding-right: 0.5rem;
    max-height: calc(100vh - 280px);
}
.produits-grid::-webkit-scrollbar { 
    width: 6px;
}
.produits-grid::-webkit-scrollbar-track { 
    background: var(--gray-100);
    border-radius: 3px;
}
.produits-grid::-webkit-scrollbar-thumb { 
    background: var(--gray-300);
    border-radius: 3px;
    transition: background 0.3s;
}
.produits-grid::-webkit-scrollbar-thumb:hover { 
    background: var(--gray-400);
}

/* ─── Carte produit réaliste ────────────────────────────────── */
.prod-card {
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-lg);
    padding: 1rem;
    cursor: pointer;
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
    position: relative;
    user-select: none;
    box-shadow: 0 8px 20px rgba(15, 23, 42, .08);
    overflow: hidden;
}
.prod-card * { pointer-events: none; }
.prod-card:hover {
    box-shadow: 0 18px 40px rgba(15, 23, 42, .12);
    transform: translateY(-4px);
    border-color: rgba(59, 130, 246, .3);
}
.prod-card.added {
    animation: product-added 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}
@keyframes product-added {
    0% { transform: scale(1); }
    30% { transform: scale(1.08) rotate(0.5deg); box-shadow: 0 0 24px rgba(59, 130, 246, 0.25); }
    60% { transform: scale(0.98) rotate(-0.5deg); }
    100% { transform: scale(1); }
}
.prod-card.zero-stock {
    opacity: 0.55;
    cursor: not-allowed;
    pointer-events: none;
    filter: grayscale(0.85);
}
.card-head {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    margin-bottom: 1rem;
}
.card-head-text {
    min-width: 0;
}
.prod-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1rem;
}
.prod-card .stock-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--gray-100);
    color: var(--gray-700);
    font-size: .78rem;
    font-weight: 700;
    padding: 0.45rem 0.9rem;
    border-radius: 999px;
    border: 1px solid var(--gray-200);
}
.prod-card .stock-badge.low {
    background: #fff3f2;
    color: var(--danger);
    border-color: #fecaca;
}
.prod-icon-wrap {
    width: 52px;
    height: 52px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.45rem;
    flex-shrink: 0;
    box-shadow: 0 12px 24px rgba(15, 23, 42, .06);
    transition: transform .25s ease, box-shadow .25s ease;
}
.prod-card:hover .prod-icon-wrap {
    transform: translateY(-2px);
    box-shadow: 0 18px 30px rgba(15, 23, 42, .09);
}
.prod-cat {
    display: inline-flex;
    font-size: .72rem;
    font-weight: 700;
    padding: 0.28rem 0.75rem;
    border-radius: 999px;
    color: var(--gray-700);
    background: var(--gray-100);
    margin-top: 0.35rem;
}
.prod-card .prod-nom {
    font-size: .95rem;
    font-weight: 700;
    color: var(--gray-900);
    line-height: 1.4;
    margin-bottom: 0.35rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.prod-card .prod-prix {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--primary);
}
.prod-gros {
    font-size: .72rem;
    color: var(--gray-600);
    background: var(--gray-100);
    display: inline-flex;
    align-items: center;
    padding: 0.38rem 0.75rem;
    border-radius: 999px;
    margin-top: 0.2rem;
}

/* ─── Panneau panier simplifié ─────────────────────────────────── */
.panier-panel {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: var(--shadow);
}

.panier-header {
    padding: 1rem 1.2rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--gray-50);
}
.panier-header .title {
    font-weight: 600;
    font-size: 1rem;
    color: var(--gray-800);
    display: flex; 
    align-items: center; 
    gap: 0.5rem;
}
.panier-items {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem;
    background: white;
}
.panier-items::-webkit-scrollbar { 
    width: 4px;
}
.panier-items::-webkit-scrollbar-track { 
    background: var(--gray-100);
}
.panier-items::-webkit-scrollbar-thumb { 
    background: var(--gray-300);
    border-radius: 2px;
}
/* ─── Item panier simplifié ───────────────────────────────────── */
.panier-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border: 1px solid var(--gray-200);
    background: white;
    border-radius: var(--radius-sm);
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
}
.panier-item:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}
.panier-item .item-nom {
    flex: 1;
    font-size: .85rem;
    font-weight: 600;
    color: var(--gray-700);
    line-height: 1.3;
}
.panier-item .item-ctrl {
    display: flex; 
    align-items: center; 
    gap: 4px;
}
.item-ctrl button {
    width: 28px; 
    height: 28px;
    border-radius: 50%;
    border: 1px solid var(--gray-300);
    background: white;
    font-size: .9rem; 
    font-weight: 700;
    cursor: pointer; 
    color: var(--gray-600);
    display: flex; 
    align-items: center; 
    justify-content: center;
    transition: all 0.2s ease;
    padding: 0;
}
.item-ctrl button:hover { 
    background: var(--primary);
    color: white; 
    border-color: var(--primary);
}
.item-ctrl .qte {
    min-width: 24px;
    text-align: center;
    font-size: .85rem;
    font-weight: 700;
    color: var(--gray-800);
}
.panier-item .item-prix {
    font-size: .85rem;
    font-weight: 700;
    color: var(--primary);
    min-width: 70px;
    text-align: right;
}
.panier-item .btn-del {
    background: none; 
    border: none; 
    cursor: pointer;
    color: var(--gray-400); 
    padding: 2px;
    transition: color 0.2s ease;
}
.panier-item .btn-del:hover { 
    color: var(--danger);
}

/* ─── Zone paiement simplifiée ─────────────────────────────────── */
.panier-footer {
    padding: 1rem 1.2rem;
    border-top: 1px solid var(--gray-200);
    background: var(--gray-50);
}
.total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .85rem;
    color: var(--gray-600);
    margin-bottom: 0.5rem;
}
.total-line.main {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
    padding: 0.75rem 0 0.5rem;
    border-top: 2px solid var(--gray-300);
    margin-top: 0.5rem;
}
.remise-input {
    width: 70px;
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    border: 1px solid var(--gray-300);
    border-right: none;
    padding: 4px 8px;
    font-size: .85rem;
    text-align: right;
    color: var(--gray-800);
    background: white;
}
.remise-input:focus { 
    outline: none; 
    border-color: var(--warning);
}
/* Bouton toggle F/% */
.remise-toggle {
    border: 1px solid var(--gray-300);
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    background: #f8fafc;
    font-size: .78rem;
    font-weight: 700;
    color: #64748b;
    padding: 4px 8px;
    cursor: pointer;
    transition: all .15s;
    white-space: nowrap;
    min-width: 32px;
    text-align: center;
}
.remise-toggle:hover { background: #e2e8f0; color: #1e293b; }
.remise-toggle.pct   { background: #fef3c7; color: #b45309; border-color: #fcd34d; }
/* Ligne remise calculée */
.remise-calculee {
    font-size: .78rem;
    color: #ea580c;
    font-weight: 600;
    text-align: right;
}
.paiement-label { 
    font-size: .8rem; 
    font-weight: 600; 
    color: var(--gray-600); 
    margin-bottom: 6px;
}
.paiement-input {
    border-radius: var(--radius-sm);
    border: 1px solid var(--gray-300);
    padding: 10px 12px;
    font-size: .9rem;
    width: 100%;
    color: var(--gray-800);
    background: white;
}
.paiement-input:focus { 
    outline: none; 
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}
.monnaie-box {
    background: var(--success);
    color: white;
    border-radius: var(--radius-sm);
    padding: 10px 12px;
    font-size: .9rem;
    font-weight: 700;
    text-align: center;
    margin-top: 0.75rem;
}
.btn-valider {
    background: var(--success);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    padding: 12px;
    font-weight: 700;
    font-size: .95rem;
    width: 100%;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: 0.75rem;
}
.btn-valider:hover { 
    background: #047857;
    transform: translateY(-1px);
}
.btn-valider:disabled { 
    background: var(--gray-400);
    cursor: not-allowed;
    transform: none;
}


/* ─── États panier vide simplifiés ─────────────────────────────── */
.panier-vide {
    display: flex; 
    flex-direction: column;
    align-items: center; 
    justify-content: center;
    height: 100%; 
    color: var(--gray-400);
    text-align: center; 
    padding: 1.5rem;
}
.panier-vide i { 
    font-size: 2.5rem; 
    margin-bottom: 0.75rem; 
    opacity: 0.5;
}
.panier-vide p { 
    font-size: .85rem;
    font-weight: 500;
}

/* ─── Responsive design simplifié ─────────────────────────────── */
@media (max-width: 768px) {
    .caisse-layout { 
        grid-template-columns: 1fr; 
        height: auto;
        padding: 0.5rem;
        gap: 0.75rem;
    }
    .produits-panel { 
        min-height: 400px;
        border-radius: var(--radius-md);
        padding: 1rem;
    }
    .panier-panel { 
        min-height: 300px;
        border-radius: var(--radius-md);
    }
    .produits-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 0.75rem;
    }
    .btn-scan span {
        display: none;
    }
}

</style>

<div class="caisse-layout">

    <!-- ── PANNEAU GAUCHE : Produits ──────────────────────── -->
    <div class="produits-panel">
        <!-- Barre Scan + Recherche -->
        <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
            <div class="search-caisse flex-grow-1">
                <i class="bi bi-search"></i>
                <input type="text" id="searchProd" class="form-control"
                       placeholder="Rechercher un produit par nom..."
                       autocomplete="off">
            </div>
            <div class="category-filter" style="min-width:200px;">
                <select id="categoryFilter" class="form-select">
                    <option value="">Toutes catégories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars(strtolower($cat['nom'])) ?>"><?= clean($cat['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-scan" id="btnScan" title="Scanner un code-barres">
                <i class="bi bi-upc-scan"></i>
                <span class="d-none d-sm-inline ms-1">Scanner</span>
            </button>
        </div>

        <!-- Zone scan code-barres (clavier physique) -->
        <div class="scan-status" id="scanStatus" style="display:none;">
            <i class="bi bi-upc-scan scan-icon"></i>
            <span id="scanMsg">En attente de scan...</span>
            <button class="btn btn-sm btn-outline-light ms-auto" onclick="stopScan()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Grille produits -->
        <div class="produits-grid" id="prodGrid">
            <?php foreach ($produits as $p):
                $stockFaible = $p['stock_actuel'] <= $p['seuil_alerte'] && $p['stock_actuel'] > 0;
            ?>
            <?php
                $style = getCategorieStyle($p['categorie_nom'] ?? '');
                $bgColor   = $style[0];
                $textColor = $style[1];
            ?>
            <div class="prod-card <?= $p['stock_actuel'] <= 0 ? 'zero-stock' : '' ?>"
                    onclick='if(typeof ajouterAuPanier === "function") { this.classList.add("added"); setTimeout(() => this.classList.remove("added"), 500); ajouterAuPanier(<?= $p['id'] ?>, <?= json_encode(clean($p['nom'])) ?>, <?= $p['prix_vente'] ?>, <?= $p['stock_actuel'] ?>, <?= $p['prix_gros'] ?? 'null' ?>, <?= (int)($p['qte_min_gros'] ?? 0) ?>) }'
                    data-nom="<?= strtolower(clean($p['nom'])) ?>"
                    data-cat="<?= htmlspecialchars(strtolower($p['categorie_nom'] ?? '')) ?>"
                    style="border-color:<?= $textColor ?>22;">

                <div class="card-head">
                    <?php $imgUrl = getProduitImageUrl($p); ?>
                    <?php if ($imgUrl): ?>
                    <div class="prod-icon-wrap" style="background:<?= $bgColor ?>; overflow:hidden; padding:0;">
                        <img src="<?= $imgUrl ?>"
                            alt="<?= clean($p['nom']) ?>"
                            style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
                    </div>
                    <?php else: ?>
                    <span class="prod-icon-wrap" style="background:<?= $bgColor ?>; color:<?= $textColor ?>;">
                        <?= getProduitEmoji($p['nom']) ?>
                    </span>
                    <?php endif; ?>

                    <div class="card-head-text">
                        <div class="prod-nom"><?= clean($p['nom']) ?></div>
                        <?php if (!empty($p['categorie_nom'])): ?>
                        <div class="prod-cat"><?= clean($p['categorie_nom']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="prod-footer">
                    <div>
                        <div class="prod-prix" style="color:<?= $textColor ?>;">
                            <?= number_format($p['prix_vente'], 0, ',', ' ') ?> F
                        </div>
                        <?php if (!empty($p['prix_gros'])): ?>
                        <div class="prod-gros">
                            Gros dès <?= (int)$p['qte_min_gros'] ?> : <?= number_format($p['prix_gros'], 0, ',', ' ') ?> F
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="stock-badge <?= $stockFaible ? 'low' : '' ?>">
                        <?= $p['stock_actuel'] ?> en stock
                    </span>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($produits)): ?>
            <div class="col-span-full text-center text-muted py-5">
                <i class="bi bi-box-seam fs-2 d-block mb-2 opacity-25"></i>
                Aucun produit disponible
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── PANNEAU DROIT : Panier ─────────────────────────── -->
    <div class="panier-panel">

        <!-- Header panier -->
        <div class="panier-header">
            <div class="title">
                <i class="bi bi-cart3" style="color:var(--bleu);"></i>
                Panier
                <span class="badge rounded-pill" style="background:#EBF3FB;color:var(--bleu);font-size:.72rem;" id="nbArticles">0</span>
            </div>
            <button class="btn btn-sm btn-light" onclick="if(typeof viderPanier === 'function') viderPanier()" style="font-size:.78rem;">
                <i class="bi bi-trash3 me-1"></i>Vider
            </button>
        </div>

        <!-- Sélecteur client -->
        <div style="background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;padding:.65rem .9rem;margin-bottom:.6rem;position:relative;">
            <div style="font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;">
                <i class="bi bi-person-circle me-1"></i>Client (optionnel)
            </div>
            <div id="clientNonSelectionne">
                <div style="position:relative;">
                    <i class="bi bi-search" style="position:absolute;left:.55rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.8rem;"></i>
                    <input type="text" id="clientSearch" placeholder="Nom ou téléphone…"
                           autocomplete="off" oninput="rechercherClient(this.value)"
                           style="width:100%;border:1px solid #e2e8f0;border-radius:7px;padding:.38rem .7rem .38rem 1.8rem;font-size:.82rem;outline:none;">
                </div>
                <div id="clientDropdown" style="display:none;position:absolute;left:0;right:0;background:#fff;border:1px solid #e2e8f0;border-radius:9px;box-shadow:0 8px 20px rgba(0,0,0,.08);z-index:100;max-height:180px;overflow-y:auto;margin-top:2px;"></div>
            </div>
            <div id="clientSelectionne" style="display:none;">
                <div style="display:flex;align-items:center;gap:.5rem;background:#f0fdf4;border:1.5px solid #86efac;border-radius:7px;padding:.35rem .65rem;font-size:.82rem;color:#15803d;font-weight:600;cursor:pointer;" onclick="effacerClient()">
                    <i class="bi bi-person-check-fill"></i>
                    <span id="clientNomAffiche"></span>
                    <i class="bi bi-x-circle-fill" style="margin-left:auto;color:#dc2626;"></i>
                </div>
                <div id="clientDetteAffiche" style="display:none;font-size:.73rem;color:#dc2626;margin-top:.2rem;"></div>
                <div id="clientPointsAffiche" style="display:none;font-size:.73rem;color:#7c3aed;margin-top:.1rem;"></div>
            </div>
        </div>

        <!-- Items -->
        <div class="panier-items" id="panierItems">
            <div class="panier-vide" id="panierVide">
                <i class="bi bi-cart-x"></i>
                <p>Cliquez sur un produit<br>pour l'ajouter au panier</p>
            </div>
        </div>

        <!-- Footer : totaux + paiement -->
        <div class="panier-footer">
            <!-- Remise -->
            <div class="total-line">
                <span><i class="bi bi-percent me-1"></i>Remise</span>
                <div style="display:flex;align-items:center;">
                    <input type="number" id="remise" class="remise-input"
                           min="0" value="0" step="any" oninput="calculerTotal()">
                    <button type="button" id="remiseToggle" class="remise-toggle"
                            onclick="toggleRemise()" title="Basculer FCFA / Pourcentage">F</button>
                </div>
            </div>
            <!-- Montant remise calculé (visible en mode %) -->
            <div class="total-line" id="ligneMontantRemise" style="display:none;">
                <span style="color:#ea580c;font-size:.78rem;">− Remise calculée</span>
                <span class="remise-calculee" id="montantRemiseCalc">0 F</span>
            </div>
            <!-- Sous-total -->
            <div class="total-line">
                <span>Sous-total</span>
                <span id="sousTotal">0 F</span>
            </div>
            <!-- Total -->
            <div class="total-line main">
                <span>Total</span>
                <span id="totalNet">0 F</span>
            </div>


            <!-- Montant reçu -->
            <div class="mt-3">
                <div class="paiement-label">Montant reçu (F)</div>
                <input type="number" id="montantRecu" class="paiement-input"
                       min="0" value="" placeholder="0" oninput="if(typeof calculerMonnaie === 'function') calculerMonnaie()">
            </div>

            <!-- Remise fidélité -->
            <div id="fideliteRow" style="display:none;justify-content:space-between;align-items:center;font-size:.8rem;color:#7c3aed;margin-top:.3rem;padding:.3rem .5rem;background:#faf5ff;border-radius:6px;">
                <span><i class="bi bi-star-fill me-1"></i>Remise fidélité</span>
                <span id="fideliteMontant">0 F</span>
            </div>
            <!-- Crédit client -->
            <div id="creditRow" style="display:none;justify-content:space-between;align-items:center;font-size:.8rem;color:#dc2626;margin-top:.3rem;padding:.3rem .5rem;background:#fef2f2;border-radius:6px;">
                <span><i class="bi bi-credit-card me-1"></i>À crédit</span>
                <input type="number" id="montantCredit" min="0" step="1" value="0" oninput="calculerMonnaie()"
                       style="width:90px;text-align:right;border:1px solid #fca5a5;border-radius:5px;padding:.2rem .4rem;font-size:.8rem;color:#dc2626;">
            </div>
            <!-- Monnaie -->
            <div class="mt-2 monnaie-box" id="monnaieBox" style="display:none;">
                Monnaie : <span id="monnaieRendue">0 F</span>
            </div>

            <!-- Note / commentaire -->
            <div class="mt-3">
                <div class="paiement-label">
                    <i class="bi bi-chat-left-text me-1" style="color:#64748b;"></i>
                    Note (optionnel)
                </div>
                <textarea id="noteVente" rows="2"
                    placeholder="Client fidèle, livraison, remarque..."
                    style="width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:6px 10px;
                           font-size:.8rem;color:#374151;resize:none;line-height:1.4;
                           background:#fafafa;transition:border-color .2s;"
                    onfocus="this.style.borderColor='var(--bleu)';this.style.background='#fff'"
                    onblur="this.style.borderColor='#e2e8f0';this.style.background='#fafafa'"
                    maxlength="500"></textarea>
            </div>

            <!-- Bouton valider -->
            <button class="btn-valider" id="btnValider" onclick="if(typeof validerVente === 'function') validerVente()">
                <i class="bi bi-check-circle-fill me-2"></i>Valider la vente
            </button>
        </div>
    </div>
</div>

<!-- Modal : Ticket de caisse -->
<div class="modal fade" id="modalTicket" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background:#16a34a; color:#fff;">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Vente validée !</h5>
            </div>
            <div class="modal-body" id="ticketContent" style="font-family:monospace; font-size:.82rem;"></div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary btn-sm" onclick="if(typeof imprimerTicket === 'function') imprimerTicket()">
                    <i class="bi bi-printer me-1"></i>Imprimer
                </button>
                <a id="btnTicketPdf" href="#" target="_blank" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i>PDF
                </a>
                <button class="btn btn-success fw-semibold" onclick="if(typeof nouvelleVente === 'function') nouvelleVente()">
                    <i class="bi bi-plus-circle me-1"></i>Nouvelle vente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let panier = [];
let clientActuel = null;
const fmt = (n) => Math.round(n).toLocaleString('fr-FR') + ' F';

function ajouterAuPanier(id, nom, prix, stock, prixGros = null, qteMinGros = 0) {
    const idx = panier.findIndex(i => i.produit_id === id);
    if (idx >= 0) {
        if (panier[idx].quantite >= stock) { alert('Stock insuffisant !'); return; }
        panier[idx].quantite++;
    } else {
        panier.push({
            produit_id: id, nom,
            prix_vente:   parseFloat(prix),
            prix_gros:    prixGros ? parseFloat(prixGros) : null,
            qte_min_gros: parseInt(qteMinGros) || 0,
            prix_unitaire: parseFloat(prix),
            quantite: 1, stock_max: stock, est_gros: false
        });
    }
    recalculerPrixGros();
    renderPanier();
}

function recalculerPrixGros() {
    panier.forEach(item => {
        const estGros = item.prix_gros && item.qte_min_gros > 0 && item.quantite >= item.qte_min_gros;
        item.prix_unitaire = estGros ? item.prix_gros : item.prix_vente;
        item.est_gros = estGros;
    });
}

// ── Fonctions client ─────────────────────────────────────────
let _timerClient = null;
function rechercherClient(q) {
    const dd = document.getElementById('clientDropdown');
    if (q.length < 1) { dd.style.display = 'none'; return; }
    clearTimeout(_timerClient);
    _timerClient = setTimeout(async () => {
        try {
            const resp = await fetch(`?action=search_client&q=${encodeURIComponent(q)}`);
            const data = await resp.json();
            dd.innerHTML = '';
            if (!Array.isArray(data) || data.length === 0) {
                dd.innerHTML = '<div style="padding:.5rem .85rem;color:#94a3b8;font-size:.82rem;">Aucun client trouvé</div>';
            } else {
                data.forEach(c => {
                    const div = document.createElement('div');
                    div.style.cssText = 'padding:.45rem .85rem;cursor:pointer;font-size:.82rem;border-bottom:1px solid #f8fafc;display:flex;justify-content:space-between;align-items:center;';
                    div.innerHTML = `<span style="font-weight:600">${c.nom}</span><span style="color:#94a3b8;font-size:.76rem;">${c.telephone||''}</span>`;
                    div.onmouseenter = () => div.style.background = '#f1f5f9';
                    div.onmouseleave = () => div.style.background = '';
                    div.onclick = () => selectionnerClient(c);
                    dd.appendChild(div);
                });
            }
            dd.style.display = 'block';
        } catch (err) {
            dd.innerHTML = '<div style="padding:.5rem .85rem;color:#dc2626;font-size:.82rem;">Erreur de recherche client</div>';
            dd.style.display = 'block';
        }
    }, 280);
}

function selectionnerClient(c) {
    clientActuel = c;
    document.getElementById('clientDropdown').style.display = 'none';
    document.getElementById('clientSearch').value = '';
    document.getElementById('clientNonSelectionne').style.display = 'none';
    document.getElementById('clientSelectionne').style.display = 'block';
    document.getElementById('clientNomAffiche').textContent = c.nom;

    const detteDiv = document.getElementById('clientDetteAffiche');
    detteDiv.style.display = c.solde_credit > 0 ? 'block' : 'none';
    if (c.solde_credit > 0) detteDiv.textContent = '⚠ Dette : ' + fmt(c.solde_credit);

    const ptsDiv = document.getElementById('clientPointsAffiche');
    ptsDiv.style.display = c.points_fidelite > 0 ? 'block' : 'none';
    if (c.points_fidelite > 0) ptsDiv.textContent = '★ ' + c.points_fidelite + ' pts = ' + fmt(c.points_fidelite * 10);

    // Fidélité
    const fideliteRow = document.getElementById('fideliteRow');
    if (c.points_fidelite > 0) {
        document.getElementById('fideliteMontant').textContent = fmt(c.points_fidelite * 10);
        fideliteRow.style.display = 'flex';
    } else {
        fideliteRow.style.display = 'none';
    }

    // Crédit : charger credit_autorise via API
    fetch(`<?= APP_URL ?>/api/client.php?id=${c.id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const creditAut = parseFloat(data.client.credit_autorise || 0);
                clientActuel.credit_autorise = creditAut;
                const creditRow = document.getElementById('creditRow');
                if (creditAut > 0) {
                    const marge = creditAut - parseFloat(data.client.solde_credit || 0);
                    document.getElementById('montantCredit').max = Math.max(0, marge);
                    creditRow.style.display = 'flex';
                } else {
                    creditRow.style.display = 'none';
                }
            }
        }).catch(() => document.getElementById('creditRow').style.display = 'none');

    calculerTotal();
}

function effacerClient() {
    clientActuel = null;
    document.getElementById('clientNonSelectionne').style.display = 'block';
    document.getElementById('clientSelectionne').style.display = 'none';
    document.getElementById('fideliteRow').style.display = 'none';
    document.getElementById('creditRow').style.display = 'none';
    document.getElementById('montantCredit').value = 0;
    calculerTotal();
}

document.addEventListener('click', e => {
    if (!e.target.closest('#clientNonSelectionne')) {
        document.getElementById('clientDropdown').style.display = 'none';
    }
});

function renderPanier() {
    const container = document.getElementById('panierItems');
    const vide      = document.getElementById('panierVide');

    if (panier.length === 0) {
        container.innerHTML = '';
        container.appendChild(vide);
        vide.style.display = 'flex';
        document.getElementById('nbArticles').textContent = '0';
        calculerTotal();
        return;
    }

    vide.style.display = 'none';

    // ── Mise à jour INTELLIGENTE sans innerHTML complet ──────
    // On met à jour item par item pour ne pas perdre le focus
    const existants = container.querySelectorAll('.panier-item');

    // Supprimer les items en trop
    for (let k = existants.length - 1; k >= panier.length; k--) {
        existants[k].remove();
    }

    panier.forEach((item, i) => {
        let el = container.querySelector(`.panier-item[data-idx="${i}"]`);

        if (!el) {
            // Créer le nouvel élément
            el = document.createElement('div');
            el.className = 'panier-item';
            el.dataset.idx = i;
            const badgeGros = item.est_gros
                ? `<span style="font-size:.6rem;background:#7c3aed;color:#fff;border-radius:20px;padding:1px 5px;margin-left:.25rem;vertical-align:middle;">GROS</span>`
                : '';
            el.innerHTML = `
                <div class="item-nom">${item.nom}${badgeGros}</div>
                <div class="item-ctrl">
                    <button class="btn-moins" data-idx="${i}">−</button>
                    <span class="qte">${item.quantite}</span>
                    <button class="btn-plus" data-idx="${i}">+</button>
                </div>
                <div class="item-prix">${fmt(item.quantite * item.prix_unitaire)}</div>
                <button class="btn-del" data-idx="${i}">
                    <i class="bi bi-x-lg" style="font-size:.75rem;"></i>
                </button>`;
            container.appendChild(el);
        } else {
            // Mettre à jour seulement les valeurs qui changent
            const badgeGros = item.est_gros
                ? `<span style="font-size:.6rem;background:#7c3aed;color:#fff;border-radius:20px;padding:1px 5px;margin-left:.25rem;vertical-align:middle;">GROS</span>`
                : '';
            el.querySelector('.item-nom').innerHTML     = `${item.nom}${badgeGros}`;
            el.querySelector('.qte').textContent       = item.quantite;
            el.querySelector('.item-prix').textContent = fmt(item.quantite * item.prix_unitaire);
            el.querySelector('.btn-moins').dataset.idx = i;
            el.querySelector('.btn-plus').dataset.idx  = i;
            el.querySelector('.btn-del').dataset.idx   = i;
        }
    });

    document.getElementById('nbArticles').textContent =
        panier.reduce((s, i) => s + i.quantite, 0);
    calculerTotal();
}

// ── Délégation d'événements sur le container panier ──────────
// Une seule fois, écoute tous les clics dans #panierItems
document.getElementById('panierItems').addEventListener('click', function(e) {
    const btnMoins = e.target.closest('.btn-moins');
    const btnPlus  = e.target.closest('.btn-plus');
    const btnDel   = e.target.closest('.btn-del');

    if (btnMoins) {
        const idx = parseInt(btnMoins.dataset.idx);
        panier[idx].quantite--;
        if (panier[idx].quantite <= 0) panier.splice(idx, 1);
        recalculerPrixGros();
        renderPanier();
    }
    if (btnPlus) {
        const idx = parseInt(btnPlus.dataset.idx);
        if (panier[idx].quantite < panier[idx].stock_max) {
            panier[idx].quantite++;
            recalculerPrixGros();
            renderPanier();
        } else {
            alert('Stock insuffisant !');
        }
    }
    if (btnDel) {
        const idx = parseInt(btnDel.dataset.idx);
        panier.splice(idx, 1);
        renderPanier();
    }
});

function changerQte(idx, delta) {
    panier[idx].quantite += delta;
    if (panier[idx].quantite <= 0) panier.splice(idx, 1);
    else if (panier[idx].quantite > panier[idx].stock_max) panier[idx].quantite = panier[idx].stock_max;
    renderPanier();
}

function supprimerItem(idx) { panier.splice(idx, 1); renderPanier(); }
function viderPanier()       { panier = []; renderPanier(); }

// ── Remise : mode F ou % ─────────────────────────────────────
let remiseEnPct = false; // false = FCFA, true = %

function toggleRemise() {
    remiseEnPct = !remiseEnPct;
    const btn   = document.getElementById('remiseToggle');
    const input = document.getElementById('remise');
    if (remiseEnPct) {
        btn.textContent = '%';
        btn.classList.add('pct');
        input.max  = 100;
        input.step = '0.1';
        input.placeholder = '0';
    } else {
        btn.textContent = 'F';
        btn.classList.remove('pct');
        input.removeAttribute('max');
        input.step = 'any';
        input.placeholder = '0';
    }
    input.value = 0;
    document.getElementById('ligneMontantRemise').style.display = 'none';
    calculerTotal();
}

function getMontantRemise(sousTotal) {
    const val = parseFloat(document.getElementById('remise').value) || 0;
    if (remiseEnPct) {
        const pct = Math.min(100, Math.max(0, val));
        return Math.round(sousTotal * pct / 100);
    }
    return Math.max(0, val);
}

function calculerTotal() {
    const sousTotal      = panier.reduce((s, i) => s + i.quantite * i.prix_unitaire, 0);
    const montantRemise  = getMontantRemise(sousTotal);
    const remiseFidelite = (clientActuel && clientActuel.points_fidelite > 0)
        ? clientActuel.points_fidelite * 10 : 0;
    const total = Math.max(0, sousTotal - montantRemise - remiseFidelite);

    document.getElementById('sousTotal').textContent = fmt(sousTotal);
    document.getElementById('totalNet').textContent  = fmt(total);

    // Remise manuelle en mode %
    const ligneMontant = document.getElementById('ligneMontantRemise');
    if (remiseEnPct && montantRemise > 0) {
        ligneMontant.style.display = 'flex';
        document.getElementById('montantRemiseCalc').textContent = '− ' + fmt(montantRemise);
    } else {
        ligneMontant.style.display = 'none';
    }

    calculerMonnaie();
}

function calculerMonnaie() {
    const sousT          = panier.reduce((s, i) => s + i.quantite * i.prix_unitaire, 0);
    const montantRemise  = getMontantRemise(sousT);
    const remiseFidelite = (clientActuel && clientActuel.points_fidelite > 0)
        ? clientActuel.points_fidelite * 10 : 0;
    const total          = Math.max(0, sousT - montantRemise - remiseFidelite);
    const recu           = parseFloat(document.getElementById('montantRecu').value) || 0;
    const credit         = parseFloat(document.getElementById('montantCredit')?.value || 0);
    const monnaie        = (recu + credit) - total;
    const box            = document.getElementById('monnaieBox');

    if ((recu > 0 || credit > 0) && panier.length > 0 && total > 0) {
        box.style.display = 'block';
        document.getElementById('monnaieRendue').textContent = fmt(Math.max(0, monnaie));
        box.style.background  = monnaie >= 0 ? '#f0fdf4' : '#fef2f2';
        box.style.borderColor = monnaie >= 0 ? '#bbf7d0' : '#fecaca';
        box.style.color       = monnaie >= 0 ? '#16a34a' : '#dc2626';
    } else {
        box.style.display = 'none';
    }
}

function validerVente() {
    if (panier.length === 0) { alert('Le panier est vide !'); return; }

    const sousTotal      = panier.reduce((s, i) => s + i.quantite * i.prix_unitaire, 0);
    const montantRemise  = getMontantRemise(sousTotal);
    const remiseFidelite = (clientActuel && clientActuel.points_fidelite > 0)
        ? clientActuel.points_fidelite * 10 : 0;
    const total          = Math.max(0, sousTotal - montantRemise - remiseFidelite);
    const recu           = parseFloat(document.getElementById('montantRecu').value) || 0;
    const credit         = parseFloat(document.getElementById('montantCredit')?.value || 0);

    if ((recu + credit) < total) { alert('Montant reçu insuffisant !'); return; }

    const btn = document.getElementById('btnValider');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';

    const fd = new FormData();
    fd.append('panier',          JSON.stringify(panier));
    fd.append('remise',          montantRemise);
    fd.append('montant_recu',    recu);
    fd.append('note',            (document.getElementById('noteVente')?.value ?? '').trim());
    fd.append('client_id',       clientActuel?.id || 0);
    fd.append('montant_credit',  credit);
    fd.append('remise_fidelite', remiseFidelite);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                afficherTicket(data, montantRemise);
            } else {
                alert('Erreur : ' + data.message);
                btn.disabled = false;
                document.getElementById('btnValider').innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Valider la vente';
            }
        });
}

function afficherTicket(data, montantRemise) {
    const lignes = panier.map(i =>
        `<div style="display:flex;justify-content:space-between;">
            <span>${i.nom} x${i.quantite}</span>
            <span>${fmt(i.quantite * i.prix_unitaire)}</span>
        </div>`
    ).join('');

    const remiseFidelite = (clientActuel && clientActuel.points_fidelite > 0)
        ? clientActuel.points_fidelite * 10 : 0;
    const recu         = parseFloat(document.getElementById('montantRecu').value) || 0;
    const ligneMonnaie = `<div style="display:flex;justify-content:space-between;"><span>Monnaie</span><strong>${fmt(data.monnaie)}</strong></div>`;

    // Ligne remise sur le ticket (n'afficher que si remise > 0)
    let ligneRemise = '';
    if (montantRemise > 0) {
        const valRemise  = parseFloat(document.getElementById('remise').value) || 0;
        const labelRemise = remiseEnPct
            ? `Remise (${valRemise}%)`
            : 'Remise';
        ligneRemise = `<div style="display:flex;justify-content:space-between;color:#ea580c;">
            <span>${labelRemise}</span><span>− ${fmt(montantRemise)}</span>
        </div>`;
    }

    const ligneFidelite = remiseFidelite > 0
        ? `<div style="display:flex;justify-content:space-between;color:#7c3aed;">
                <span>Remise fidélité</span><span>− ${fmt(remiseFidelite)}</span>
           </div>`
        : '';

    document.getElementById('ticketContent').innerHTML = `
        <div style="text-align:center;border-bottom:1px dashed #ccc;padding-bottom:.6rem;margin-bottom:.6rem;">
            <strong style="font-size:1rem;"><?= APP_NAME ?></strong><br>
            <small><?= date('d/m/Y H:i') ?></small><br>
            <small>Ticket : ${data.reference}</small>
        </div>
        <div style="border-bottom:1px dashed #ccc;padding-bottom:.6rem;margin-bottom:.6rem;">${lignes}</div>
        ${ligneRemise}
        ${ligneFidelite}
        <div style="display:flex;justify-content:space-between;"><span>Total net</span><strong>${fmt(data.total_net)}</strong></div>
        <div style="display:flex;justify-content:space-between;"><span>Reçu</span><span>${fmt(recu)}</span></div>
        ${ligneMonnaie}
        <div style="text-align:center;margin-top:.8rem;border-top:1px dashed #ccc;padding-top:.5rem;font-size:.75rem;">
            ${data.note ? `<div style="text-align:left;margin-bottom:.4rem;padding:.4rem .6rem;background:#f8fafc;border-radius:4px;font-style:italic;color:#475569;">
                <small style="font-weight:700;font-style:normal;">Note :</small> ${data.note}
            </div>` : ''}
            Merci pour votre achat !
        </div>`;
    new bootstrap.Modal(document.getElementById('modalTicket')).show();
    // Mettre à jour le lien PDF
    const btnPdf = document.getElementById('btnTicketPdf');
    if (btnPdf) btnPdf.href = '<?= APP_URL ?>/modules/pdf/ticket.php?id=' + data.vente_id;
}

function imprimerTicket() {
    const contenu = document.getElementById('ticketContent').innerHTML;
    const w = window.open('', '', 'width=400,height=600');
    w.document.write('<html><body style="font-family:monospace;font-size:12px;padding:10px;">' + contenu + '</body></html>');
    w.print(); w.close();
}

function nouvelleVente() {
    bootstrap.Modal.getInstance(document.getElementById('modalTicket')).hide();
    viderPanier();
    document.getElementById('remise').value      = 0;
    document.getElementById('montantRecu').value = '';
    document.getElementById('monnaieBox').style.display         = 'none';
    document.getElementById('ligneMontantRemise').style.display = 'none';
    const noteEl = document.getElementById('noteVente');
    if (noteEl) noteEl.value = '';
    // Réinitialiser le mode remise à FCFA
    remiseEnPct = false;
    const btn = document.getElementById('remiseToggle');
    if (btn) { btn.textContent = 'F'; btn.classList.remove('pct'); }
    document.getElementById('btnValider').disabled  = false;
    document.getElementById('btnValider').innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Valider la vente';
    location.reload();
}



// ── Recherche produits par nom / catégorie ───────────────────────────────
function filterProduits() {
    const query = document.getElementById('searchProd').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    document.querySelectorAll('.prod-card').forEach(c => {
        const matchesNom = c.dataset.nom.includes(query);
        const matchesCat = category === '' || c.dataset.cat === category;
        c.style.display = matchesNom && matchesCat ? '' : 'none';
    });
}

document.getElementById('searchProd').addEventListener('input', filterProduits);
document.getElementById('categoryFilter').addEventListener('change', filterProduits);

// ── Système de scan code-barres ──────────────────────────────
let scanMode    = false;
let scanBuffer  = '';
let scanTimer   = null;
const SCAN_DELAY = 80; // ms entre caractères (scanner physique = très rapide)

function startScan() {
    scanMode = true;
    scanBuffer = '';
    document.getElementById('btnScan').classList.add('active');
    document.getElementById('btnScan').innerHTML = '<i class="bi bi-upc-scan"></i><span class="d-none d-sm-inline ms-1">En écoute...</span>';
    document.getElementById('scanStatus').style.display = 'flex';
    document.getElementById('scanMsg').textContent = 'Scannez un code-barres ou tapez sur clavier...';
    // Focus sur le document pour capter les touches
    document.body.focus();
}

function stopScan() {
    scanMode = false;
    scanBuffer = '';
    document.getElementById('btnScan').classList.remove('active');
    document.getElementById('btnScan').innerHTML = '<i class="bi bi-upc-scan"></i><span class="d-none d-sm-inline ms-1">Scanner</span>';
    document.getElementById('scanStatus').style.display = 'none';
}

document.getElementById('btnScan').addEventListener('click', function() {
    scanMode ? stopScan() : startScan();
});

// Écoute globale clavier pour le scanner physique
document.addEventListener('keydown', function(e) {
    if (!scanMode) return;
    // Ignorer les touches de contrôle
    if (e.key === 'Shift' || e.key === 'Alt' || e.key === 'Control' || e.key === 'Meta') return;

    if (e.key === 'Enter') {
        // Le scanner envoie Enter à la fin
        if (scanBuffer.length >= 3) {
            rechercherParCode(scanBuffer.trim());
        }
        scanBuffer = '';
        clearTimeout(scanTimer);
        return;
    }

    // Accumule les caractères
    if (e.key.length === 1) {
        scanBuffer += e.key;
        clearTimeout(scanTimer);
        // Si pas de nouveau caractère après SCAN_DELAY → considérer comme saisi clavier (pas scanner)
        scanTimer = setTimeout(() => {
            // Saisie manuelle si trop lente → on laisse tomber le buffer partiel
            // (un vrai scanner envoie tout en < 50ms)
        }, 500);
    }
});

// ── Recherche produit par code-barres via AJAX ───────────────
function rechercherParCode(code) {
    document.getElementById('scanMsg').textContent = 'Code : ' + code + ' — Recherche...';

    fetch('<?= APP_URL ?>/modules/ventes/Scan.php?code=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                ajouterAuPanier(
                    data.produit.id,
                    data.produit.nom,
                    data.produit.prix_vente,
                    data.produit.stock_actuel,
                    data.produit.prix_gros,
                    data.produit.qte_min_gros
                );
                document.getElementById('scanMsg').textContent = 'En attente de scan...';
                afficherNotif(data.produit.nom + ' ajouté au panier', 'found');
            } else {
                afficherNotif('Introuvable : ' + code, 'notfound');
                document.getElementById('scanMsg').textContent = 'Produit non trouvé — réessayez';
            }
        })
        .catch(() => {
            afficherNotif('Erreur de connexion au serveur', 'notfound');
        });
}

// Notification flottante
function afficherNotif(msg, type) {
    // Supprimer ancienne notif si présente
    document.querySelectorAll('.scan-found,.scan-notfound').forEach(n => n.remove());
    const div = document.createElement('div');
    div.className = type === 'found' ? 'scan-found' : 'scan-notfound';
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 3000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>