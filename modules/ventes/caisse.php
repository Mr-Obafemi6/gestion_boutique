<?php
// ============================================================
//  modules/ventes/caisse.php — Point de vente
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Vente.php';
require_once __DIR__ . '/../../includes/Produit.php';
requireLogin();
if (!peutFaire('ventes','creer')) redirect(APP_URL . '/includes/acces_refuse.php');

$venteModel   = new Vente();
$produitModel = new Produit();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $panier = json_decode($_POST['panier'] ?? '[]', true);
    $remise = (float)($_POST['remise'] ?? 0);
    $recu   = (float)($_POST['montant_recu'] ?? 0);
    $note   = trim(strip_tags($_POST['note'] ?? ''));

    if (empty($panier)) { echo json_encode(['success'=>false,'message'=>'Le panier est vide.']); exit; }
    $total = array_sum(array_map(fn($l)=>$l['quantite']*$l['prix_unitaire'], $panier)) - $remise;
    if ($recu < $total) { echo json_encode(['success'=>false,'message'=>'Montant reçu insuffisant.']); exit; }

    $result = $venteModel->enregistrer($panier, $remise, $recu, $note);
    if (is_array($result)) {
        echo json_encode(['success'=>true,'vente_id'=>$result['vente_id'],'reference'=>$result['reference'],
                          'total_net'=>$result['total_net'],'monnaie'=>$result['monnaie'],
                          'note'=>$result['note'] ?? '']);
    } else {
        echo json_encode(['success'=>false,'message'=>$result]);
    }
    exit;
}

$produits  = $produitModel->getPourVente();
$pageTitle = "Point de vente";

// Debug pour vérifier les produits chargés
error_log("Nombre de produits chargés: " . count($produits));

// ── Couleurs par catégorie ───────────────────────────────────

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
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
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
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}
.prod-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gray-300), var(--gray-400));
    transform: scaleX(0);
    transition: transform 0.3s;
}
.prod-card * { pointer-events: none; }
.prod-card:hover {
    box-shadow: var(--shadow-xl);
    transform: translateY(-6px) scale(1.02);
    border-color: var(--primary);
}
.prod-card:hover::before {
    transform: scaleX(1);
}
.prod-card.added {
    animation: product-added 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}
