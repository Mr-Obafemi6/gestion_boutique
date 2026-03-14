<?php
require_once __DIR__ . '/../config/config.php';
$pageTitle = "Accès refusé";
require_once __DIR__ . '/header.php';
?>
<div class="d-flex flex-column align-items-center justify-content-center" style="min-height:60vh;">
    <i class="bi bi-shield-lock-fill text-danger" style="font-size:4rem;"></i>
    <h2 class="mt-3 fw-bold text-danger">Accès refusé</h2>
    <p class="text-muted">Vous n'avez pas les droits nécessaires pour accéder à cette page.</p>
    <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-primary mt-2">
        <i class="bi bi-house-fill me-2"></i>Retour au tableau de bord
    </a>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
