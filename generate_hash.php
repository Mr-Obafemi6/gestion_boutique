-- ============================================================
--  IMPORTANT : Exécute ce script PHP UNE SEULE FOIS
--  pour mettre à jour le hash du mot de passe admin
--  Mot de passe par défaut : Admin@boutique@05
-- ============================================================
--
-- Crée un fichier generate_hash.php à la racine et accède-y
-- via ton navigateur : http://localhost/gestion_boutique/generate_hash.php
-- Copie le hash affiché, puis supprime ce fichier.

<?php
$password = 'Admin@boutique05';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Mettre à jour en BDD
require_once __DIR__ . '/config/config.php';
$db = getDB();
$stmt = $db->prepare("UPDATE utilisateurs SET mot_de_passe = :hash WHERE email = 'admin05@boutique.tg'");
$stmt->execute([':hash' => $hash]);

echo "<h2>✅ Mot de passe admin réinitialisé avec succès !</h2>";
echo "<p><strong>Email :</strong> admin05@boutique.tg</p>";
echo "<p><strong>Mot de passe :</strong> Admin@boutique05</p>";
echo "<p style='color:red;'><strong>⚠️ Supprime ce fichier immédiatement après utilisation !</strong></p>";
echo "<a href='modules/auth/login.php'>→ Aller à la connexion</a>";

//Fichier à supprimer  ⚠️
//juste apres avoir tous fais

