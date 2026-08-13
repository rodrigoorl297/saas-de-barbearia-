<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/whatsapp.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'dono') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Não autorizado.']);
    exit;
}

if (!evo_configured()) {
    echo json_encode(['ok' => false, 'error' => 'Preencha a URL e a API Key da Evolution API antes de conectar.']);
    exit;
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'status');
$cfg = evo_settings();
$client = evo_client();

try {
    if ($action === 'connect') {
        // Tenta pedir o QR direto; se a instância ainda não existe na Evolution, cria e tenta de novo.
        try {
            $resp = $client->connect($cfg['instance']);
        } catch (Throwable $e) {
            $client->createInstance($cfg['instance']);
            $resp = $client->connect($cfg['instance']);
        }
        $qr = EvolutionClient::extractQr($resp);
        $state = EvolutionClient::parseConnectionState($resp);
        echo json_encode(['ok' => true, 'state' => $state, 'qr' => $qr], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'status') {
        $resp = $client->connectionState($cfg['instance']);
        echo json_encode(['ok' => true, 'state' => EvolutionClient::parseConnectionState($resp)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $client->logout($cfg['instance']);
        echo json_encode(['ok' => true, 'state' => 'close']);
        exit;
    }

    if ($action === 'send_reminders_now' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = send_appointment_reminders();
        echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Ação inválida.']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
