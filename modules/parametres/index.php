<?php
require_once __DIR__ . '/../../config/config.php';
requireLogin();

$db     = getDB();
$userId = $_SESSION['user_id'];

function getParam(PDO $db, string $cle, string $defaut = ''): string {
    $s = $db->prepare("SELECT valeur FROM parametres WHERE cle=:cle");
    $s->execute([':cle'=>$cle]);
    $r = $s->fetchColumn();
    return $r !== false ? $r : $defaut;
}
function setParam(PDO $db, string $cle, string $val): void {
    $db->prepare("INSERT INTO parametres (cle,valeur) VALUES(:k,:v) ON DUPLICATE KEY UPDATE valeur=:v2")
    ->execute([':k'=>$cle,':v'=>$val,':v2'=>$val]);
}

$stmt = $db->prepare("SELECT u.*, r.nom AS role FROM utilisateurs u JOIN roles r ON u.role_id=r.id WHERE u.id=:id");
$stmt->execute([':id'=>$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCSRF();
    $section = $_POST['section'] ?? '';

    if ($section === 'avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                setFlash('danger','Format non autorise. Utilisez JPG ou PNG.');
            } elseif ($file['size'] > 2*1024*1024) {
                setFlash('danger','La photo ne doit pas depasser 2 Mo.');
            } else {
                if ($user['avatar'] && file_exists(__DIR__.'/../../uploads/avatars/'.$user['avatar'])) {
                    unlink(__DIR__.'/../../uploads/avatars/'.$user['avatar']);
                }
                $nom = 'avatar_'.$userId.'_'.time().'.'.$ext;
                if (move_uploaded_file($file['tmp_name'], __DIR__.'/../../uploads/avatars/'.$nom)) {
                    $db->prepare("UPDATE utilisateurs SET avatar=:a WHERE id=:id")->execute([':a'=>$nom,':id'=>$userId]);
                    $_SESSION['user_avatar'] = $nom;
                    setFlash('success','Photo de profil mise a jour !');
                } else { setFlash('danger','Erreur upload.'); }
            }
        } else { setFlash('danger','Aucune photo selectionnee.'); }
        redirect(APP_URL.'/modules/parametres/index.php?s=profil');
    }

    if ($section === 'suppr_avatar') {
        if ($user['avatar'] && file_exists(__DIR__.'/../../uploads/avatars/'.$user['avatar']))
            unlink(__DIR__.'/../../uploads/avatars/'.$user['avatar']);
        $db->prepare("UPDATE utilisateurs SET avatar=NULL WHERE id=:id")->execute([':id'=>$userId]);
        $_SESSION['user_avatar'] = null;
        setFlash('success','Photo supprimee.');
        redirect(APP_URL.'/modules/parametres/index.php?s=profil');
    }

    if ($section === 'profil') {
        $nom=clean($_POST['nom']??''); $prenom=clean($_POST['prenom']??''); $email=clean($_POST['email']??'');
        if ($nom && $prenom && $email) {
            $db->prepare("UPDATE utilisateurs SET nom=:n,prenom=:p,email=:e WHERE id=:id")->execute([':n'=>$nom,':p'=>$prenom,':e'=>$email,':id'=>$userId]);
            $_SESSION['user_nom']=$nom; $_SESSION['user_prenom']=$prenom;
            setFlash('success','Profil mis a jour !');
        } else setFlash('danger','Tous les champs sont obligatoires.');
        redirect(APP_URL.'/modules/parametres/index.php?s=profil');
    }

    if ($section === 'password') {
        $actuel=$_POST['mdp_actuel']??''; $nouveau=$_POST['mdp_nouveau']??''; $confirm=$_POST['mdp_confirm']??'';
        if (!password_verify($actuel,$user['mot_de_passe'])) setFlash('danger','Mot de passe actuel incorrect.');
        elseif (strlen($nouveau)<8) setFlash('danger','Minimum 8 caracteres.');
        elseif ($nouveau!==$confirm) setFlash('danger','Les mots de passe ne correspondent pas.');
        else {
            $db->prepare("UPDATE utilisateurs SET mot_de_passe=:h WHERE id=:id")->execute([':h'=>password_hash($nouveau,PASSWORD_BCRYPT,['cost'=>12]),':id'=>$userId]);
            setFlash('success','Mot de passe change !');
        }
        redirect(APP_URL.'/modules/parametres/index.php?s=profil');
    }

    if ($section === 'permissions') {
        // Seul l'administrateur peut modifier les permissions
        if ($_SESSION['user_role'] !== 'administrateur') {
            setFlash('danger', 'Accès refusé.'); redirect(APP_URL.'/modules/parametres/index.php?s=permissions');
        }
        $modules = ['produits','stock','ventes','rapports','fournisseurs','messagerie','utilisateurs'];
        $actions = ['voir','creer','modifier','supprimer','exporter'];
        $roles   = ['caissier','magasinier'];
        foreach ($roles as $role) {
            foreach ($modules as $mod) {
                foreach ($actions as $act) {
                    $key = $role.'_'.$mod.'_'.$act;
                    $val = isset($_POST[$key]) ? 1 : 0;
                    $db->prepare("INSERT INTO permissions (role, module, action, autorise)
                        VALUES (:r,:m,:a,:v)
                        ON DUPLICATE KEY UPDATE autorise=:v2")
                       ->execute([':r'=>$role,':m'=>$mod,':a'=>$act,':v'=>$val,':v2'=>$val]);
                }
            }
        }
        // Invalider le cache permissions de tous les utilisateurs connectés
        invaliderPermissions();
        setFlash('success', 'Permissions sauvegardées !');
        redirect(APP_URL.'/modules/parametres/index.php?s=permissions');
    }

    if ($section === 'boutique') {
        foreach (['boutique_nom','boutique_adresse','boutique_tel','boutique_email','boutique_devise','ticket_footer'] as $cle)
            setParam($db,$cle,clean($_POST[$cle]??''));
        setFlash('success','Parametres boutique sauvegardes !');
        redirect(APP_URL.'/modules/parametres/index.php?s=boutique');
    }

}

