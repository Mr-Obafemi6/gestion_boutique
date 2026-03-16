<?php
// ============================================================
//  dashboard.php — Tableau de bord principal
// ============================================================
require_once __DIR__ . '/config/config.php';

requireLogin();

$db = getDB();

// ── Statistiques du jour ─────────────────────────────────────
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$stmt = $db->prepare("
    SELECT COUNT(id) AS nb_ventes, COALESCE(SUM(total_net),0) AS ca_jour
    FROM ventes
    WHERE DATE(created_at) = :today AND statut = 'validee'
");
$stmt->execute([':today' => $today]);
$statsJour = $stmt->fetch();

// ── Statistiques d'hier (pour comparaison) ───────────────────
$stmtHier = $db->prepare("
    SELECT COUNT(id) AS nb_ventes, COALESCE(SUM(total_net),0) AS ca_jour
    FROM ventes
    WHERE DATE(created_at) = :hier AND statut = 'validee'
");
$stmtHier->execute([':hier' => $yesterday]);
$statsHier = $stmtHier->fetch();

// ── Statistiques du mois en cours ────────────────────────────
$stmtMois = $db->prepare("
    SELECT COUNT(id) AS nb_ventes, COALESCE(SUM(total_net),0) AS ca_mois
    FROM ventes
    WHERE YEAR(created_at)  = YEAR(CURDATE())
      AND MONTH(created_at) = MONTH(CURDATE())
      AND statut = 'validee'
");
$stmtMois->execute();
$statsMois = $stmtMois->fetch();

// ── Statistiques du mois précédent (pour comparaison mois) ───
$stmtMoisPrec = $db->prepare("
    SELECT COALESCE(SUM(total_net),0) AS ca_mois
    FROM ventes
    WHERE YEAR(created_at)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND statut = 'validee'
");
$stmtMoisPrec->execute();
$statsMoisPrec = $stmtMoisPrec->fetch();

// ── Helpers comparaison ──────────────────────────────────────
function calcEvolution(float $avant, float $apres): array {
    if ($avant <= 0) {
        return ['pct' => null, 'sens' => 'neutre', 'label' => 'Nouveau'];
    }
    $pct = round(($apres - $avant) / $avant * 100, 1);
    if ($pct > 0)  return ['pct' => $pct,    'sens' => 'hausse', 'label' => '+' . $pct . '%'];
    if ($pct < 0)  return ['pct' => abs($pct),'sens' => 'baisse', 'label' => $pct . '%'];
    return ['pct' => 0, 'sens' => 'neutre', 'label' => '='];
}

$evoCA     = calcEvolution((float)$statsHier['ca_jour'],   (float)$statsJour['ca_jour']);
$evoVentes = calcEvolution((float)$statsHier['nb_ventes'], (float)$statsJour['nb_ventes']);
$evoMois   = calcEvolution((float)$statsMoisPrec['ca_mois'], (float)$statsMois['ca_mois']);

// ── Total produits ───────────────────────────────────────────
$stmtProd = $db->prepare("SELECT COUNT(id) FROM produits WHERE statut = 'actif'");
$stmtProd->execute();
$nbProduits = (int)$stmtProd->fetchColumn();

// ── Stock critique ────────────────────────────────────────────
// Utilise prepare() + fallback direct sur produits si la vue n'existe pas
try {
    $stmtCritique = $db->prepare("SELECT * FROM v_stock_critique LIMIT 5");
    $stmtCritique->execute();
    $stockCritique = $stmtCritique->fetchAll();

    $stmtNb = $db->prepare("SELECT COUNT(*) FROM v_stock_critique");
    $stmtNb->execute();
    $nbCritique = (int)$stmtNb->fetchColumn();
} catch (Exception $e) {
    // Fallback : requête directe si la vue v_stock_critique n'existe pas encore
    $stmtCritique = $db->prepare("
        SELECT p.id, p.reference, p.nom, c.nom AS categorie,
               p.stock_actuel, p.seuil_alerte,
               (p.seuil_alerte - p.stock_actuel) AS ecart
        FROM produits p
        JOIN categories c ON p.categorie_id = c.id
        WHERE p.stock_actuel <= p.seuil_alerte
          AND p.statut = 'actif'
        ORDER BY ecart DESC
        LIMIT 5
    ");
    $stmtCritique->execute();
    $stockCritique = $stmtCritique->fetchAll();

    $stmtNb = $db->prepare("
        SELECT COUNT(*) FROM produits
        WHERE stock_actuel <= seuil_alerte AND statut = 'actif'
    ");
    $stmtNb->execute();
    $nbCritique = (int)$stmtNb->fetchColumn();
}

// ── Dernières ventes ─────────────────────────────────────────
$stmtDernieres = $db->prepare("
    SELECT v.reference, v.total_net, v.created_at, u.nom, u.prenom
    FROM ventes v
    JOIN utilisateurs u ON v.utilisateur_id = u.id
    WHERE v.statut = 'validee'
    ORDER BY v.created_at DESC
    LIMIT 6
");
$stmtDernieres->execute();
$dernieresVentes = $stmtDernieres->fetchAll();

// ── CA des 7 derniers jours (pour graphique) ─────────────────
$stmtCa7 = $db->prepare("
    SELECT DATE(created_at) AS jour, COALESCE(SUM(total_net),0) AS ca
    FROM ventes
    WHERE statut = 'validee'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY jour ASC
");
$stmtCa7->execute();
$ca7j = $stmtCa7->fetchAll();

$labels  = array_column($ca7j, 'jour');
$valeurs = array_column($ca7j, 'ca');

$pageTitle = "Tableau de bord";
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── KPI Cards ─────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- CA du jour -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-bleu h-100">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="kpi-icon" style="background:#D6E4F0;">
                    <i class="bi bi-coin" style="color:#1F5C99;"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="kpi-label">CA du jour</div>
                    <div class="kpi-value" style="color:#1F5C99; font-size:1.25rem;">
                        <?= formatMontant((float)$statsJour['ca_jour']) ?>
                    </div>
                    <div class="kpi-footer d-flex align-items-center gap-1">
                        <?php $e=$evoCA; $ic=$e['sens']==='hausse'?'bi-arrow-up-short':($e['sens']==='baisse'?'bi-arrow-down-short':'bi-dash'); $clr=$e['sens']==='hausse'?'#16a34a':($e['sens']==='baisse'?'#dc2626':'#64748b'); ?>
                        <i class="bi <?= $ic ?>" style="color:<?= $clr ?>;font-size:1rem;"></i>
                        <span style="font-size:.72rem;color:<?= $clr ?>;font-weight:600;"><?= $e['label'] ?></span>
                        <span style="font-size:.72rem;color:#94a3b8;">vs hier</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ventes du jour -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-vert h-100">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="kpi-icon" style="background:#dcfce7;">
                    <i class="bi bi-cart-check-fill" style="color:#16a34a;"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="kpi-label">Ventes</div>
                    <div class="kpi-value" style="color:#16a34a;"><?= $statsJour['nb_ventes'] ?></div>
                    <div class="kpi-footer d-flex align-items-center gap-1">
                        <?php $e=$evoVentes; $ic=$e['sens']==='hausse'?'bi-arrow-up-short':($e['sens']==='baisse'?'bi-arrow-down-short':'bi-dash'); $clr=$e['sens']==='hausse'?'#16a34a':($e['sens']==='baisse'?'#dc2626':'#64748b'); ?>
                        <i class="bi <?= $ic ?>" style="color:<?= $clr ?>;font-size:1rem;"></i>
                        <span style="font-size:.72rem;color:<?= $clr ?>;font-weight:600;"><?= $e['label'] ?></span>
                        <span style="font-size:.72rem;color:#94a3b8;">vs hier (<?= $statsHier['nb_ventes'] ?>)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CA du mois -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-orange h-100">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="kpi-icon" style="background:#ffedd5;">
                    <i class="bi bi-calendar-month-fill" style="color:#ea580c;"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="kpi-label">CA du mois</div>
                    <div class="kpi-value" style="color:#ea580c;font-size:1.05rem;">
                        <?= formatMontant((float)$statsMois['ca_mois']) ?>
                    </div>
                    <div class="kpi-footer d-flex align-items-center gap-1">
                        <?php $e=$evoMois; $ic=$e['sens']==='hausse'?'bi-arrow-up-short':($e['sens']==='baisse'?'bi-arrow-down-short':'bi-dash'); $clr=$e['sens']==='hausse'?'#16a34a':($e['sens']==='baisse'?'#dc2626':'#64748b'); ?>
                        <i class="bi <?= $ic ?>" style="color:<?= $clr ?>;font-size:1rem;"></i>
                        <span style="font-size:.72rem;color:<?= $clr ?>;font-weight:600;"><?= $e['label'] ?></span>
                        <span style="font-size:.72rem;color:#94a3b8;">vs mois préc.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock critique -->
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-rouge h-100">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="kpi-icon" style="background:#fee2e2;">
                    <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="kpi-label">Stock critique</div>
                    <div class="kpi-value" style="color:#dc2626;"><?= $nbCritique ?></div>
                    <div class="kpi-footer">
                        <?php if ($nbCritique === 0): ?>
                            <span style="font-size:.72rem;color:#16a34a;font-weight:600;">
                                <i class="bi bi-check-circle-fill me-1"></i>Tout est OK
                            </span>
                        <?php else: ?>
                            <a href="<?= APP_URL ?>/modules/stock/mouvements.php"
                               style="font-size:.72rem;color:#dc2626;font-weight:600;text-decoration:none;">
                                Voir les produits →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Graphique + Stock critique ──────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Graphique CA 7 jours -->
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-header-icon" style="background:#D6E4F0;">
                    <i class="bi bi-bar-chart-line-fill" style="color:#1F5C99;"></i>
                </div>
                Chiffre d'affaires — 7 derniers jours
            </div>
            <div class="card-body">
                <canvas id="caChart" height="115"></canvas>
            </div>
        </div>
    </div>

    <!-- Stock critique -->
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-header-icon" style="background:#fee2e2;">
                    <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                </div>
                Alertes stock
                <?php if ($nbCritique > 0): ?>
                <span class="badge ms-auto" style="background:#fee2e2;color:#b91c1c;"><?= $nbCritique ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($stockCritique)): ?>
                    <div class="empty-state">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <p>Tous les stocks sont suffisants</p>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($stockCritique as $s):
                            $pct = $s['seuil_alerte'] > 0 ? min(100, round($s['stock_actuel']/$s['seuil_alerte']*100)) : 0;
                            $cls = $pct <= 0 ? 'critique' : ($pct <= 50 ? 'alerte' : 'ok');
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-3">
                            <div class="flex-grow-1 me-2">
                                <span class="fw-semibold"><?= clean($s['nom']) ?></span>
                                <div class="stock-bar-wrap mt-1">
                                    <div class="stock-bar">
                                        <div class="stock-bar-fill <?= $cls ?>" style="width:<?= $pct ?>%;"></div>
                                    </div>
                                    <small class="text-muted" style="white-space:nowrap;"><?= $s['stock_actuel'] ?>/<?= $s['seuil_alerte'] ?></small>
                                </div>
                            </div>
                            <span class="badge badge-stock-critique rounded-pill">Critique</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($nbCritique > 5): ?>
                    <div class="text-center p-2 border-top">
                        <a href="<?= APP_URL ?>/modules/stock/mouvements.php" class="small fw-semibold" style="color:var(--gb-primary);">
                            Voir les <?= $nbCritique ?> produits critiques →
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Dernières ventes ────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div class="card-header-icon" style="background:#D6E4F0;">
            <i class="bi bi-clock-history" style="color:#1F5C99;"></i>
        </div>
        Dernières ventes
        <a href="<?= APP_URL ?>/modules/ventes/historique.php" class="btn btn-sm btn-outline-primary ms-auto">
            Voir tout <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Référence</th>
                        <th>Caissier</th>
                        <th class="text-end">Montant</th>
                        <th class="text-end pe-3">Date &amp; heure</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($dernieresVentes)): ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-cart-x d-block fs-3 mb-2 opacity-25"></i>Aucune vente enregistrée.</td></tr>
                <?php else: ?>
                    <?php foreach ($dernieresVentes as $v): ?>
                    <tr>
                        <td class="ps-3">
                            <span class="stat-chip">
                                <i class="bi bi-receipt"></i><?= clean($v['reference']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:28px;height:28px;border-radius:50%;background:var(--gb-primary-light);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:var(--gb-primary);">
                                    <?= strtoupper(substr($v['prenom'],0,1).substr($v['nom'],0,1)) ?>
                                </div>
                                <?= clean($v['prenom'].' '.$v['nom']) ?>
                            </div>
                        </td>
                        <td class="text-end fw-bold" style="color:#16a34a;"><?= formatMontant((float)$v['total_net']) ?></td>
                        <td class="text-end pe-3 text-muted" style="font-size:.82rem;">
                            <?= date('d/m/Y', strtotime($v['created_at'])) ?>
                            <small class="d-block"><?= date('H:i', strtotime($v['created_at'])) ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('caChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'CA (FCFA)',
            data: <?= json_encode($valeurs) ?>,
            backgroundColor: 'rgba(31,92,153,0.7)',
            borderColor: '#1F5C99',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: v => v.toLocaleString('fr-FR') + ' F' }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
