# Module Clients - Documentation

## 📋 Vue d'ensemble

Le module **Clients** permet de gérer complètement les clients de la boutique avec :
- ✅ Liste des clients (filtrable par type et recherche)
- ✅ Fiche client détaillée avec historique et statistiques
- ✅ Formulaire d'ajout/modification de clients
- ✅ Gestion des remboursements et du crédit
- ✅ Onglet "Clients en crédit" avec vue d'ensemble

---

## 🎯 Fonctionnalités principales

### 1. **Liste des clients** (`?action=list`)
- Vue en grille avec cards interactives
- Recherche par nom ou téléphone (temps réel)
- Filtre par type (particulier / grossiste)
- Affichage rapide : points, crédit autorisé, dette
- Clic pour voir la fiche complète

### 2. **Fiche client** (`?action=view&id=N`)
- **Bloc d'en-tête** : informations principales (nom, type, contact)
- **Indicateurs** : points fidélité, dette, crédit autorisé
- **Statistiques** : nb achats, total dépensé, plus grosse vente, dates
- **Onglet "Achats"** : historique complet des ventes (référence, montant, caissier)
- **Onglet "Remboursements"** : historique des paiements de crédit
- **Boutons d'action** : modifier, rembourser, supprimer

### 3. **Ajouter/Modifier client** (`?action=add` ou `?action=edit&id=N`)
- Formulaire avec validation côté serveur
- Champs : nom, téléphone, email, adresse, type, crédit autorisé
- Vérification des doublons sur le téléphone
- Redirection vers la fiche après création/modification

### 4. **Clients en crédit** (`?action=credit`)
- Vue en tableau de tous les clients avec dettes
- Colonnes : nom, téléphone, type, dette, crédit autorisé, marge restante
- Tri par montant de dette (décroissant)
- Boutons rapides : "Rembourser" et "Voir"
- Code couleur pour les marges faibles (< 20%)

### 5. **Modale Remboursement**
- Affichage de la dette actuelle
- Saisie du montant avec maximum = dette
- Champ optionnel pour la note (chèque, virement, etc.)
- Enregistrement en base de données via POST

---

## 🔧 Méthodes Client.php utilisées

```php
// Liste et recherche
$clientModel->getAll($search = '', $type = '')  // Tous les clients
$clientModel->getById($id)                      // Détails d'un client
$clientModel->rechercher($q)                    // Recherche rapide

// CRUD
$clientModel->create($data)                     // Créer un client
$clientModel->update($id, $data)                // Modifier un client
$clientModel->delete($id)                       // Supprimer (ou désactiver)

// Historique
$clientModel->getHistorique($clientId)          // Achats du client
$clientModel->getStats($clientId)               // Statistiques

// Crédit/Remboursement
$clientModel->peutAcheterCredit($id, $montant) // Vérifie limite
$clientModel->rembourser($id, $montant, $note) // Enregistre remboursement
$clientModel->getPaiementsCredit($id)           // Historique remboursements

// Fidélité
$clientModel->ajouterPoints($id, $montant)     // Ajoute points après achat
$clientModel->remiseFidelite($id)               // Calcule remise disponible
$clientModel->consommerPoints($id, $remise)    // Utilise points
```

---

## 🔐 Permissions requises

- `clients:lire` — Accès au module
- `clients:creer` — Ajouter/modifier/supprimer clients, rembourser

---

## 📱 Responsive Design

- ✅ Desktop : layout 2 colonnes (liste + détail)
- ✅ Mobile : full-width avec onglets
- ✅ Cards fluides et boutons adaptés

---

## 🎨 Thème et couleurs

- **Particulier** : vert (#f0fdf4)
- **Grossiste** : violet (#faf5ff)
- **Crédit/Dette** : rouge (#fef2f2)
- **Fidélité** : violet-clair (#7c3aed)

---

## 🚀 Intégration avec le reste du système

### Caisse (`modules/ventes/caisse.php`)
- Recherche client lors de la vente
- Affiche détails client (téléphone, dette, points)
- Déduit automatiquement la remise fidélité
- Enregistre le crédit utilisé

### Ventes (`includes/Vente.php`)
- Appelle `Client::ajouterPoints()` après validation
- Enregistre le `client_id` et `montant_credit`

### Rapports
- Affiche statistiques clients par type
- Analyse crédit global

---

## 💡 Cas d'usage

**Gérer un client particulier avec dette**
1. Aller à `?action=credit`
2. Cliquer "Rembourser" sur le client
3. Saisir montant et note (chèque reçu)
4. ✅ Compte remis à jour automatiquement

**Voir historique d'un client grossiste**
1. Aller à `?action=list`
2. Chercher par nom
3. Cliquer sur la card
4. Voir onglet "Achats" avec tous les détails

**Créer un client et lui autoriser du crédit**
1. Aller à `?action=add`
2. Remplir formulaire avec crédit autorisé = 500 000
3. ✅ Clients peut acheter à crédit jusqu'à ce montant

---

## 🐛 Dépannage

**"Client introuvable"** → Vérifier l'ID dans l'URL
**Doublon téléphone** → Modifier le numéro existant ou en ajouter un nouveau
**Remboursement invalide** → Montant > dette ou ≤ 0
**Pas de permission** → Contacter administrateur pour les droits `clients:creer`

---

## 📊 Colonnes table `clients` requises

```
id               (INT PRIMARY KEY)
nom              (VARCHAR)
telephone        (VARCHAR UNIQUE)
email            (VARCHAR)
adresse          (TEXT)
type_client      (ENUM: particulier, grossiste)
solde_credit     (DECIMAL)
points_fidelite  (INT)
credit_autorise  (DECIMAL)
statut           (ENUM: actif, inactif)
created_at       (TIMESTAMP)
updated_at       (TIMESTAMP)
```

---

**Version** : 1.0.0 | **Créé** : 15/05/2026
