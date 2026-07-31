<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido']);
    exit;
}

$client = current_client();
if (!$client) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Faça login para assinar.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$planId = (int)($payload['plan_id'] ?? 0);
$cardId = (int)($payload['card_id'] ?? 0);
$plan = find_plan($planId);

if (!$plan || empty($plan['active'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Plano indisponível.']);
    exit;
}

foreach (client_subscriptions((int)$client['id']) as $s) {
    if ((int)$s['plan_id'] === $planId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Você já assina este plano.']);
        exit;
    }
}

$card = null;
foreach (client_cards((int)$client['id']) as $c) {
    if ((int)$c['id'] === $cardId) {
        $card = $c;
        break;
    }
}

if (!$card) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Selecione um cartão cadastrado.']);
    exit;
}

$amount = (float) $plan['price'];
$ext = 'plan-' . $planId . '-client-' . (int)$client['id'] . '-' . time();
$charge = mp_charge_saved_card(
    $client,
    $card,
    $amount,
    'Assinatura ' . ($plan['name'] ?? 'Plano'),
    $ext
);

if (!$charge['ok']) {
    http_response_code(402);
    echo json_encode(['ok' => false, 'error' => $charge['error']]);
    exit;
}

$sub = subscribe_client_to_plan($client, $plan, $card, $charge['payment'] ?? []);

echo json_encode([
    'ok' => true,
    'subscription_id' => (int)($sub['id'] ?? 0),
    'payment_id' => (string)(($charge['payment']['id'] ?? '')),
]);
