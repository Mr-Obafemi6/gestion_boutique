    <?php
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/Message.php';
    requireLogin();

    $msgModel  = new Message();
    $userId    = $_SESSION['user_id'];

    // ── Actions POST ─────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCSRF();
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
    .msg-sidebar { background:#fff; border-radius:20px; border:1px solid #e2e8f2; padding:1.2rem; display:flex; flex-direction:column; gap:.75rem; box-shadow:0 12px 30px rgba(15, 23, 42, .05); }
    .msg-sidebar .btn-compose { background:var(--bleu); color:#fff; border:none; border-radius:14px; padding:.95rem 1rem; font-weight:700; font-size:.95rem; width:100%; cursor:pointer; transition:background .2s, transform .2s; display:flex; align-items:center; justify-content:center; gap:.5rem; }
    .msg-sidebar .btn-compose:hover { background:#153F6E; transform:translateY(-1px); }
    .msg-search { margin-top:1rem; }
    .msg-search input { width:100%; border-radius:14px; border:1px solid #e2e8f2; padding:.8rem 1rem; font-size:.95rem; color:#0f172a; background:#f8fafc; }

    .msg-nav-item { display:flex; align-items:center; gap:.75rem; padding:.85rem 1rem; border-radius:16px; cursor:pointer; font-size:.92rem; font-weight:600; color:#475569; transition:all .2s; text-decoration:none; border:1px solid transparent; }
    .msg-nav-item:hover { background:#f8fafc; color:var(--bleu); border-color:#e2e8f2; }
    .msg-nav-item.active { background:#EFF6FF; color:var(--bleu); border-color:#dbeafe; }
    .msg-nav-item .badge { margin-left:auto; background:#ef4444; color:#fff; border-radius:20px; font-size:.72rem; padding:3px 9px; }

    .msg-panel { background:linear-gradient(180deg,#f8fafc 0%,#eef2ff 100%); border-radius:20px; border:none; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 18px 50px rgba(15, 23, 42, .06); }
    .msg-panel-header { padding:1.1rem 1.4rem; border-bottom:1px solid #e2e8f2; display:flex; align-items:center; justify-content:space-between; background:#fff; }
    .msg-panel-header h5 { margin:0; font-size:1.08rem; font-weight:800; color:#0f172a; }

    .msg-list { flex:1; overflow-y:auto; padding:1rem 0; background:transparent; }
    .msg-list::-webkit-scrollbar { width:8px; }
    .msg-list::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:999px; }
    .msg-list::-webkit-scrollbar-track { background:transparent; }
    .msg-item { display:flex; align-items:flex-start; gap:1rem; padding:1rem 1.15rem; border-radius:18px; margin:.4rem 1rem; background:#fff; border:1px solid transparent; box-shadow:0 8px 24px rgba(15, 23, 42, .05); transition:transform .2s, box-shadow .2s, border-color .2s; }
    .msg-item:hover { background:#fff; transform:translateY(-1px); border-color:#e2e8f2; box-shadow:0 12px 28px rgba(15, 23, 42, .1); }
    .msg-item.unread { background:#EFF6FF; }
    .msg-item.unread .msg-sujet { font-weight:700; color:#0f172a; }
    .msg-avatar { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,#2563eb,#22c55e); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.95rem; flex-shrink:0; box-shadow:0 10px 20px rgba(37, 99, 235, .14); }
    .msg-meta { flex:1; min-width:0; }
    .msg-nom { font-size:.88rem; font-weight:700; color:#1e293b; }
    .msg-sujet { font-size:.85rem; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .msg-apercu { font-size:.79rem; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:.35rem; }
    .msg-date { font-size:.75rem; color:#64748b; white-space:nowrap; flex-shrink:0; }
    .msg-unread-dot { width:10px; height:10px; border-radius:50%; background:var(--bleu); flex-shrink:0; margin-top:8px; }

    .msg-detail { padding:1.5rem; background:transparent; min-height:calc(100vh - 240px); }
    .msg-detail-header { background:#fff; border-radius:24px; padding:1.5rem 1.6rem; display:flex; flex-direction:column; gap:.85rem; box-shadow:0 16px 40px rgba(15, 23, 42, .06); border:1px solid #e2e8f0; }
    .msg-detail-sujet { font-size:1.35rem; font-weight:800; color:#0f172a; margin-bottom:0; }
    .msg-detail-meta { font-size:.88rem; color:#475569; display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
    .msg-detail-corps { font-size:.96rem; color:#334155; line-height:1.9; white-space:pre-wrap; }

    .chat-thread { display:flex; flex-direction:column; gap:1rem; margin-top:1.5rem; }
    .msg-bubble { max-width:720px; padding:1.35rem 1.4rem; border-radius:24px; position:relative; box-shadow:0 20px 40px rgba(15, 23, 42, .08); }
    .msg-bubble.incoming { align-self:flex-start; background:#fff; border-top-left-radius:4px; }
    .msg-bubble.incoming::after { content:''; position:absolute; left:-12px; top:18px; width:18px; height:18px; background:#fff; border-radius:0 0 0 18px; box-shadow:0 5px 10px rgba(15,23,42,.04); }
    .msg-bubble.outgoing { align-self:flex-end; background:linear-gradient(135deg,#2563eb,#22c55e); color:#fff; border-top-right-radius:4px; }
    .msg-bubble.outgoing::after { content:''; position:absolute; right:-12px; top:18px; width:18px; height:18px; background:linear-gradient(135deg,#2563eb,#22c55e); border-radius:0 0 18px 0; }
    .msg-bubble-meta { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.8rem; font-size:.83rem; color:#64748b; }
    .msg-bubble.outgoing .msg-bubble-meta { color:rgba(255,255,255,.85); }
    .msg-bubble-label { font-weight:700; }

    .vide-msg { display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; color:#94a3b8; padding:3rem; text-align:center; }
    .vide-msg i { font-size:3rem; margin-bottom:1rem; opacity:.3; }

    @media(max-width:768px) { .msg-layout { grid-template-columns:1fr; } .msg-sidebar { order:2; } .msg-panel { order:1; } }
    </style>

    <div class="msg-layout">

        <!-- ── SIDEBAR ───────────────────────────────────────────── -->
        <div class="msg-sidebar">
            <button class="btn-compose" data-bs-toggle="modal" data-bs-target="#modalCompose">
                <i class="bi bi-pencil-square"></i> Nouveau message
            </button>
            <div class="msg-search">
                <input type="text" class="form-control" placeholder="Rechercher un message..." disabled>
            </div>
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
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
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
                <div class="chat-thread">
                    <div class="msg-bubble <?= $moi ? 'outgoing' : 'incoming' ?>">
                        <div class="msg-bubble-meta">
                            <span class="msg-bubble-label"><?= $moi ? 'Envoyé à' : 'Reçu de' ?></span>
                            <span><?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?></span>
                        </div>
                        <div class="msg-detail-corps"><?= nl2br(clean($msg['contenu'])) ?></div>
                    </div>
                </div>
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
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
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

    // ── Rafraîchissement automatique du badge messages ───────
    // Toutes les 30 secondes, vérifie le nb de messages non lus
    (function autoRefreshMessages() {
        const badge = document.querySelector('.badge.rounded-pill[style*="ef4444"]') ||
                      document.querySelector('#msgBadge');

        function checkMessages() {
            fetch('<?= APP_URL ?>/modules/messagerie/index.php', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.text())
            .then(html => {
                // Extraire le nb de messages non lus depuis le HTML retourné
                const parser   = new DOMParser();
                const doc      = parser.parseFromString(html, 'text/html');
                const newBadge = doc.querySelector('.badge.rounded-pill[style*="ef4444"]');
                const oldBadge = document.querySelector('.badge.rounded-pill[style*="ef4444"]');

                if (newBadge && oldBadge) {
                    if (newBadge.textContent !== oldBadge.textContent) {
                        // Nouveau message — rechargement complet pour afficher
                        window.location.reload();
                    }
                } else if (newBadge && !oldBadge) {
                    // Nouveau message non lu apparu
                    window.location.reload();
                }
            })
            .catch(() => {}); // silencieux si erreur réseau
        }

        // Vérifier toutes les 30 secondes
        setInterval(checkMessages, 30000);
    })();

    // ── Notification browser si onglet non actif ──────────────
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>