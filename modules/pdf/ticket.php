<?php
// ============================================================
//  modules/pdf/ticket.php — Ticket de caisse PDF
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/Vente.php';
requireLogin();
if (!peutFaire('ventes','voir')) redirect(APP_URL . '/includes/acces_refuse.php');

$id    = (int)($_GET['id'] ?? 0);
$vente = (new Vente())->getById($id);
if (!$vente) { header('Location: '.APP_URL.'/modules/ventes/historique.php'); exit; }

$lignes = $vente['lignes'];
$sousTotal = array_sum(array_map(fn($l) => $l['sous_total'], $lignes));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Ticket <?= clean($vente['reference']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    background: #fff;
    color: #000;
    width: 80mm;
    margin: 0 auto;
    padding: 8px;
}
.center  { text-align: center; }
.right   { text-align: right; }
.bold    { font-weight: bold; }
.big     { font-size: 14px; }
.sep     { border-top: 1px dashed #000; margin: 6px 0; }
.sep2    { border-top: 2px solid #000; margin: 6px 0; }
.row     { display: flex; justify-content: space-between; margin: 2px 0; }
.row-item{ margin: 3px 0; }
.row-item .nom { font-weight: bold; }
.row-item .detail { color: #555; padding-left: 8px; }
.badge-statut {
    display: inline-block;
    border: 1px solid #000;
    padding: 1px 6px;
    font-size: 10px;
    margin-top: 4px;
}
.note-box {
    border: 1px dashed #000;
    padding: 4px;
    margin: 4px 0;
    font-style: italic;
    font-size: 10px;
}
.footer { margin-top: 8px; font-size: 10px; }
/* Impression */
@media print {
    body { width: 72mm; }
    .no-print { display: none !important; }
    @page { size: 80mm auto; margin: 0; }
}
/* Boutons écran uniquement */
.toolbar {
    width: 100%;
    max-width: 300px;
    margin: 12px auto;
    display: flex;
    gap: 8px;
    justify-content: center;
}
.btn-pdf {
    padding: 8px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: bold;
    font-family: sans-serif;
}
.btn-print { background: #1F5C99; color: #fff; }
.btn-back  { background: #e2e8f0; color: #374151; }
</style>
</head>
<body>

<!-- Barre d'actions (masquée à l'impression) -->
<div class="toolbar no-print">
    <button class="btn-pdf btn-print" onclick="window.print()">⬇ Imprimer / Enregistrer PDF</button>
    <button class="btn-pdf btn-back"  onclick="history.back()">← Retour</button>
</div>

<!-- ── Ticket ── -->
<div class="center bold big"><?= APP_NAME ?></div>
<div class="center" style="font-size:10px;">Gestion Stock & Caisse — Lomé, Togo</div>
<div class="sep2"></div>

<div class="row"><span>Ticket :</span><span class="bold"><?= clean($vente['reference']) ?></span></div>
<div class="row"><span>Date :</span><span><?= date('d/m/Y H:i', strtotime($vente['created_at'])) ?></span></div>
<div class="row"><span>Caissier :</span><span><?= clean($vente['prenom'].' '.$vente['nom']) ?></span></div>
<div class="sep"></div>

<!-- Articles -->
<?php foreach ($lignes as $l): ?>
<div class="row-item">
    <div class="row">
        <span class="nom"><?= clean($l['produit_nom']) ?></span>
        <span class="bold"><?= number_format($l['sous_total'],0,',',' ') ?> F</span>
    </div>
    <div class="detail"><?= $l['quantite'] ?> x <?= number_format($l['prix_unitaire'],0,',',' ') ?> F</div>
</div>
<?php endforeach; ?>
<div class="sep"></div>

<!-- Totaux -->
<div class="row"><span>Sous-total</span><span><?= number_format($sousTotal,0,',',' ') ?> F</span></div>
<?php if ($vente['remise'] > 0): ?>
<div class="row"><span>Remise</span><span>- <?= number_format($vente['remise'],0,',',' ') ?> F</span></div>
<?php endif; ?>
<div class="sep"></div>
<div class="row bold big"><span>TOTAL NET</span><span><?= number_format($vente['total_net'],0,',',' ') ?> F</span></div>
<div class="sep"></div>
<div class="row"><span>Montant reçu</span><span><?= number_format($vente['montant_recu'],0,',',' ') ?> F</span></div>
<div class="row bold"><span>Monnaie rendue</span><span><?= number_format($vente['monnaie_rendue'],0,',',' ') ?> F</span></div>

<?php if (!empty($vente['note'])): ?>
<div class="sep"></div>
<div class="note-box"><strong>Note :</strong> <?= clean($vente['note']) ?></div>
<?php endif; ?>

<div class="sep2"></div>
<div class="center footer">
    Merci pour votre achat !<br>
    <?= date('d/m/Y H:i:s') ?><br>
    <span class="badge-statut"><?= strtoupper($vente['statut']) ?></span>
</div>

<script>
// Auto-print si paramètre auto=1
if (new URLSearchParams(location.search).get('auto') === '1') {
    window.onload = () => setTimeout(() => window.print(), 400);
}
</script>
</body>
</html>
