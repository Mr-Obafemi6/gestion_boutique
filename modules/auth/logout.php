<?php
// ============================================================
//  modules/auth/logout.php — Déconnexion
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/User.php';

$userModel = new User();
$userModel->logout();

redirect(APP_URL . '/modules/auth/login.php?logout=1');
