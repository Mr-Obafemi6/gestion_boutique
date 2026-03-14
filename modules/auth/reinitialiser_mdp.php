<?php
// ============================================================
//  modules/auth/reinitialiser_mdp.php
//  Étape 2 — Nouveau mot de passe via token
// ============================================================
require_once __DIR__ . '/../../config/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . '/dashboard.php');
}

$erreur      = '';
$success     = '';
$token       = clean($_GET['token'] ?? $_POST['token'] ?? '');
$email       = clean($_GET['email'] ?? $_POST['email'] ?? '');
$tokenValide = false;
$userReset   = null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérifier le token
if ($token && $email) {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT pr.*, u.id AS user_id, u.nom, u.prenom
        FROM password_resets pr
        JOIN utilisateurs u ON u.email COLLATE utf8mb4_general_ci = pr.email COLLATE utf8mb4_general_ci
        WHERE pr.email COLLATE utf8mb4_general_ci  = :email
          AND pr.token  = :token
          AND pr.expires_at > NOW()
          AND u.statut  = 'actif'
        LIMIT 1
    ");
    $stmt->execute([':email' => $email, ':token' => $token]);
    $userReset = $stmt->fetch();

    if ($userReset) {
        $tokenValide = true;
    } else {
        $erreur = "Ce lien est invalide ou a expiré. Demandez-en un nouveau.";
    }
}

