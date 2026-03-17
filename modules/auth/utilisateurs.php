<?php
// ============================================================
//  modules/auth/utilisateurs.php — Gestion des utilisateurs
//  Accès : administrateur uniquement
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/User.php';

/**
 * @global function verifierCSRF()
 * @global function setFlash()
 * @global function redirect()
 * @global function peutFaire()
 * @global function clean()
 * @global function getDB()
 */

requireLogin();
if (!peutFaire('utilisateurs','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$userModel = new User();
$erreur    = '';
$success   = '';

// ── Créer un utilisateur ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    /** @intelephense-ignore-line */
    verifierCSRF();

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
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-0" style="color:#153F6E; font-size:2rem;">
                <i class="bi bi-people-fill me-3" style="color:#1F5C99;"></i>Gestion des utilisateurs
            </h2>
            <small class="text-muted d-block mt-2">
                <i class="bi bi-info-circle me-1"></i><?= count($utilisateurs) ?> utilisateur(s) actif(s)
            </small>
        </div>
        <button class="btn btn-primary fw-semibold px-4 py-2" style="font-size:0.95rem; border-radius:10px; box-shadow: 0 4px 12px rgba(31,92,153,0.3);" data-bs-toggle="modal" data-bs-target="#modalCreer">
            <i class="bi bi-person-plus-fill me-2"></i>Nouvel utilisateur
        </button>
    </div>
</div>

<!-- ── Tableau des utilisateurs ──────────────────────────── -->
<div class="card shadow-sm border-0" style="border-radius:15px; overflow:hidden;">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background: linear-gradient(135deg, #1F5C99 0%, #153F6E 100%); color:#fff;">
                    <tr>
                        <th class="ps-4" style="font-weight:600;">
                            <i class="bi bi-hash me-2"></i>#
                        </th>
                        <th style="font-weight:600;">
                            <i class="bi bi-person me-2"></i>Nom complet
                        </th>
                        <th style="font-weight:600;">
                            <i class="bi bi-envelope me-2"></i>Email
                        </th>
                        <th style="font-weight:600;">
                            <i class="bi bi-shield-check me-2"></i>Rôle
                        </th>
                        <th style="font-weight:600;">
                            <i class="bi bi-circle-fill me-2" style="font-size:0.6rem;"></i>Statut
                        </th>
                        <th style="font-weight:600;">
                            <i class="bi bi-calendar3 me-2"></i>Créé le
                        </th>
                        <th class="text-center" style="font-weight:600;">
                            <i class="bi bi-gear me-2"></i>Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($utilisateurs)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2rem; opacity:0.5;"></i><p class="mt-2">Aucun utilisateur trouvé</p></td></tr>
                <?php else: ?>
                    <?php foreach ($utilisateurs as $i => $u): ?>
                    <tr style="transition: background-color 0.2s; border-bottom: 1px solid #f0f0f0;">
                        <td class="ps-4 text-muted fw-semibold"><?= $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?php if (!empty($u['avatar'])): ?>
                                <img src="<?= APP_URL ?>/uploads/avatars/<?= $u['avatar'] ?>"
                                        style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid #1F5C99; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                                        alt="Photo">
                                <?php else: ?>
                                <div style="width:42px;height:42px;border-radius:50%;background: linear-gradient(135deg, #1F5C99 0%, #153F6E 100%);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-weight:700;font-size:.9rem;flex-shrink:0; box-shadow: 0 2px 8px rgba(31,92,153,0.3);">
                                    <?= strtoupper(substr($u['nom'], 0, 1) . substr($u['prenom'], 0, 1)) ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <span class="fw-semibold d-block" style="color:#153F6E;"><?= clean($u['nom'] . ' ' . $u['prenom']) ?></span>
                                    <small class="text-muted"><?= clean($u['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:0.9rem; color:#555;"><?= clean($u['email']) ?></td>
                        <td>
                            <?php
                            $badgeConfig = match($u['role']) {
                                'administrateur' => ['bg' => '#dc3545', 'icon' => 'shield-lock'],
                                'caissier'       => ['bg' => '#1F5C99', 'icon' => 'cash-coin'],
                                'magasinier'     => ['bg' => '#16a34a', 'icon' => 'boxes'],
                                default          => ['bg' => '#6c757d', 'icon' => 'person']
                            };
                            ?>
                            <span class="badge" style="background-color:<?= $badgeConfig['bg'] ?>; padding:0.5rem 0.8rem; font-size:0.85rem;">
                                <i class="bi bi-<?= $badgeConfig['icon'] ?> me-1"></i><?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="padding:0.5rem 0.8rem; background-color:<?= $u['statut'] === 'actif' ? '#10b981' : '#ef4444' ?>; font-size:0.85rem;">
                                <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i><?= ucfirst($u['statut']) ?>
                            </span>
                        </td>
                        <td class="text-muted" style="font-size:0.9rem;">
                            <i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td class="text-center">
                            <!-- Toggle statut -->
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="action"  value="toggle">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm" style="background-color:<?= $u['statut'] === 'actif' ? '#fbbf24' : '#34d399' ?>; color:#fff; border:none; transition:all 0.2s; border-radius:8px;"
                                        title="<?= $u['statut'] === 'actif' ? 'Désactiver' : 'Activer' ?>" 
                                        onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.2)';"
                                        onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                                    <i class="bi bi-<?= $u['statut'] === 'actif' ? 'person-x' : 'person-check' ?>"></i>
                                </button>
                            </form>
                            <!-- Reset mdp -->
                            <button class="btn btn-sm" style="background-color:#3b82f6; color:#fff; border:none; transition:all 0.2s; border-radius:8px; margin-left:0.5rem;"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalReset<?= $u['id'] ?>"
                                    title="Réinitialiser mot de passe"
                                    onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.2)';"
                                    onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                                🔑
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Modals pour réinitialiser les mots de passe ────────── -->
<?php foreach ($utilisateurs as $u): ?>
<div class="modal fade" id="modalReset<?= $u['id'] ?>" tabindex="-1" aria-labelledby="modalReset<?= $u['id'] ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <form method="POST" class="modal-content" style="border-radius:12px; border:none; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action"   value="reset_password">
            <input type="hidden" name="user_id"  value="<?= $u['id'] ?>">
            <div class="modal-header" style="background: linear-gradient(135deg, #1F5C99 0%, #153F6E 100%); color:#fff; border:none; border-radius:12px 12px 0 0;">
                <div>
                    <h5 class="modal-title mb-0" id="modalReset<?= $u['id'] ?>Label" style="font-weight:700; font-size:1.2rem;">
                        🔑 Réinitialiser le mot de passe
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" style="padding:2rem;">
                <div style="background:#f0f4f8; padding:1rem; border-radius:10px; margin-bottom:1.5rem;">
                    <p class="mb-0 text-muted " style="font-size:0.9rem;">Nouvel accès pour :</p>
                    <p class="fw-bold text-primary mb-0" style="font-size:1.2rem; margin-top:0.3rem;">
                        <i class="bi bi-person-circle me-2"></i><?= clean($u['nom'] . ' ' . $u['prenom']) ?>
                    </p>
                </div>
                <label class="form-label fw-semibold" style="color:#153F6E;">Nouveau mot de passe <span class="text-danger">*</span></label>
                <input type="password" name="new_password" class="form-control" style="padding:0.75rem 1rem; font-size:1rem; border:2px solid #e2e8f0; border-radius:8px; transition: border-color 0.2s;" 
                        required minlength="8" placeholder="Minimum 8 caractères"
                        onfocus="this.style.borderColor='#1F5C99'; this.style.boxShadow='0 0 0 3px rgba(31,92,153,0.1)';"
                        onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    <small class="text-muted d-block mt-2" style="font-size:0.85rem;">
                    <i class="bi bi-info-circle me-1"></i> Minimum 8 caractères. Achetez un mot de passe fort et unique.
                </small>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e8edf3; padding:1.5rem;">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px; border:1px solid #ddd;">
                    <i class="bi bi-x-lg me-2"></i>Annuler
                </button>
                <button type="submit" class="btn btn-primary fw-semibold" style="border-radius:8px; background: linear-gradient(135deg, #1F5C99 0%, #153F6E 100%); border:none; padding:0.7rem 1.5rem;">
                    <i class="bi bi-check-lg me-2"></i>Réinitialiser
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>


<!-- ── Modal : Créer un utilisateur ──────────────────────── -->
<div class="modal fade" id="modalCreer" tabindex="-1" aria-labelledby="modalCreerLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" novalidate style="border-radius:12px; border:none; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="action" value="creer">
            <div class="modal-header" style="background: linear-gradient(135deg, #1F5C99 0%, #153F6E 100%); color:#fff; border:none; border-radius:12px 12px 0 0;">
                    <h5 class="modal-title fw-bold" id="modalCreerLabel" style="font-size:1.3rem;">
                        <i class="bi bi-person-plus-fill me-2"></i>Créer un nouveau compte
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body" style="padding:2rem;">
                    <div class="row g-3">
                        <div class="col-md-6">
                                <label class="form-label fw-semibold" style="color:#153F6E;">Nom <span class="text-danger">*</span></label>
                                <input type="text" name="nom" class="form-control" style="border-radius:8px; border:2px solid #e2e8f0; padding:0.75rem 1rem; font-size:0.95rem;" required placeholder="Nom de famille" 
                                    onfocus="this.style.borderColor='#1F5C99'; this.style.boxShadow='0 0 0 3px rgba(31,92,153,0.1)';"
                                    onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="color:#153F6E;">Prénom <span class="text-danger">*</span></label>
                            <input type="text" name="prenom" class="form-control" style="border-radius:8px; border:2px solid #e2e8f0; padding:0.75rem 1rem; font-size:0.95rem;" required placeholder="Prénom" 
                                    onfocus="this.style.borderColor='#1F5C99'; this.style.boxShadow='0 0 0 3px rgba(31,92,153,0.1)';"
                                    onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="color:#153F6E;">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" style="border-radius:8px; border:2px solid #e2e8f0; padding:0.75rem 1rem; font-size:0.95rem;" required placeholder="exemple@domaine.com" 
                                    onfocus="this.style.borderColor='#1F5C99'; this.style.boxShadow='0 0 0 3px rgba(31,92,153,0.1)';"
                                    onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="color:#153F6E;">Rôle <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" style="border-radius:8px; border:2px solid #e2e8f0; padding:0.75rem 1rem; font-size:0.95rem;" required 
                                    onfocus="this.style.borderColor='#1F5C99'; this.style.boxShadow='0 0 0 3px rgba(31,92,153,0.1)';"
                                    onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                                <option value="">-- Sélectionner un rôle --</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= ucfirst($r['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="color:#153F6E;">Mot de passe <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" style="border-radius:8px; border:2px solid #e2e8f0; padding:0.75rem 1rem; font-size:0.95rem;" required minlength="8" placeholder="Minimum 8 caractères" 
                                onfocus="this.style.borderColor='#1F5C99'; this.style.boxShadow='0 0 0 3px rgba(31,92,153,0.1)';"
                                onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            <small class="text-muted d-block mt-2"><i class="bi bi-shield-check me-1"></i>Utilisez un mot de passe fort et unique</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e8edf3; padding:1.5rem; background:#f9fafb; border-radius:0 0 12px 12px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:8px; border:1px solid #ddd;">
                        <i class="bi bi-x-lg me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-primary fw-semibold" style="border-radius:8px; background: linear-gradient(135deg, #1F5C99 0%, #153F6E 100%); border:none; padding:0.7rem 1.5rem;">
                        <i class="bi bi-check-lg me-2"></i>Créer le compte
                    </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>