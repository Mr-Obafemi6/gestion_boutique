<?php
// ============================================================
//  modules/auth/login.php — Page de connexion
// ============================================================
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/User.php';

// Si déjà connecté → dashboard
if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . '/dashboard.php');
}

$erreur  = '';
$success = '';

// Message d'expiration de session
if (isset($_GET['expire'])) {
    $erreur = "Votre session a expiré. Veuillez vous reconnecter.";
}
if (isset($_GET['logout'])) {
    $success = "Vous avez été déconnecté avec succès.";
}

// Génération du token CSRF (avant le traitement POST)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Protection CSRF basique
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erreur = "Requête invalide. Veuillez réessayer.";
    } else {
        $userModel = new User();
        $result = $userModel->login(
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? ''
        );

        if ($result === true) {
            redirect(APP_URL . '/dashboard.php');
        } else {
            $erreur = $result;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= APP_NAME ?></title>

    <!-- Bootstrap 5 CDN -->
    <link href="<?= APP_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="<?= APP_URL ?>/assets/css/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --bleu:        #1F5C99;
            --bleu-clair:  #D6E4F0;
            --bleu-fonce:  #153F6E;
        }

        * { box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #0f2d52 0%, var(--bleu) 55%, #3a8fd4 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        /* Cercles décoratifs en arrière-plan */
        body::before, body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: .08;
            background: #fff;
        }
        body::before {
            width: 500px; height: 500px;
            top: -150px; right: -100px;
        }
        body::after {
            width: 350px; height: 350px;
            bottom: -100px; left: -80px;
        }

        .login-wrap {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }

        /* Carte principale */
        .login-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 25px 70px rgba(0,0,0,.30);
            padding: 2.5rem 2rem 2rem;
            animation: fadeIn .45s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Logo */
        .login-logo {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--bleu), var(--bleu-fonce));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.1rem;
            box-shadow: 0 8px 24px rgba(31,92,153,.35);
        }
        .login-logo i { font-size: 2rem; color: #fff; }

        .login-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--bleu-fonce);
            text-align: center;
            margin-bottom: .2rem;
            letter-spacing: -.02em;
        }

        .login-subtitle {
            text-align: center;
            color: #94a3b8;
            font-size: .85rem;
            margin-bottom: 1.8rem;
        }

        /* Séparateur avec texte */
        .login-divider {
            border: none;
            border-top: 1px solid #e8edf3;
            margin: 1.5rem 0 1.2rem;
        }

        .form-label {
            font-weight: 600;
            font-size: .82rem;
            color: #374151;
            margin-bottom: .35rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .form-control {
            border-radius: 10px;
            border-color: #e2e8f0;
            font-size: .9rem;
            padding: .65rem .9rem;
            transition: all .2s;
        }

        .form-control:focus {
            border-color: var(--bleu);
            box-shadow: 0 0 0 .2rem rgba(31,92,153,.15);
        }

        .input-group-text {
            background: var(--bleu-clair);
            border-color: #e2e8f0;
            color: var(--bleu);
            border-radius: 10px 0 0 10px;
        }

        .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }

        .input-group .toggle-password {
            border-radius: 0 10px 10px 0;
        }

        .btn-connexion {
            background: linear-gradient(135deg, var(--bleu), var(--bleu-fonce));
            border: none;
            color: #fff;
            font-weight: 700;
            padding: .8rem;
            border-radius: 10px;
            font-size: .95rem;
            letter-spacing: .02em;
            transition: all .2s;
            box-shadow: 0 4px 15px rgba(31,92,153,.3);
        }

        .btn-connexion:hover {
            background: linear-gradient(135deg, var(--bleu-fonce), #0d2e50);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(31,92,153,.4);
        }

        .btn-connexion:active { transform: translateY(0); }

        .toggle-password {
            cursor: pointer;
            background: var(--bleu-clair);
            border-color: #e2e8f0;
            color: var(--bleu);
            transition: background .2s;
        }
        .toggle-password:hover { background: #c3d9ef; }

        /* Pied de carte */
        .login-footer {
            text-align: center;
            color: #cbd5e1;
            font-size: .75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #f0f4f8;
        }

        .login-footer strong { color: var(--bleu); }

        /* Badge version */
        .version-badge {
            display: inline-block;
            background: var(--bleu-clair);
            color: var(--bleu);
            font-size: .7rem;
            font-weight: 700;
            padding: .15rem .55rem;
            border-radius: 20px;
            letter-spacing: .04em;
        }
    </style>
</head>
<body>

<div class="login-wrap">
<div class="login-card">

    <!-- Logo -->
    <div class="login-logo">
        <i class="bi bi-shop-window"></i>
    </div>
    <h1 class="login-title"><?= APP_NAME ?></h1>
    <p class="login-subtitle">Connectez-vous à votre espace de gestion</p>

    <!-- Alertes -->
    <?php if ($erreur): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= clean($erreur) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= clean($success) ?>
        </div>
    <?php endif; ?>

    <!-- Formulaire -->
    <form method="POST" action="" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <!-- Email -->
        <div class="mb-3">
            <label for="email" class="form-label">Adresse email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="Votre Adresse boutique.tg"
                    value="<?= clean($_POST['email'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>
        </div>

        <!-- Mot de passe -->
        <div class="mb-4">
            <label for="password" class="form-label">Mot de passe</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="*********************"
                    required
                >
                <button class="btn toggle-password" type="button" id="togglePwd" tabindex="-1">
                    <i class="bi bi-eye-fill" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <!-- Mot de passe oublié -->
        <div class="text-end mb-3" style="margin-top:-.5rem;">
            <a href="<?= APP_URL ?>/modules/auth/mot_de_passe_oublie.php"
               style="font-size:.83rem; color:#64748b; text-decoration:none; transition:color .2s;"
               onmouseover="this.style.color='#1F5C99'"
               onmouseout="this.style.color='#64748b'">
                <i class="bi bi-question-circle me-1"></i>Mot de passe oublié ?
            </a>
        </div>

        <!-- Bouton -->
        <div class="d-grid">
            <button type="submit" class="btn btn-connexion">
                <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
            </button>
        </div>
    </form>

    <div class="login-footer">
        <strong><?= APP_NAME ?></strong> &nbsp;
        <span class="version-badge">v<?= APP_VERSION ?></span>
        <br>Gestion de Stock &amp; Caisse — Lomé, Togo
    </div>
</div>
</div>

<!-- Bootstrap JS -->
<script src="<?= APP_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle affichage mot de passe
    document.getElementById('togglePwd').addEventListener('click', function () {
        const pwd  = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('bi-eye-fill', 'bi-eye-slash-fill');
        } else {
            pwd.type = 'password';
            icon.classList.replace('bi-eye-slash-fill', 'bi-eye-fill');
        }
    });
</script>
</body>
</html>
