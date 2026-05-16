    <?php
    // ============================================================
    //  modules/clients/index.php — Gestion des clients
    // ============================================================
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/Client.php';

    requireLogin();
    if (!peutFaire('clients','lire')) redirect(APP_URL . '/includes/acces_refuse.php');

    $clientModel = new Client();
    $action      = $_GET['action'] ?? 'list';
    $clientId    = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $message     = '';
    $messageType = 'info';

    // ── Traitement des formulaires ──────────────────────────────

    // Ajouter/Modifier client
    if ($_POST['form'] ?? false) {
        if (!peutFaire('clients', 'creer')) die('Accès refusé');

        $data = [
            'nom'             => $_POST['nom'] ?? '',
            'telephone'       => $_POST['telephone'] ?? '',
            'email'           => $_POST['email'] ?? '',
            'adresse'         => $_POST['adresse'] ?? '',
            'type_client'     => $_POST['type_client'] ?? 'particulier',
            'credit_autorise' => $_POST['credit_autorise'] ?? 0,
        ];

        if ($clientId) {
            $result = $clientModel->update($clientId, $data);
            $action = 'view';
        } else {
            $result = $clientModel->create($data);
            if ($result === true) {
                // Récupérer l'ID du nouveau client
                $stmt = getDB()->prepare("SELECT id FROM clients WHERE telephone = :tel ORDER BY id DESC LIMIT 1");
                $stmt->execute([':tel' => clean($data['telephone'])]);
                if ($row = $stmt->fetch()) {
                    $clientId = $row['id'];
                    $_GET['id'] = $clientId;
                    $action = 'view';
                }
            }
        }

        if ($result !== true) {
            $message = $result;
            $messageType = 'danger';
        } else {
            $message = 'Client ' . ($clientId ? 'modifié' : 'créé') . ' avec succès.';
            $messageType = 'success';
            if (!isset($_POST['redirect'])) {
                $action = 'view';
                $clientId = $clientId ?: $result;
            }
        }
    }

    // Remboursement
    if (($_POST['form'] ?? false) === 'rembourser' && peutFaire('clients', 'creer')) {
        $montant = (float)($_POST['montant'] ?? 0);
        $note    = $_POST['note'] ?? '';
        $result  = $clientModel->rembourser($clientId, $montant, $note);

        if ($result === true) {
            $message = "Remboursement de " . fmt($montant) . " enregistré.";
            $messageType = 'success';
        } else {
            $message = $result;
            $messageType = 'danger';
        }
    }

    // Suppression client
    if (($_GET['action'] ?? false) === 'delete' && $clientId && peutFaire('clients', 'creer')) {
        $result = $clientModel->delete($clientId);
        if ($result === true) {
            $message = "Client supprimé.";
            $messageType = 'success';
            $action = 'list';
            $clientId = 0;
        } else {
            $message = $result;
            $messageType = 'danger';
        }
    }

    require_once __DIR__ . '/../../includes/header.php';
    ?>

    <style>
        :root {
            --clients-bg: #fff;
            --clients-border: #e2e8f0;
            --clients-card-hover: rgba(37, 99, 235, 0.08);
            --clients-primary: #2563eb;
            --clients-primary-dark: #1d4ed8;
            --clients-muted: #64748b;
            --clients-text: #1f2937;
            --clients-light: #f8fafc;
            --clients-success: #059669;
            --clients-danger: #dc2626;
        }

        .client-card {
            background: var(--clients-bg);
            border: 1px solid var(--clients-border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .client-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-color: var(--clients-primary);
        }
        .client-card.selected {
            background: #eff6ff;
            border-color: #2563eb;
        }
        .client-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: .75rem;
        }
        .client-nom {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--clients-text);
        }
        .client-tel {
            font-size: .9rem;
            color: var(--clients-muted);
            margin-top: .25rem;
        }
        .badge-type {
            font-size: .75rem;
            font-weight: 700;
            padding: .25rem .6rem;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .badge-particulier {
            background: #f0fdf4;
            color: #15803d;
        }
        .badge-grossiste {
            background: #faf5ff;
            color: #7c3aed;
        }
        .badge-credit {
            background: #fef2f2;
            color: #dc2626;
            font-size: .8rem;
            margin-top: .5rem;
        }
        .client-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: .75rem;
            font-size: .85rem;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: .5rem;
            background: var(--clients-light);
            border-radius: 6px;
        }
        .stat-label {
            color: var(--clients-muted);
        }
        .stat-value {
            font-weight: 700;
            color: var(--clients-text);
        }

        /* Onglets */
        .nav-tabs {
            border-bottom: 2px solid var(--clients-border);
            gap: 1rem;
        }
        .nav-link {
            border: none;
            color: var(--clients-muted);
            font-weight: 600;
            padding: .75rem 0;
            border-bottom: 3px solid transparent;
            transition: all .2s;
        }
        .nav-link:hover {
            color: var(--clients-primary);
        }
        .nav-link.active {
            color: var(--clients-primary);
            border-bottom-color: var(--clients-primary);
            background: none;
        }

        /* Fiche client */
        .fiche-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .fiche-titre {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .fiche-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .meta-label {
            font-size: .85rem;
            opacity: 0.8;
        }
        .meta-value {
            font-size: 1.2rem;
            font-weight: 700;
        }

        /* Historique */
        .historique-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: .5rem;
            font-size: .85rem;
        }
        .historique-ref {
            font-weight: 600;
            color: #2563eb;
        }
        .historique-date {
            color: #64748b;
            font-size: .78rem;
        }
        .historique-montant {
            font-weight: 700;
            color: #1f2937;
        }

        /* Formulaire */
        .form-group-col {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            font-size: .9rem;
            color: #374151;
            margin-bottom: .5rem;
            display: block;
        }
        .form-control {
            width: 100%;
            padding: .6rem .85rem;
            border: 1px solid var(--clients-border);
            border-radius: 6px;
            font-size: .9rem;
            transition: border-color .2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--clients-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .btn-group {
            display: flex;
            gap: .75rem;
            margin-top: 1.5rem;
        }
        .btn-primary {
            background: var(--clients-primary);
            color: white;
            padding: .75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }
        .btn-primary:hover {
            background: var(--clients-primary-dark);
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            padding: .75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }

        /* Modale */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }
        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .modal-title {
            font-weight: 700;
        }
        .modal-client-info {
            margin-bottom: 1rem;
        }
        .modal-client-label {
            font-size: .82rem;
            color: #64748b;
            margin-bottom: .25rem;
        }
        .modal-client-name {
            font-size: 1rem;
            font-weight: 700;
            color: #1f2937;
        }
        .modal-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .modal-summary span {
            display: block;
            color: #64748b;
            font-size: .82rem;
            margin-bottom: .35rem;
        }
        .modal-summary .summary-value {
            font-size: 1rem;
            font-weight: 700;
            color: #1f2937;
        }
        .modal-alert {
            display: none;
            margin-bottom: 1rem;
        }
        .modal-footer .btn-secondary,
        .modal-footer .btn-success {
            min-width: 110px;
        }
    </style>

    <div class="container-fluid py-4">
        <!-- Messages d'alerte -->
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ONGLETS PRINCIPAUX -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $action === 'list' ? 'active' : '' ?>" 
                href="<?= APP_URL ?>/modules/clients/?action=list">
                    <i class="bi bi-people-fill me-2"></i>Tous les clients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $action === 'credit' ? 'active' : '' ?>" 
                href="<?= APP_URL ?>/modules/clients/?action=credit">
                    <i class="bi bi-credit-card me-2"></i>Clients en crédit
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $action === 'add' ? 'active' : '' ?>" 
                href="<?= APP_URL ?>/modules/clients/?action=add">
                    <i class="bi bi-person-plus-fill me-2"></i>Ajouter client
                </a>
            </li>
        </ul>

        <!-- CONTENU DES ONGLETS -->

        <!-- 1️⃣ LISTE DES CLIENTS -->
        <?php if ($action === 'list'): ?>
        <div class="row">
            <div class="col-12 mb-3">
                <div class="d-flex gap-2">
                    <input type="text" id="searchClient" class="form-control" 
                        placeholder="Rechercher par nom ou téléphone…"
                        autocomplete="off" style="max-width: 300px;">
                    <select id="filterType" class="form-select" style="max-width: 150px;">
                        <option value="">Tous les types</option>
                        <option value="particulier">Particuliers</option>
                        <option value="grossiste">Grossistes</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-3">
                <div id="clientsList" style="max-height: calc(100vh - 250px); overflow-y: auto;">
                    <?php 
                    $clients = $clientModel->getAll();
                    if (empty($clients)): ?>
                        <div class="alert alert-info">Aucun client enregistré.</div>
                    <?php else:
                        foreach ($clients as $c): ?>
                            <div class="client-card" onclick="afficherClient(<?= $c['id'] ?>)" 
                                data-nom="<?= strtolower($c['nom']) ?>" 
                                data-type="<?= $c['type_client'] ?>">
                                <div class="client-header">
                                    <div>
                                        <div class="client-nom"><?= clean($c['nom']) ?></div>
                                        <div class="client-tel">
                                            <i class="bi bi-telephone me-1"></i><?= $c['telephone'] ?: 'N/A' ?>
                                        </div>
                                    </div>
                                    <span class="badge-type badge-<?= $c['type_client'] ?>">
                                        <?= ucfirst($c['type_client']) ?>
                                    </span>
                                </div>
                                <?php if ($c['solde_credit'] > 0): ?>
                                <div class="badge-credit">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    Dette : <?= fmt($c['solde_credit']) ?>
                                </div>
                                <?php endif; ?>
                                <div class="client-stats">
                                    <div class="stat-item">
                                        <span class="stat-label">Points</span>
                                        <span class="stat-value"><?= (int)$c['points_fidelite'] ?></span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-label">Crédit autorisé</span>
                                        <span class="stat-value"><?= fmt($c['credit_autorise']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach;
                    endif; ?>
                </div>
            </div>

            <div class="col-lg-9">
                <div id="clientDetail" style="display: none;">
                    <!-- Chargé par AJAX -->
                </div>
                <div id="noClientSelected" class="text-center text-muted py-5">
                    <i class="bi bi-person me-2" style="font-size: 3rem;"></i>
                    <p>Sélectionnez un client pour voir les détails</p>
                </div>
            </div>
        </div>

        <?php endif; ?>

        <!-- 2️⃣ CLIENTS EN CRÉDIT -->
        <?php if ($action === 'credit'): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Client</th>
                        <th>Téléphone</th>
                        <th>Type</th>
                        <th>Dette</th>
                        <th>Crédit autorisé</th>
                        <th>Marge</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = getDB()->prepare("
                        SELECT * FROM clients
                        WHERE statut = 'actif' AND solde_credit > 0
                        ORDER BY solde_credit DESC
                    ");
                    $stmt->execute();
                    $creditClients = $stmt->fetchAll();

                    if (empty($creditClients)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Aucun client en crédit
                            </td>
                        </tr>
                    <?php else:
                        foreach ($creditClients as $c): ?>
                            <tr>
                                <td>
                                    <strong><?= clean($c['nom']) ?></strong>
                                </td>
                                <td><?= $c['telephone'] ?: '—' ?></td>
                                <td>
                                    <span class="badge-type badge-<?= $c['type_client'] ?>">
                                        <?= ucfirst($c['type_client']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: #dc2626; font-weight: 700;">
                                        <?= fmt($c['solde_credit']) ?>
                                    </span>
                                </td>
                                <td><?= fmt($c['credit_autorise']) ?></td>
                                <td>
                                    <?php $marge = $c['credit_autorise'] - $c['solde_credit']; ?>
                                    <span style="color: <?= $marge < $c['credit_autorise'] * 0.2 ? '#dc2626' : '#059669' ?>; font-weight: 600;">
                                        <?= fmt($marge) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="ouvrirModalRemboursement(<?= $c['id'] ?>)"
                                            title="Encaisser un remboursement">
                                        <i class="bi bi-cash-coin"></i> Rembourser
                                    </button>
                                    <a href="?action=view&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Voir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

        <!-- 3️⃣ AJOUTER CLIENT -->
        <?php if ($action === 'add' || $action === 'edit'): 
            $client = $action === 'edit' && $clientId ? $clientModel->getById($clientId) : null;
        ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-person-fill me-2"></i>
                            <?= $action === 'edit' ? 'Modifier client' : 'Ajouter un nouveau client' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="form" value="1">
                            <?php if ($client): ?>
                            <input type="hidden" name="id" value="<?= $client['id'] ?>">
                            <?php endif; ?>

                            <div class="form-group-col">
                                <div>
                                    <label class="form-label">Nom complet *</label>
                                    <input type="text" name="nom" class="form-control" 
                                        value="<?= $client ? clean($client['nom']) : '' ?>"
                                        required autocomplete="off">
                                </div>
                                <div>
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" name="telephone" class="form-control" 
                                        value="<?= $client ? $client['telephone'] : '' ?>"
                                        autocomplete="off">
                                </div>
                                <div>
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                            value="<?= $client ? $client['email'] : '' ?>"
                                            autocomplete="off">
                                </div>
                                <div>
                                    <label class="form-label">Type de client *</label>
                                    <select name="type_client" class="form-control" required>
                                        <option value="particulier" <?= $client && $client['type_client'] === 'particulier' ? 'selected' : '' ?>>
                                            👤 Particulier
                                        </option>
                                        <option value="grossiste" <?= $client && $client['type_client'] === 'grossiste' ? 'selected' : '' ?>>
                                            🏢 Grossiste
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Crédit autorisé (FCFA)</label>
                                    <input type="number" name="credit_autorise" class="form-control" 
                                            value="<?= $client ? (int)$client['credit_autorise'] : 0 ?>"
                                            min="0" step="100">
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Adresse</label>
                                <textarea name="adresse" class="form-control" rows="3" 
                                        autocomplete="off"><?= $client ? clean($client['adresse']) : '' ?></textarea>
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <?= $action === 'edit' ? 'Enregistrer les modifications' : 'Créer le client' ?>
                                </button>
                                <a href="<?= APP_URL ?>/modules/clients/" class="btn-secondary">
                                    Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>

        <!-- 4️⃣ VOIR FICHE CLIENT (AJAX) -->
        <?php if ($action === 'view' && $clientId): 
            $client = $clientModel->getById($clientId);
            $histo  = $clientModel->getHistorique($clientId);
            $stats  = $clientModel->getStats($clientId);
            $paiem  = $clientModel->getPaiementsCredit($clientId);
            
            if (!$client):
                echo '<div class="alert alert-danger">Client introuvable.</div>';
            else:
        ?>
        <div class="fiche-header">
            <div class="fiche-titre"><?= clean($client['nom']) ?></div>
            <div class="fiche-meta">
                <div class="meta-item">
                    <span class="meta-label">Type</span>
                    <span class="badge-type badge-<?= $client['type_client'] ?>">
                        <?= ucfirst($client['type_client']) ?>
                    </span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Téléphone</span>
                    <span class="meta-value"><?= $client['telephone'] ?: '—' ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Email</span>
                    <span class="meta-value" style="font-size: 1rem;"><?= $client['email'] ?: '—' ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Adresse</span>
                    <span class="meta-value" style="font-size: 1rem;"><?= $client['adresse'] ?: '—' ?></span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Gauche : Infos + Stats -->
            <div class="col-lg-4">
                <!-- Indicateurs -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-bottom">
                        <h6 class="mb-0">Indicateurs</h6>
                    </div>
                    <div class="card-body">
                        <div class="stat-item mb-3" style="border: none; background: #f0fdf4;">
                            <span class="stat-label">Points fidélité</span>
                            <span class="stat-value" style="color: #7c3aed;">⭐ <?= (int)$client['points_fidelite'] ?></span>
                        </div>
                        <div class="stat-item mb-3" style="border: none; background: #fef2f2;">
                            <span class="stat-label">Dette</span>
                            <span class="stat-value" style="color: #dc2626;">
                                <?= fmt($client['solde_credit']) ?>
                            </span>
                        </div>
                        <div class="stat-item" style="border: none; background: #eff6ff;">
                            <span class="stat-label">Crédit autorisé</span>
                            <span class="stat-value" style="color: #2563eb;">
                                <?= fmt($client['credit_autorise']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Stats globales -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light border-bottom">
                        <h6 class="mb-0">Statistiques</h6>
                    </div>
                    <div class="card-body" style="font-size: .9rem;">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Nombre d'achats</span>
                            <strong><?= (int)($stats['nb_achats'] ?? 0) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total dépensé</span>
                            <strong><?= fmt($stats['total_depense'] ?? 0) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Plus grosse vente</span>
                            <strong><?= fmt($stats['plus_grosse_vente'] ?? 0) ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between" style="font-size: .8rem; color: #64748b;">
                            <span>1er achat</span>
                            <span><?= $stats['premier_achat'] ? date('d/m/Y', strtotime($stats['premier_achat'])) : '—' ?></span>
                        </div>
                        <div class="d-flex justify-content-between" style="font-size: .8rem; color: #64748b;">
                            <span>Dernier achat</span>
                            <span><?= $stats['dernier_achat'] ? date('d/m/Y H:i', strtotime($stats['dernier_achat'])) : '—' ?></span>
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <a href="?action=edit&id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                            <i class="bi bi-pencil-fill me-2"></i>Modifier
                        </a>
                        <?php if ($client['solde_credit'] > 0): ?>
                        <button type="button" class="btn btn-sm btn-outline-success w-100 mb-2"
                                onclick="ouvrirModalRemboursement(<?= $client['id'] ?>)">
                            <i class="bi bi-cash-coin me-2"></i>Rembourser
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-danger w-100"
                                onclick="if(confirm('Êtes-vous sûr ? Cette action est irréversible.')) window.location.href='?action=delete&id=<?= $client['id'] ?>'">
                            <i class="bi bi-trash me-2"></i>Supprimer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Droite : Historique -->
            <div class="col-lg-8">
                <!-- Onglets historique -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tabAchats">
                            <i class="bi bi-bag-fill me-2"></i>Achats (<?= count($histo) ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tabRemboursements">
                            <i class="bi bi-arrow-clockwise me-2"></i>Remboursements (<?= count($paiem) ?>)
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Achats -->
                    <div id="tabAchats" class="tab-pane fade show active">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <?php if (empty($histo)): ?>
                                    <p class="text-muted text-center py-4">Aucun achat enregistré.</p>
                                <?php else:
                                    foreach ($histo as $v): ?>
                                        <div class="historique-item">
                                            <div>
                                                <div class="historique-ref">
                                                    <?= $v['reference'] ?>
                                                    <span class="badge bg-light text-dark ms-2" style="font-size: .75rem;">
                                                        <?= $v['type_vente'] === 'gros' ? 'GROS' : 'Détail' ?>
                                                    </span>
                                                </div>
                                                <div class="historique-date">
                                                    <?= $v['caissier_nom'] ?> · <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="historique-montant">
                                                    <?= fmt($v['total_net']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Remboursements -->
                    <div id="tabRemboursements" class="tab-pane fade">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <?php if (empty($paiem)): ?>
                                    <p class="text-muted text-center py-4">Aucun remboursement enregistré.</p>
                                <?php else:
                                    foreach ($paiem as $p): ?>
                                        <div class="historique-item">
                                            <div>
                                                <div class="historique-ref">
                                                    Remboursement
                                                    <?php if (!empty($p['note'])): ?>
                                                    <span class="text-muted ms-2" style="font-size: .85rem;">
                                                        (<?= clean($p['note']) ?>)
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="historique-date">
                                                    <?= $p['caissier'] ?> · <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="historique-montant" style="color: #059669;">
                                                    + <?= fmt($p['montant']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <?php endif; endif; ?>

    </div>

    <!-- MODALE REMBOURSEMENT -->
    <div class="modal fade" id="modalRemboursement" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">
                            <i class="bi bi-cash-coin me-2"></i>Encaisser un remboursement
                        </h5>
                        <div class="modal-client-info">
                            <div class="modal-client-label">Client</div>
                            <div class="modal-client-name" id="modalClientName">Sélection en cours...</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formRemboursement">
                    <input type="hidden" name="form" value="rembourser">
                    <input type="hidden" name="id" id="modalClientId">
                    <div class="modal-body">
                        <div id="modalAlert" class="alert alert-danger modal-alert" role="alert"></div>

                        <div class="modal-summary">
                            <div>
                                <span>Dette actuelle</span>
                                <strong class="summary-value" id="modalDette">0 F</strong>
                            </div>
                            <div>
                                <span>Montant maximum</span>
                                <strong class="summary-value" id="modalMaxMontant">0 F</strong>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Montant à rembourser (FCFA) *</label>
                            <input type="number" name="montant" class="form-control" 
                                id="modalMontant" min="0" step="100" required autocomplete="off">
                            <small class="text-muted">Saisis un montant jusqu'à la dette restante.</small>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Note (optionnel)</label>
                            <input type="text" name="note" class="form-control" 
                                placeholder="Ex: Chèque, virement, cash…"
                                autocomplete="off">
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success" id="modalSubmitButton">
                            <i class="bi bi-check-circle me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // ── Filtrage liste clients ─────────────────────────────────
    document.getElementById('searchClient')?.addEventListener('input', function() {
        filtrerClients();
    });
    document.getElementById('filterType')?.addEventListener('change', function() {
        filtrerClients();
    });

    function filtrerClients() {
        const q    = document.getElementById('searchClient').value.toLowerCase();
        const type = document.getElementById('filterType').value;

        document.querySelectorAll('.client-card').forEach(card => {
            const matchNom  = card.dataset.nom.includes(q);
            const matchType = !type || card.dataset.type === type;
            card.style.display = matchNom && matchType ? '' : 'none';
        });
    }

    // ── Afficher fiche client ──────────────────────────────────
    function afficherClient(id) {
        window.location.href = '?action=view&id=' + id;
    }

    // ── Modale remboursement ──────────────────────────────────
    function ouvrirModalRemboursement(clientId) {
        const modal = new bootstrap.Modal(document.getElementById('modalRemboursement'));

        // Récupérer les infos du client
        fetch('<?= APP_URL ?>/api/client.php?id=' + clientId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.client) {
                    const c = data.client;
                    document.getElementById('modalClientName').textContent = c.nom || 'Client inconnu';
                    document.getElementById('modalClientId').value = clientId;
                    document.getElementById('modalDette').textContent = fmt(c.solde_credit);
                    document.getElementById('modalMontant').max = Math.max(0, c.solde_credit);
                    document.getElementById('modalMaxMontant').textContent = fmt(c.solde_credit);
                    document.getElementById('modalMontant').value = c.solde_credit > 0 ? Math.min(c.solde_credit, parseFloat(document.getElementById('modalMontant').value) || c.solde_credit) : '';

                    const alertBox = document.getElementById('modalAlert');
                    const submitBtn = document.getElementById('modalSubmitButton');
                    if (c.solde_credit <= 0) {
                        alertBox.style.display = 'block';
                        alertBox.textContent = 'Ce client n’a pas de dette à rembourser.';
                        submitBtn.disabled = true;
                    } else {
                        alertBox.style.display = 'none';
                        alertBox.textContent = '';
                        submitBtn.disabled = false;
                    }
                } else {
                    const alertBox = document.getElementById('modalAlert');
                    const submitBtn = document.getElementById('modalSubmitButton');
                    alertBox.style.display = 'block';
                    alertBox.textContent = data.message || 'Impossible de charger les informations du client.';
                    submitBtn.disabled = true;
                }
            }).catch(() => {
                const alertBox = document.getElementById('modalAlert');
                const submitBtn = document.getElementById('modalSubmitButton');
                alertBox.style.display = 'block';
                alertBox.textContent = 'Erreur réseau. Veuillez réessayer.';
                submitBtn.disabled = true;
            });

        modal.show();
    }

    // ── Helper ────────────────────────────────────────────────
    function fmt(n) {
        return Math.round(n).toLocaleString('fr-FR') + ' F';
    }
    </script>

    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>