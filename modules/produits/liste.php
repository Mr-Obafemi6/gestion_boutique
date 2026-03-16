<?php
// ============================================================
//  modules/produits/liste.php — Gestion des produits
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Produit.php';
requireLogin();
if (!peutFaire('produits','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$produitModel = new Produit();

// ── Traitements POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'creer') {
        if (!peutFaire('produits','creer')) { setFlash('error','Action non autorisée.'); }
        else { $result = $produitModel->create($_POST); setFlash($result === true ? 'success' : 'error', $result === true ? 'Produit ajouté avec succès.' : $result); }
    }
    if ($action === 'modifier') {
        if (!peutFaire('produits','modifier')) { setFlash('error','Action non autorisée.'); }
        else { $result = $produitModel->update((int)$_POST['id'], $_POST); setFlash($result === true ? 'success' : 'error', $result === true ? 'Produit mis à jour.' : $result); }
    }
    if ($action === 'supprimer') {
        if (!peutFaire('produits','supprimer')) { setFlash('error','Action non autorisée.'); }
        else { $produitModel->delete((int)$_POST['id']); setFlash('success', 'Produit supprimé / désactivé.'); }
    }
    if ($action === 'add_categorie') {
        if (!peutFaire('produits','creer')) { setFlash('error','Action non autorisée.'); }
        else { $produitModel->createCategorie($_POST['nom_cat'] ?? '', $_POST['desc_cat'] ?? ''); setFlash('success', 'Catégorie ajoutée.'); }
    }
    redirect(APP_URL . '/modules/produits/liste.php?' . http_build_query(['q'=>$_POST['q']??'','cat'=>$_POST['cat_filter']??$_POST['cat']??0,'page'=>1]));
}

$search    = clean($_GET['q']   ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);

// ── Pagination ────────────────────────────────────────────────
const PAR_PAGE  = 15;
$page           = max(1, (int)($_GET['page'] ?? 1));
$totalProduits  = $produitModel->getCount($search, $catFilter);
$totalPages     = max(1, (int)ceil($totalProduits / PAR_PAGE));
$page           = min($page, $totalPages); // borne supérieure
$offset         = ($page - 1) * PAR_PAGE;

// Pour l'export CSV on récupère tout (sans limite)
$produits  = $produitModel->getAll($search, $catFilter, PAR_PAGE, $offset);
$categories= $produitModel->getCategories();

// ── Export CSV ───────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!peutFaire('produits', 'exporter')) redirect(APP_URL . '/includes/acces_refuse.php');
    // Export sans limite de pagination
    $tousLesProduits = $produitModel->getAll($search, $catFilter);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="produits_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 (Excel)

    // En-têtes
    fputcsv($out, [
        'Référence', 'Nom', 'Catégorie', 'Code-barres',
        'Prix achat (FCFA)', 'Prix vente (FCFA)', 'Marge (FCFA)', 'Taux marge %',
        'Stock actuel', 'Unité', 'Seuil alerte', 'Statut',
    ], ';');

    // Lignes
    foreach ($tousLesProduits as $p) {
        $marge    = (float)$p['prix_vente'] - (float)$p['prix_achat'];
        $tauxMarge= $p['prix_vente'] > 0
            ? round($marge / $p['prix_vente'] * 100, 1)
            : 0;
        $statut   = $p['stock_actuel'] <= $p['seuil_alerte'] ? 'Stock critique' : 'OK';

        fputcsv($out, [
            $p['reference'],
            $p['nom'],
            $p['categorie_nom'],
            $p['code_barre'] ?? '',
            $p['prix_achat'],
            $p['prix_vente'],
            $marge,
            $tauxMarge . ' %',
            $p['stock_actuel'],
            $p['unite_mesure'],
            $p['seuil_alerte'],
            $statut,
        ], ';');
    }
    fclose($out);
    exit;
}

