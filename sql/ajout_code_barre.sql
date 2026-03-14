-- ============================================================
--  Ajout colonne code_barre dans la table produits
--  À exécuter UNE SEULE FOIS dans phpMyAdmin
-- ============================================================

ALTER TABLE produits 
ADD COLUMN code_barre VARCHAR(100) DEFAULT NULL UNIQUE 
AFTER reference;
