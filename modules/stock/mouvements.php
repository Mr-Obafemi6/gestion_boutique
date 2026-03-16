<?php
// ============================================================
//  modules/stock/mouvements.php — Gestion des stocks
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Stock.php';
require_once __DIR__ . '/../../includes/Produit.php';
requireLogin();
if (!peutFaire('stock','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$stockModel   = new Stock();
$produitModel = new Produit();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCSRF();
    $action = $_POST['action'] ?? '';
    if ($action === 'entree') {
        if (!peutFaire('stock','creer')) { setFlash('error','Action non autorisée.'); }
        else { $result = $stockModel->entreeStock($_POST); setFlash($result === true ? 'success' : 'error', $result === true ? 'Entrée de stock enregistrée.' : $result); }
    }
    if ($action === 'sortie') {
        if (!peutFaire('stock','modifier')) { setFlash('error','Action non autorisée.'); }
        else { $result = $stockModel->sortieStock($_POST); setFlash($result === true ? 'success' : 'error', $result === true ? 'Sortie de stock enregistrée.' : $result); }
    }
    if ($action === 'add_fournisseur') {
        $stockModel->addFournisseur($_POST);
        setFlash('success', 'Fournisseur ajouté.');
    }
    redirect(APP_URL . '/modules/stock/mouvements.php');
}

$search      = clean($_GET['q'] ?? '');
$typeFilter  = clean($_GET['type'] ?? '');
$mouvements  = $stockModel->getMouvements($search, $typeFilter);
$critique    = $stockModel->getStockCritique();
$produits    = $produitModel->getAll();
$fournisseurs= $stockModel->getFournisseurs();

$pageTitle = "Gestion du stock";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-arrow-left-right me-2"></i> Mouvements de stock</h2>
        <small class="text-muted"><?= count($mouvements) ?> mouvement(s) affiché(s)</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalFournisseur"><i class="bi bi-truck me-1"></i>Fournisseur</button>
        <button class="btn btn-success fw-semibold" data-bs-toggle="modal" data-bs-target="#modalEntree"><i class="bi bi-box-arrow-in-down me-2"></i>Entrée stock</button>
        <button class="btn btn-warning fw-semibold text-dark" data-bs-toggle="modal" data-bs-target="#modalSortie"><i class="bi bi-box-arrow-up me-2"></i>Sortie stock</button>
    </div>
</div>

<!-- Alertes stock critique -->
<?php if (!empty($critique)): ?>
<div class="alert alert-danger d-flex align-items-start gap-3 mb-3" style="border-left:4px solid #dc2626 !important;">
    <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
    <div class="flex-grow-1">
        <strong><?= count($critique) ?> produit(s) en rupture ou stock critique</strong>
        <div class="mt-2 d-flex flex-wrap gap-1">
        <?php foreach ($critique as $sc): ?>
            <span class="badge badge-stock-critique">
                <i class="bi bi-box-seam me-1"></i><?= clean($sc['nom']) ?> — <?= $sc['stock_actuel'] ?> restant(s)
            </span>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtres -->
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-6"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" name="q" class="form-control" placeholder="Rechercher un produit..." value="<?= $search ?>"></div></div>
    <div class="col-md-4">
        <select name="type" class="form-select">
            <option value="">Tous les types</option>
            <option value="entree" <?= $typeFilter==='entree' ?'selected':'' ?>>Entrées</option>
            <option value="sortie" <?= $typeFilter==='sortie' ?'selected':'' ?>>Sorties</option>
            <option value="perte"  <?= $typeFilter==='perte'  ?'selected':'' ?>>Pertes</option>
            <option value="retour" <?= $typeFilter==='retour' ?'selected':'' ?>>Retours</option>
        </select>
    </div>
    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filtrer</button></div>
</form>

<!-- Tableau mouvements -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Date</th>
                    <th>Produit</th>
                    <th class="text-center">Type</th>
                    <th class="text-center">Quantité</th>
                    <th>Motif / Fournisseur</th>
                    <th>Opérateur</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($mouvements)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">Aucun mouvement enregistré.</td></tr>
            <?php else: ?>
                <?php foreach ($mouvements as $m): ?>
                <?php
                $typeConfig = match($m['type_mvt']) {
                    'entree' => ['success', 'bi-arrow-down-circle-fill', '+'],
                    'sortie' => ['warning',  'bi-arrow-up-circle-fill',   '-'],
                    'perte'  => ['danger',   'bi-x-circle-fill',          '-'],
                    'retour' => ['info',     'bi-arrow-repeat',            '+'],
                    default  => ['secondary','bi-circle',                  ''],
                };
                ?>
                <tr>
                    <td class="ps-3 text-muted" style="font-size:.85rem;"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                    <td>
                        <span class="fw-semibold"><?= clean($m['produit_nom']) ?></span><br>
                        <small class="text-muted"><code><?= clean($m['reference']) ?></code></small>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?= $typeConfig[0] ?>">
                            <i class="bi <?= $typeConfig[1] ?> me-1"></i><?= ucfirst($m['type_mvt']) ?>
                        </span>
                    </td>
                    <td class="text-center fw-bold text-<?= $typeConfig[0] ?>">
                        <?= $typeConfig[2] ?><?= $m['quantite'] ?>
                    </td>
                    <td>
                        <?= clean($m['motif'] ?: '—') ?>
                        <?php if ($m['fournisseur_nom']): ?>
                            <br><small class="text-muted"><i class="bi bi-truck me-1"></i><?= clean($m['fournisseur_nom']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= clean($m['user_nom'] . ' ' . $m['user_prenom']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal : Entrée stock -->
<div class="modal fade" id="modalEntree" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2"></i>Entrée de stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="entree">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Produit *</label>
                    <select name="produit_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($produits as $p): ?>
                            <option value="<?= $p['id'] ?>">[<?= clean($p['reference']) ?>] <?= clean($p['nom']) ?> (stock: <?= $p['stock_actuel'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Quantité *</label>
                        <input type="number" name="quantite" class="form-control" required min="1" value="1">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Prix unitaire (FCFA)</label>
                        <input type="number" name="prix_unitaire" class="form-control" min="0" step="1" value="0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Fournisseur</label>
                    <select name="fournisseur_id" class="form-select">
                        <option value="">-- Aucun --</option>
                        <?php foreach ($fournisseurs as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= clean($f['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Motif</label>
                    <input type="text" name="motif" class="form-control" value="Approvisionnement" placeholder="Ex: Livraison fournisseur">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-success fw-semibold"><i class="bi bi-check2 me-2"></i>Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : Sortie stock -->
<div class="modal fade" id="modalSortie" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-box-arrow-up me-2"></i>Sortie de stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="sortie">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Produit *</label>
                    <select name="produit_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($produits as $p): ?>
                            <option value="<?= $p['id'] ?>">[<?= clean($p['reference']) ?>] <?= clean($p['nom']) ?> (stock: <?= $p['stock_actuel'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Quantité *</label>
                        <input type="number" name="quantite" class="form-control" required min="1" value="1">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Type de sortie</label>
                        <select name="type_mvt" class="form-select">
                            <option value="sortie">Sortie normale</option>
                            <option value="perte">Perte / casse</option>
                            <option value="retour">Retour fournisseur</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Motif</label>
                    <input type="text" name="motif" class="form-control" placeholder="Ex: Produit périmé, casse...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-warning fw-semibold"><i class="bi bi-check2 me-2"></i>Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : Fournisseur -->
<div class="modal fade" id="modalFournisseur" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" class="modal-content">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="modal-header"><h5 class="modal-title">Nouveau fournisseur</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_fournisseur">
                <div class="mb-2"><label class="form-label fw-semibold">Nom *</label><input type="text" name="nom" class="form-control" required></div>
                <div class="mb-2"><label class="form-label fw-semibold">Téléphone</label><input type="text" name="telephone" class="form-control"></div>
                <div class="mb-2"><label class="form-label fw-semibold">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="mb-2"><label class="form-label fw-semibold">Adresse</label><textarea name="adresse" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>