// Traitement du nouveau mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValide) {

    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $erreur = "Requête invalide.";
    } else {
        $nouveau = $_POST['mdp_nouveau'] ?? '';
        $confirm = $_POST['mdp_confirm'] ?? '';

        if (strlen($nouveau) < 8) {
            $erreur = "Le mot de passe doit contenir au moins 8 caractères.";
        } elseif ($nouveau !== $confirm) {
            $erreur = "Les deux mots de passe ne correspondent pas.";
        } else {
            $db   = getDB();
            $hash = password_hash($nouveau, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("UPDATE utilisateurs SET mot_de_passe = :h WHERE id = :id")
               ->execute([':h' => $hash, ':id' => $userReset['user_id']]);
            $db->prepare("DELETE FROM password_resets WHERE email = :e")
               ->execute([':e' => $email]);

            $success     = "Mot de passe réinitialisé ! Vous pouvez maintenant vous connecter.";
            $tokenValide = false;
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
    <title>Nouveau mot de passe — <?= APP_NAME ?></title>
    <link href="<?= APP_URL ?>/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --bleu:#1F5C99; --bleu-clair:#D6E4F0; --bleu-fonce:#153F6E; }
        * { box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #0f2d52 0%, var(--bleu) 55%, #3a8fd4 100%);
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            font-family:'Segoe UI', system-ui, sans-serif;
            padding:1rem; position:relative; overflow:hidden;
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
            padding:2.5rem 2rem 2rem; animation:fadeIn .45s ease;
        }
        @keyframes fadeIn {
            from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)}
        }
        .login-logo {
            width:72px; height:72px;
            background:linear-gradient(135deg,var(--bleu),var(--bleu-fonce));
            border-radius:20px; display:flex; align-items:center; justify-content:center;
            margin:0 auto 1.1rem; box-shadow:0 8px 24px rgba(31,92,153,.35);
        }
        .login-logo i { font-size:2rem; color:#fff; }
        .login-logo.success-logo { background:linear-gradient(135deg,#16a34a,#14532d); }
        .login-logo.error-logo   { background:linear-gradient(135deg,#dc2626,#991b1b); }
        .login-title {
            font-size:1.45rem; font-weight:800; color:var(--bleu-fonce);
            text-align:center; margin-bottom:.2rem; letter-spacing:-.02em;
        }
        .login-subtitle { text-align:center; color:#94a3b8; font-size:.85rem; margin-bottom:1.5rem; }
        .form-label {
            font-weight:600; font-size:.82rem; color:#374151;
            margin-bottom:.35rem; text-transform:uppercase; letter-spacing:.04em;
        }
        .input-wrap { position:relative; }
        .input-wrap .ico {
            position:absolute; left:13px; top:50%; transform:translateY(-50%);
            color:#94a3b8; font-size:.95rem; pointer-events:none; z-index:5;
        }
        .input-wrap .form-control {
            padding-left:2.5rem !important; border-radius:10px; border-color:#e2e8f0;
            font-size:.9rem; padding-top:.65rem; padding-bottom:.65rem; transition:all .2s;
        }
        .input-wrap .form-control:focus {
            border-color:var(--bleu); box-shadow:0 0 0 .2rem rgba(31,92,153,.15);
        }
        .btn-eye {
            position:absolute; right:10px; top:50%; transform:translateY(-50%);
            background:none; border:none; color:#94a3b8; cursor:pointer; z-index:5;
            padding:4px 6px; border-radius:6px; transition:color .2s;
        }
        .btn-eye:hover { color:var(--bleu); }
        .btn-connexion {
            background:linear-gradient(135deg,var(--bleu),var(--bleu-fonce));
            border:none; color:#fff; font-weight:700; padding:.8rem; border-radius:10px;
            font-size:.95rem; transition:all .2s; box-shadow:0 4px 15px rgba(31,92,153,.3);
        }
        .btn-connexion:hover {
            background:linear-gradient(135deg,var(--bleu-fonce),#0d2e50);
            color:#fff; transform:translateY(-1px); box-shadow:0 6px 20px rgba(31,92,153,.4);
        }
        .btn-success-big {
            background:linear-gradient(135deg,#16a34a,#15803d);
            border:none; color:#fff; font-weight:700; padding:.8rem; border-radius:10px;
            font-size:.95rem; transition:all .2s; box-shadow:0 4px 15px rgba(22,163,74,.3);
            text-decoration:none; display:block; text-align:center;
        }
        .btn-success-big:hover { color:#fff; transform:translateY(-1px); }
        .alert { border-radius:10px; font-size:.85rem; }
        /* Barre de force */
        .force-wrap { margin-top:.4rem; }
        .force-barre { height:5px; background:#e2e8f0; border-radius:3px; overflow:hidden; }
        .force-fill  { height:100%; border-radius:3px; width:0; transition:width .3s, background .3s; }
        .force-label { font-size:.73rem; color:#64748b; margin-top:.25rem; height:16px; }
        /* Match feedback */
        .match-msg { font-size:.73rem; min-height:16px; margin-top:.25rem; }
        /* Retour */
        .retour-lien {
            text-align:center; margin-top:1.2rem; font-size:.83rem; color:#64748b;
        }
        .retour-lien a { color:var(--bleu); font-weight:600; text-decoration:none; }
        .retour-lien a:hover { text-decoration:underline; }
        .login-footer {
            text-align:center; color:#cbd5e1; font-size:.75rem;
            margin-top:1.5rem; padding-top:1rem; border-top:1px solid #f0f4f8;
        }
        .login-footer strong { color:var(--bleu); }
        .version-badge {
            display:inline-block; background:var(--bleu-clair); color:var(--bleu);
            font-size:.7rem; font-weight:700; padding:.15rem .55rem; border-radius:20px;
        }
        /* Tips */
        .tip-list { display:flex; flex-direction:column; gap:.4rem; margin-bottom:1.5rem; }
        .tip-item {
            display:flex; align-items:center; gap:.6rem;
            font-size:.8rem; color:#64748b;
            background:#f8fafc; padding:.45rem .75rem; border-radius:8px;
        }
        .tip-item i { color:#16a34a; font-size:.85rem; }
    </style>
</head>
<body>
<div class="login-wrap">
<div class="login-card">

    <?php if ($success): ?>
        <!-- ── Succès ── -->
        <div class="login-logo success-logo"><i class="bi bi-check-lg"></i></div>
        <h1 class="login-title" style="color:#16a34a;">Mot de passe mis à jour !</h1>
        <p class="login-subtitle">Votre mot de passe a été réinitialisé avec succès.</p>
        <div class="alert alert-success mb-4">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
        </div>
        <a href="<?= APP_URL ?>/modules/auth/login.php" class="btn-success-big">
            <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter maintenant
        </a>

    <?php elseif (!$tokenValide && $erreur): ?>
        <!-- ── Token invalide ── -->
        <div class="login-logo error-logo"><i class="bi bi-x-lg"></i></div>
        <h1 class="login-title" style="color:#dc2626;">Lien invalide</h1>
        <p class="login-subtitle">Ce lien de réinitialisation est invalide ou a expiré.</p>
        <div class="alert alert-danger mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $erreur ?>
        </div>
        <a href="<?= APP_URL ?>/modules/auth/mot_de_passe_oublie.php"
           class="btn btn-connexion d-grid w-100">
            <i class="bi bi-arrow-repeat me-2"></i>Demander un nouveau lien
        </a>

    <?php elseif ($tokenValide): ?>
        <!-- ── Formulaire ── -->
        <div class="login-logo"><i class="bi bi-shield-lock-fill"></i></div>
        <h1 class="login-title">Nouveau mot de passe</h1>
        <p class="login-subtitle">
            Bonjour <strong><?= clean($userReset['prenom'].' '.$userReset['nom']) ?></strong> —
            choisissez un mot de passe sécurisé.
        </p>

        <!-- Conseils -->
        <div class="tip-list">
            <div class="tip-item"><i class="bi bi-check-circle-fill"></i> Au moins 8 caractères</div>
            <div class="tip-item"><i class="bi bi-check-circle-fill"></i> Majuscules + chiffres recommandés</div>
            <div class="tip-item"><i class="bi bi-check-circle-fill"></i> Caractères spéciaux pour plus de sécurité</div>
        </div>

        <?php if ($erreur): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $erreur ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="token"      value="<?= clean($token) ?>">
            <input type="hidden" name="email"      value="<?= clean($email) ?>">

            <!-- Nouveau mot de passe -->
            <div class="mb-3">
                <label class="form-label">Nouveau mot de passe</label>
                <div class="input-wrap">
                    <i class="bi bi-lock-fill ico"></i>
                    <input type="password" id="mdpNouv" name="mdp_nouveau"
                        class="form-control" placeholder="Min. 8 caractères"
                        minlength="8" required autofocus>
                    <button class="btn-eye" type="button"
                        onclick="toggleVis('mdpNouv','eye1')">
                        <i class="bi bi-eye-fill" id="eye1"></i>
                    </button>
                </div>
                <div class="force-wrap">
                    <div class="force-barre"><div class="force-fill" id="forceFill"></div></div>
                    <div class="force-label" id="forceLabel"></div>
                </div>
            </div>

            <!-- Confirmer -->
            <div class="mb-4">
                <label class="form-label">Confirmer le mot de passe</label>
                <div class="input-wrap">
                    <i class="bi bi-lock-fill ico"></i>
                    <input type="password" id="mdpConfirm" name="mdp_confirm"
                        class="form-control" placeholder="Répétez le mot de passe" required>
                    <button class="btn-eye" type="button"
                        onclick="toggleVis('mdpConfirm','eye2')">
                        <i class="bi bi-eye-fill" id="eye2"></i>
                    </button>
                </div>
                <div class="match-msg" id="matchMsg"></div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-connexion">
                    <i class="bi bi-shield-check-fill me-2"></i>Réinitialiser le mot de passe
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
<script>
function toggleVis(id, iconId) {
    const input = document.getElementById(id);
    const icon  = document.getElementById(iconId);
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('bi-eye-fill');
    icon.classList.toggle('bi-eye-slash-fill');
}

// Force du mot de passe
document.getElementById('mdpNouv')?.addEventListener('input', function () {
    const v = this.value, fill = document.getElementById('forceFill'), lbl = document.getElementById('forceLabel');
    let s = 0;
    if (v.length >= 8)           s++;
    if (/[A-Z]/.test(v))         s++;
    if (/[0-9]/.test(v))         s++;
    if (/[^A-Za-z0-9]/.test(v))  s++;
    const n = [
        {p:'25%', c:'#ef4444', t:'⚠️ Faible'},
        {p:'50%', c:'#f97316', t:'🔶 Moyen'},
        {p:'75%', c:'#eab308', t:'✅ Bon'},
        {p:'100%',c:'#22c55e', t:'💪 Très fort'},
    ][Math.max(0, s-1)];
    if (fill) { fill.style.width = v.length ? n.p : '0'; fill.style.background = n.c; }
    if (lbl)    lbl.textContent  = v.length ? n.t : '';
    checkMatch();
});

// Correspondance
document.getElementById('mdpConfirm')?.addEventListener('input', checkMatch);
function checkMatch() {
    const n = document.getElementById('mdpNouv')?.value;
    const c = document.getElementById('mdpConfirm')?.value;
    const el = document.getElementById('matchMsg');
    if (!el || !c) { el && (el.innerHTML = ''); return; }
    el.innerHTML = c === n
        ? '<span style="color:#16a34a"><i class="bi bi-check-circle-fill me-1"></i>Les mots de passe correspondent</span>'
        : '<span style="color:#dc2626"><i class="bi bi-x-circle-fill me-1"></i>Les mots de passe ne correspondent pas</span>';
}
</script>
</body>
</html>
