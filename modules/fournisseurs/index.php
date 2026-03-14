    <?php
    // ============================================================
    //  modules/fournisseurs/index.php — Gestion des fournisseurs
    // ============================================================
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/Stock.php';

    requireLogin();
if (!peutFaire('fournisseurs','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

    $stockModel = new Stock();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'creer') {
        if (!peutFaire('fournisseurs','creer')) { setFlash('error','Action non autorisée.'); redirect(APP_URL.'/modules/fournisseurs/index.php'); }
            if (empty(trim($_POST['nom'] ?? ''))) {
                setFlash('error', 'Le nom du fournisseur est obligatoire.');
            } else {
                $stockModel->addFournisseur([
                    'nom'       => $_POST['nom']       ?? '',
                    'telephone' => $_POST['telephone'] ?? '',
                    'email'     => $_POST['email']     ?? '',
                    'adresse'   => $_POST['adresse']   ?? '',
                ]);
                setFlash('success', 'Fournisseur ajouté avec succès.');
            }
        }

        if ($action === 'modifier' && !empty($_POST['id'])) {
            $result = $stockModel->updateFournisseur((int)$_POST['id'], [
                'nom'       => $_POST['nom']       ?? '',
                'telephone' => $_POST['telephone'] ?? '',
                'email'     => $_POST['email']     ?? '',
                'adresse'   => $_POST['adresse']   ?? '',
            ]);
            setFlash($result === true ? 'success' : 'error',
                    $result === true ? 'Fournisseur modifié avec succès.' : $result);
        }

        if ($action === 'supprimer' && !empty($_POST['id'])) {
            $result = $stockModel->deleteFournisseur((int)$_POST['id']);
            setFlash($result === true ? 'success' : 'error',
                    $result === true ? 'Fournisseur supprimé.' : $result);
        }

        redirect(APP_URL . '/modules/fournisseurs/index.php?' . http_build_query(['q' => $_POST['q'] ?? '']));
    }

    $search       = clean($_GET['q'] ?? '');
    $fournisseurs = $stockModel->getFournisseurs();

    if ($search) {
        $fournisseurs = array_values(array_filter($fournisseurs, fn($f) =>
            stripos($f['nom'], $search) !== false ||
            stripos($f['telephone'] ?? '', $search) !== false ||
            stripos($f['email'] ?? '', $search) !== false
        ));
    }

    $pageTitle = "Fournisseurs";
    require_once __DIR__ . '/../../includes/header.php';
    ?>

    <style>
    .fournisseur-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem;
        transition: box-shadow .2s, transform .15s;
    }
    .fournisseur-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,.1);
        transform: translateY(-2px);
    }
    .fournisseur-icon {
        width: 42px; height: 42px;
        background: #EBF3FB;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .fournisseur-icon i { color: var(--bleu); font-size: 1.15rem; }
    .fournisseur-info { font-size: .875rem; color: #64748b; }
    .fournisseur-info i { color: var(--bleu); width: 15px; font-size: .8rem; }
    .btn-card-action {
        width: 30px; height: 30px;
        border-radius: 6px;
        border: none;
        background: transparent;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: background .15s;
        padding: 0;
    }
    .btn-card-action:hover { background: #f1f5f9; }
    .search-wrapper { position: relative; max-width: 340px; }
    .search-wrapper .bi-search {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: .9rem; pointer-events: none;
    }
    .search-wrapper input { padding-left: 2.2rem; border-radius: 8px; border: 1px solid #e2e8f0; }
    .search-wrapper input:focus { border-color: var(--bleu); box-shadow: 0 0 0 .2rem rgba(31,92,153,.15); }
    </style>

    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-0" style="color:#153F6E;">
                <i class="bi bi-truck me-2"></i>Fournisseurs
            </h2>
            <small class="text-muted"><?= count($fournisseurs) ?> fournisseur(s)</small>
        </div>
        <button class="btn btn-primary fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#modalCreer">
            <i class="bi bi-plus-lg me-2"></i>Nouveau fournisseur
        </button>
    </div>

    <!-- Barre de recherche -->
    <div class="mb-4">
        <form method="GET">
            <div class="search-wrapper">
                <i class="bi bi-search"></i>
                <input type="text" name="q" class="form-control"
                    placeholder="Rechercher un fournisseur..."
                    value="<?= $search ?>">
            </div>
        </form>
    </div>

    <!-- Grille de cartes -->
    <?php if (empty($fournisseurs)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-truck fs-1 d-block mb-3 opacity-25"></i>
            <p class="mb-0 fw-semibold">Aucun fournisseur trouvé</p>
            <?php if ($search): ?>
                <a href="?" class="btn btn-sm btn-outline-secondary mt-3">Effacer la recherche</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($fournisseurs as $f): ?>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="fournisseur-card h-100">

                <!-- Header carte : icône + nom + actions -->
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="fournisseur-icon">
                            <i class="bi bi-truck"></i>
                        </div>
                        <span class="fw-semibold text-dark" style="font-size:.95rem;">
                            <?= clean($f['nom']) ?>
                        </span>
                    </div>
                    <div class="d-flex gap-1 ms-2">
                        <!-- Modifier -->
                        <button class="btn-card-action" title="Modifier"
                                data-bs-toggle="modal" data-bs-target="#modalModifier"
                                data-id="<?= $f['id'] ?>"
                                data-nom="<?= clean($f['nom']) ?>"
                                data-telephone="<?= clean($f['telephone'] ?? '') ?>"
                                data-email="<?= clean($f['email'] ?? '') ?>"
                                data-adresse="<?= clean($f['adresse'] ?? '') ?>">
                            <i class="bi bi-pencil" style="color:#64748b; font-size:.85rem;"></i>
                        </button>
                        <!-- Supprimer -->
                        <button class="btn-card-action" title="Supprimer"
                                data-bs-toggle="modal" data-bs-target="#modalSupprimer"
                                data-id="<?= $f['id'] ?>"
                                data-nom="<?= clean($f['nom']) ?>">
                            <i class="bi bi-trash3" style="color:#ef4444; font-size:.85rem;"></i>
                        </button>
                    </div>
                </div>

                <!-- Infos contact -->
                <div class="fournisseur-info d-flex flex-column gap-2">
                    <?php if (!empty($f['telephone'])): ?>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-telephone-fill"></i>
                        <span><?= clean($f['telephone']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($f['email'])): ?>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-envelope-fill"></i>
                        <span><?= clean($f['email']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($f['adresse'])): ?>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span><?= clean($f['adresse']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (empty($f['telephone']) && empty($f['email']) && empty($f['adresse'])): ?>
                    <span class="text-muted" style="font-size:.8rem; font-style:italic;">
                        Aucune information de contact
                    </span>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>


    <!-- ══════════ MODALS ══════════ -->

    <!-- Modal : Créer -->
    <div class="modal fade" id="modalCreer" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content" novalidate>
                <div class="modal-header" style="background:var(--bleu); color:#fff;">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nouveau fournisseur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="creer">
                    <input type="hidden" name="q" value="<?= $search ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="nom" class="form-control" required placeholder="Nom du fournisseur">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Téléphone</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone-fill text-muted"></i></span>
                                <input type="text" name="telephone" class="form-control" placeholder="+228 XX XX XX XX">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope-fill text-muted"></i></span>
                                <input type="email" name="email" class="form-control" placeholder="email@exemple.com">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Adresse</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-geo-alt-fill text-muted"></i></span>
                                <input type="text" name="adresse" class="form-control" placeholder="Adresse du fournisseur">
                            </div>
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

    <!-- Modal : Modifier -->
    <div class="modal fade" id="modalModifier" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content" novalidate>
                <div class="modal-header" style="background:#153F6E; color:#fff;">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Modifier le fournisseur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="modifier">
                    <input type="hidden" name="q" value="<?= $search ?>">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="nom" id="edit_nom" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Téléphone</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone-fill text-muted"></i></span>
                                <input type="text" name="telephone" id="edit_telephone" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope-fill text-muted"></i></span>
                                <input type="email" name="email" id="edit_email" class="form-control">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Adresse</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-geo-alt-fill text-muted"></i></span>
                                <input type="text" name="adresse" id="edit_adresse" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-check2 me-2"></i>Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal : Supprimer -->
    <div class="modal fade" id="modalSupprimer" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <form method="POST" class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="q" value="<?= $search ?>">
                    <input type="hidden" name="id" id="del_id">
                    <i class="bi bi-trash3-fill text-danger mb-3 d-block" style="font-size:2.5rem;"></i>
                    <p class="mb-1 text-muted">Supprimer le fournisseur :</p>
                    <p class="fw-bold mb-1" id="del_nom"></p>
                    <small class="text-muted">Cette action est irréversible.</small>
                </div>
                <div class="modal-footer justify-content-center gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger fw-semibold">
                        <i class="bi bi-trash3 me-1"></i>Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('modalModifier').addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        document.getElementById('edit_id').value        = btn.dataset.id;
        document.getElementById('edit_nom').value       = btn.dataset.nom;
        document.getElementById('edit_telephone').value = btn.dataset.telephone;
        document.getElementById('edit_email').value     = btn.dataset.email;
        document.getElementById('edit_adresse').value   = btn.dataset.adresse;
    });
    document.getElementById('modalSupprimer').addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        document.getElementById('del_id').value        = btn.dataset.id;
        document.getElementById('del_nom').textContent = btn.dataset.nom;
    });

    // Recherche en temps réel (optionnel)
    document.querySelector('input[name="q"]').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') this.closest('form').submit();
    });
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>