<?php
declare(strict_types=1);

/**
 * Teste de integração isolado dos fluxos críticos.
 * Copia os JSON atuais para uma pasta temporária e nunca altera data/ real.
 *
 * Rodar: php tests/integration.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Rode via linha de comando: php tests/integration.php');
}

$tempData = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'barbaflow-integration-' . bin2hex(random_bytes(6));
if (!mkdir($tempData, 0775, true) && !is_dir($tempData)) {
    fwrite(STDERR, "Não foi possível criar a área temporária.\n");
    exit(1);
}

foreach (glob(__DIR__ . '/../data/*.json') ?: [] as $source) {
    copy($source, $tempData . DIRECTORY_SEPARATOR . basename($source));
}

putenv('DB_ENABLED=false');
putenv('DB_NAME=');

define('DB_PATH', $tempData . DIRECTORY_SEPARATOR . 'suprema.db');
define('APP_KEY', base64_encode(str_repeat('i', 32)));
define('TIMEZONE', 'America/Sao_Paulo');
date_default_timezone_set(TIMEZONE);

require __DIR__ . '/../autoload.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';

if (!store_read('users')) {
    seed_database();
}
ensure_extra_tables();
if (!store_read('stock')) {
    store_write('stock', [[
        'id' => 1,
        'name' => 'Produto de integração',
        'sku' => 'TEST-01',
        'qty' => 3,
        'min_qty' => 1,
        'cost' => 5,
        'price' => 10,
        'active' => 1,
    ]]);
}

$passed = 0;
$failed = 0;

function integration_check(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  OK   $label\n";
        $passed++;
        return;
    }
    echo "  FALHA $label\n";
    $failed++;
}

$owner = null;
$barber = active_barbers()[0] ?? null;
foreach (store_read('users') as $candidate) {
    if (($candidate['role'] ?? '') === 'dono' && !empty($candidate['active'])) {
        $owner = $candidate;
        break;
    }
}
$services = array_slice(active_services(), 0, 2);

integration_check('há um dono ativo para faturar', is_array($owner));
integration_check('há um barbeiro ativo para atender', is_array($barber));
integration_check('há ao menos um serviço ativo', count($services) > 0);

if (!$owner || !$barber || !$services) {
    echo "\n$passed passaram, $failed falharam.\n";
    exit(1);
}

$phone = '119' . random_int(10000000, 99999999);
$client = upsert_client([
    'name' => 'Cliente de integração',
    'phone' => $phone,
    'birth_date' => '1990-01-01',
]);
integration_check('cliente temporário foi criado', (int)($client['id'] ?? 0) > 0);

$serviceIds = array_map(static fn(array $service): int => (int)$service['id'], $services);
$expectedTotal = array_sum(array_map(static fn(array $service): float => (float)$service['price'], $services));
$expectedDuration = max(15, array_sum(array_map(
    static fn(array $service): int => (int)($service['duration_min'] ?? 30),
    $services
)));

$date = '';
$time = '';
for ($offset = 0; $offset <= 21; $offset++) {
    $candidateDate = date('Y-m-d', strtotime('+' . $offset . ' days'));
    $candidateSlots = available_slots((int)$barber['id'], $candidateDate, $expectedDuration);
    if ($candidateSlots) {
        $date = $candidateDate;
        $time = $candidateSlots[0];
        break;
    }
}
integration_check('foi encontrado um horário disponível', $date !== '' && $time !== '');

$appointment = $date !== ''
    ? book_services_for_client((int)$client['id'], (int)$barber['id'], $serviceIds, $date, $time)
    : 'Sem horário disponível.';
integration_check('agendamento foi criado', is_array($appointment));

if (is_array($appointment)) {
    integration_check('todos os serviços ficaram no mesmo atendimento', count($appointment['service_ids'] ?? []) === count($serviceIds));
    integration_check('valor dos serviços foi somado', abs((float)$appointment['price'] - $expectedTotal) < 0.001);

    $appointment['status'] = 'em_andamento';
    save_appointment($appointment);
    $appointment['status'] = 'concluido';
    save_appointment($appointment);
    sync_appointment_cash($appointment, (int)$owner['id']);
    sync_appointment_loyalty($appointment);

    integration_check('atendimento foi concluído', (save_appointment($appointment)['status'] ?? '') === 'concluido');
    integration_check('faturamento do atendimento foi criado', cash_entry_for_appointment((int)$appointment['id']) !== null);
}

$product = null;
foreach (store_read('stock') as $candidate) {
    if ((int)($candidate['qty'] ?? 0) > 0 && (float)($candidate['price'] ?? 0) > 0) {
        $product = $candidate;
        break;
    }
}

if ($product) {
    $before = (int)$product['qty'];
    $tooMuch = stock_consume((int)$product['id'], $before + 1, 'out_venda', (int)$owner['id'], 'Teste isolado', false);
    $afterRejected = null;
    foreach (store_read('stock') as $row) {
        if ((int)$row['id'] === (int)$product['id']) {
            $afterRejected = (int)$row['qty'];
            break;
        }
    }
    integration_check('estoque insuficiente é rejeitado', is_string($tooMuch) && $afterRejected === $before);

    $sold = stock_consume((int)$product['id'], 1, 'out_venda', (int)$owner['id'], 'Teste isolado', false);
    $afterSale = null;
    foreach (store_read('stock') as $row) {
        if ((int)$row['id'] === (int)$product['id']) {
            $afterSale = (int)$row['qty'];
            break;
        }
    }
    integration_check('venda válida baixa uma unidade', $sold === null && $afterSale === $before - 1);
} else {
    echo "  PULA estoque: nenhum produto vendável na cópia atual\n";
}

echo "\nDados reais preservados; cópia temporária: $tempData\n";
echo "$passed passaram, $failed falharam.\n";
exit($failed > 0 ? 1 : 0);
