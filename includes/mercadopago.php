<?php
declare(strict_types=1);

/**
 * Integração Mercado Pago (Checkout Transparente) via API REST.
 * Cartões são tokenizados no browser — o servidor nunca recebe o número completo.
 */

function mp_settings(): array
{
    $s = settings();
    return [
        'public_key' => trim((string)($s['mp_public_key'] ?? (defined('MP_PUBLIC_KEY') ? MP_PUBLIC_KEY : ''))),
        'access_token' => trim((string)($s['mp_access_token'] ?? (defined('MP_ACCESS_TOKEN') ? MP_ACCESS_TOKEN : ''))),
    ];
}

function mp_configured(): bool
{
    $c = mp_settings();
    return $c['public_key'] !== '' && $c['access_token'] !== '';
}

function mp_request(string $method, string $path, ?array $body = null, ?string $idempotency = null): array
{
    $token = mp_settings()['access_token'];
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'error' => 'Mercado Pago não configurado.', 'data' => null];
    }

    $url = 'https://api.mercadopago.com' . $path;
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($idempotency) {
        $headers[] = 'X-Idempotency-Key: ' . $idempotency;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 45,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'error' => 'Falha de conexão: ' . $curlErr, 'data' => null];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = ['raw' => $raw];
    }

    $ok = $status >= 200 && $status < 300;
    $error = null;
    if (!$ok) {
        $error = $data['message'] ?? ($data['error'] ?? ('Erro HTTP ' . $status));
        if (!empty($data['cause'][0]['description'])) {
            $error = (string) $data['cause'][0]['description'];
        }
    }

    return ['ok' => $ok, 'status' => $status, 'error' => $error, 'data' => $data];
}

