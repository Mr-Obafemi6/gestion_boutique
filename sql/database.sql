-- ============================================================
--  BASE DE DONNÉES : Gestion de Stock et Caisse - Boutique
-- ============================================================

CREATE DATABASE IF NOT EXISTS gestion_boutique
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gestion_boutique;

-- ============================================================
-- TABLE : roles
-- ============================================================
CREATE TABLE roles (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom      VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO roles (nom) VALUES
    ('administrateur'),
    ('caissier'),
    ('magasinier');

-- ============================================================
-- TABLE : utilisateurs
-- ============================================================
CREATE TABLE utilisateurs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe  VARCHAR(255) NOT NULL,          -- bcrypt
    role_id       INT UNSIGNED NOT NULL,
    statut        ENUM('actif','inactif') DEFAULT 'actif',
    token_reset   VARCHAR(255) DEFAULT NULL,       -- réinitialisation mdp
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Mot de passe par défaut : Admin@1234 (hashé bcrypt)
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role_id) VALUES
(
    'Admin',
    'Super',
    'admin@boutique.tg',
    '$2y$12$eImiTXuWVxfM37uY4JANjQ==.eImiTXuWVxfM37uY4JANjQCrGWeSUCm2',
    1
);

-- ============================================================
-- TABLE : categories
-- ============================================================
CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO categories (nom, description) VALUES
    ('Alimentation',    'Produits alimentaires et boissons'),
    ('Hygiène',         'Produits de soins et hygiène corporelle'),
    ('Électronique',    'Appareils et accessoires électroniques'),
    ('Papeterie',       'Fournitures scolaires et de bureau'),
    ('Textile',         'Vêtements et tissus');

-- ============================================================
-- TABLE : clients
-- ============================================================
CREATE TABLE clients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(150) NOT NULL,
    telephone       VARCHAR(20)  DEFAULT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    adresse         TEXT         DEFAULT NULL,
    type_client     ENUM('particulier','grossiste') DEFAULT 'particulier',
    points_fidelite INT UNSIGNED NOT NULL DEFAULT 0,
    credit_autorise DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    solde_credit    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    statut          ENUM('actif','inactif') DEFAULT 'actif',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : paiements_credit
-- (Enregistre les remboursements partiels ou totaux)
-- ============================================================
CREATE TABLE paiements_credit (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id       INT UNSIGNED NOT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    note            VARCHAR(255) DEFAULT NULL,
    utilisateur_id  INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id)      REFERENCES clients(id)      ON DELETE RESTRICT,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : produits
-- ============================================================
CREATE TABLE produits (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference      VARCHAR(50) NOT NULL UNIQUE,
    code_barre     VARCHAR(100) UNIQUE NULL DEFAULT NULL,
    nom            VARCHAR(150) NOT NULL,
    description    TEXT DEFAULT NULL,
    categorie_id   INT UNSIGNED NOT NULL,
    unite_mesure   VARCHAR(30) DEFAULT 'pièce',
    prix_achat     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prix_vente     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prix_gros      DECIMAL(10,2) NULL DEFAULT NULL,
    qte_min_gros   INT UNSIGNED NOT NULL DEFAULT 0,
    image          VARCHAR(255) NULL DEFAULT NULL,
    stock_actuel   INT NOT NULL DEFAULT 0,
    seuil_alerte   INT NOT NULL DEFAULT 5,
    statut         ENUM('actif','inactif') DEFAULT 'actif',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : fournisseurs
-- ============================================================
CREATE TABLE fournisseurs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(150) NOT NULL,
    telephone  VARCHAR(20) DEFAULT NULL,
    email      VARCHAR(150) DEFAULT NULL,
    adresse    TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : mouvements_stock
-- (Entrées et sorties de stock)
-- ============================================================
CREATE TABLE mouvements_stock (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produit_id     INT UNSIGNED NOT NULL,
    type_mvt       ENUM('entree','sortie','perte','retour') NOT NULL,
    quantite       INT NOT NULL,
    prix_unitaire  DECIMAL(10,2) DEFAULT 0.00,
    motif          VARCHAR(255) DEFAULT NULL,
    fournisseur_id INT UNSIGNED DEFAULT NULL,
    utilisateur_id INT UNSIGNED NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produit_id)     REFERENCES produits(id) ON DELETE RESTRICT,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id) ON DELETE SET NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : ventes
-- (En-tête de vente / ticket)
-- ============================================================
CREATE TABLE ventes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(50) NOT NULL UNIQUE,
    total_ht        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    remise          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_net       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    montant_recu    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monnaie_rendue  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    mode_paiement   ENUM('especes','flooz','tmoney','carte') NOT NULL DEFAULT 'especes',
    note            VARCHAR(500) NULL DEFAULT NULL,
    statut          ENUM('validee','annulee') DEFAULT 'validee',
    utilisateur_id  INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NULL DEFAULT NULL,
    type_vente      ENUM('detail','gros') NOT NULL DEFAULT 'detail',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE RESTRICT,
    FOREIGN KEY (client_id)      REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : lignes_vente