$pageTitle = "Gestion des produits";
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ── En-tête ──────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h2><i class="bi bi-box-seam-fill"></i> Produits</h2>
        <div class="page-meta">
            <span class="stat-chip"><i class="bi bi-grid-3x3-gap"></i> <?= $totalProduits ?> produit(s) trouvé(s)</span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if(peutFaire('produits','exporter')): ?>
        <a href="?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>&export=csv"
           class="btn btn-outline-success btn-sm fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
        </a>
        <a href="<?= APP_URL ?>/modules/pdf/produits.php?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>"
           target="_blank" class="btn btn-outline-danger btn-sm fw-semibold">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i>PDF
        </a>
        <?php endif; ?>
        <?php if(peutFaire('produits','creer')): ?>
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCategorie">
            <i class="bi bi-tag-fill me-1"></i>Catégorie
        </button>
        <?php endif; ?>
        <?php if(peutFaire('produits','creer')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreer">
            <i class="bi bi-plus-circle-fill me-1"></i>Nouveau produit
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ── Filtres ───────────────────────────────────────────────── -->
<form method="GET" class="row g-2 mb-3">
    <input type="hidden" name="page" value="1">
    <div class="col-md-6">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control" placeholder="Rechercher par nom ou référence..." value="<?= $search ?>">
        </div>
    </div>
    <div class="col-md-4">
        <select name="cat" class="form-select">
            <option value="0">Toutes les catégories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catFilter == $c['id'] ? 'selected' : '' ?>><?= clean($c['nom']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
    </div>
</form>

<!-- ── Tableau produits ──────────────────────────────────────── -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Référence</th>
                    <th>Produit</th>
                    <th>Catégorie</th>
                    <th class="text-end">Prix achat</th>
                    <th class="text-end">Prix vente</th>
                    <th class="text-center">Stock</th>
                    <th class="text-center">Statut</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($produits)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">Aucun produit trouvé.</td></tr>
            <?php else: ?>
                <?php foreach ($produits as $p): ?>
                <?php $critique = $p['stock_actuel'] <= $p['seuil_alerte']; ?>
                <tr class="<?= $critique ? 'table-danger' : '' ?>">
                    <td class="ps-3"><code><?= clean($p['reference']) ?></code></td>
                    <td>
                        <div class="fw-semibold"><?= clean($p['nom']) ?></div>
                        <?php if ($p['description']): ?>
                            <small class="text-muted"><?= clean(substr($p['description'], 0, 50)) ?>...</small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-secondary"><?= clean($p['categorie_nom']) ?></span></td>
                    <td class="text-end"><?= formatMontant($p['prix_achat']) ?></td>
                    <td class="text-end fw-semibold"><?= formatMontant($p['prix_vente']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $critique ? 'danger' : 'success' ?> rounded-pill">
                            <?= $p['stock_actuel'] ?> <?= clean($p['unite_mesure']) ?>
                        </span>
                    </td>
                    <td class="text-center text-muted"><?= $p['seuil_alerte'] ?></td>
                    <td class="text-center">
                        <!-- Modifier -->
                        <?php if(peutFaire('produits','modifier')): ?>
                        <button class="btn btn-sm btn-outline-primary" title="Modifier"
                            data-bs-toggle="modal" data-bs-target="#modalModifier"
                            data-id="<?= $p['id'] ?>"
                            data-ref="<?= clean($p['reference']) ?>"
                            data-nom="<?= clean($p['nom']) ?>"
                            data-desc="<?= clean($p['description']??'') ?>"
                            data-cat="<?= $p['categorie_id'] ?>"
                            data-unite="<?= clean($p['unite_mesure']) ?>"
                            data-pa="<?= $p['prix_achat'] ?>"
                            data-pv="<?= $p['prix_vente'] ?>"
                            data-seuil="<?= $p['seuil_alerte'] ?>">
                            <i class="bi bi-pencil-fill"></i>
                        </button>
                        <?php endif; ?>
                        <!-- Supprimer -->
                        <?php if(peutFaire('produits','supprimer')): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce produit ?');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="action" value="supprimer">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger ms-1" title="Supprimer">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Pagination ─────────────────────────────────────────────── -->
<?php if ($totalPages > 1): ?>
<div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">

    <!-- Info résultats -->
    <small class="text-muted">
        Affichage <?= ($offset + 1) ?> – <?= min($offset + PAR_PAGE, $totalProduits) ?>
        sur <?= $totalProduits ?> produits
        &nbsp;·&nbsp; Page <?= $page ?> / <?= $totalPages ?>
    </small>

    <!-- Navigation pages -->
    <nav>
        <ul class="pagination pagination-sm mb-0">

            <!-- Précédent -->
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>&page=<?= $page - 1 ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>

            <?php
            // Fenêtre de pages : toujours afficher 1, dernière, et pages autour de la courante
            $fenetre = 2; // pages de chaque côté
            $debut   = max(2, $page - $fenetre);
            $fin     = min($totalPages - 1, $page + $fenetre);
            ?>

            <!-- Page 1 toujours visible -->
            <li class="page-item <?= $page === 1 ? 'active' : '' ?>">
                <a class="page-link" href="?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>&page=1">1</a>
            </li>

            <!-- Ellipse gauche -->
            <?php if ($debut > 2): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>

            <!-- Pages intermédiaires -->
            <?php for ($i = $debut; $i <= $fin; $i++): ?>
                <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                    <a class="page-link" href="?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <!-- Ellipse droite -->
            <?php if ($fin < $totalPages - 1): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>

            <!-- Dernière page toujours visible -->
            <?php if ($totalPages > 1): ?>
            <li class="page-item <?= $page === $totalPages ? 'active' : '' ?>">
                <a class="page-link" href="?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a>
            </li>
            <?php endif; ?>

            <!-- Suivant -->
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?q=<?= urlencode($search) ?>&cat=<?= $catFilter ?>&page=<?= $page + 1 ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>

        </ul>
    </nav>

    <!-- Sélecteur de taille de page (info) -->
    <small class="text-muted"><?= PAR_PAGE ?> par page</small>

</div>
<?php endif; ?>

<!-- ══════════ MODALS ══════════ -->

<!-- Modal : Créer produit -->
<div class="modal fade" id="modalCreer" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="modal-header" style="background:var(--bleu);color:#fff;">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nouveau produit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="creer">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Référence <span class="text-muted fw-normal">(optionnel)</span></label>
                        <input type="text" name="reference" class="form-control" placeholder="Laisser vide pour générer automatiquement">
                        <small class="text-muted">Ex: PROD-001 — généré automatiquement si vide</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Code-barres <span class="text-muted fw-normal">(optionnel)</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-upc-scan text-muted"></i></span>
                            <input type="text" name="code_barre" class="form-control" placeholder="Ex: 6194003641205">
                        </div>
                        <small class="text-muted">Scannez ou saisissez le code-barres du produit</small>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Nom du produit *</label>
                        <input type="text" name="nom" class="form-control" required placeholder="Nom complet du produit">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Catégorie *</label>
                        <select name="categorie_id" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= clean($c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Unité de mesure</label>
                        <select name="unite_mesure" class="form-select">
                            <option value="pièce">Pièce</option>
                            <option value="kg">Kilogramme (kg)</option>
                            <option value="litre">Litre</option>
                            <option value="mètre">Mètre</option>
                            <option value="paquet">Paquet</option>
                            <option value="carton">Carton</option>
                            <option value="sachet">Sachet</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Prix d'achat (FCFA)</label>
                        <input type="number" name="prix_achat" class="form-control" value="0" min="0" step="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Prix de vente (FCFA) *</label>
                        <input type="number" name="prix_vente" class="form-control" required min="1" step="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Stock initial</label>
                        <input type="number" name="stock_initial" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Seuil alerte</label>
                        <input type="number" name="seuil_alerte" class="form-control" value="5" min="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Description optionnelle..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-check2 me-2"></i>Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : Modifier produit -->
<div class="modal fade" id="modalModifier" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="modal-header" style="background:#153F6E;color:#fff;">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Modifier le produit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="modifier">
                <input type="hidden" name="id" id="edit_id">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label fw-semibold">Référence *</label><input type="text" name="reference" id="edit_ref" class="form-control" required></div>
                    <div class="col-md-8"><label class="form-label fw-semibold">Nom *</label><input type="text" name="nom" id="edit_nom" class="form-control" required></div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Catégorie *</label>
                        <select name="categorie_id" id="edit_cat" class="form-select" required>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= clean($c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Unité</label>
                        <select name="unite_mesure" id="edit_unite" class="form-select">
                            <option value="pièce">Pièce</option><option value="kg">kg</option>
                            <option value="litre">Litre</option><option value="mètre">Mètre</option>
                            <option value="paquet">Paquet</option><option value="carton">Carton</option><option value="sachet">Sachet</option>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Prix achat</label><input type="number" name="prix_achat" id="edit_pa" class="form-control" min="0" step="1"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Prix vente *</label><input type="number" name="prix_vente" id="edit_pv" class="form-control" required min="1" step="1"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Seuil alerte</label><input type="number" name="seuil_alerte" id="edit_seuil" class="form-control" min="0"></div>
                    <div class="col-12"><label class="form-label fw-semibold">Description</label><textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-check2 me-2"></i>Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal : Ajouter catégorie -->
<div class="modal fade" id="modalCategorie" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" class="modal-content">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <div class="modal-header"><h5 class="modal-title">Nouvelle catégorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_categorie">
                <label class="form-label fw-semibold">Nom *</label>
                <input type="text" name="nom_cat" class="form-control mb-2" required placeholder="Ex: Boissons">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="desc_cat" class="form-control" rows="2"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<script>
// Pré-remplir le modal Modifier
document.getElementById('modalModifier').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_id').value    = btn.dataset.id;
    document.getElementById('edit_ref').value   = btn.dataset.ref;
    document.getElementById('edit_nom').value   = btn.dataset.nom;
    document.getElementById('edit_desc').value  = btn.dataset.desc;
    document.getElementById('edit_cat').value   = btn.dataset.cat;
    document.getElementById('edit_unite').value = btn.dataset.unite;
    document.getElementById('edit_pa').value    = btn.dataset.pa;
    document.getElementById('edit_pv').value    = btn.dataset.pv;
    document.getElementById('edit_seuil').value = btn.dataset.seuil;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>