<?php
// ============================================================
//  modules/auth/mot_de_passe_oublie.php
//  Étape 1 — Saisie de l'email pour réinitialisation
// ============================================================
require_once __DIR__ . '/../../config/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . '/dashboard.php');
}

$erreur  = '';
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erreur = "Requête invalide. Veuillez réessayer.";
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur = "Veuillez saisir une adresse email valide.";
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, nom, prenom FROM utilisateurs WHERE email = :email AND statut = 'actif'");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                $token  = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $db->prepare("
                    INSERT INTO password_resets (email, token, expires_at)
                    VALUES (:email, :token, :expiry)
                    ON DUPLICATE KEY UPDATE token = :token2, expires_at = :expiry2, created_at = NOW()
                ")->execute([
                    ':email'   => $email,
                    ':token'   => $token,
                    ':expiry'  => $expiry,
                    ':token2'  => $token,
                    ':expiry2' => $expiry,
                ]);

                // Sans SMTP : on affiche le lien directement
                $_SESSION['reset_link'] = APP_URL
                    . '/modules/auth/reinitialiser_mdp.php?token='
                    . $token . '&email=' . urlencode($email);
            }

            // Message identique qu'un compte existe ou non (anti-énumération)
            $success = "Si cette adresse est associée à un compte actif, un lien de réinitialisation a été généré.";
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — <?= APP_NAME ?></title>
    <link href="<?= APP_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --bleu:#1F5C99; --bleu-clair:#D6E4F0; --bleu-fonce:#153F6E; }
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #0f2d52 0%, var(--bleu) 55%, #3a8fd4 100%);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 1rem; position: relative; overflow: hidden;
        }
        body::before {
            content:''; position:absolute; border-radius:50%; opacity:.08;
            background:#fff; width:500px; height:500px; top:-150px; right:-100px;
        }
        body::after {
            content:''; position:absolute; border-radius:50%; opacity:.08;
            background:#fff; width:350px; height:350px; bottom:-100px; left:-80px;
        }
        .login-wrap { width:100%; max-width:440px; position:relative; z-index:1; }
        .login-card {
            background:#fff; border-radius:20px;
            box-shadow:0 25px 70px rgba(0,0,0,.30);
            padding:2.5rem 2rem 2rem;
            animation:fadeIn .45s ease;
        }
        @keyframes fadeIn {
            from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)}
        }
        .login-logo {
            width:72px; height:72px;
            background:linear-gradient(135deg,var(--bleu),var(--bleu-fonce));
            border-radius:20px; display:flex; align-items:center; justify-content:center;
            margin:0 auto 1.1rem;
            box-shadow:0 8px 24px rgba(31,92,153,.35);
        }
        .login-logo i { font-size:2rem; color:#fff; }
        .login-title {
            font-size:1.45rem; font-weight:800; color:var(--bleu-fonce);
            text-align:center; margin-bottom:.2rem; letter-spacing:-.02em;
        }
        .login-subtitle { text-align:center; color:#94a3b8; font-size:.85rem; margin-bottom:1.8rem; }
        .form-label {
            font-weight:600; font-size:.82rem; color:#374151;
            margin-bottom:.35rem; text-transform:uppercase; letter-spacing:.04em;
        }
        .form-control {
            border-radius:10px; border-color:#e2e8f0; font-size:.9rem;
            padding:.65rem .9rem; transition:all .2s;
        }
        .form-control:focus { border-color:var(--bleu); box-shadow:0 0 0 .2rem rgba(31,92,153,.15); }
        .input-group-text {
            background:var(--bleu-clair); border-color:#e2e8f0;
            color:var(--bleu); border-radius:10px 0 0 10px;
        }
        .input-group .form-control { border-radius:0 10px 10px 0; }
        .btn-connexion {
            background:linear-gradient(135deg,var(--bleu),var(--bleu-fonce));
            border:none; color:#fff; font-weight:700; padding:.8rem;
            border-radius:10px; font-size:.95rem; transition:all .2s;
            box-shadow:0 4px 15px rgba(31,92,153,.3);
        }
        .btn-connexion:hover {
            background:linear-gradient(135deg,var(--bleu-fonce),#0d2e50);
            color:#fff; transform:translateY(-1px);
            box-shadow:0 6px 20px rgba(31,92,153,.4);
        }
        .btn-connexion:active { transform:translateY(0); }
        /* Alerte inline */
        .alert { border-radius:10px; font-size:.85rem; }
        /* Boîte lien reset */
        .reset-box {
            background:#fffbeb; border:1px solid #fcd34d;
            border-radius:12px; padding:1rem; margin-top:1rem;
            font-size:.82rem; color:#92400e;
        }
        .reset-box code {
            display:block; margin-top:.4rem; word-break:break-all;
            background:#fef3c7; padding:.4rem .7rem; border-radius:8px; font-size:.76rem;
        }
        .retour-lien {
            text-align:center; margin-top:1.2rem;
            font-size:.83rem; color:#64748b;
        }
        .retour-lien a { color:var(--bleu); font-weight:600; text-decoration:none; }
        .retour-lien a:hover { text-decoration:underline; }
        .login-footer {
            text-align:center; color:#cbd5e1; font-size:.75rem;
            margin-top:1.5rem; padding-top:1rem; border-top:1px solid #f0f4f8;
        }
        .login-footer strong { color:var(--bleu); }
        .version-badge {
            display:inline-block; background:var(--bleu-clair);
            color:var(--bleu); font-size:.7rem; font-weight:700;
            padding:.15rem .55rem; border-radius:20px;
        }
        /* Étapes */
        .steps { display:flex; flex-direction:column; gap:.5rem; margin-bottom:1.8rem; }
        .step-item {
            display:flex; align-items:center; gap:.7rem;
            background:#f8fafc; border-radius:8px; padding:.5rem .75rem;
            font-size:.82rem; color:#475569;
            border:1px solid #e8edf2;
        }
        .step-num {
            width:22px; height:22px; border-radius:50%; background:var(--bleu);
            color:#fff; font-size:.72rem; font-weight:700; flex-shrink:0;
            display:flex; align-items:center; justify-content:center;
        }
    </style>
</head>
<body>
<div class="login-wrap">
<div class="login-card">

    <!-- Logo -->
    <div class="login-logo"><i class="bi bi-key-fill"></i></div>
    <h1 class="login-title">Mot de passe oublié ?</h1>
    <p class="login-subtitle">Entrez votre email pour recevoir un lien de réinitialisation</p>

    <!-- Étapes -->
    <div class="steps">
        <div class="step-item"><div class="step-num">1</div> Saisissez votre adresse email</div>
        <div class="step-item"><div class="step-num">2</div> Obtenez votre lien de réinitialisation</div>
        <div class="step-item"><div class="step-num">3</div> Créez un nouveau mot de passe sécurisé</div>
    </div>

    <!-- Alertes -->
    <?php if ($erreur): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= clean($erreur) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-2" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
        </div>

        <?php if (!empty($_SESSION['reset_link'])): ?>
        <div class="reset-box">
            <strong><i class="bi bi-info-circle-fill me-1"></i>Lien de réinitialisation généré :</strong><br>
            <small>(Dans un vrai système ce lien serait envoyé par email. Cliquez ci-dessous :)</small>
            <code><?= htmlspecialchars($_SESSION['reset_link']) ?></code>
            <div class="mt-2">
                <a href="<?= htmlspecialchars($_SESSION['reset_link']) ?>"
                   class="btn btn-sm btn-warning fw-semibold w-100">
                    <i class="bi bi-arrow-right-circle me-1"></i>Cliquer ici pour réinitialiser
                </a>
            </div>
        </div>
        <?php unset($_SESSION['reset_link']); ?>
        <?php endif; ?>

    <?php else: ?>

    <!-- Formulaire -->
    <form method="POST" action="" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="mb-4">
            <label for="email" class="form-label">Adresse email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                <input type="email" id="email" name="email" class="form-control"
                    placeholder="votre@email.com"
                    value="<?= clean($_POST['email'] ?? '') ?>"
                    required autofocus>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-connexion">
                <i class="bi bi-send-fill me-2"></i>Envoyer le lien
            </button>
        </div>
    </form>

    <?php endif; ?>

    <div class="retour-lien">
        <i class="bi bi-arrow-left me-1"></i>
        <a href="<?= APP_URL ?>/modules/auth/login.php">Retour à la connexion</a>
    </div>

    <div class="login-footer">
        <strong><?= APP_NAME ?></strong> &nbsp;
        <span class="version-badge">v<?= APP_VERSION ?></span>
        <br>Gestion de Stock &amp; Caisse — Lomé, Togo
    </div>

</div>
</div>

<script src="<?= APP_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
