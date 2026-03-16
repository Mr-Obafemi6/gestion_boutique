<?php
// ============================================================
//  modules/pdf/historique.php — Export PDF historique ventes
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Vente.php';
requireLogin();
if (!peutFaire('ventes','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$venteModel = new Vente();
$dateDebut  = clean($_GET['dd'] ?? date('Y-m-01'));
$dateFin    = clean($_GET['df'] ?? date('Y-m-d'));
$ventes     = $venteModel->getHistorique($dateDebut, $dateFin);

$totalCA     = array_sum(array_column($ventes, 'total_net'));
$totalRemise = array_sum(array_column($ventes, 'remise'));
$nbVentes    = count($ventes);
$panierMoyen = $nbVentes > 0 ? $totalCA / $nbVentes : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Historique ventes — <?= APP_NAME ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 10px; color: #000; background: #fff; padding: 20px; }
h1  { font-size: 16px; color: #1F5C99; margin-bottom: 3px; }
.subtitle { color: #64748b; font-size: 9px; margin-bottom: 14px; }
.stats-row { display: flex; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.stat-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 7px 12px; min-width: 120px; }
.stat-box .val { font-size: 14px; font-weight: bold; color: #1F5C99; }
.stat-box .lbl { font-size: 9px; color: #64748b; }
table { width: 100%; border-collapse: collapse; font-size: 9px; }
thead th { background: #1F5C99; color: #fff; padding: 5px 7px; text-align: left; }
thead th.r { text-align: right; }
thead th.c { text-align: center; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody tr.annulee { background: #fff8f8; color: #94a3b8; }
td { padding: 4px 7px; border-bottom: 1px solid #e2e8f0; }
td.r { text-align: right; }
td.c { text-align: center; }
tfoot td { background: #f1f5f9; font-weight: bold; padding: 5px 7px; border-top: 2px solid #1F5C99; }
.badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; font-weight: bold; }
.b-ok   { background: #d1e7dd; color: #0a3622; }
.b-ann  { background: #f8d7da; color: #842029; }
.footer { margin-top: 12px; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
@media print {
    body { padding: 8px; }
    .no-print { display: none !important; }
    @page { size: A4 landscape; margin: 12mm; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
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

<h1><?= APP_NAME ?> — Historique des ventes</h1>
<div class="subtitle">
    Période : <?= date('d/m/Y', strtotime($dateDebut)) ?> → <?= date('d/m/Y', strtotime($dateFin)) ?>
    · Généré le <?= date('d/m/Y à H:i') ?>
</div>

<!-- KPIs -->
<div class="stats-row">
    <div class="stat-box">
        <div class="val"><?= $nbVentes ?></div>
        <div class="lbl">Nb ventes</div>
    </div>
    <div class="stat-box">
        <div class="val"><?= number_format($totalCA,0,',',' ') ?> F</div>
        <div class="lbl">CA total</div>
    </div>
    <div class="stat-box">
        <div class="val"><?= number_format($panierMoyen,0,',',' ') ?> F</div>
        <div class="lbl">Panier moyen</div>
    </div>
    <div class="stat-box">
        <div class="val" style="color:#6f42c1;"><?= number_format($totalRemise,0,',',' ') ?> F</div>
        <div class="lbl">Total remises</div>
    </div>
</div>

<!-- Tableau -->
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Référence</th>
            <th>Date & Heure</th>
            <th>Caissier</th>
            <th class="c">Articles</th>
            <th class="r">Remise</th>
            <th class="r">Total net</th>
            <th>Note</th>
            <th class="c">Statut</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($ventes as $i => $v): ?>
    <tr class="<?= $v['statut'] === 'annulee' ? 'annulee' : '' ?>">
        <td style="color:#94a3b8;"><?= $i + 1 ?></td>
        <td><strong><?= clean($v['reference']) ?></strong></td>
        <td><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></td>
        <td><?= clean($v['prenom'].' '.$v['nom']) ?></td>
        <td class="c"><?= $v['nb_articles'] ?></td>
        <td class="r"><?= $v['remise'] > 0 ? '-'.number_format($v['remise'],0,',',' ').' F' : '—' ?></td>
        <td class="r"><strong><?= number_format($v['total_net'],0,',',' ') ?> F</strong></td>
        <td style="font-style:italic;color:#64748b;max-width:120px;">
            <?= !empty($v['note']) ? clean(mb_substr($v['note'],0,40)).(mb_strlen($v['note'])>40?'…':'') : '' ?>
        </td>
        <td class="c">
            <span class="badge <?= $v['statut'] === 'validee' ? 'b-ok' : 'b-ann' ?>">
                <?= ucfirst($v['statut']) ?>
            </span>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($ventes)): ?>
    <tr><td colspan="9" style="text-align:center;padding:16px;color:#94a3b8;">Aucune vente sur cette période.</td></tr>
    <?php endif; ?>
    </tbody>
    <?php if (!empty($ventes)): ?>
    <tfoot>
        <tr>
            <td colspan="6" style="text-align:right;">TOTAL PÉRIODE :</td>
            <td class="r"><?= number_format($totalCA,0,',',' ') ?> F</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<div class="footer">
    <?= APP_NAME ?> v<?= APP_VERSION ?> · Exporté le <?= date('d/m/Y H:i:s') ?> · <?= $nbVentes ?> vente(s)
</div>

<script>
if (new URLSearchParams(location.search).get('auto') === '1') {
    window.onload = () => setTimeout(() => window.print(), 400);
}
</script>
</body>
</html>
