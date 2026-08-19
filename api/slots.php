<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['dono', 'barbeiro'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Não autorizado.', 'slots' => []]);
    exit;
}

$barberId = (int)($_GET['barber_id'] ?? 0);
$date = (string)($_GET['date'] ?? date('Y-m-d'));
$serviceId = (int)($_GET['service_id'] ?? 0);
$serviceIdsInput = $_GET['service_ids'] ?? [];
$serviceIds = is_array($serviceIdsInput)
    ? $serviceIdsInput
    : explode(',', (string)$serviceIdsInput);
$serviceIds = array_values(array_unique(array_filter(array_map('intval', $serviceIds))));
$duration = 60;

if (!is_valid_iso_date($date)) {
    $date = date('Y-m-d');
}

if (($user['role'] ?? '') === 'barbeiro') {
    $barberId = (int)$user['id'];
}

if ($serviceIds) {
    $pickedServices = services_by_ids($serviceIds);
    if (!$pickedServices) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Serviço inválido.', 'slots' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $duration = max(15, array_sum(array_map(
        static fn($service) => (int)($service['duration_min'] ?? 60),
        $pickedServices
    )));
} elseif ($serviceId > 0) {
    $svc = find_service($serviceId);
    if (!$svc || empty($svc['active'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Serviço inválido.', 'slots' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $duration = max(15, (int)($svc['duration_min'] ?? 60));
}

if ($barberId < 1) {
    echo json_encode(['ok' => true, 'slots' => [], 'date' => $date]);
    exit;
}

$barber = find_user_by_id($barberId);
if (!$barber || ($barber['role'] ?? '') !== 'barbeiro' || empty($barber['active'])) {
    echo json_encode(['ok' => false, 'error' => 'Barbeiro inválido.', 'slots' => []]);
    exit;
}

$slots = available_slots($barberId, $date, $duration);
echo json_encode([
    'ok' => true,
    'date' => $date,
    'barber_id' => $barberId,
    'duration' => $duration,
    'slots' => array_values($slots),
], JSON_UNESCAPED_UNICODE);
