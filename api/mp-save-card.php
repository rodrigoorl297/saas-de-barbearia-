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
    echo json_encode(['ok' => false, 'error' => 'Faça login para cadastrar um cartão.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$token = trim((string)($payload['token'] ?? ''));
$holder = trim((string)($payload['holder'] ?? ($client['name'] ?? 'Cliente')));
$email = trim((string)($payload['email'] ?? ''));

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    save_user([
        'id' => (int) $client['id'],
        'email' => $email,
    ]);
    $client = find_user_by_id((int) $client['id']) ?: $client;
}

$result = mp_save_card_token($client, $token, $holder);
if (!$result['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

$card = $result['card'];
echo json_encode([
    'ok' => true,
    'card' => [
        'id' => (int) $card['id'],
        'holder' => $card['holder'] ?? '',
        'last4' => $card['last4'] ?? '',
        'brand' => $card['brand'] ?? '',
        'exp' => $card['exp'] ?? '',
    ],
]);