-- (Détail de chaque produit vendu)
-- ============================================================
CREATE TABLE lignes_vente (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vente_id       INT UNSIGNED NOT NULL,
    produit_id     INT UNSIGNED NOT NULL,
    quantite       INT NOT NULL,
    prix_unitaire  DECIMAL(10,2) NOT NULL,
    sous_total     DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (vente_id)   REFERENCES ventes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : sessions (gestion PHP sessions en BDD - optionnel)
-- ============================================================
CREATE TABLE sessions_log (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    ip_address     VARCHAR(45) DEFAULT NULL,
    user_agent     VARCHAR(255) DEFAULT NULL,
    action         VARCHAR(100) DEFAULT NULL,    -- 'login', 'logout'
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- VUES UTILES
-- ============================================================

-- Vue : stock critique (produits sous le seuil d'alerte)
CREATE OR REPLACE VIEW v_stock_critique AS
SELECT
    p.id,
    p.reference,
    p.nom,
    c.nom AS categorie,
    p.stock_actuel,
    p.seuil_alerte,
    (p.seuil_alerte - p.stock_actuel) AS ecart
FROM produits p
JOIN categories c ON p.categorie_id = c.id
WHERE p.stock_actuel <= p.seuil_alerte
    AND p.statut = 'actif'
ORDER BY ecart DESC;

-- Vue : résumé des ventes par jour
CREATE OR REPLACE VIEW v_ventes_jour AS
SELECT
    DATE(created_at)     AS jour,
    COUNT(id)            AS nombre_ventes,
    SUM(total_net)       AS chiffre_affaires,
    SUM(remise)          AS total_remises
FROM ventes
WHERE statut = 'validee'
GROUP BY DATE(created_at)
ORDER BY jour DESC;

-- Vue : produits les plus vendus
CREATE OR REPLACE VIEW v_top_produits AS
SELECT
    p.nom           AS produit,
    c.nom           AS categorie,
    SUM(lv.quantite) AS total_vendu,
    SUM(lv.sous_total) AS chiffre_affaires
FROM lignes_vente lv
JOIN produits p  ON lv.produit_id = p.id
JOIN categories c ON p.categorie_id = c.id
JOIN ventes v    ON lv.vente_id = v.id
WHERE v.statut = 'validee'
GROUP BY p.id
ORDER BY total_vendu DESC;

-- ============================================================
-- TABLE : password_resets (réinitialisation mot de passe)
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL UNIQUE,
    token      VARCHAR(64)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- TABLE : permissions (granulaires par rôle)
-- ============================================================
CREATE TABLE IF NOT EXISTS permissions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    role       VARCHAR(50)  NOT NULL,
    module     VARCHAR(50)  NOT NULL,
    action     VARCHAR(20)  NOT NULL,
    autorise   TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_perm (role, module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Permissions par défaut
INSERT INTO permissions (role, module, action, autorise) VALUES
('administrateur','produits','voir',1),('administrateur','produits','creer',1),('administrateur','produits','modifier',1),('administrateur','produits','supprimer',1),('administrateur','produits','exporter',1),
('administrateur','stock','voir',1),('administrateur','stock','creer',1),('administrateur','stock','modifier',1),('administrateur','stock','supprimer',1),('administrateur','stock','exporter',1),
('administrateur','ventes','voir',1),('administrateur','ventes','creer',1),('administrateur','ventes','modifier',1),('administrateur','ventes','supprimer',1),('administrateur','ventes','exporter',1),
('administrateur','rapports','voir',1),('administrateur','rapports','creer',1),('administrateur','rapports','modifier',1),('administrateur','rapports','supprimer',1),('administrateur','rapports','exporter',1),
('administrateur','fournisseurs','voir',1),('administrateur','fournisseurs','creer',1),('administrateur','fournisseurs','modifier',1),('administrateur','fournisseurs','supprimer',1),('administrateur','fournisseurs','exporter',1),
('administrateur','messagerie','voir',1),('administrateur','messagerie','creer',1),('administrateur','messagerie','modifier',1),('administrateur','messagerie','supprimer',1),('administrateur','messagerie','exporter',1),
('administrateur','utilisateurs','voir',1),('administrateur','utilisateurs','creer',1),('administrateur','utilisateurs','modifier',1),('administrateur','utilisateurs','supprimer',1),('administrateur','utilisateurs','exporter',1),
('administrateur','clients','lire',1),('administrateur','clients','voir',1),('administrateur','clients','creer',1),('administrateur','clients','modifier',1),('administrateur','clients','supprimer',1),('administrateur','clients','exporter',1),
('caissier','produits','voir',1),('caissier','produits','creer',0),('caissier','produits','modifier',0),('caissier','produits','supprimer',0),('caissier','produits','exporter',0),
('caissier','stock','voir',0),('caissier','stock','creer',0),('caissier','stock','modifier',0),('caissier','stock','supprimer',0),('caissier','stock','exporter',0),
('caissier','ventes','voir',1),('caissier','ventes','creer',1),('caissier','ventes','modifier',0),('caissier','ventes','supprimer',0),('caissier','ventes','exporter',0),
('caissier','rapports','voir',1),('caissier','rapports','creer',0),('caissier','rapports','modifier',0),('caissier','rapports','supprimer',0),('caissier','rapports','exporter',0),
('caissier','fournisseurs','voir',0),('caissier','fournisseurs','creer',0),('caissier','fournisseurs','modifier',0),('caissier','fournisseurs','supprimer',0),('caissier','fournisseurs','exporter',0),
('caissier','messagerie','voir',1),('caissier','messagerie','creer',1),('caissier','messagerie','modifier',0),('caissier','messagerie','supprimer',1),('caissier','messagerie','exporter',0),
('caissier','utilisateurs','voir',0),('caissier','utilisateurs','creer',0),('caissier','utilisateurs','modifier',0),('caissier','utilisateurs','supprimer',0),('caissier','utilisateurs','exporter',0),
('caissier','clients','lire',1),('caissier','clients','voir',1),('caissier','clients','creer',1),('caissier','clients','modifier',1),('caissier','clients','supprimer',0),('caissier','clients','exporter',0),
('magasinier','produits','voir',1),('magasinier','produits','creer',1),('magasinier','produits','modifier',1),('magasinier','produits','supprimer',0),('magasinier','produits','exporter',0),
('magasinier','stock','voir',1),('magasinier','stock','creer',1),('magasinier','stock','modifier',1),('magasinier','stock','supprimer',0),('magasinier','stock','exporter',0),
('magasinier','ventes','voir',1),('magasinier','ventes','creer',0),('magasinier','ventes','modifier',0),('magasinier','ventes','supprimer',0),('magasinier','ventes','exporter',0),
('magasinier','rapports','voir',0),('magasinier','rapports','creer',0),('magasinier','rapports','modifier',0),('magasinier','rapports','supprimer',0),('magasinier','rapports','exporter',0),
('magasinier','fournisseurs','voir',1),('magasinier','fournisseurs','creer',1),('magasinier','fournisseurs','modifier',1),('magasinier','fournisseurs','supprimer',0),('magasinier','fournisseurs','exporter',0),
('magasinier','messagerie','voir',1),('magasinier','messagerie','creer',1),('magasinier','messagerie','modifier',0),('magasinier','messagerie','supprimer',1),('magasinier','messagerie','exporter',0),
('magasinier','utilisateurs','voir',0),('magasinier','utilisateurs','creer',0),('magasinier','utilisateurs','modifier',0),('magasinier','utilisateurs','supprimer',0),('magasinier','utilisateurs','exporter',0),
('magasinier','clients','lire',1),('magasinier','clients','voir',1),('magasinier','clients','creer',0),('magasinier','clients','modifier',0),('magasinier','clients','supprimer',0),('magasinier','clients','exporter',0)
ON DUPLICATE KEY UPDATE autorise = VALUES(autorise);

-- ============================================================
-- TABLE : connexions_log (historique des connexions)
-- ============================================================
CREATE TABLE IF NOT EXISTS connexions_log (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT UNSIGNED NOT NULL,
    ip             VARCHAR(45)  NOT NULL DEFAULT '',
    user_agent     VARCHAR(255) DEFAULT NULL,
    statut         ENUM('succes','echec') NOT NULL DEFAULT 'succes',
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uid  (utilisateur_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
CREATE TABLE messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expediteur_id   INT UNSIGNED NOT NULL,
    destinataire_id INT UNSIGNED NOT NULL,
    sujet           VARCHAR(255) NOT NULL,
    contenu         TEXT NOT NULL,
    lu              TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expediteur_id)   REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : parametres (configuration boutique + themes)
-- ============================================================
CREATE TABLE parametres (
    cle        VARCHAR(100) PRIMARY KEY,
    valeur     TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Valeurs par défaut
INSERT INTO parametres (cle, valeur) VALUES
('boutique_nom',      'GestiBoutique'),
('boutique_adresse',  'Lome, Togo'),
('boutique_tel',      ''),
('boutique_email',    ''),
('boutique_devise',   'FCFA'),
('ticket_footer',     'Merci pour votre achat ! A bientot.'),
('theme_couleur',     'bleu'),
('theme_mode',        'clair');

-- Colonne avatar dans utilisateurs
ALTER TABLE utilisateurs ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL;
