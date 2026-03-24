<?php
// ============================================================
//  modules/ventes/detail.php — Détail d'une vente + ticket
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Vente.php';
requireLogin();

$id    = (int)($_GET['id'] ?? 0);
$vente = (new Vente())->getById($id);
if (!$vente) { setFlash('error','Vente introuvable.'); redirect(APP_URL.'/modules/ventes/historique.php'); }

$pageTitle = "Vente " . $vente['reference'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="historique.php" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i>Retour
        </a>
        <h2 class="fw-bold mb-0" style="color:#153F6E;">Détail vente <code><?= clean($vente['reference']) ?></code></h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/modules/pdf/ticket.php?id=<?= $vente['id'] ?>"
            target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-receipt me-2"></i>Ticket PDF
        </a>
        <a href="<?= APP_URL ?>/modules/pdf/facture.php?id=<?= $vente['id'] ?>"
            target="_blank" class="btn btn-primary">
            <i class="bi bi-file-earmark-pdf-fill me-2"></i>Facture PDF
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Infos vente -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header fw-bold" style="background:var(--bleu);color:#fff;">
                <i class="bi bi-info-circle me-2"></i>Informations
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0" style="font-size:.9rem;">
                    <tr><td class="text-muted fw-semibold">Référence</td><td><code><?= clean($vente['reference']) ?></code></td></tr>
                    <tr><td class="text-muted fw-semibold">Date</td><td><?= date('d/m/Y H:i', strtotime($vente['created_at'])) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Caissier</td><td><?= clean($vente['nom'].' '.$vente['prenom']) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Statut</td><td><span class="badge bg-<?= $vente['statut']==='validee'?'success':'danger' ?>"><?= ucfirst($vente['statut']) ?></span></td></tr>
                    <tr><td class="text-muted fw-semibold">Sous-total</td><td><?= formatMontant($vente['total_ht']) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Remise</td><td class="text-danger">-<?= formatMontant($vente['remise']) ?></td></tr>
                    <tr><td class="fw-bold">TOTAL NET</td><td class="fw-bold text-success fs-5"><?= formatMontant($vente['total_net']) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Montant reçu</td><td><?= formatMontant($vente['montant_recu']) ?></td></tr>
                    <tr><td class="text-muted fw-semibold">Monnaie</td><td><?= formatMontant($vente['monnaie_rendue']) ?></td></tr>
                    <?php if (!empty($vente['note'])): ?>
                    <tr>
                        <td class="text-muted fw-semibold" style="vertical-align:top;">Note</td>
                        <td>
                            <div style="background:#f8fafc;border-left:3px solid #e2e8f0;
                                        padding:.4rem .75rem;border-radius:0 6px 6px 0;
                                        font-size:.88rem;color:#374151;font-style:italic;">
                                <?= clean($vente['note']) ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Lignes -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header fw-bold" style="background:var(--bleu);color:#fff;">
                <i class="bi bi-list-ul me-2"></i>Articles vendus (<?= count($vente['lignes']) ?>)
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Produit</th><th class="text-center">Qté</th><th class="text-end">Prix unit.</th><th class="text-end">Sous-total</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vente['lignes'] as $l): ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?= clean($l['produit_nom']) ?></span><br>
                            <small class="text-muted"><code><?= clean($l['reference']) ?></code></small>
                        </td>
                        <td class="text-center"><?= $l['quantite'] ?></td>
                        <td class="text-end"><?= formatMontant($l['prix_unitaire']) ?></td>
                        <td class="text-end fw-semibold"><?= formatMontant($l['sous_total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background:#f8f9fa;">
                        <tr>
                            <td colspan="3" class="text-end fw-bold">TOTAL NET</td>
                            <td class="text-end fw-bold text-success"><?= formatMontant($vente['total_net']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Style impression -->
<style>
@media print {
    .sidebar, .topbar, .btn, .alert { display:none!important; }
    .main-content { margin:0!important; padding:0!important; }
    .card { box-shadow:none!important; border:1px solid #ccc!important; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>