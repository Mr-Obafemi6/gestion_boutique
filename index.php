<?php
// ============================================================
//  index.php — Point d'entrée de l'application
// ============================================================
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . '/dashboard.php');
} else {
    redirect(APP_URL . '/modules/auth/login.php');
}
