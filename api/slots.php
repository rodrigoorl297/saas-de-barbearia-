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
$duration = 60;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

if (($user['role'] ?? '') === 'barbeiro') {
    $barberId = (int)$user['id'];
}

if ($serviceId > 0) {
    $svc = find_service($serviceId);
    if ($svc) {
        $duration = max(15, (int)($svc['duration_min'] ?? 60));
    }
}

if ($barberId < 1) {
    echo json_encode(['ok' => true, 'slots' => [], 'date' => $date]);
    exit;
}

$barber = find_user_by_id($barberId);
if (!$barber || ($barber['role'] ?? '') !== 'barbeiro') {
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
