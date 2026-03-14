<?php
// ============================================================
//  modules/auth/utilisateurs.php — Gestion des utilisateurs
//  Accès : administrateur uniquement
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/User.php';

requireLogin();
if (!peutFaire('utilisateurs','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$userModel = new User();
$erreur    = '';
$success   = '';

// ── Créer un utilisateur ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'creer') {
        $result = $userModel->create([
            'nom'      => $_POST['nom']      ?? '',
            'prenom'   => $_POST['prenom']   ?? '',
            'email'    => $_POST['email']    ?? '',
            'password' => $_POST['password'] ?? '',
            'role_id'  => $_POST['role_id']  ?? '',
        ]);
        if ($result === true) {
            setFlash('success', 'Utilisateur créé avec succès.');
        } else {
            setFlash('error', $result);
        }
        redirect(APP_URL . '/modules/auth/utilisateurs.php');
    }

    if ($_POST['action'] === 'toggle' && !empty($_POST['user_id'])) {
        $userModel->toggleStatut((int) $_POST['user_id']);
        setFlash('success', 'Statut mis à jour.');
        redirect(APP_URL . '/modules/auth/utilisateurs.php');
    }

    if ($_POST['action'] === 'reset_password' &&
        !empty($_POST['user_id']) && !empty($_POST['new_password'])) {
        $result = $userModel->resetPassword(
            (int) $_POST['user_id'],
            $_POST['new_password']
        );
        if ($result === true) {
            setFlash('success', 'Mot de passe réinitialisé.');
        } else {
            setFlash('error', $result);
        }
        redirect(APP_URL . '/modules/auth/utilisateurs.php');
    }
}

$utilisateurs = $userModel->getAll();
$roles        = $userModel->getRoles();

$pageTitle = "Gestion des utilisateurs";
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ── En-tête page ─────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0" style="color:#153F6E;">
            <i class="bi bi-people-fill me-2"></i>Utilisateurs
        </h2>
        <small class="text-muted"><?= count($utilisateurs) ?> compte(s) enregistré(s)</small>
    </div>
    <button class="btn btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalCreer">
        <i class="bi bi-person-plus-fill me-2"></i>Nouvel utilisateur
    </button>
</div>

<!-- ── Tableau des utilisateurs ──────────────────────────── -->
<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background:var(--bleu); color:#fff;">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($utilisateurs)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Aucun utilisateur trouvé.</td></tr>
                <?php else: ?>
                    <?php foreach ($utilisateurs as $i => $u): ?>
                    <tr>
                        <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($u['avatar'])): ?>
                                <img src="<?= APP_URL ?>/uploads/avatars/<?= $u['avatar'] ?>"
                                        style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--bleu);"
                                        alt="Photo">
                                <?php else: ?>
                                <div style="width:38px;height:38px;border-radius:50%;background:var(--bleu);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-weight:700;font-size:.8rem;flex-shrink:0;">
                                    <?= strtoupper(substr($u['nom'], 0, 1) . substr($u['prenom'], 0, 1)) ?>
                                </div>
                                <?php endif; ?>
                                <span class="fw-semibold"><?= clean($u['nom'] . ' ' . $u['prenom']) ?></span>
                            </div>
                        </td>
                        <td><?= clean($u['email']) ?></td>
                        <td>
                            <?php
                            $badgeColor = match($u['role']) {
                                'administrateur' => 'danger',
                                'caissier'       => 'primary',
                                'magasinier'     => 'success',
                                default          => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $badgeColor ?>"><?= ucfirst($u['role']) ?></span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $u['statut'] === 'actif' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($u['statut']) ?>
                            </span>
                        </td>
                        <td class="text-muted" style="font-size:.85rem;">
                            <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td class="text-center">
                            <!-- Toggle statut -->
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action"  value="toggle">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-<?= $u['statut'] === 'actif' ? 'warning' : 'success' ?>"
                                        title="<?= $u['statut'] === 'actif' ? 'Désactiver' : 'Activer' ?>">
                                    <i class="bi bi-<?= $u['statut'] === 'actif' ? 'person-x' : 'person-check' ?>"></i>
                                </button>
                            </form>
                            <!-- Reset mdp -->
                            <button class="btn btn-sm btn-outline-secondary ms-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalReset<?= $u['id'] ?>"
                                    title="Réinitialiser mot de passe">
                                <i class="bi bi-key-fill"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- Modal reset mdp -->
                    <div class="modal fade" id="modalReset<?= $u['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <form method="POST" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Réinitialiser le mot de passe</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted mb-3">
                                        Nouveau mot de passe pour <strong><?= clean($u['nom'] . ' ' . $u['prenom']) ?></strong>
                                    </p>
                                    <input type="hidden" name="action"   value="reset_password">
                                    <input type="hidden" name="user_id"  value="<?= $u['id'] ?>">
                                    <input type="password" name="new_password" class="form-control"
                                        placeholder="Minimum 8 caractères" required minlength="8">
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check2 me-1"></i>Valider
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Modal : Créer un utilisateur ──────────────────────── -->
<div class="modal fade" id="modalCreer" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" novalidate>
            <div class="modal-header" style="background:var(--bleu); color:#fff;">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nouvel utilisateur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="creer">

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="nom" class="form-control" required placeholder="KOFI">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Prénom <span class="text-danger">*</span></label>
                        <input type="text" name="prenom" class="form-control" required placeholder="Ama">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="email@boutique.tg">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Rôle <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= ucfirst($r['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Mot de passe <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control"
                            required minlength="8" placeholder="Minimum 8 caractères">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary fw-semibold">
                    <i class="bi bi-person-check-fill me-2"></i>Créer
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>