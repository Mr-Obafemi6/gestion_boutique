<?php
// ============================================================
//  modules/pdf/produits.php — Export PDF liste des produits
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Produit.php';
requireLogin();
if (!peutFaire('produits','exporter')) redirect(APP_URL . '/includes/acces_refuse.php');

$produitModel = new Produit();
$search       = clean($_GET['q']   ?? '');
$catFilter    = (int)($_GET['cat'] ?? 0);
$produits     = $produitModel->getAll($search, $catFilter); // tous, sans pagination
$categories   = $produitModel->getCategories();
$catNom       = '';
foreach ($categories as $c) {
    if ($c['id'] == $catFilter) { $catNom = $c['nom']; break; }
}

$nbCritiques = count(array_filter($produits, fn($p) => $p['stock_actuel'] <= $p['seuil_alerte']));
$valeurStock = array_sum(array_map(fn($p) => $p['stock_actuel'] * $p['prix_achat'], $produits));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Produits — <?= APP_NAME ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 11px; color: #000; background: #fff; padding: 20px; }
h1  { font-size: 18px; color: #1F5C99; margin-bottom: 4px; }
.subtitle { color: #64748b; font-size: 10px; margin-bottom: 16px; }
.stats-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.stat-box {
    border: 1px solid #e2e8f0; border-radius: 6px;
    padding: 8px 14px; min-width: 130px;
}
.stat-box .val { font-size: 16px; font-weight: bold; color: #1F5C99; }
.stat-box .lbl { font-size: 9px; color: #64748b; }
table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
thead th {
    background: #1F5C99; color: #fff;
    padding: 6px 8px; text-align: left; font-size: 10px;
}
thead th.r { text-align: right; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody tr.critique { background: #fef2f2; }
td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
td.r { text-align: right; }
td.c { text-align: center; }
.badge {
    display: inline-block; padding: 2px 7px; border-radius: 10px;
    font-size: 9px; font-weight: bold;
}
.b-ok      { background: #d1e7dd; color: #0a3622; }
.b-crit    { background: #f8d7da; color: #842029; }
.b-cat     { background: #dbeafe; color: #1e40af; }
.footer { margin-top: 16px; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
/* Impression */
@media print {
    body { padding: 10px; }
    .no-print { display: none !important; }
    @page { size: A4 landscape; margin: 15mm; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
}
.toolbar {
    margin-bottom: 16px; display: flex; gap: 8px;
    font-family: Arial, sans-serif;
}
.btn { padding: 7px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; }
.btn-blue  { background: #1F5C99; color: #fff; }
.btn-gray  { background: #e2e8f0; color: #374151; }
</style>
</head>
<body>

<div class="toolbar no-print">
    <button class="btn btn-blue" onclick="window.print()">⬇ Imprimer / Enregistrer PDF</button>
    <button class="btn btn-gray" onclick="history.back()">← Retour</button>
</div>

<!-- En-tête -->
<h1><?= APP_NAME ?> — Catalogue produits</h1>
<div class="subtitle">
    Généré le <?= date('d/m/Y à H:i') ?>
    <?= $search ? ' · Recherche : "'.clean($search).'"' : '' ?>
    <?= $catNom  ? ' · Catégorie : '.$catNom : '' ?>
</div>

<!-- Stats -->
<div class="stats-row">
    <div class="stat-box">
        <div class="val"><?= count($produits) ?></div>
        <div class="lbl">Produits actifs</div>
    </div>
    <div class="stat-box">
        <div class="val" style="color:#dc3545;"><?= $nbCritiques ?></div>
        <div class="lbl">Stocks critiques</div>
    </div>
    <div class="stat-box">
        <div class="val"><?= number_format($valeurStock,0,',',' ') ?> F</div>
        <div class="lbl">Valeur totale stock</div>
    </div>
</div>

<!-- Tableau -->
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Référence</th>
            <th>Produit</th>
            <th>Catégorie</th>
            <th class="r">Prix achat</th>
            <th class="r">Prix vente</th>
            <th class="r">Marge</th>
            <th class="c">Stock</th>
            <th class="c">Statut</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($produits as $i => $p):
        $critique = $p['stock_actuel'] <= $p['seuil_alerte'];
        $marge    = $p['prix_vente'] - $p['prix_achat'];
        $taux     = $p['prix_vente'] > 0 ? round($marge / $p['prix_vente'] * 100) : 0;
    ?>
    <tr class="<?= $critique ? 'critique' : '' ?>">
        <td class="c" style="color:#94a3b8;"><?= $i + 1 ?></td>
        <td><strong><?= clean($p['reference']) ?></strong></td>
        <td><?= clean($p['nom']) ?></td>
        <td><span class="badge b-cat"><?= clean($p['categorie_nom']) ?></span></td>
        <td class="r"><?= number_format($p['prix_achat'],0,',',' ') ?> F</td>
        <td class="r"><strong><?= number_format($p['prix_vente'],0,',',' ') ?> F</strong></td>
        <td class="r" style="color:<?= $marge >= 0 ? '#198754' : '#dc3545' ?>;">
            <?= number_format($marge,0,',',' ') ?> F
            <small style="color:#94a3b8;">(<?= $taux ?>%)</small>
        </td>
        <td class="c">
            <strong><?= $p['stock_actuel'] ?></strong>
            <small style="color:#94a3b8;"><?= clean($p['unite_mesure']) ?></small>
        </td>
        <td class="c">
            <span class="badge <?= $critique ? 'b-crit' : 'b-ok' ?>">
                <?= $critique ? 'Critique' : 'OK' ?>
            </span>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($produits)): ?>
    <tr><td colspan="9" style="text-align:center;padding:20px;color:#94a3b8;">Aucun produit trouvé.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="footer">
    <?= APP_NAME ?> v<?= APP_VERSION ?> · Exporté le <?= date('d/m/Y H:i:s') ?> · <?= count($produits) ?> produit(s)
</div>

<script>
if (new URLSearchParams(location.search).get('auto') === '1') {
    window.onload = () => setTimeout(() => window.print(), 400);
}
</script>
</body>
</html>
