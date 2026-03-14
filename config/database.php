<?php
// ============================================================
//  config/database.php — Connexion à la base de données
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'gestion_boutique');
define('DB_USER',    'root');        // ← change selon ton hébergement
define('DB_PASS',    '');            // ← change selon ton hébergement
define('DB_CHARSET', 'utf8mb4');

/**
 * Retourne une connexion PDO unique (singleton).
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En production, ne jamais afficher le message brut
            error_log("Erreur BDD : " . $e->getMessage());
            die(json_encode(['erreur' => 'Connexion à la base de données impossible.']));
        }
    }

    return $pdo;
}
