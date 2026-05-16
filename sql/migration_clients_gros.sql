-- ============================================================
--  MIGRATION : Clients + Vente en Gros + Fidélité
--  Projet : gestion_boutique
--  À exécuter dans phpMyAdmin (base : gestion_boutique)
-- ============================================================

USE gestion_boutique;

-- ============================================================
-- 1. TABLE : clients
-- ============================================================
CREATE TABLE IF NOT EXISTS clients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(150) NOT NULL,
    telephone       VARCHAR(20)  DEFAULT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    adresse         TEXT         DEFAULT NULL,
    type_client     ENUM('particulier','grossiste') DEFAULT 'particulier',
    -- Fidélité
    points_fidelite INT UNSIGNED NOT NULL DEFAULT 0,
    -- Crédit / dette
    credit_autorise DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- plafond dette autorisé
    solde_credit    DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- montant actuellement dû (0 = rien dû)
    -- Meta
    statut          ENUM('actif','inactif') DEFAULT 'actif',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. TABLE : paiements_credit
--    Enregistre les remboursements partiels ou totaux d'un client
-- ============================================================
CREATE TABLE IF NOT EXISTS paiements_credit (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id       INT UNSIGNED NOT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    note            VARCHAR(255) DEFAULT NULL,
    utilisateur_id  INT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id)      REFERENCES clients(id)      ON DELETE RESTRICT,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. MIGRATION TABLE : produits
--    Ajout prix_gros et qte_min_gros
-- ============================================================
ALTER TABLE produits
    ADD COLUMN IF NOT EXISTS prix_gros     DECIMAL(10,2) NULL DEFAULT NULL  AFTER prix_vente,
    ADD COLUMN IF NOT EXISTS qte_min_gros  INT UNSIGNED  NOT NULL DEFAULT 0 AFTER prix_gros;

-- ============================================================
-- 4. MIGRATION TABLE : ventes
--    Ajout client_id et type_vente
-- ============================================================
ALTER TABLE ventes
    ADD COLUMN IF NOT EXISTS client_id   INT UNSIGNED NULL DEFAULT NULL
        AFTER utilisateur_id,
    ADD COLUMN IF NOT EXISTS type_vente  ENUM('detail','gros') NOT NULL DEFAULT 'detail'
        AFTER client_id;

-- Clé étrangère client (seulement si pas déjà là)
ALTER TABLE ventes
    ADD CONSTRAINT IF NOT EXISTS fk_ventes_client
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL;

-- ============================================================
-- 5. VUE : historique achats par client
-- ============================================================
CREATE OR REPLACE VIEW v_historique_client AS
SELECT
    c.id            AS client_id,
    c.nom           AS client_nom,
    c.telephone,
    COUNT(v.id)     AS nb_achats,
    SUM(v.total_net) AS total_depense,
    MAX(v.created_at) AS dernier_achat
FROM clients c
LEFT JOIN ventes v ON v.client_id = c.id AND v.statut = 'validee'
GROUP BY c.id
ORDER BY total_depense DESC;

-- ============================================================
-- 6. VUE : clients en crédit (dette > 0)
-- ============================================================
CREATE OR REPLACE VIEW v_clients_credit AS
SELECT
    c.id, c.nom, c.telephone,
    c.solde_credit,
    c.credit_autorise,
    (c.credit_autorise - c.solde_credit) AS marge_restante
FROM clients c
WHERE c.solde_credit > 0
ORDER BY c.solde_credit DESC;

-- ============================================================
-- 7. PERMISSIONS : ajouter module "clients" pour les rôles
-- ============================================================
INSERT INTO permissions (role, module, action, autorise) VALUES
('administrateur','clients','lire',1),
('administrateur','clients','voir',1),
('administrateur','clients','creer',1),
('administrateur','clients','modifier',1),
('administrateur','clients','supprimer',1),
('administrateur','clients','exporter',1),
('caissier','clients','lire',1),
('caissier','clients','voir',1),
('caissier','clients','creer',1),
('caissier','clients','modifier',1),
('caissier','clients','supprimer',0),
('caissier','clients','exporter',0),
('magasinier','clients','lire',1),
('magasinier','clients','voir',1),
('magasinier','clients','creer',0),
('magasinier','clients','modifier',0),
('magasinier','clients','supprimer',0),
('magasinier','clients','exporter',0)
ON DUPLICATE KEY UPDATE autorise = VALUES(autorise);
