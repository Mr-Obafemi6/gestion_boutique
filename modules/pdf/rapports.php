<?php
// ============================================================
//  modules/pdf/rapports.php — Export PDF rapports & stats
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Vente.php';
requireLogin();
if (!peutFaire('rapports','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$db        = getDB();
$dateDebut = clean($_GET['dd'] ?? date('Y-m-01'));
$dateFin   = clean($_GET['df'] ?? date('Y-m-d'));

// Stats générales
$stmtS = $db->prepare("SELECT COUNT(id) AS nb_ventes, COALESCE(SUM(total_net),0) AS ca_total,
    COALESCE(AVG(total_net),0) AS ca_moyen, COALESCE(SUM(remise),0) AS total_remises
    FROM ventes WHERE statut='validee' AND DATE(created_at) BETWEEN :dd AND :df");
$stmtS->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
$stats = $stmtS->fetch();

// Marge bénéficiaire
$stmtM = $db->prepare("SELECT COALESCE(SUM(lv.quantite*(lv.prix_unitaire-p.prix_achat)),0) AS marge_brute,
    COALESCE(SUM(lv.quantite*lv.prix_unitaire),0) AS ca_vente,
    COALESCE(SUM(lv.quantite*p.prix_achat),0) AS cout_achat
    FROM lignes_vente lv JOIN produits p ON lv.produit_id=p.id JOIN ventes v ON lv.vente_id=v.id
    WHERE v.statut='validee' AND DATE(v.created_at) BETWEEN :dd AND :df");
$stmtM->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
$marge = $stmtM->fetch();
$tauxMarge = $marge['ca_vente'] > 0 ? round($marge['marge_brute']/$marge['ca_vente']*100,1) : 0;

// Top 10 produits
$stmtT = $db->prepare("SELECT p.nom, SUM(lv.quantite) AS qte, SUM(lv.sous_total) AS ca,
    SUM(lv.quantite*(lv.prix_unitaire-p.prix_achat)) AS marge,
    CASE WHEN SUM(lv.sous_total)>0
    THEN ROUND(SUM(lv.quantite*(lv.prix_unitaire-p.prix_achat))/SUM(lv.sous_total)*100,1) ELSE 0 END AS taux
    FROM lignes_vente lv JOIN produits p ON lv.produit_id=p.id JOIN ventes v ON lv.vente_id=v.id
    WHERE v.statut='validee' AND DATE(v.created_at) BETWEEN :dd AND :df
    GROUP BY p.id ORDER BY ca DESC LIMIT 10");
$stmtT->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
$topProduits = $stmtT->fetchAll();

// Par caissier
$stmtC = $db->prepare("SELECT u.nom, u.prenom, COUNT(v.id) AS nb_ventes, SUM(v.total_net) AS ca
    FROM ventes v JOIN utilisateurs u ON v.utilisateur_id=u.id
    WHERE v.statut='validee' AND DATE(v.created_at) BETWEEN :dd AND :df
    GROUP BY u.id ORDER BY ca DESC");
$stmtC->execute([':dd'=>$dateDebut,':df'=>$dateFin]);
$parCaissier = $stmtC->fetchAll();

// Valeur stock
$stmtV = $db->prepare("SELECT SUM(stock_actuel*prix_achat) FROM produits WHERE statut='actif'");
$stmtV->execute();
$valeurStock = (float)$stmtV->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport — <?= APP_NAME ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 10px; color: #000; background: #fff; padding: 20px; }
h1   { font-size: 18px; color: #1F5C99; }
h2   { font-size: 12px; color: #1F5C99; margin: 16px 0 6px; border-bottom: 2px solid #1F5C99; padding-bottom: 3px; }
.subtitle { color: #64748b; font-size: 9px; margin: 3px 0 14px; }
.kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 14px; }
.kpi { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; }
.kpi .val { font-size: 14px; font-weight: bold; }
.kpi .lbl { font-size: 8px; color: #64748b; }
.kpi.blue  .val { color: #1F5C99; }
.kpi.green .val { color: #198754; }
.kpi.orange.val { color: #ea580c; }
.kpi.purple .val { color: #6f42c1; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 8px; }
table { width: 100%; border-collapse: collapse; font-size: 9px; }
thead th { background: #1F5C99; color: #fff; padding: 5px 7px; text-align: left; }
th.r, td.r { text-align: right; }
th.c, td.c { text-align: center; }
tbody tr:nth-child(even) { background: #f8fafc; }
td { padding: 4px 7px; border-bottom: 1px solid #e2e8f0; }
.marge-bar { background: #e2e8f0; border-radius: 4px; height: 8px; margin: 12px 0 6px; }
.marge-fill { background: #198754; height: 100%; border-radius: 4px; }
.badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; }
.b-g { background: #d1e7dd; color: #0a3622; }
.b-y { background: #fff3cd; color: #664d03; }
.b-r { background: #f8d7da; color: #842029; }
.footer { margin-top: 16px; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
@media print {
    body { padding: 8px; }
    .no-print { display: none !important; }
    @page { size: A4; margin: 12mm; }
    .two-col { page-break-inside: avoid; }
    h2 { page-break-before: auto; }
    .pagebreak { page-break-before: always; }
}
.toolbar { margin-bottom: 14px; display: flex; gap: 8px; font-family: Arial; }
.btn { padding: 6px 14px; border: none; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: bold; }
.btn-blue { background: #1F5C99; color: #fff; }
.btn-gray { background: #e2e8f0; color: #374151; }
</style>
</head>
<body>

<div class="toolbar no-print">
    <button class="btn btn-blue" onclick="window.print()">⬇ Imprimer / Enregistrer PDF</button>
    <button class="btn btn-gray" onclick="history.back()">← Retour</button>
</div>

<h1><?= APP_NAME ?> — Rapport de gestion</h1>
<div class="subtitle">
    Période : <?= date('d/m/Y', strtotime($dateDebut)) ?> → <?= date('d/m/Y', strtotime($dateFin)) ?>
    · Généré le <?= date('d/m/Y à H:i') ?>
</div>

<!-- KPIs -->
<h2>Indicateurs clés</h2>
<div class="kpi-grid">
    <div class="kpi blue"><div class="val"><?= number_format($stats['ca_total'],0,',',' ') ?> F</div><div class="lbl">CA total</div></div>
    <div class="kpi green"><div class="val"><?= $stats['nb_ventes'] ?></div><div class="lbl">Nb ventes</div></div>
    <div class="kpi orange"><div class="val"><?= number_format($stats['ca_moyen'],0,',',' ') ?> F</div><div class="lbl">Panier moyen</div></div>
    <div class="kpi purple"><div class="val"><?= number_format($stats['total_remises'],0,',',' ') ?> F</div><div class="lbl">Total remises</div></div>
    <div class="kpi blue"><div class="val"><?= number_format($marge['marge_brute'],0,',',' ') ?> F</div><div class="lbl">Marge brute</div></div>
    <div class="kpi green"><div class="val"><?= $tauxMarge ?> %</div><div class="lbl">Taux de marge</div></div>
    <div class="kpi orange"><div class="val"><?= number_format($marge['cout_achat'],0,',',' ') ?> F</div><div class="lbl">Coût d'achat</div></div>
    <div class="kpi blue"><div class="val"><?= number_format($valeurStock,0,',',' ') ?> F</div><div class="lbl">Valeur stock</div></div>
</div>

<!-- Barre marge -->
<div style="display:flex;justify-content:space-between;font-size:9px;color:#64748b;margin-bottom:3px;">
    <span>Répartition CA : marge vs coût</span>
    <span>Marge <?= $tauxMarge ?>% — Coût <?= 100-$tauxMarge ?>%</span>
</div>
<div class="marge-bar">
    <div class="marge-fill" style="width:<?= min(100,max(0,$tauxMarge)) ?>%;"></div>
</div>

<!-- Top produits + Par caissier -->
<div class="two-col">
    <div>
        <h2>Top 10 produits</h2>
        <table>
            <thead><tr><th>#</th><th>Produit</th><th class="r">CA</th><th class="r">Marge</th><th class="c">Taux</th></tr></thead>
            <tbody>
            <?php foreach ($topProduits as $i => $p):
                $t = (float)$p['taux'];
                $bc = $t >= 20 ? 'b-g' : ($t >= 10 ? 'b-y' : 'b-r');
            ?>
            <tr>
                <td class="c" style="color:#94a3b8;"><?= $i+1 ?></td>
                <td><?= clean($p['nom']) ?></td>
                <td class="r"><?= number_format($p['ca'],0,',',' ') ?> F</td>
                <td class="r" style="color:#198754;"><?= number_format($p['marge'],0,',',' ') ?> F</td>
                <td class="c"><span class="badge <?= $bc ?>"><?= $t ?>%</span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topProduits)): ?>
            <tr><td colspan="5" style="text-align:center;padding:10px;color:#94a3b8;">Aucune donnée</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div>
        <h2>Performance par caissier</h2>
        <table>
            <thead><tr><th>Caissier</th><th class="c">Ventes</th><th class="r">CA</th></tr></thead>
            <tbody>
            <?php foreach ($parCaissier as $c): ?>
            <tr>
                <td><?= clean($c['prenom'].' '.$c['nom']) ?></td>
                <td class="c"><?= $c['nb_ventes'] ?></td>
                <td class="r"><strong><?= number_format($c['ca'],0,',',' ') ?> F</strong></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($parCaissier)): ?>
            <tr><td colspan="3" style="text-align:center;padding:10px;color:#94a3b8;">Aucune donnée</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="footer">
    <?= APP_NAME ?> v<?= APP_VERSION ?> · Exporté le <?= date('d/m/Y H:i:s') ?>
</div>

<script>
if (new URLSearchParams(location.search).get('auto') === '1') {
    window.onload = () => setTimeout(() => window.print(), 400);
}
</script>
</body>
</html>
