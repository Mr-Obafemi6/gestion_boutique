<?php
// ============================================================
//  modules/pdf/facture.php — Facture / détail d'une vente PDF
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Vente.php';
requireLogin();
if (!peutFaire('ventes','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$id    = (int)($_GET['id'] ?? 0);
$vente = (new Vente())->getById($id);
if (!$vente) { header('Location: '.APP_URL.'/modules/ventes/historique.php'); exit; }

$lignes     = $vente['lignes'];
$sousTotal  = array_sum(array_map(fn($l) => $l['sous_total'], $lignes));

// Calcul marge si prix_achat disponible
$db = getDB();
$stmtM = $db->prepare("SELECT COALESCE(SUM(lv.quantite*(lv.prix_unitaire-p.prix_achat)),0) AS marge
    FROM lignes_vente lv JOIN produits p ON lv.produit_id=p.id WHERE lv.vente_id=:id");
$stmtM->execute([':id'=>$id]);
$margeVente = (float)$stmtM->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Facture <?= clean($vente['reference']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 11px; color: #000; background: #fff; padding: 30px; max-width: 800px; margin: 0 auto; }
.header-doc { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.logo-block .app-name { font-size: 22px; font-weight: bold; color: #1F5C99; }
.logo-block .app-sub  { font-size: 10px; color: #64748b; }
.ref-block { text-align: right; }
.ref-block .ref { font-size: 18px; font-weight: bold; color: #1F5C99; }
.ref-block .date { font-size: 10px; color: #64748b; margin-top: 3px; }
.ref-block .badge-statut {
    display: inline-block; padding: 3px 10px; border-radius: 4px;
    font-size: 10px; font-weight: bold; margin-top: 5px;
}
.b-validee { background: #d1e7dd; color: #0a3622; }
.b-annulee { background: #f8d7da; color: #842029; }
.sep { border-top: 2px solid #1F5C99; margin: 14px 0; }
.sep-light { border-top: 1px solid #e2e8f0; margin: 10px 0; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
.info-box { background: #f8fafc; border-radius: 6px; padding: 10px 14px; }
.info-box h3 { font-size: 10px; color: #64748b; text-transform: uppercase; margin-bottom: 6px; letter-spacing: .04em; }
.info-box .val { font-size: 12px; font-weight: bold; color: #153F6E; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
thead th { background: #1F5C99; color: #fff; padding: 8px 10px; text-align: left; font-size: 10px; }
thead th.r { text-align: right; }
tbody tr:nth-child(even) { background: #f8fafc; }
td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
td.r { text-align: right; }
.totaux { margin-left: auto; width: 260px; }
.totaux table { margin-bottom: 0; }
.totaux td { padding: 5px 10px; border: none; }
.totaux tr.total-final td { font-size: 14px; font-weight: bold; color: #1F5C99; background: #EBF3FB; border-radius: 4px; padding: 8px 10px; }
.note-section { background: #f8fafc; border-left: 3px solid #1F5C99; padding: 8px 12px; margin-top: 12px; font-style: italic; font-size: 10px; color: #475569; }
.footer { margin-top: 24px; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; display: flex; justify-content: space-between; }
@media print {
    body { padding: 10px; }
    .no-print { display: none !important; }
    @page { size: A4; margin: 15mm; }
}
.toolbar { margin-bottom: 20px; display: flex; gap: 8px; font-family: Arial; }
.btn { padding: 7px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; }
.btn-blue { background: #1F5C99; color: #fff; }
.btn-gray { background: #e2e8f0; color: #374151; }
</style>
</head>
<body>

<div class="toolbar no-print">
    <button class="btn btn-blue" onclick="window.print()">⬇ Imprimer / Enregistrer PDF</button>
    <button class="btn btn-gray" onclick="history.back()">← Retour</button>
</div>

<!-- En-tête document -->
<div class="header-doc">
    <div class="logo-block">
        <div class="app-name"><?= APP_NAME ?></div>
        <div class="app-sub">Gestion Stock & Caisse — Lomé, Togo</div>
    </div>
    <div class="ref-block">
        <div class="ref"><?= clean($vente['reference']) ?></div>
        <div class="date"><?= date('d/m/Y à H:i', strtotime($vente['created_at'])) ?></div>
        <div>
            <span class="badge-statut <?= $vente['statut']==='validee' ? 'b-validee' : 'b-annulee' ?>">
                <?= strtoupper($vente['statut']) ?>
            </span>
        </div>
    </div>
</div>
<div class="sep"></div>

<!-- Infos -->
<div class="info-grid">
    <div class="info-box">
        <h3>Caissier</h3>
        <div class="val"><?= clean($vente['prenom'].' '.$vente['nom']) ?></div>
    </div>
    <div class="info-box">
        <h3>Date & heure</h3>
        <div class="val"><?= date('d/m/Y H:i:s', strtotime($vente['created_at'])) ?></div>
    </div>
</div>

<!-- Articles -->
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Référence</th>
            <th>Produit</th>
            <th class="r">Prix unitaire</th>
            <th class="r">Quantité</th>
            <th class="r">Sous-total</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($lignes as $i => $l): ?>
    <tr>
        <td style="color:#94a3b8;"><?= $i + 1 ?></td>
        <td><code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;font-size:9px;"><?= clean($l['reference']) ?></code></td>
        <td><strong><?= clean($l['produit_nom']) ?></strong></td>
        <td class="r"><?= number_format($l['prix_unitaire'],0,',',' ') ?> F</td>
        <td class="r"><?= $l['quantite'] ?></td>
        <td class="r"><strong><?= number_format($l['sous_total'],0,',',' ') ?> F</strong></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Totaux -->
<div class="totaux">
    <table>
        <tr>
            <td style="color:#64748b;">Sous-total HT</td>
            <td class="r"><?= number_format($sousTotal,0,',',' ') ?> F</td>
        </tr>
        <?php if ($vente['remise'] > 0): ?>
        <tr>
            <td style="color:#ea580c;">Remise</td>
            <td class="r" style="color:#ea580c;">- <?= number_format($vente['remise'],0,',',' ') ?> F</td>
        </tr>
        <?php endif; ?>
        <tr class="total-final">
            <td>TOTAL NET</td>
            <td class="r"><?= number_format($vente['total_net'],0,',',' ') ?> F</td>
        </tr>
    </table>
    <div class="sep-light"></div>
    <table>
        <tr>
            <td style="color:#64748b;">Montant reçu</td>
            <td class="r"><?= number_format($vente['montant_recu'],0,',',' ') ?> F</td>
        </tr>
        <tr>
            <td style="color:#64748b;">Monnaie rendue</td>
            <td class="r"><?= number_format($vente['monnaie_rendue'],0,',',' ') ?> F</td>
        </tr>
        <?php if ($margeVente > 0): ?>
        <tr>
            <td style="color:#198754;">Marge brute</td>
            <td class="r" style="color:#198754;"><?= number_format($margeVente,0,',',' ') ?> F</td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<!-- Note -->
<?php if (!empty($vente['note'])): ?>
<div class="note-section">
    <strong style="font-style:normal;">Note :</strong> <?= clean($vente['note']) ?>
</div>
<?php endif; ?>

<div class="footer">
    <span><?= APP_NAME ?> v<?= APP_VERSION ?></span>
    <span>Exporté le <?= date('d/m/Y H:i:s') ?></span>
    <span>Document généré automatiquement</span>
</div>

<script>
if (new URLSearchParams(location.search).get('auto') === '1') {
    window.onload = () => setTimeout(() => window.print(), 400);
}
</script>
</body>
</html>
