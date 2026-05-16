# Guide d'intégration — Vente en Gros + Clients + Fidélité
## Projet `gestion_boutique`

---

## 📁 Fichiers livrés

| Fichier | Rôle |
|---|---|
| `migration_clients_gros.sql` | Script SQL à exécuter en premier |
| `Client.php` | Nouvelle classe — copier dans `includes/` |
| `Vente.php` | Remplace `includes/Vente.php` |
| `Produit.php` | Remplace `includes/Produit.php` |
| `caisse_patch.html` | Blocs à intégrer dans `caisse.php` |

---

## ✅ Ordre d'installation

### Étape 1 — Base de données
Exécuter `migration_clients_gros.sql` dans phpMyAdmin (base `gestion_boutique`).

Ce script ajoute :
- Table `clients` (nom, téléphone, email, type, points fidélité, crédit)
- Table `paiements_credit` (historique remboursements)
- Colonnes `prix_gros` et `qte_min_gros` dans `produits`
- Colonnes `client_id` et `type_vente` dans `ventes`
- Vues `v_historique_client` et `v_clients_credit`
- Permissions module "clients" pour tous les rôles

### Étape 2 — Classes PHP
```
Copier Client.php   → glass/gestion_boutique/includes/Client.php  (nouveau fichier)
Copier Vente.php    → glass/gestion_boutique/includes/Vente.php   (remplace l'existant)
Copier Produit.php  → glass/gestion_boutique/includes/Produit.php (remplace l'existant)
```

### Étape 3 — caisse.php

Ouvrir `caisse_patch.html` et suivre les 5 sections :

**SECTION 1** → Remplacer les ~35 premières lignes PHP de `caisse.php`

**SECTION 2** → Coller le CSS dans le bloc `<style>` existant

**SECTION 3** → Coller le HTML client AVANT `<div class="panier-items">` 
et les deux lignes fidélité/crédit dans `.panier-footer` avant le bouton valider

**SECTION 4** → Dans le bloc `<script>`, remplacer les fonctions :
- `ajouterAuPanier()`
- `renderPanier()`
- `recalculerTotaux()`
- `validerVente()`

Et ajouter les nouvelles fonctions :
- `rechercherClient()`
- `selectionnerClient()`
- `effacerClient()`
- `recalculerPrixGros()`

**SECTION 5** → Modifier le `onclick` des cartes produit (boucle PHP)

---

## 🔧 Fonctionnement

### Vente en gros automatique
- Chaque produit peut avoir un `prix_gros` et une `qte_min_gros`
- Quand la quantité dans le panier ≥ `qte_min_gros` → le prix bascule automatiquement sur `prix_gros`
- Un badge **GROS** (violet) apparaît sur la ligne du panier
- La vente est enregistrée avec `type_vente = 'gros'`

### Saisie prix gros dans les produits
Dans `modules/produits/liste.php`, dans le formulaire de création/modification, 
ajouter deux champs :
```html
<label>Prix de gros (FCFA)</label>
<input type="number" name="prix_gros" placeholder="Laisser vide si pas de tarif gros">

<label>Quantité minimum pour tarif gros</label>
<input type="number" name="qte_min_gros" value="0" min="0">
```

### Client à la caisse
1. Le caissier tape le nom ou téléphone dans la boîte client
2. L'autocomplete cherche en AJAX (`?action=search_client&q=...`)
3. Le client sélectionné affiche : dette en cours, points fidélité
4. Si le client a des points → une remise fidélité est proposée
5. Si paiement partiel → saisir le montant à crédit

### Fidélité
- Calcul : 1 point par tranche de 1 000 FCFA payés en cash
- Valeur : 1 point = 10 FCFA de remise
- Constantes modifiables dans `Client.php` : `POINTS_PAR_1000` et `VALEUR_POINT`

### Crédit / dette
- Le plafond de crédit se configure dans la fiche client (`credit_autorise`)
- La vente est bloquée si la nouvelle dette dépasse le plafond
- Pour rembourser : utiliser `$clientModel->rembourser($clientId, $montant)`
- Créer un module `modules/clients/index.php` pour gérer les clients et encaisser les remboursements

---

## 📋 Module clients à créer

Il reste à créer `modules/clients/index.php` avec :
- Liste des clients (filtrable par type : particulier / grossiste)
- Fiche client : historique achats, points, dette
- Formulaire ajout / modification client
- Bouton "Encaisser un remboursement"
- Onglet "Clients en crédit" (utilise la vue `v_clients_credit`)

La classe `Client.php` fournie contient toutes les méthodes nécessaires.

---

## 🗄️ Résumé des changements en base

```sql
-- Nouvelles tables
clients
paiements_credit

-- Colonnes ajoutées
produits.prix_gros
produits.qte_min_gros
ventes.client_id
ventes.type_vente

-- Nouvelles vues
v_historique_client
v_clients_credit

-- Permissions
clients (voir/creer/modifier/supprimer/exporter) × 3 rôles
```