$p = [
    'boutique_nom'     => getParam($db,'boutique_nom','GestiBoutique'),
    'boutique_adresse' => getParam($db,'boutique_adresse','Lome, Togo'),
    'boutique_tel'     => getParam($db,'boutique_tel',''),
    'boutique_email'   => getParam($db,'boutique_email',''),
    'boutique_devise'  => getParam($db,'boutique_devise','FCFA'),
    'ticket_footer'    => getParam($db,'ticket_footer','Merci pour votre achat !'),
    'theme_couleur'    => $_SESSION['theme_couleur'] ?? getParam($db,'theme_couleur','bleu'),
    'theme_mode'       => $_SESSION['theme_mode']    ?? getParam($db,'theme_mode','clair'),
];

$stmt->execute([':id'=>$userId]); $user=$stmt->fetch();
$historique=$db->prepare("SELECT * FROM sessions_log WHERE utilisateur_id=:uid ORDER BY created_at DESC LIMIT 20");
$historique->execute([':uid'=>$userId]); $historique=$historique->fetchAll();

$section='profil'; if(isset($_GET['s'])) $section=$_GET['s'];

// Charger les permissions actuelles pour la page permissions
$permsDB = [];
if ($section === 'permissions') {
    $rows = $db->query("SELECT role, module, action, autorise FROM permissions WHERE role IN ('caissier','magasinier')")->fetchAll();
    foreach ($rows as $r) {
        $permsDB[$r['role']][$r['module']][$r['action']] = (bool)$r['autorise'];
    }
}
$pageTitle="Parametres";

// Récupérer les variables de thème depuis la session (chargées par header.php)
$modeSombre = ($_SESSION['theme_mode'] ?? 'clair') === 'sombre';
$themeCouleurs = ['bleu'=>'#1F5C99','vert'=>'#16a34a','violet'=>'#7c3aed','orange'=>'#ea580c','rouge'=>'#dc2626'];
$couleurActive = $themeCouleurs[$_SESSION['theme_couleur'] ?? 'bleu'] ?? '#1F5C99';

