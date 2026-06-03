-- ============================================================
-- DONNEES TEST : Configuration des produits pour vente en gros
-- ============================================================

USE gestion_boutique;

-- ============================================================
-- 1. CLIENTS TEST
-- ============================================================
INSERT INTO clients (nom, telephone, type_client, credit_autorise, points_fidelite) VALUES
('Abalo', '91111111', 'grossiste', 2000000, 0),
('Aïssatou Traoré', '92222222', 'particulier', 50000, 0)
ON DUPLICATE KEY UPDATE nom = VALUES(nom);

-- ============================================================
-- 2. PRODUITS AVEC TARIF GROS
-- ============================================================

-- Oignons : 200 F détail, 150 F à partir de 50 unités
UPDATE produits 
SET 
    prix_gros = 150,
    qte_min_gros = 50,
    code_barre = 'OIGNON-001'
WHERE LOWER(nom) LIKE '%oignon%';

-- Riz : 1500 F détail, 1200 F à partir de 20 unités
UPDATE produits 
SET 
    prix_gros = 1200,
    qte_min_gros = 20,
    code_barre = 'RIZ-001'
WHERE LOWER(nom) LIKE '%riz%' AND LOWER(nom) NOT LIKE '%risotto%';

-- Tomates : 100 F détail, 80 F à partir de 100 unités  
UPDATE produits 
SET 
    prix_gros = 80,
    qte_min_gros = 100,
    code_barre = 'TOMATE-001'
WHERE LOWER(nom) LIKE '%tomate%';

-- Sucre : 1500 F détail, 1200 F à partir de 20 unités
UPDATE produits 
SET 
    prix_gros = 1200,
    qte_min_gros = 20,
    code_barre = 'SUCRE-001'
WHERE LOWER(nom) LIKE '%sucre%';

-- ============================================================
-- FIN - Données de test chargées
-- ============================================================