function mp_client_email(array $client): string
{
    $email = trim((string)($client['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    $phone = preg_replace('/\D+/', '', (string)($client['phone'] ?? ''));
    if ($phone === '') {
        $phone = 'cliente' . (int)($client['id'] ?? 0);
    }
    return $phone . '@cliente.suprema.local';
}

function mp_ensure_customer(array $client): array
{
    $existingId = trim((string)($client['mp_customer_id'] ?? ''));
    if ($existingId !== '') {
        $get = mp_request('GET', '/v1/customers/' . rawurlencode($existingId));
        if ($get['ok']) {
            return ['ok' => true, 'customer_id' => $existingId, 'error' => null];
        }
    }

    $email = mp_client_email($client);
    $search = mp_request('GET', '/v1/customers/search?email=' . rawurlencode($email));
    if ($search['ok'] && !empty($search['data']['results'][0]['id'])) {
        $cid = (string) $search['data']['results'][0]['id'];
        save_user([
            'id' => (int) $client['id'],
            'mp_customer_id' => $cid,
            'email' => $client['email'] ?: $email,
        ]);
        return ['ok' => true, 'customer_id' => $cid, 'error' => null];
    }

    $create = mp_request('POST', '/v1/customers', [
        'email' => $email,
        'first_name' => trim((string)($client['name'] ?? 'Cliente')) ?: 'Cliente',
        'phone' => [
            'number' => preg_replace('/\D+/', '', (string)($client['phone'] ?? '')) ?: null,
        ],
    ]);

    if (!$create['ok'] || empty($create['data']['id'])) {
        return ['ok' => false, 'customer_id' => null, 'error' => $create['error'] ?? 'Não foi possível criar o cliente no Mercado Pago.'];
    }

    $cid = (string) $create['data']['id'];
    save_user([
        'id' => (int) $client['id'],
        'mp_customer_id' => $cid,
        'email' => $client['email'] ?: $email,
    ]);

    return ['ok' => true, 'customer_id' => $cid, 'error' => null];
}

function mp_save_card_token(array $client, string $token, string $holder = ''): array
{
    if (!mp_configured()) {
        return ['ok' => false, 'error' => 'Configure as chaves do Mercado Pago nas configurações do dono.', 'card' => null];
    }
    if ($token === '') {
        return ['ok' => false, 'error' => 'Token do cartão inválido.', 'card' => null];
    }

    $customer = mp_ensure_customer($client);
    if (!$customer['ok']) {
        return ['ok' => false, 'error' => $customer['error'], 'card' => null];
    }

    $res = mp_request('POST', '/v1/customers/' . rawurlencode((string)$customer['customer_id']) . '/cards', [
        'token' => $token,
    ]);

    if (!$res['ok'] || empty($res['data']['id'])) {
        return ['ok' => false, 'error' => $res['error'] ?? 'Falha ao cadastrar cartão.', 'card' => null];
    }

    $d = $res['data'];
    $expMonth = str_pad((string)($d['expiration_month'] ?? ''), 2, '0', STR_PAD_LEFT);
    $expYear = substr((string)($d['expiration_year'] ?? ''), -2);
    $card = save_client_card([
        'client_id' => (int) $client['id'],
        'holder' => $holder !== '' ? $holder : (string)($d['cardholder']['name'] ?? 'Cartão'),
        'last4' => (string)($d['last_four_digits'] ?? '----'),
        'brand' => (string)($d['payment_method']['id'] ?? ($d['payment_method_id'] ?? 'card')),
        'exp' => trim($expMonth . '/' . $expYear, '/'),
        'mp_customer_id' => (string) $customer['customer_id'],
        'mp_card_id' => (string) $d['id'],
        'active' => 1,
    ]);

    return ['ok' => true, 'error' => null, 'card' => $card];
}

function mp_create_card_token_from_saved(string $mpCardId): array
{
    return mp_request('POST', '/v1/card_tokens', [
        'card_id' => $mpCardId,
    ]);
}

/**
 * Cobra um cartão salvo. $idempotencyKey DEVE ser estável para a mesma tentativa
 * lógica de cobrança (ex.: "plan-{planId}-client-{clientId}" para a assinatura
 * inicial, ou "sub-{subId}-{renewsAt}" para uma renovação) — nunca gerado aleatório
 * a cada chamada, senão retries de rede/reexecuções de cron não ficam protegidos
 * contra cobrança duplicada.
 */
function mp_charge_saved_card(array $client, array $card, float $amount, string $description, string $externalRef, string $idempotencyKey): array
{
    if (!mp_configured()) {
        return ['ok' => false, 'error' => 'Mercado Pago não configurado.', 'payment' => null];
    }
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Valor inválido.', 'payment' => null];
    }
    if ($idempotencyKey === '') {
        return ['ok' => false, 'error' => 'Chave de idempotência obrigatória.', 'payment' => null];
    }

    $mpCardId = trim((string)($card['mp_card_id'] ?? ''));
    $mpCustomerId = trim((string)($card['mp_customer_id'] ?? ($client['mp_customer_id'] ?? '')));
    if ($mpCardId === '' || $mpCustomerId === '') {
        return ['ok' => false, 'error' => 'Cartão sem vínculo no Mercado Pago. Cadastre o cartão novamente.', 'payment' => null];
    }

    $tok = mp_create_card_token_from_saved($mpCardId);
    if (!$tok['ok'] || empty($tok['data']['id'])) {
        return ['ok' => false, 'error' => $tok['error'] ?? 'Não foi possível tokenizar o cartão salvo.', 'payment' => null];
    }

    $payment = mp_request('POST', '/v1/payments', [
        'transaction_amount' => round($amount, 2),
        'token' => (string) $tok['data']['id'],
        'description' => $description,
        'installments' => 1,
        'payment_method_id' => (string)($card['brand'] ?? ($tok['data']['payment_method_id'] ?? 'visa')),
        'payer' => [
            'type' => 'customer',
            'id' => $mpCustomerId,
            'email' => mp_client_email($client),
        ],
        'external_reference' => $externalRef,
        'capture' => true,
    ], $idempotencyKey);

    if (!$payment['ok']) {
        return ['ok' => false, 'error' => $payment['error'] ?? 'Pagamento recusado.', 'payment' => $payment['data']];
    }

    $status = (string)($payment['data']['status'] ?? '');
    if (!in_array($status, ['approved', 'authorized'], true)) {
        $detail = (string)($payment['data']['status_detail'] ?? $status);
        return ['ok' => false, 'error' => 'Pagamento não aprovado: ' . $detail, 'payment' => $payment['data']];
    }

    return ['ok' => true, 'error' => null, 'payment' => $payment['data']];
}

function mp_charge_with_token(array $client, string $token, string $paymentMethodId, float $amount, string $description, string $externalRef, int $installments = 1, ?string $issuerId = null): array
{
    if (!mp_configured()) {
        return ['ok' => false, 'error' => 'Mercado Pago não configurado.', 'payment' => null];
    }

    $payload = [
        'transaction_amount' => round($amount, 2),
        'token' => $token,
        'description' => $description,
        'installments' => max(1, $installments),
        'payment_method_id' => $paymentMethodId,
        'payer' => [
            'email' => mp_client_email($client),
        ],
        'external_reference' => $externalRef,
        'capture' => true,
    ];
    if ($issuerId) {
        $payload['issuer_id'] = $issuerId;
    }

    $payment = mp_request('POST', '/v1/payments', $payload, bin2hex(random_bytes(16)));
    if (!$payment['ok']) {
        return ['ok' => false, 'error' => $payment['error'] ?? 'Pagamento recusado.', 'payment' => $payment['data']];
    }

    $status = (string)($payment['data']['status'] ?? '');
    if (!in_array($status, ['approved', 'authorized'], true)) {
        $detail = (string)($payment['data']['status_detail'] ?? $status);
        return ['ok' => false, 'error' => 'Pagamento não aprovado: ' . $detail, 'payment' => $payment['data']];
    }

    return ['ok' => true, 'error' => null, 'payment' => $payment['data']];
}

function mp_delete_remote_card(array $card): void
{
    $customerId = trim((string)($card['mp_customer_id'] ?? ''));
    $cardId = trim((string)($card['mp_card_id'] ?? ''));
    if ($customerId === '' || $cardId === '') {
        return;
    }
    mp_request('DELETE', '/v1/customers/' . rawurlencode($customerId) . '/cards/' . rawurlencode($cardId));
}

function icon_svg(string $name, int $size = 20): string
{
    $s = (string) $size;
    $icons = [
        'card' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/></svg>',
        'transfer' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 8h13l-3-3"/><path d="M17 16H4l3 3"/><path d="M20 8H7"/><path d="M4 16h13"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 9a6 6 0 0 1 12 0c0 7 3 7 3 7H3s3 0 3-7"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 14.5-4 16 0"/></svg>',
        'eye' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>',
        'camera' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7h3l2-2h6l2 2h3v12H4V7z"/><circle cx="12" cy="13" r="3.5"/></svg>',
        'scissors' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.5 12 20 20"/><path d="M8.5 12H14"/></svg>',
        'owner' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 8l4 2 5-6 5 6 4-2v11H3V8z"/><path d="M9 19v-5h6v5"/></svg>',
        'back' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>',
        'wallet' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4H6a2 2 0 0 1-2-2z"/><circle cx="16" cy="14" r="1"/></svg>',
        'chart' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><polyline points="21 8 15 14 10 9 3 16"/><polyline points="15 8 21 8 21 14"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'alert' => '<svg viewBox="0 0 24 24" width="' . $s . '" height="' . $s . '" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    ];
    return $icons[$name] ?? null;
}
