<?php
// ============================================================
//  modules/ventes/historique.php — Historique des ventes
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Vente.php';
requireLogin();
if (!peutFaire('ventes','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$venteModel = new Vente();

// Annulation (admin seulement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_id'])) {
    $result = $venteModel->annuler((int)$_POST['annuler_id']);
    setFlash($result === true ? 'success' : 'error', $result === true ? 'Vente annulée et stock restauré.' : $result);
    redirect(APP_URL . '/modules/ventes/historique.php');
}

$dateDebut = clean($_GET['dd'] ?? date('Y-m-01'));
$dateFin   = clean($_GET['df'] ?? date('Y-m-d'));
$ventes    = $venteModel->getHistorique($dateDebut, $dateFin);

// Total de la période
$totalPeriode = array_sum(array_column($ventes, 'total_net'));

$pageTitle = "Historique des ventes";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-clock-history me-2"></i> Historique des ventes</h2>
        <small class="text-muted"><?= count($ventes) ?> vente(s) | CA période : <strong class="text-success"><?= formatMontant($totalPeriode) ?></strong></small>
    </div>
</div>

<!-- Filtres date -->
<form method="GET" class="row g-2 mb-3 align-items-end">
    <div class="col-md-3">
        <label class="form-label fw-semibold mb-1">Date début</label>
        <input type="date" name="dd" class="form-control" value="<?= $dateDebut ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold mb-1">Date fin</label>
        <input type="date" name="df" class="form-control" value="<?= $dateFin ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Filtrer</button>
    </div>
    <div class="col-md-2">
        <a href="?dd=<?= date('Y-m-d') ?>&df=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary w-100">Aujourd'hui</a>
    </div>
</form>

<!-- Tableau des ventes -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Référence</th>
                    <th>Date & Heure</th>
                    <th>Caissier</th>
                    <th class="text-center">Articles</th>
                    <th class="text-end">Remise</th>
                    <th class="text-end">Total net</th>
                    <th class="text-center">Statut</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($ventes)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">Aucune vente sur cette période.</td></tr>
            <?php else: ?>
                <?php foreach ($ventes as $v): ?>
                <tr>
                    <td class="ps-3">
                        <span class="badge bg-light text-dark fw-semibold"><?= clean($v['reference']) ?></span>
                    </td>
                    <td class="text-muted" style="font-size:.85rem;"><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></td>
                    <td><?= clean($v['nom'] . ' ' . $v['prenom']) ?></td>
                    <td class="text-center"><?= $v['nb_articles'] ?></td>
                    <td class="text-end text-muted"><?= $v['remise'] > 0 ? '-'.formatMontant($v['remise']) : '—' ?></td>
                    <td class="text-end fw-bold text-success"><?= formatMontant($v['total_net']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $v['statut']==='validee' ? 'success' : 'danger' ?>">
                            <?= ucfirst($v['statut']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <!-- Voir détail -->
                        <a href="detail.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir détail">
                            <i class="bi bi-eye-fill"></i>
                        </a>
                        <?php if (peutFaire('ventes','supprimer') && $v['statut']==='validee'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Annuler cette vente et restaurer le stock ?');">
                            <input type="hidden" name="annuler_id" value="<?= $v['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger ms-1" title="Annuler">
                                <i class="bi bi-x-circle-fill"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($ventes)): ?>
            <tfoot style="background:#f8f9fa;">
                <tr>
                    <td colspan="5" class="text-end fw-bold ps-3">TOTAL PÉRIODE :</td>
                    <td class="text-end fw-bold text-success fs-6"><?= formatMontant($totalPeriode) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>