require_once __DIR__ . '/../../includes/header.php';
?>
<style>
/* Couleur active depuis le thème */
:root{--bleu-clair:<?=$couleurActive?>cc;}
<?php if($modeSombre):?>
body,.main-content{background:#0f172a!important;color:#e2e8f0!important;}
.param-panel{background:#1e293b!important;border-color:#334155!important;}
.topbar{background:#1e293b!important;border-color:#334155!important;}
.form-control,.form-select{background:#0f172a!important;border-color:#334155!important;color:#e2e8f0!important;}
.param-panel-header{border-color:#334155!important;}
hr{border-color:#334155!important;}
<?php endif;?>
.param-container{max-width:1200px;margin:0 auto;padding:1.5rem;}
.param-tabs .nav-tabs{border-bottom:1px solid #e2e8f0;margin-bottom:1.5rem;}
.param-tabs .nav-link{border-radius:12px 12px 0 0;border:1px solid #e2e8f0;border-bottom:none;background:#f8fafc;color:#475569;font-weight:600;padding:.75rem 1.25rem;transition:all .2s;}
.param-tabs .nav-link:hover{background:#e2e8f0;color:#1e293b;}
.param-tabs .nav-link.active{background:#fff;color:var(--bleu);border-color:#e2e8f0 #e2e8f0 #fff;}
.param-tab-content{background:#fff;border:1px solid #e2e8f0;border-radius:0 12px 12px 12px;padding:2rem;animation:fadeIn .5s ease;}
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.tab-pane.show { display: block; }
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.param-section{margin-bottom:2rem;}
.param-section h6{font-size:1.1rem;font-weight:700;color:#1e293b;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
.param-section h6 i{color:var(--bleu);}
.param-card{background: linear-gradient(135deg, #fafbfc 0%, #f1f5f9 100%);border:1px solid #e2e8f0;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;box-shadow:0 4px 12px rgba(0,0,0,0.05);}
.param-card:hover{box-shadow:0 6px 18px rgba(0,0,0,0.08);}
.avatar-wrapper{position:relative;display:inline-block;}
.avatar-img{width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--bleu);box-shadow:0 10px 18px rgba(31,92,153,0.2);}
.avatar-initiales{width:96px;height:96px;border-radius:50%;background:var(--bleu);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;box-shadow:0 10px 18px rgba(31,92,153,0.18);}
.avatar-edit-btn{position:absolute;bottom:0;right:0;width:30px;height:30px;border-radius:50%;background:var(--bleu);color:#fff;border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.75rem;transition:transform .2s,box-shadow .2s;}
.avatar-edit-btn:hover{transform:scale(1.1);box-shadow:0 6px 12px rgba(31,92,153,0.35);}
.histo-item{display:flex;align-items:center;gap:1rem;padding:.8rem 0;border-bottom:1px solid rgba(248,250,252,0.8);}
.histo-icon{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.histo-icon.login{background:#dcfce7;color:#16a34a;}
.histo-icon.logout{background:#fee2e2;color:#dc2626;}
.form-control{border-radius:12px;transition:border-color .2s,box-shadow .2s;}
.form-control:focus{border-color:var(--bleu);box-shadow:0 0 0 .2rem rgba(31,92,153,0.16);}
.btn{border-radius:12px;transition:all .2s;}
.btn:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(0,0,0,0.12);}
@media(max-width:768px){.param-layout{grid-template-columns:1fr;}}
</style>

<div class="param-container">
    <ul class="nav nav-tabs param-tabs" id="paramTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a href="?s=profil" class="nav-link <?=$section==='profil'?'active':''?>"><i class="bi bi-person-circle me-2"></i>Mon profil</a>
            </li>
            <li class="nav-item" role="presentation">
            <a href="?s=boutique" class="nav-link <?=$section==='boutique'?'active':''?>"><i class="bi bi-shop me-2"></i>Boutique</a>
            </li>
            <li class="nav-item" role="presentation">
            <a href="?s=securite" class="nav-link <?=$section==='securite'?'active':''?>"><i class="bi bi-shield-lock me-2"></i>Sécurité</a>
            </li>
            <?php if($_SESSION['user_role']==='administrateur'): ?>
            <li class="nav-item" role="presentation">
            <a href="?s=permissions" class="nav-link <?=$section==='permissions'?'active':''?>"><i class="bi bi-toggles me-2"></i>Permissions</a>
        </li>
    <?php endif; ?>
</ul>

<div class="tab-content param-tab-content" id="paramTabsContent">
<div class="tab-pane fade <?=$section==='profil'?'show active':''?>" id="profil" role="tabpanel" aria-labelledby="profil-tab">
<?php $f=getFlash(); if($f):?>
<div class="alert alert-<?=$f['type']?> alert-dismissible mx-4 mt-3 mb-0 fade show"><?=$f['msg']?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif;?>

<div class="param-section">
<h6><i class="bi bi-person-circle"></i>Informations personnelles</h6>
<div class="param-card">
    <!-- Photo de profil -->
    <div class="d-flex align-items-center gap-4 mb-4 p-4" style="background: linear-gradient(135deg, rgba(248,250,252,0.8) 0%, rgba(241,245,249,0.9) 100%);border-radius:16px;border:1px solid rgba(226,232,240,0.6);box-shadow:0 6px 20px rgba(0,0,0,0.04);">
        <div class="avatar-wrapper">
            <?php if(!empty($user['avatar'])&&file_exists(__DIR__.'/../../uploads/avatars/'.$user['avatar'])):?>
            <img src="<?=APP_URL?>/uploads/avatars/<?=$user['avatar']?>" class="avatar-img" alt="Photo">
            <?php else:?>
            <div class="avatar-initiales"><?=strtoupper(substr($user['prenom'],0,1).substr($user['nom'],0,1))?></div>
            <?php endif;?>
            <label for="inputAvatar" class="avatar-edit-btn" title="Changer la photo"><i class="bi bi-camera-fill"></i></label>
        </div>
        <div style="flex:1;">
            <div style="font-size:1.1rem;font-weight:700;color:#1e293b;"><?=clean($user['prenom'].' '.$user['nom'])?></div>
            <div style="font-size:.83rem;color:#64748b;"><?=clean($user['email'])?></div>
            <span class="badge mt-1" style="background:var(--bleu);"><?=ucfirst($user['role'])?></span>
            <?php if(!empty($user['avatar'])):?>
            <form method="POST" class="d-inline ms-2">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="section" value="suppr_avatar">
                <button class="btn btn-sm btn-outline-danger mt-1" onclick="return confirm('Supprimer la photo ?')"><i class="bi bi-trash3 me-1"></i>Supprimer</button>
            </form>
            <?php endif;?>
        </div>
    </div>
    <form method="POST" enctype="multipart/form-data" id="formAvatar">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="section" value="avatar">
        <input type="file" id="inputAvatar" name="avatar" accept="image/*" class="d-none">
    </form>

    <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="section" value="profil">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Nom</label><input type="text" name="nom" class="form-control" value="<?=clean($user['nom'])?>" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Prenom</label><input type="text" name="prenom" class="form-control" value="<?=clean($user['prenom'])?>" required></div>
            <div class="col-12"><label class="form-label fw-semibold">Adresse email</label><input type="email" name="email" class="form-control" value="<?=clean($user['email'])?>" required></div>
        </div>
        <button type="submit" class="btn mt-3 fw-semibold" style="background:var(--bleu);color:#fff;"><i class="bi bi-check2 me-1"></i>Enregistrer le profil</button>
    </form>
</div>
</div>

<div class="param-section">
<h6><i class="bi bi-key"></i>Changer le mot de passe</h6>
<div class="param-card">
    <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="section" value="password">
        <div class="row g-3">
            <div class="col-12"><label class="form-label fw-semibold">Mot de passe actuel</label><input type="password" name="mdp_actuel" class="form-control" required placeholder="........"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Nouveau mot de passe</label><input type="password" name="mdp_nouveau" id="mdpNouv" class="form-control" required placeholder="Min. 8 caracteres" minlength="8"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Confirmer</label><input type="password" name="mdp_confirm" class="form-control" required placeholder="........"></div>
            <div class="col-12"><div id="mdpForce" style="height:5px;border-radius:3px;background:#e2e8f0;"><div id="mdpForceBarre" style="height:100%;border-radius:3px;width:0;transition:.3s;"></div></div><div id="mdpForceLabel" style="font-size:.75rem;color:#64748b;margin-top:.25rem;"></div></div>
        </div>
        <button type="submit" class="btn btn-warning mt-3 fw-semibold"><i class="bi bi-shield-lock me-1"></i>Changer le mot de passe</button>
    </form>
</div>
</div>
</div>

<div class="tab-pane fade <?=$section==='boutique'?'show active':''?>" id="boutique" role="tabpanel" aria-labelledby="boutique-tab">
<div class="param-section">
<h6><i class="bi bi-shop"></i>Paramètres de la boutique</h6>
<div class="param-card">
    <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="section" value="boutique">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Nom de la boutique</label><input type="text" name="boutique_nom" class="form-control" value="<?=clean($p['boutique_nom'])?>" required oninput="document.getElementById('prev_nom').textContent=this.value"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Devise</label><select name="boutique_devise" class="form-select"><?php foreach(['FCFA','EUR','USD','XOF','GHS'] as $dev):?><option <?=$p['boutique_devise']===$dev?'selected':''?>><?=$dev?></option><?php endforeach;?></select></div>
            <div class="col-12"><label class="form-label fw-semibold">Adresse</label><input type="text" name="boutique_adresse" class="form-control" value="<?=clean($p['boutique_adresse'])?>" oninput="document.getElementById('prev_adresse').textContent=this.value"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Téléphone</label><input type="text" name="boutique_tel" class="form-control" value="<?=clean($p['boutique_tel'])?>" placeholder="+228 XX XX XX XX"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Email</label><input type="email" name="boutique_email" class="form-control" value="<?=clean($p['boutique_email'])?>"></div>
            <div class="col-12"><label class="form-label fw-semibold">Message pied de ticket</label><textarea name="ticket_footer" class="form-control" rows="2" oninput="document.getElementById('prev_footer').textContent=this.value"><?=clean($p['ticket_footer'])?></textarea></div>
        </div>
        <div class="mt-4 p-3" style="background:#f8fafc;border-radius:12px;border:1px dashed #cbd5e1;font-family:monospace;font-size:.82rem;color:#334155;text-align:center;">
            <div style="font-weight:700;font-size:1rem;" id="prev_nom"><?=clean($p['boutique_nom'])?></div>
            <div id="prev_adresse"><?=clean($p['boutique_adresse'])?></div>
            <div>------------------</div>
            <div style="text-align:left;">Article 1 ......... 500 FCFA</div>
            <div>------------------</div>
            <div style="font-weight:700;">TOTAL : 700 FCFA</div>
            <div>------------------</div>
            <div id="prev_footer" style="font-style:italic;"><?=clean($p['ticket_footer'])?></div>
        </div>
        <button type="submit" class="btn mt-4 fw-semibold" style="background:var(--bleu);color:#fff;"><i class="bi bi-check2 me-1"></i>Sauvegarder</button>
    </form>
</div>
</div>
</div>

<div class="tab-pane fade <?=$section==='permissions'?'show active':''?>" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">

<div class="param-section">

<h6><i class="bi bi-toggles"></i>Permissions par rôle</h6>

<div class="param-card">

<div class="alert alert-info mb-4" style="background:#eff6ff;color:#1e40af;border:none;border-radius:10px;font-size:.85rem;">
    <i class="bi bi-info-circle-fill me-2"></i>
    <strong>Administrateur</strong> — a toujours accès à tout et ne peut pas être restreint.<br>
    Configurez les permissions pour <strong>Caissier</strong> et <strong>Magasinier</strong>.
</div>

<style>
.perm-table { width:100%; border-collapse:separate; border-spacing:0; font-size:.82rem; }
.perm-table th { background:var(--bleu); color:#fff; padding:8px 10px; font-weight:600; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; text-align:center; }
.perm-table th:first-child { text-align:left; border-radius:8px 0 0 0; }
.perm-table th:last-child { border-radius:0 8px 0 0; }
.perm-table td { padding:8px 10px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.perm-table td:first-child { font-weight:600; color:#374151; }
.perm-table tr:hover td { background:#f8fafc; }
.perm-table tr:last-child td { border-bottom:none; }
.perm-check { width:18px; height:18px; cursor:pointer; accent-color:var(--bleu); }
.role-tab { display:flex; gap:.5rem; margin-bottom:1.25rem; }
.role-tab button {
    padding:.5rem 1.25rem; border-radius:8px; border:2px solid #e2e8f0;
    background:#f8fafc; font-size:.85rem; font-weight:600; color:#64748b; cursor:pointer; transition:all .15s;
}
.role-tab button.active { background:var(--bleu); color:#fff; border-color:var(--bleu); }
.perm-section { display:none; }
.perm-section.active { display:block; }
.module-badge { display:inline-flex; align-items:center; gap:.4rem; }
.module-badge i { font-size:.9rem; color:var(--bleu); }
.check-all-row { cursor:pointer; }
.perm-legend { display:flex; gap:1.25rem; font-size:.75rem; color:#64748b; margin-top:1rem; align-items:center; flex-wrap:wrap; }
.perm-legend span { display:flex; align-items:center; gap:.4rem; }
</style>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="section" value="permissions">

    <!-- Onglets rôles -->
    <div class="role-tab">
        <button type="button" class="active" onclick="switchRole('caissier',this)">
            <i class="bi bi-cash-register me-1"></i>Caissier
        </button>
        <button type="button" onclick="switchRole('magasinier',this)">
            <i class="bi bi-boxes me-1"></i>Magasinier
        </button>
    </div>

    <?php
    $modules = [
        'produits'     => ['label'=>'Produits',           'icon'=>'bi-box-seam'],
        'stock'        => ['label'=>'Mouvements stock',   'icon'=>'bi-arrow-left-right'],
        'ventes'       => ['label'=>'Ventes & Caisse',    'icon'=>'bi-cash-register'],
        'rapports'     => ['label'=>'Rapports',           'icon'=>'bi-bar-chart-line'],
        'fournisseurs' => ['label'=>'Fournisseurs',       'icon'=>'bi-truck'],
        'messagerie'   => ['label'=>'Messagerie',         'icon'=>'bi-chat-dots'],
        'utilisateurs' => ['label'=>'Utilisateurs',       'icon'=>'bi-people'],
    ];
    $actions = ['voir'=>'👁 Voir','creer'=>'➕ Créer','modifier'=>'✏️ Modifier','supprimer'=>'🗑 Supprimer','exporter'=>'📤 Exporter'];
    $roles   = ['caissier'=>'Caissier','magasinier'=>'Magasinier'];
    foreach ($roles as $role => $roleLabel): ?>
    <div class="perm-section <?=($role==='caissier'?'active':'')?>" id="perm-<?=$role?>">
        <table class="perm-table">
            <thead>
                <tr>
                    <th style="width:180px;">Module</th>
                    <?php foreach($actions as $act=>$actLabel): ?>
                    <th><?=$actLabel?></th>
                    <?php endforeach; ?>
                    <th>Tout</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($modules as $mod=>$modInfo): ?>
            <tr>
                <td>
                    <span class="module-badge">
                        <i class="bi <?=$modInfo['icon']?>"></i>
                        <?=$modInfo['label']?>
                    </span>
                </td>
                <?php foreach($actions as $act=>$actLabel):
                    $key = $role.'_'.$mod.'_'.$act;
                    $checked = $permsDB[$role][$mod][$act] ?? false;
                ?>
                <td class="text-center">
                    <input type="checkbox" class="perm-check perm-<?=$role?>-<?=$mod?>"
                        name="<?=$key?>" id="<?=$key?>"
                        <?=$checked?'checked':''?>>
                </td>
                <?php endforeach; ?>
                <td class="text-center">
                    <input type="checkbox" class="perm-check check-all-row"
                        title="Tout cocher/décocher pour ce module"
                        data-role="<?=$role?>" data-mod="<?=$mod?>"
                        <?=(array_sum(array_values($permsDB[$role][$mod] ?? []))==count($actions)?'checked':'')?>
                        onchange="toggleAll(this,'<?=$role?>','<?=$mod?>')">
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="perm-legend">
            <span><i class="bi bi-eye text-primary"></i> Voir = accéder à la page</span>
            <span><i class="bi bi-plus-circle text-success"></i> Créer = ajouter un élément</span>
            <span><i class="bi bi-pencil text-warning"></i> Modifier = éditer un élément</span>
            <span><i class="bi bi-trash text-danger"></i> Supprimer = effacer un élément</span>
            <span><i class="bi bi-download text-info"></i> Exporter = télécharger CSV/PDF</span>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn fw-semibold" style="background:var(--bleu);color:#fff;">
            <i class="bi bi-check2 me-1"></i>Sauvegarder les permissions
        </button>
        <a href="?s=permissions" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
</div>

<script>
function switchRole(role, btn) {
    document.querySelectorAll('.role-tab button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.perm-section').forEach(s => s.classList.remove('active'));
    document.getElementById('perm-' + role).classList.add('active');
}
function toggleAll(masterChk, role, mod) {
    const boxes = document.querySelectorAll('.perm-' + role + '-' + mod);
    boxes.forEach(b => { b.checked = masterChk.checked; });
}
// Synchroniser le check-all quand on coche individuellement
document.querySelectorAll('.perm-check:not(.check-all-row)').forEach(chk => {
    chk.addEventListener('change', function() {
        const name = this.name; // role_module_action
        const parts = name.split('_');
        const role = parts[0], mod = parts[1];
        const total  = document.querySelectorAll('.perm-' + role + '-' + mod).length;
        const checked = document.querySelectorAll('.perm-' + role + '-' + mod + ':checked').length;
        const master = document.querySelector('[data-role="' + role + '"][data-mod="' + mod + '"]');
        if (master) master.checked = (checked === total);
    });
});
</script>
</div>
</div>
</div>

<div class="tab-pane fade <?=$section==='securite'?'show active':''?>" id="securite" role="tabpanel" aria-labelledby="securite-tab">
<div class="param-section">
<h6><i class="bi bi-shield-lock"></i>Securite et Connexions</h6>
<div class="param-card">
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="p-3 rounded-3 text-center" style="background:var(--bleu-clair);"><div style="font-size:1.5rem;font-weight:700;color:var(--bleu);"><?=count($historique)?></div><div style="font-size:.78rem;color:#64748b;">Connexions</div></div></div>
        <div class="col-md-4"><div class="p-3 rounded-3 text-center" style="background:#dcfce7;"><div style="font-size:1.5rem;font-weight:700;color:#16a34a;"><?=count(array_filter($historique,fn($h)=>$h['action']==='login'))?></div><div style="font-size:.78rem;color:#64748b;">Ouvertures session</div></div></div>
        <div class="col-md-4"><div class="p-3 rounded-3 text-center" style="background:#fff7ed;"><div style="font-size:1rem;font-weight:700;color:#ea580c;"><?=$historique?date('d/m/Y H:i',strtotime($historique[0]['created_at'])):'--'?></div><div style="font-size:.78rem;color:#64748b;">Derniere activite</div></div></div>
    </div>
    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Historique des connexions</h6>
    <?php if(empty($historique)):?><div class="text-center text-muted py-4"><i class="bi bi-shield-check fs-2 d-block mb-2"></i>Aucun historique</div>
    <?php else: foreach($historique as $h): $il=$h['action']==='login';?>
    <div class="histo-item">
        <div class="histo-icon <?=$il?'login':'logout'?>"><i class="bi bi-<?=$il?'box-arrow-in-right':'box-arrow-right'?>"></i></div>
        <div style="flex:1;"><div style="font-size:.87rem;font-weight:600;"><?=$il?'Connexion':'Deconnexion'?></div><div style="font-size:.78rem;color:#64748b;">IP : <?=clean($h['ip_address']??'Inconnue')?></div></div>
        <div style="font-size:.78rem;color:#94a3b8;"><?=date('d/m/Y H:i',strtotime($h['created_at']))?></div>
    </div>
    <?php endforeach; endif;?>
    <hr class="my-4">
    <h6 class="fw-bold mb-2 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Zone de danger</h6>
    <div class="p-3 rounded-3" style="background:#fef2f2;border:1px solid #fecaca;">
        <div style="font-size:.87rem;color:#7f1d1d;margin-bottom:.75rem;"><strong>Deconnexion immediate</strong><br>Invalide votre session sur tous les appareils.</div>
        <a href="<?=APP_URL?>/modules/auth/logout.php" class="btn btn-outline-danger btn-sm fw-semibold"><i class="bi bi-box-arrow-right me-1"></i>Se deconnecter</a>
    </div>
</div>
</div>
</div>
</div>
</div>

<script>
document.getElementById('inputAvatar')?.addEventListener('change',function(){
    if(this.files.length>0) document.getElementById('formAvatar').submit();
});
document.getElementById('mdpNouv')?.addEventListener('input',function(){
    const v=this.value; let s=0;
    if(v.length>=8)s++; if(/[A-Z]/.test(v))s++; if(/[0-9]/.test(v))s++; if(/[^A-Za-z0-9]/.test(v))s++;
    const n=[{p:'25%',c:'#ef4444',t:'Faible'},{p:'50%',c:'#f97316',t:'Moyen'},{p:'75%',c:'#eab308',t:'Bon'},{p:'100%',c:'#22c55e',t:'Tres fort'}][Math.max(0,s-1)];
    document.getElementById('mdpForceBarre').style.cssText=`width:${v.length?n.p:'0'};background:${n.c};height:100%;border-radius:3px;transition:.3s;`;
    document.getElementById('mdpForceLabel').textContent=v.length?n.t:'';
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>