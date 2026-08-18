<?php
declare(strict_types=1);

/**
 * Cobrança recorrente de assinaturas (planos). Rode diariamente via cron da
 * hospedagem, ex.: 0 6 * * * php /caminho/do/site/cron/cobrar-renovacoes.php
 *
 * Idempotente: cada assinatura só é cobrada se ainda existir uma cobrança
 * "proxima" pendente para o ciclo em questão (criada na assinatura inicial ou
 * na renovação anterior). Reexecutar o script no mesmo dia não cobra duas vezes.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso permitido apenas via linha de comando (cron).');
}

require_once __DIR__ . '/../config/config.php';

$lockDir = data_dir() . '/locks';
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0775, true);
}
$lock = fopen($lockDir . '/cron-cobrar-renovacoes.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Já existe uma execução em andamento. Abortando.\n");
    exit(1);
}

$today = date('Y-m-d');
echo '[' . date('c') . "] Iniciando cobrança de renovações (referência: $today)\n";

$due = array_filter(
    store_read('subscriptions'),
    fn($s) => ($s['status'] ?? '') === 'active' && (string)($s['renews_at'] ?? '') <= $today
);

$cobradas = 0;
$falhas = 0;

foreach ($due as $sub) {
    $subId = (int) $sub['id'];
    $clientId = (int) ($sub['client_id'] ?? 0);
    $planId = (int) ($sub['plan_id'] ?? 0);
    $cardId = (int) ($sub['card_id'] ?? 0);
    $renewsAt = (string) ($sub['renews_at'] ?? '');

    $client = find_user_by_id($clientId);
    $plan = find_plan($planId);
    $card = null;
    foreach (client_cards($clientId) as $c) {
        if ((int) $c['id'] === $cardId) {
            $card = $c;
            break;
        }
    }

    if (!$client || !$plan || !$card) {
        echo "  [assinatura #$subId] pulada: cliente/plano/cartão não encontrado.\n";
        $falhas++;
        continue;
    }

    // Trava de idempotência local: só cobra se a cobrança "proxima" prevista
    // para este ciclo ainda existir (evita cobrar duas vezes em reexecuções).
    $charges = store_read('charges');
    $pendingIndex = null;
    foreach ($charges as $i => $c) {
        if ((int) ($c['client_id'] ?? 0) === $clientId
            && (int) ($c['plan_id'] ?? 0) === $planId
            && (string) ($c['date'] ?? '') === $renewsAt
            && ($c['status'] ?? '') === 'proxima'
        ) {
            $pendingIndex = $i;
            break;
        }
    }
    if ($pendingIndex === null) {
        echo "  [assinatura #$subId] pulada: nenhuma cobrança pendente para $renewsAt (já processada).\n";
        continue;
    }

    $idempotencyKey = 'sub-' . $subId . '-' . $renewsAt;
    $externalRef = 'renewal-sub-' . $subId . '-' . $renewsAt;

    $charge = mp_charge_saved_card(
        $client,
        $card,
        (float) $plan['price'],
        'Renovação ' . ($plan['name'] ?? 'Plano'),
        $externalRef,
        $idempotencyKey
    );

    if (!$charge['ok']) {
        // Não avança renews_at: a assinatura segue "devendo" este ciclo e será
        // tentada de novo na próxima execução do cron. Não há retry/backoff nem
        // cancelamento automático por falta de pagamento — decisão manual do dono.
        echo "  [assinatura #$subId] FALHOU: " . $charge['error'] . "\n";
        $falhas++;
        continue;
    }

    $paymentId = (string) ($charge['payment']['id'] ?? '');
    $nextRenewal = date('Y-m-d', strtotime($renewsAt . ' +1 month'));

    $charges[$pendingIndex]['status'] = 'pago';
    $charges[$pendingIndex]['mp_payment_id'] = $paymentId;
    $charges[$pendingIndex]['mp_status'] = (string) ($charge['payment']['status'] ?? 'approved');
    store_write('charges', $charges);

    save_charge([
        'client_id' => $clientId,
        'plan_id' => $planId,
        'plan_name' => $plan['name'],
        'amount' => (float) $plan['price'],
        'card_last4' => (string) ($card['last4'] ?? '----'),
        'card_brand' => (string) ($card['brand'] ?? ''),
        'date' => $nextRenewal,
        'status' => 'proxima',
        'mp_payment_id' => null,
        'mp_status' => null,
    ]);

    save_subscription(['id' => $subId, 'renews_at' => $nextRenewal]);

    echo "  [assinatura #$subId] cobrada com sucesso · próxima renovação: $nextRenewal\n";
    $cobradas++;
}

echo '[' . date('c') . "] Concluído: $cobradas cobrada(s), $falhas falha(s)/pulada(s).\n";

flock($lock, LOCK_UN);
fclose($lock);
