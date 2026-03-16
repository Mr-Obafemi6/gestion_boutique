<?php
// ============================================================
//  modules/rapports/index.php — Rapports & statistiques
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Vente.php';
require_once __DIR__ . '/../../includes/Produit.php';

requireLogin();
if (!peutFaire('rapports','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$db = getDB();
$venteModel = new Vente();

// ── Période par défaut : mois en cours ──────────────────────
$dateDebut = clean($_GET['dd'] ?? date('Y-m-01'));
$dateFin   = clean($_GET['df'] ?? date('Y-m-d'));

// ── Statistiques générales ──────────────────────────────────
$stats = $db->prepare("
    SELECT
        COUNT(id)            AS nb_ventes,
        COALESCE(SUM(total_net),0)  AS ca_total,
        COALESCE(AVG(total_net),0)  AS ca_moyen,
        COALESCE(SUM(remise),0)     AS total_remises
    FROM ventes
    WHERE statut='validee' AND DATE(created_at) BETWEEN :dd AND :df
");
$stats->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
$stats = $stats->fetch();

// ── CA par jour ─────────────────────────────────────────────
$caJour = $db->prepare("
    SELECT DATE(created_at) AS jour, SUM(total_net) AS ca
    FROM ventes
    WHERE statut='validee' AND DATE(created_at) BETWEEN :dd AND :df
    GROUP BY DATE(created_at) ORDER BY jour
");
$caJour->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
$caJour = $caJour->fetchAll();

// ── Top 10 produits vendus ───────────────────────────────────
$topProduits = $db->prepare("
    SELECT p.nom,
           SUM(lv.quantite) AS qte_vendue,
           SUM(lv.sous_total) AS ca,
           SUM(lv.quantite * p.prix_achat) AS cout,
           SUM(lv.quantite * (lv.prix_unitaire - p.prix_achat)) AS marge,
           CASE WHEN SUM(lv.sous_total) > 0
                THEN ROUND(SUM(lv.quantite*(lv.prix_unitaire-p.prix_achat))/SUM(lv.sous_total)*100,1)
                ELSE 0 END AS taux_marge
    FROM lignes_vente lv
    JOIN produits p ON lv.produit_id=p.id
    JOIN ventes v ON lv.vente_id=v.id
    WHERE v.statut='validee' AND DATE(v.created_at) BETWEEN :dd AND :df
    GROUP BY p.id ORDER BY qte_vendue DESC LIMIT 10
");
$topProduits->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
$topProduits = $topProduits->fetchAll();

// ── Ventes par heure ─────────────────────────────────────────
$parHeure = $db->prepare("
    SELECT HOUR(created_at) AS heure, COUNT(id) AS nb, SUM(total_net) AS ca
    FROM ventes
    WHERE statut='validee' AND DATE(created_at) BETWEEN :dd AND :df
    GROUP BY HOUR(created_at) ORDER BY heure
");
$parHeure->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
$parHeure = $parHeure->fetchAll();

// ── CA par caissier ──────────────────────────────────────────
$parCaissier = $db->prepare("
    SELECT u.nom, u.prenom, COUNT(v.id) AS nb_ventes, SUM(v.total_net) AS ca
    FROM ventes v JOIN utilisateurs u ON v.utilisateur_id=u.id
    WHERE v.statut='validee' AND DATE(v.created_at) BETWEEN :dd AND :df
    GROUP BY u.id ORDER BY ca DESC
");
$parCaissier->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
$parCaissier = $parCaissier->fetchAll();

// ── Valeur du stock actuel ────────────────────────────────────
$stmtStock = $db->prepare("SELECT SUM(stock_actuel * prix_achat) FROM produits WHERE statut='actif'");
$stmtStock->execute();
$valeurStock = (float)$stmtStock->fetchColumn();

try {
    $stmtRupture = $db->prepare("SELECT COUNT(*) FROM v_stock_critique");
    $stmtRupture->execute();
    $nbRupture = (int)$stmtRupture->fetchColumn();
} catch (Exception $e) {
    $stmtRupture = $db->prepare("SELECT COUNT(*) FROM produits WHERE stock_actuel <= seuil_alerte AND statut='actif'");
    $stmtRupture->execute();
    $nbRupture = (int)$stmtRupture->fetchColumn();
}

// ── Marge bénéficiaire sur la période ────────────────────────
$stmtMarge = $db->prepare("
    SELECT
        SUM(lv.quantite * lv.prix_unitaire)       AS ca_vente,
        SUM(lv.quantite * p.prix_achat)           AS cout_achat,
        SUM(lv.quantite * (lv.prix_unitaire - p.prix_achat)) AS marge_brute
    FROM lignes_vente lv
    JOIN produits p  ON lv.produit_id = p.id
    JOIN ventes v    ON lv.vente_id   = v.id
    WHERE v.statut = 'validee'
      AND DATE(v.created_at) BETWEEN :dd AND :df
");
$stmtMarge->execute([':dd' => $dateDebut, ':df' => $dateFin]);
$marge       = $stmtMarge->fetch();
$margeBrute  = (float)($marge['marge_brute'] ?? 0);
$coutAchat   = (float)($marge['cout_achat']  ?? 0);
$caTotalMarge= (float)($marge['ca_vente']    ?? 0);
$tauxMarge   = $caTotalMarge > 0 ? round($margeBrute / $caTotalMarge * 100, 1) : 0;

// ── Marge par produit (top 10) ────────────────────────────────
$stmtMargeProd = $db->prepare("
    SELECT
        p.nom,
        SUM(lv.quantite)                                    AS qte_vendue,
        SUM(lv.sous_total)                                  AS ca,
        SUM(lv.quantite * p.prix_achat)                     AS cout,
        SUM(lv.quantite * (lv.prix_unitaire - p.prix_achat)) AS marge,
        CASE WHEN SUM(lv.sous_total) > 0
             THEN ROUND(SUM(lv.quantite * (lv.prix_unitaire - p.prix_achat))
                        / SUM(lv.sous_total) * 100, 1)
             ELSE 0 END                                     AS taux_marge
    FROM lignes_vente lv
    JOIN produits p ON lv.produit_id = p.id
    JOIN ventes v   ON lv.vente_id   = v.id
    WHERE v.statut = 'validee'
      AND DATE(v.created_at) BETWEEN :dd AND :df
    GROUP BY p.id
    ORDER BY marge DESC
    LIMIT 10
");
$stmtMargeProd->execute([':dd' => $dateDebut, ':df' => $dateFin]);
$margeProduits = $stmtMargeProd->fetchAll();

// ── Données pour graphiques ──────────────────────────────────
$labelsJour  = array_column($caJour, 'jour');
$valeursJour = array_column($caJour, 'ca');
$labelsHeure = array_map(fn($h)=>str_pad($h['heure'],2,'0',STR_PAD_LEFT).'h', $parHeure);
$valeursHeure= array_column($parHeure, 'ca');
$labelsProd  = array_column($topProduits, 'nom');
$valeursProd = array_column($topProduits, 'qte_vendue');

// ── EXPORT CSV ───────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!peutFaire('rapports','exporter')) redirect(APP_URL . '/includes/acces_refuse.php');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_ventes_'.$dateDebut.'_'.$dateFin.'.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['Date','Référence','Caissier','Total HT','Remise','Total Net','Montant Reçu','Monnaie','Coût Achat','Marge Brute','Taux Marge %'], ';');
    $ventesExport = $db->prepare("
        SELECT v.*, u.nom, u.prenom,
               COALESCE(SUM(lv.quantite * p.prix_achat), 0) AS cout_achat,
               COALESCE(SUM(lv.quantite * (lv.prix_unitaire - p.prix_achat)), 0) AS marge_brute,
               CASE WHEN SUM(lv.sous_total) > 0
                    THEN ROUND(SUM(lv.quantite*(lv.prix_unitaire-p.prix_achat))/SUM(lv.sous_total)*100,1)
                    ELSE 0 END AS taux_marge
        FROM ventes v
        JOIN utilisateurs u ON v.utilisateur_id = u.id
        LEFT JOIN lignes_vente lv ON lv.vente_id = v.id
        LEFT JOIN produits p ON lv.produit_id = p.id
        WHERE v.statut='validee' AND DATE(v.created_at) BETWEEN :dd AND :df
        GROUP BY v.id ORDER BY v.created_at
    ");
    $ventesExport->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
    foreach ($ventesExport->fetchAll() as $row) {
        fputcsv($out, [
            date('d/m/Y H:i', strtotime($row['created_at'])),
            $row['reference'], $row['nom'].' '.$row['prenom'],
            $row['total_ht'], $row['remise'], $row['total_net'],
            $row['montant_recu'], $row['monnaie_rendue'],
            number_format($row['cout_achat'],  2, ',', ' '),
            number_format($row['marge_brute'], 2, ',', ' '),
            $row['taux_marge'].' %',
        ], ';');
    }
    fclose($out); exit;
}

$pageTitle = "Rapports & Statistiques";
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ── En-tête ─────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h2><i class="bi bi-bar-chart-line-fill me-2"></i> Rapports</h2>
        <small class="text-muted">Période : du <strong><?= date('d/m/Y', strtotime($dateDebut)) ?></strong> au <strong><?= date('d/m/Y', strtotime($dateFin)) ?></strong></small>
    </div>
    <?php if(peutFaire('rapports','exporter')): ?>
    <div class="d-flex gap-2">
        <a href="?dd=<?= $dateDebut ?>&df=<?= $dateFin ?>&export=csv" class="btn btn-success fw-semibold">
            <i class="bi bi-file-earmark-spreadsheet me-2"></i>CSV
        </a>
        <a href="<?= APP_URL ?>/modules/pdf/rapports.php?dd=<?= $dateDebut ?>&df=<?= $dateFin ?>"
           target="_blank" class="btn btn-danger fw-semibold">
            <i class="bi bi-file-earmark-pdf-fill me-2"></i>PDF
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Filtres -->
<form method="GET" class="row g-2 mb-4 align-items-end">
    <div class="col-md-3"><label class="form-label fw-semibold mb-1">Date début</label><input type="date" name="dd" class="form-control" value="<?= $dateDebut ?>"></div>
    <div class="col-md-3"><label class="form-label fw-semibold mb-1">Date fin</label><input type="date" name="df" class="form-control" value="<?= $dateFin ?>"></div>
    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Appliquer</button></div>
    <div class="col-auto"><a href="?dd=<?= date('Y-m-d') ?>&df=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Aujourd'hui</a></div>
    <div class="col-auto"><a href="?dd=<?= date('Y-m-01') ?>&df=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Ce mois</a></div>
    <div class="col-auto"><a href="?dd=<?= date('Y-01-01') ?>&df=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Cette année</a></div>
</form>

<!-- ── KPIs ─────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['CA total',       formatMontant($stats['ca_total']),   'currency-dollar',           $_themeCouleurs[$_SESSION['theme_couleur'] ?? 'bleu'] ?? '#1F5C99', '#D6E4F0'],
        ['Nb ventes',      $stats['nb_ventes'],                 'cart-check-fill',           '#198754', '#d1e7dd'],
        ['Panier moyen',   formatMontant($stats['ca_moyen']),   'graph-up-arrow',            '#fd7e14', '#ffe5d0'],
        ['Total remises',  formatMontant($stats['total_remises']),'tag-fill',                '#6f42c1', '#e8d5f5'],
        ['Marge brute',    formatMontant($margeBrute),          'graph-up-arrow',            '#0d6efd', '#cfe2ff'],
        ['Taux de marge',  $tauxMarge . ' %',                  'percent',                   '#198754', '#d1e7dd'],
        ['Valeur stock',   formatMontant($valeurStock),         'archive-fill',              '#20c997', '#d1f5ed'],
        ['Produits critiques', $nbRupture.' produit(s)',        'exclamation-triangle-fill', '#dc3545', '#f8d7da'],
    ];
    foreach ($kpis as [$label,$val,$icon,$color,$bg]):
    ?>
    <div class="col-6 col-md-3 col-lg-3">
        <div class="card h-100">
            <div class="card-body text-center p-3">
                <div class="mb-2 p-2 d-inline-block rounded-3" style="background:<?= $bg ?>;">
                    <i class="bi bi-<?= $icon ?>" style="color:<?= $color ?>;font-size:1.3rem;"></i>
                </div>
                <div class="fw-bold" style="color:<?= $color ?>;font-size:.95rem;"><?= $val ?></div>
                <div class="text-muted" style="font-size:.75rem;"><?= $label ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Graphiques ────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white fw-bold"><i class="bi bi-bar-chart-fill me-2" style="color:var(--bleu);"></i>CA par jour</div>
            <div class="card-body"><canvas id="caJourChart" height="90"></canvas></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white fw-bold"><i class="bi bi-clock me-2" style="color:var(--bleu);"></i>Ventes par heure</div>
            <div class="card-body"><canvas id="heureChart" height="200"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Top produits -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-trophy-fill me-2 text-warning"></i>Top 10 produits</div>
            <div class="card-body"><canvas id="topProdChart" height="180"></canvas></div>
        </div>
    </div>
    <!-- CA par caissier -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-bold"><i class="bi bi-people-fill me-2" style="color:var(--bleu);"></i>Performance par caissier</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Caissier</th><th class="text-center">Ventes</th><th class="text-end">CA</th></tr></thead>
                    <tbody>
                    <?php if (empty($parCaissier)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Aucune donnée</td></tr>
                    <?php else: ?>
                        <?php foreach ($parCaissier as $c): ?>
                        <tr>
                            <td class="fw-semibold"><?= clean($c['nom'].' '.$c['prenom']) ?></td>
                            <td class="text-center"><span class="badge bg-primary"><?= $c['nb_ventes'] ?></span></td>
                            <td class="text-end text-success fw-semibold"><?= formatMontant($c['ca']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Marge bénéficiaire ─────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header bg-white fw-bold d-flex align-items-center justify-content-between">
        <span><i class="bi bi-graph-up-arrow me-2" style="color:#0d6efd;"></i>Analyse de la marge bénéficiaire</span>
        <span class="badge" style="background:#cfe2ff;color:#0a58ca;font-size:.78rem;">
            Période : <?= date('d/m/Y', strtotime($dateDebut)) ?> → <?= date('d/m/Y', strtotime($dateFin)) ?>
        </span>
    </div>
    <div class="card-body">

        <!-- Résumé marge globale -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 text-center" style="background:#cfe2ff;">
                    <div class="fw-bold" style="color:#0a58ca;font-size:1.05rem;"><?= formatMontant($caTotalMarge) ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Chiffre d'affaires</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 text-center" style="background:#f8d7da;">
                    <div class="fw-bold" style="color:#842029;font-size:1.05rem;"><?= formatMontant($coutAchat) ?></div>
                    <div class="text-muted" style="font-size:.75rem;">Coût d'achat total</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 text-center" style="background:<?= $margeBrute >= 0 ? '#d1e7dd' : '#f8d7da' ?>;">
                    <div class="fw-bold" style="color:<?= $margeBrute >= 0 ? '#0a3622' : '#842029' ?>;font-size:1.05rem;">
                        <?= formatMontant($margeBrute) ?>
                    </div>
                    <div class="text-muted" style="font-size:.75rem;">Marge brute</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 text-center" style="background:<?= $tauxMarge >= 20 ? '#d1e7dd' : ($tauxMarge >= 10 ? '#fff3cd' : '#f8d7da') ?>;">
                    <div class="fw-bold" style="color:<?= $tauxMarge >= 20 ? '#0a3622' : ($tauxMarge >= 10 ? '#664d03' : '#842029') ?>;font-size:1.3rem;">
                        <?= $tauxMarge ?> %
                    </div>
                    <div class="text-muted" style="font-size:.75rem;">Taux de marge</div>
                    <div style="font-size:.7rem;margin-top:2px;color:<?= $tauxMarge >= 20 ? '#198754' : ($tauxMarge >= 10 ? '#b8860b' : '#dc3545') ?>;">
                        <?php if ($tauxMarge >= 20): ?>Bonne marge
                        <?php elseif ($tauxMarge >= 10): ?>Marge correcte
                        <?php elseif ($tauxMarge > 0): ?>Marge faible
                        <?php else: ?>Aucune donnée<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barre de progression marge visuelle -->
        <?php if ($caTotalMarge > 0): ?>
        <div class="mb-4">
            <div class="d-flex justify-content-between mb-1" style="font-size:.82rem;">
                <span class="text-muted">Répartition CA</span>
                <span class="fw-semibold"><?= $tauxMarge ?>% de marge</span>
            </div>
            <div class="progress" style="height:18px;border-radius:8px;">
                <div class="progress-bar" style="width:<?= min(100, max(0, $tauxMarge)) ?>%;background:#198754;border-radius:8px 0 0 8px;font-size:.7rem;">
                    <?= $tauxMarge > 5 ? $tauxMarge.'%' : '' ?>
                </div>
                <div class="progress-bar" style="width:<?= max(0, 100 - $tauxMarge) ?>%;background:#dc3545;border-radius:0 8px 8px 0;font-size:.7rem;">
                    <?= (100 - $tauxMarge) > 5 ? (100 - $tauxMarge).'% coût' : '' ?>
                </div>
            </div>
            <div class="d-flex justify-content-between mt-1" style="font-size:.72rem;color:#64748b;">
                <span style="color:#198754;">Marge : <?= formatMontant($margeBrute) ?></span>
                <span style="color:#dc3545;">Coût : <?= formatMontant($coutAchat) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tableau marge par produit -->
        <div class="table-responsive">
            <table class="table align-middle mb-0" style="font-size:.85rem;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th class="ps-3">#</th>
                        <th>Produit</th>
                        <th class="text-center">Qté</th>
                        <th class="text-end">CA</th>
                        <th class="text-end">Coût achat</th>
                        <th class="text-end">Marge brute</th>
                        <th class="text-center">Taux</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($margeProduits)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Aucune vente sur cette période.</td></tr>
                <?php else: ?>
                    <?php foreach ($margeProduits as $i => $mp): ?>
                    <?php
                        $tm   = (float)$mp['taux_marge'];
                        $clrTaux = $tm >= 20 ? '#198754' : ($tm >= 10 ? '#b8860b' : '#dc3545');
                        $bgTaux  = $tm >= 20 ? '#d1e7dd' : ($tm >= 10 ? '#fff3cd' : '#f8d7da');
                    ?>
                    <tr>
                        <td class="ps-3 text-muted" style="font-size:.8rem;"><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= clean($mp['nom']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill"><?= $mp['qte_vendue'] ?></span>
                        </td>
                        <td class="text-end text-success fw-semibold"><?= formatMontant($mp['ca']) ?></td>
                        <td class="text-end text-danger"><?= formatMontant($mp['cout']) ?></td>
                        <td class="text-end fw-bold" style="color:<?= $clrTaux ?>;">
                            <?= formatMontant($mp['marge']) ?>
                        </td>
                        <td class="text-center">
                            <span class="badge" style="background:<?= $bgTaux ?>;color:<?= $clrTaux ?>;">
                                <?= $tm ?> %
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Top produits détail -->
<div class="card">
    <div class="card-header bg-white fw-bold"><i class="bi bi-list-ol me-2" style="color:var(--bleu);"></i>Détail top produits</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th class="ps-3">#</th><th>Produit</th><th class="text-center">Qté vendue</th><th class="text-end">CA généré</th><th class="text-end">Marge</th><th class="text-center">Taux</th></tr>
            </thead>
            <tbody>
            <?php if (empty($topProduits)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aucune vente sur cette période.</td></tr>
            <?php else: ?>
                <?php foreach ($topProduits as $i => $p): ?>
                <?php
                    $tm  = (float)$p['taux_marge'];
                    $clr = $tm >= 20 ? '#198754' : ($tm >= 10 ? '#b8860b' : '#dc3545');
                    $bg  = $tm >= 20 ? '#d1e7dd' : ($tm >= 10 ? '#fff3cd' : '#f8d7da');
                ?>
                <tr>
                    <td class="ps-3">
                        <?php if ($i < 3): ?>
                            <i class="bi bi-trophy-fill text-<?= ['warning','secondary','danger'][$i] ?>"></i>
                        <?php else: ?>
                            <span class="text-muted"><?= $i+1 ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-semibold"><?= clean($p['nom']) ?></td>
                    <td class="text-center"><span class="badge bg-primary rounded-pill"><?= $p['qte_vendue'] ?></span></td>
                    <td class="text-end text-success fw-semibold"><?= formatMontant($p['ca']) ?></td>
                    <td class="text-end fw-semibold" style="color:<?= $clr ?>;"><?= formatMontant($p['marge']) ?></td>
                    <td class="text-center">
                        <span class="badge" style="background:<?= $bg ?>;color:<?= $clr ?>;"><?= $tm ?> %</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const BLUE = '<?= $_SESSION["theme_couleur"] !== null ? ($_SESSION["theme_couleur"] === "vert" ? "#16a34a" : ($_SESSION["theme_couleur"] === "violet" ? "#7c3aed" : ($_SESSION["theme_couleur"] === "orange" ? "#ea580c" : ($_SESSION["theme_couleur"] === "rouge" ? "#dc2626" : "#1F5C99")))) : "#1F5C99" ?>';

// Graphique CA par jour
new Chart(document.getElementById('caJourChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelsJour) ?>,
        datasets: [{ label:'CA (FCFA)', data: <?= json_encode($valeursJour) ?>, backgroundColor:'rgba(31,92,153,.7)', borderColor:BLUE, borderWidth:2, borderRadius:6 }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{ y:{ beginAtZero:true, ticks:{ callback: v=>v.toLocaleString('fr-FR') } } } }
});

// Graphique par heure
new Chart(document.getElementById('heureChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labelsHeure) ?>,
        datasets: [{ label:'CA', data: <?= json_encode($valeursHeure) ?>, fill:true, backgroundColor:'rgba(31,92,153,.1)', borderColor:BLUE, tension:.4, pointBackgroundColor:BLUE }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{ y:{ beginAtZero:true } } }
});

// Top produits
new Chart(document.getElementById('topProdChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelsProd) ?>,
        datasets: [{ label:'Qté vendue', data: <?= json_encode($valeursProd) ?>,
            backgroundColor: ['<?= $_SESSION["theme_couleur"] !== null ? ($_SESSION["theme_couleur"] === "vert" ? "#16a34a" : ($_SESSION["theme_couleur"] === "violet" ? "#7c3aed" : ($_SESSION["theme_couleur"] === "orange" ? "#ea580c" : ($_SESSION["theme_couleur"] === "rouge" ? "#dc2626" : "#1F5C99")))) : "#1F5C99" ?>','#2980d4','#3498db','#5dade2','#7fb3d3','#a8d2eb','#c8e3f4','#d6e4f0','#e8f1f8','#f5f8fc'],
            borderRadius:6 }]
    },
    options: {
        indexAxis:'y', responsive:true,
        plugins:{legend:{display:false}},
        scales:{ x:{ beginAtZero:true } }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>