@keyframes product-added {
    0% { transform: scale(1); }
    30% { transform: scale(1.15) rotate(3deg); box-shadow: 0 0 30px rgba(5, 150, 105, 0.6); }
    60% { transform: scale(0.95) rotate(-2deg); }
    100% { transform: scale(1); }
}
.prod-card.zero-stock {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
    filter: grayscale(0.8);
}
.prod-card .stock-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--gray-100);
    color: var(--gray-600);
    font-size: .75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    margin-bottom: 0.75rem;
    border: 1px solid var(--gray-200);
}
.prod-card .stock-badge.low { 
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    color: var(--danger);
    border-color: #fecaca;
    animation: stock-warning 2s infinite;
}
@keyframes stock-warning {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
.prod-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    flex-shrink: 0;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s;
}
.prod-card:hover .prod-icon-wrap {
    transform: scale(1.1) rotate(-5deg);
    box-shadow: var(--shadow-md);
}
.prod-cat {
    display: inline-block;
    font-size: .7rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 12px;
    margin-top: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1px solid rgba(0,0,0,0.1);
}
.prod-card .prod-nom {
    font-size: .9rem;
    font-weight: 600;
    color: var(--gray-800);
    line-height: 1.4;
    margin-bottom: 0.5rem;
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
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
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
        <div class="d-flex gap-2 mb-3">
            <div class="search-caisse flex-grow-1">
                <i class="bi bi-search"></i>
                <input type="text" id="searchProd" class="form-control"
                       placeholder="Rechercher un produit par nom..."
                       autocomplete="off">
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
                 onclick="if(typeof ajouterAuPanier === 'function') { this.classList.add('added'); setTimeout(() => this.classList.remove('added'), 500); ajouterAuPanier(<?= $p['id'] ?>, '<?= clean(addslashes($p['nom'])) ?>', <?= $p['prix_vente'] ?>, <?= $p['stock_actuel'] ?>) }"
                 data-nom="<?= strtolower(clean($p['nom'])) ?>"
                 style="border-color:<?= $textColor ?>22;">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <?php if (!empty($p['image'])): ?>
                    <div class="prod-icon-wrap" style="background:<?= $bgColor ?>; overflow:hidden; padding:0;">
                        <img src="<?= APP_URL ?>/uploads/produits/<?= htmlspecialchars($p['image']) ?>"
                             alt="<?= clean($p['nom']) ?>"
                             style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
                    </div>
                    <?php else: ?>
                    <span class="prod-icon-wrap" style="background:<?= $bgColor ?>; color:<?= $textColor ?>;">
                        <?= getProduitEmoji($p['nom']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="stock-badge <?= $stockFaible ? 'low' : '' ?>">
                        <?= $p['stock_actuel'] ?> en stock
                    </span>
                </div>

                <div class="prod-nom"><?= clean($p['nom']) ?></div>
                <div class="prod-prix" style="color:<?= $textColor ?>;">
                    <?= number_format($p['prix_vente'], 0, ',', ' ') ?> F
                </div>

                <?php if (!empty($p['categorie_nom'])): ?>
                <div class="prod-cat" style="color:<?= $textColor ?>; background:<?= $bgColor ?>;">
                    <?= clean($p['categorie_nom']) ?>
                </div>
                <?php endif; ?>
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
const fmt = (n) => Math.round(n).toLocaleString('fr-FR') + ' F';
function ajouterAuPanier(id, nom, prix, stock) {
    const idx = panier.findIndex(i => i.produit_id === id);
    if (idx >= 0) {
        if (panier[idx].quantite >= stock) { alert('Stock insuffisant !'); return; }
        panier[idx].quantite++;
    } else {
        panier.push({ produit_id: id, nom, prix_unitaire: prix, quantite: 1, stock_max: stock });
    }
    renderPanier();
}

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
            el.innerHTML = `
                <div class="item-nom">${item.nom}</div>
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
        renderPanier();
    }
    if (btnPlus) {
        const idx = parseInt(btnPlus.dataset.idx);
        if (panier[idx].quantite < panier[idx].stock_max) {
            panier[idx].quantite++;
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
    const sousTotal     = panier.reduce((s, i) => s + i.quantite * i.prix_unitaire, 0);
    const montantRemise = getMontantRemise(sousTotal);
    const total         = Math.max(0, sousTotal - montantRemise);

    document.getElementById('sousTotal').textContent = fmt(sousTotal);
    document.getElementById('totalNet').textContent  = fmt(total);

    // Afficher le montant remise calculé uniquement en mode %
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
    const sousT         = panier.reduce((s, i) => s + i.quantite * i.prix_unitaire, 0);
    const montantRemise = getMontantRemise(sousT);
    const total         = Math.max(0, sousT - montantRemise);
    const recu          = parseFloat(document.getElementById('montantRecu').value) || 0;
    const monnaie       = recu - total;
    const box           = document.getElementById('monnaieBox');

    if (recu > 0 && panier.length > 0 && total > 0) {
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

    const sousTotal     = panier.reduce((s, i) => s + i.quantite * i.prix_unitaire, 0);
    const montantRemise = getMontantRemise(sousTotal);
    const total         = Math.max(0, sousTotal - montantRemise);
    const recu          = parseFloat(document.getElementById('montantRecu').value) || 0;

    if (recu < total) { alert('Montant reçu insuffisant !'); return; }

    const btn = document.getElementById('btnValider');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';

    const fd = new FormData();
    fd.append('panier',       JSON.stringify(panier));
    fd.append('remise',       montantRemise); // toujours envoyer le montant FCFA
    fd.append('montant_recu', recu);
    fd.append('note',         (document.getElementById('noteVente')?.value ?? '').trim());

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

    document.getElementById('ticketContent').innerHTML = `
        <div style="text-align:center;border-bottom:1px dashed #ccc;padding-bottom:.6rem;margin-bottom:.6rem;">
            <strong style="font-size:1rem;"><?= APP_NAME ?></strong><br>
            <small><?= date('d/m/Y H:i') ?></small><br>
            <small>Ticket : ${data.reference}</small>
        </div>
        <div style="border-bottom:1px dashed #ccc;padding-bottom:.6rem;margin-bottom:.6rem;">${lignes}</div>
        ${ligneRemise}
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



// ── Recherche produits par nom ───────────────────────────────
document.getElementById('searchProd').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.prod-card').forEach(c => {
        c.style.display = c.dataset.nom.includes(q) ? '' : 'none';
    });
});

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
                ajouterAuPanier(data.produit.id, data.produit.nom, data.produit.prix_vente, data.produit.stock_actuel);
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