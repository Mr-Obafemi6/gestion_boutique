<?php
// ============================================================
//  api/client.php — Retourne les infos complètes d'un client (JSON)
//  Utilisé par caisse.php pour charger credit_autorise en AJAX
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Client.php';

requireLogin();
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide.']);
    exit;
}

$clientModel = new Client();
$client = $clientModel->getById($id);

if (!$client) {
    echo json_encode(['success' => false, 'message' => 'Client introuvable.']);
    exit;
}

echo json_encode([
    'success' => true,
    'client'  => [
        'id'              => $client['id'],
        'nom'             => $client['nom'],
        'telephone'       => $client['telephone'],
        'type_client'     => $client['type_client'],
        'points_fidelite' => $client['points_fidelite'],
        'solde_credit'    => $client['solde_credit'],
        'credit_autorise' => $client['credit_autorise'],
    ]
]);
