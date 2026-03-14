# 🏪 GestiBoutique

> Application web de gestion de stock et de caisse pour commerces de détail  
> Développée dans le cadre d'un mémoire de Licence en Informatique de Gestion — FORMATEC 2025-2026

---

## 📋 Description

**GestiBoutique** est une application web complète permettant à un commerçant de gérer :
- Son catalogue de produits et ses catégories
- Ses entrées et sorties de stock
- Sa caisse avec émission de tickets
- Ses fournisseurs
- Ses rapports et statistiques de vente
- Ses utilisateurs avec contrôle d'accès par rôles

---

## 🚀 Technologies utilisées

| Technologie | Version | Rôle |
|-------------|---------|------|
| PHP | 8.2 | Langage serveur |
| MySQL | 8.x | Base de données |
| Bootstrap | 5.3 | Interface responsive |
| Chart.js | 4.4 | Graphiques statistiques |
| Bootstrap Icons | 1.11 | Icônes |
| PDO | Natif PHP | Accès sécurisé BDD |

---

## ⚙️ Installation

### Prérequis
- XAMPP (ou WAMP) installé sur votre machine
- PHP 8.x
- MySQL 8.x

### Étapes

**1. Cloner le projet**
```bash
git clone https://github.com/Mr-Obafemi6/gestion_boutique.git
```
Ou télécharger le ZIP et extraire dans `C:\xampp\htdocs\`

**2. Importer la base de données**
- Ouvrir **phpMyAdmin** → `http://localhost/phpmyadmin`
- Créer une base de données `gestion_boutique`
- Importer le fichier `sql/database.sql`

**3. Configurer la connexion**
- Copier `config/database.example.php` en `config/database.php`
- Modifier les identifiants :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_boutique');
define('DB_USER', 'root');
define('DB_PASS', '');
```

**4. Lancer l'application**
- Démarrer Apache et MySQL dans XAMPP
- Ouvrir `http://localhost/gestion_boutique`

---

## 🔐 Identifiants par défaut

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| admin@boutique.tg | Admin@1234 | Administrateur |

> ⚠️ Changez le mot de passe après la première connexion !

---

## 👥 Rôles et permissions

| Rôle | Accès |
|------|-------|
| **Administrateur** | Tout (produits, ventes, rapports, utilisateurs) |
| **Caissier** | Point de vente et historique des ventes |
| **Magasinier** | Produits, stock et fournisseurs |

---

## 📁 Structure du projet

```
gestion_boutique/
├── config/
│   ├── config.php          # Configuration générale & sessions
│   └── database.php        # Connexion PDO (ne pas publier !)
├── includes/
│   ├── User.php            # Classe gestion utilisateurs
│   ├── Produit.php         # Classe gestion produits
│   ├── Stock.php           # Classe gestion stock & fournisseurs
│   ├── Vente.php           # Classe gestion ventes
│   ├── header.php          # Navigation commune
│   └── footer.php          # Pied de page commun
├── modules/
│   ├── auth/               # Connexion, déconnexion, utilisateurs
│   ├── produits/           # Gestion catalogue produits
│   ├── stock/              # Mouvements de stock
│   ├── ventes/             # Caisse & historique
│   ├── fournisseurs/       # Gestion fournisseurs
│   └── rapports/           # Statistiques & export CSV
├── sql/
│   └── database.sql        # Script création base de données
└── index.php               # Point d'entrée
```

---

## 🔒 Sécurité

- ✅ Mots de passe hachés en **bcrypt** (coût 12)
- ✅ Requêtes préparées **PDO** (protection injections SQL)
- ✅ Fonction `clean()` sur toutes les données affichées (protection XSS)
- ✅ Token **CSRF** sur le formulaire de connexion
- ✅ Régénération de session après connexion (anti-fixation)
- ✅ Contrôle d'accès par rôle sur chaque page
- ✅ Session expire après **8 heures** d'inactivité

---

## 📊 Fonctionnalités principales

- 🏠 **Tableau de bord** — KPIs en temps réel, graphiques, alertes stock
- 📦 **Produits** — CRUD complet avec catégories et seuils d'alerte
- 📊 **Stock** — Suivi des entrées/sorties/pertes avec historique
- 🛒 **Point de vente** — Caisse interactive avec tickets imprimables
- 📋 **Historique** — Consultation et annulation des ventes
- 🚚 **Fournisseurs** — Répertoire complet avec recherche
- 📈 **Rapports** — Statistiques par période avec export CSV
- 👤 **Utilisateurs** — Gestion des comptes et des rôles

---

## 👨‍💻 Auteur

    **TCHALLA Obafemi Gérard**  
    Étudiant en Licence 3 — Informatique de Gestion 
    FORMATEC, Lomé, Togo
    Année académique 2025-2026
Sous la direction de **Mr. AKANATE**

---

## 📄 Licence

Projet académique — FORMATEC 2025-2026  
Tous droits réservés © TCHALLA Obafemi Gérard
