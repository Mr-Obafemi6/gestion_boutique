    <?php
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/Message.php';
    requireLogin();

    $msgModel  = new Message();
    $userId    = $_SESSION['user_id'];

    // ── Actions POST ─────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'envoyer') {
            $ok = $msgModel->envoyer(
                $userId,
                (int)$_POST['destinataire_id'],
                $_POST['sujet'] ?? '',
                $_POST['contenu'] ?? ''
            );
            setFlash($ok ? 'success' : 'danger', $ok ? 'Message envoyé avec succès !' : 'Erreur lors de l\'envoi.');
            redirect(APP_URL . '/modules/messagerie/index.php');
        }

        if ($action === 'supprimer') {
            $msgModel->supprimer((int)$_POST['msg_id'], $userId);
            setFlash('success', 'Message supprimé.');
            redirect(APP_URL . '/modules/messagerie/index.php?onglet=' . ($_POST['onglet'] ?? 'recus'));
        }
    }

    // ── Marquer lu si lecture ────────────────────────────────────
    if (isset($_GET['lire'])) {
        $msgModel->marquerLu((int)$_GET['lire'], $userId);
    }

    $onglet       = $_GET['onglet'] ?? 'recus';
    $recus        = $msgModel->getRecus($userId);
    $envoyes      = $msgModel->getEnvoyes($userId);
    $destinataires= $msgModel->getDestinataires($userId);
    $nonLus       = $msgModel->getNonLus($userId);
    $pageTitle    = "Messagerie";
    require_once __DIR__ . '/../../includes/header.php';
    ?>

    <style>
    .msg-layout { display:grid; grid-template-columns:260px 1fr; gap:1.25rem; min-height:calc(100vh - 140px); }
    .msg-sidebar { background:#fff; border-radius:14px; border:1px solid #e8edf2; padding:1rem; display:flex; flex-direction:column; gap:.5rem; }
    .msg-sidebar .btn-compose { background:var(--bleu); color:#fff; border:none; border-radius:10px; padding:.75rem 1rem; font-weight:700; font-size:.9rem; width:100%; cursor:pointer; transition:background .2s; display:flex; align-items:center; justify-content:center; gap:.5rem; }
    .msg-sidebar .btn-compose:hover { background:#153F6E; }
    .msg-nav-item { display:flex; align-items:center; gap:.6rem; padding:.6rem .75rem; border-radius:8px; cursor:pointer; font-size:.88rem; font-weight:600; color:#475569; transition:all .15s; text-decoration:none; }
    .msg-nav-item:hover { background:#f1f5f9; color:var(--bleu); }
    .msg-nav-item.active { background:#EBF3FB; color:var(--bleu); }
    .msg-nav-item .badge { margin-left:auto; background:#ef4444; color:#fff; border-radius:20px; font-size:.7rem; padding:2px 7px; }

    .msg-panel { background:#fff; border-radius:14px; border:1px solid #e8edf2; display:flex; flex-direction:column; overflow:hidden; }
    .msg-panel-header { padding:.9rem 1.2rem; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between; }
    .msg-panel-header h5 { margin:0; font-size:1rem; font-weight:700; color:#1e293b; }

    .msg-list { flex:1; overflow-y:auto; }
    .msg-item { display:flex; align-items:flex-start; gap:.75rem; padding:.85rem 1.2rem; border-bottom:1px solid #f8fafc; cursor:pointer; transition:background .15s; text-decoration:none; color:inherit; }
    .msg-item:hover { background:#f8fafc; }
    .msg-item.unread { background:#EBF3FB; }
    .msg-item.unread .msg-sujet { font-weight:700; color:#1e293b; }
    .msg-avatar { width:38px; height:38px; border-radius:50%; background:var(--bleu); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; flex-shrink:0; }
    .msg-meta { flex:1; min-width:0; }
    .msg-nom { font-size:.85rem; font-weight:600; color:#334155; }
    .msg-sujet { font-size:.82rem; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .msg-apercu { font-size:.78rem; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .msg-date { font-size:.75rem; color:#94a3b8; white-space:nowrap; flex-shrink:0; }
    .msg-unread-dot { width:8px; height:8px; border-radius:50%; background:var(--bleu); flex-shrink:0; margin-top:6px; }

    .msg-detail { padding:1.5rem; }
    .msg-detail-header { border-bottom:1px solid #f1f5f9; padding-bottom:1rem; margin-bottom:1rem; }
    .msg-detail-sujet { font-size:1.2rem; font-weight:700; color:#1e293b; margin-bottom:.5rem; }
    .msg-detail-meta { font-size:.83rem; color:#64748b; }
    .msg-detail-corps { font-size:.92rem; color:#334155; line-height:1.7; white-space:pre-wrap; }

    .vide-msg { display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; color:#94a3b8; padding:3rem; text-align:center; }
    .vide-msg i { font-size:3rem; margin-bottom:1rem; opacity:.3; }

    @media(max-width:768px) { .msg-layout { grid-template-columns:1fr; } }
    </style>

    <div class="msg-layout">

        <!-- ── SIDEBAR ───────────────────────────────────────────── -->
        <div class="msg-sidebar">
            <button class="btn-compose" data-bs-toggle="modal" data-bs-target="#modalCompose">
                <i class="bi bi-pencil-square"></i> Nouveau message
            </button>

            <div style="margin-top:.5rem;">
                <a href="?onglet=recus" class="msg-nav-item <?= $onglet==='recus'?'active':'' ?>">
                    <i class="bi bi-inbox"></i> Boite de reception
                    <?php if ($nonLus > 0): ?>
                    <span class="badge"><?= $nonLus ?></span>
                    <?php endif; ?>
                </a>
                <a href="?onglet=envoyes" class="msg-nav-item <?= $onglet==='envoyes'?'active':'' ?>">
                    <i class="bi bi-send"></i> Messages envoyes
                </a>
            </div>

            <div style="margin-top:auto; padding-top:1rem; border-top:1px solid #f1f5f9;">
                <div style="font-size:.75rem; color:#94a3b8; text-align:center;">
                    <i class="bi bi-envelope-check"></i>
                    <?= count($recus) ?> recu(s) · <?= count($envoyes) ?> envoye(s)
                </div>
            </div>
        </div>

        <!-- ── PANNEAU PRINCIPAL ─────────────────────────────────── -->
        <div class="msg-panel">

            <?php if (isset($_GET['lire'])): ?>
            <!-- ── DETAIL D'UN MESSAGE ── -->
            <?php
                $msg = $msgModel->getById((int)$_GET['lire']);
                if ($msg):
                    $moi = $msg['expediteur_id'] == $userId;
                    $interlocuteur = $moi
                        ? $msg['dest_nom'] . ' ' . $msg['dest_prenom']
                        : $msg['exp_nom']  . ' ' . $msg['exp_prenom'];
                    $initiale = strtoupper(substr($moi ? $msg['dest_nom'] : $msg['exp_nom'], 0, 1));
            ?>
            <div class="msg-panel-header">
                <h5><i class="bi bi-envelope-open me-2 text-primary"></i>Detail du message</h5>
                <div class="d-flex gap-2">
                    <a href="?onglet=<?= $onglet ?>" class="btn btn-sm btn-light">
                        <i class="bi bi-arrow-left me-1"></i>Retour
                    </a>
                    <?php if (!$moi): ?>
                    <button class="btn btn-sm btn-primary" onclick="repondre('<?= $msg['expediteur_id'] ?>', '<?= $msg['exp_nom'].' '.$msg['exp_prenom'] ?>', 'Re: <?= addslashes($msg['sujet']) ?>')" data-bs-toggle="modal" data-bs-target="#modalCompose">
                        <i class="bi bi-reply me-1"></i>Repondre
                    </button>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Supprimer ce message ?')">
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="msg_id" value="<?= $msg['id'] ?>">
                        <input type="hidden" name="onglet" value="<?= $onglet ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                    </form>
                </div>
            </div>
            <div class="msg-detail">
                <div class="msg-detail-header">
                    <div class="msg-detail-sujet"><?= clean($msg['sujet']) ?></div>
                    <div class="msg-detail-meta d-flex align-items-center gap-3">
                        <div class="msg-avatar"><?= $initiale ?></div>
                        <div>
                            <div style="font-weight:600; color:#334155;">
                                <?= $moi ? 'A : '.$interlocuteur : 'De : '.$interlocuteur ?>
                            </div>
                            <div><?= date('d/m/Y à H:i', strtotime($msg['created_at'])) ?></div>
                        </div>
                    </div>
                </div>
                <div class="msg-detail-corps"><?= nl2br(clean($msg['contenu'])) ?></div>
            </div>
            <?php else: ?>
            <div class="vide-msg"><i class="bi bi-envelope-x"></i><p>Message introuvable.</p></div>
            <?php endif; ?>

            <?php else: ?>
            <!-- ── LISTE DES MESSAGES ── -->
            <div class="msg-panel-header">
                <h5>
                    <i class="bi bi-<?= $onglet==='recus'?'inbox':'send' ?> me-2 text-primary"></i>
                    <?= $onglet==='recus' ? 'Boite de reception' : 'Messages envoyes' ?>
                </h5>
                <span style="font-size:.8rem; color:#94a3b8;">
                    <?= $onglet==='recus' ? count($recus) : count($envoyes) ?> message(s)
                </span>
            </div>

            <div class="msg-list">
                <?php
                $liste = $onglet === 'recus' ? $recus : $envoyes;
                if (empty($liste)):
                ?>
                <div class="vide-msg">
                    <i class="bi bi-envelope"></i>
                    <p>Aucun message <?= $onglet==='recus'?'recu':'envoye' ?></p>
                    <?php if ($onglet === 'recus'): ?>
                    <button class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#modalCompose">
                        <i class="bi bi-pencil-square me-1"></i>Ecrire un message
                    </button>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <?php foreach ($liste as $m):
                    $nonLu  = $onglet==='recus' && !$m['lu'];
                    $nom    = $onglet==='recus'
                        ? $m['exp_nom'].' '.$m['exp_prenom']
                        : $m['dest_nom'].' '.$m['dest_prenom'];
                    $initiale = strtoupper(substr($onglet==='recus' ? $m['exp_nom'] : $m['dest_nom'], 0, 1));
                    $apercu = mb_substr(strip_tags($m['contenu']), 0, 80) . '...';
                ?>
                <a class="msg-item <?= $nonLu?'unread':'' ?>"
                href="?lire=<?= $m['id'] ?>&onglet=<?= $onglet ?>">
                    <?php if ($nonLu): ?><div class="msg-unread-dot"></div><?php endif; ?>
                    <div class="msg-avatar"><?= $initiale ?></div>
                    <div class="msg-meta">
                        <div class="msg-nom"><?= clean($nom) ?></div>
                        <div class="msg-sujet"><?= clean($m['sujet']) ?></div>
                        <div class="msg-apercu"><?= clean($apercu) ?></div>
                    </div>
                    <div class="msg-date"><?= date('d/m', strtotime($m['created_at'])) ?></div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── MODAL COMPOSER ────────────────────────────────────────── -->
    <div class="modal fade" id="modalCompose" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--bleu); color:#fff;">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Nouveau message</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="envoyer">
                    <div class="modal-body">
                        <!-- Destinataire -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Destinataire</label>
                            <select name="destinataire_id" id="selectDest" class="form-select" required>
                                <option value="">-- Choisir un destinataire --</option>
                                <?php foreach ($destinataires as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= clean($d['nom'].' '.$d['prenom']) ?> — <?= ucfirst($d['role']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Sujet -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sujet</label>
                            <input type="text" name="sujet" id="inputSujet" class="form-control"
                                placeholder="Objet du message..." required maxlength="255">
                        </div>
                        <!-- Contenu -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message</label>
                            <textarea name="contenu" class="form-control" rows="7"
                                    placeholder="Ecrivez votre message ici..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary fw-semibold">
                            <i class="bi bi-send me-1"></i>Envoyer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Pré-remplir la modal pour répondre
    function repondre(destId, destNom, sujet) {
        document.getElementById('selectDest').value = destId;
        document.getElementById('inputSujet').value  = sujet;
    }
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>