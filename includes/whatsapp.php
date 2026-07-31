<?php
declare(strict_types=1);

/**
 * WhatsApp Cloud API (Meta).
 * Campanhas em massa exigem templates aprovados no Business Manager.
 */

function wa_settings(): array
{
    $s = settings();
    return [
        'phone_number_id' => trim((string)($s['wa_phone_number_id'] ?? (defined('WA_PHONE_NUMBER_ID') ? WA_PHONE_NUMBER_ID : ''))),
        'access_token' => trim((string)($s['wa_access_token'] ?? (defined('WA_ACCESS_TOKEN') ? WA_ACCESS_TOKEN : ''))),
    ];
}

function wa_configured(): bool
{
    $c = wa_settings();
    return $c['phone_number_id'] !== '' && $c['access_token'] !== '';
}

function send_whatsapp_campaign(array $campaign): int
{
    if (!wa_configured()) {
        return 0;
    }

    $type = $campaign['type'] ?? 'promocional';
    $templateName = trim((string)($campaign['message'] ?? ''));
    if ($templateName === '') {
        return 0;
    }
    // Se a mensagem parece texto livre, usa como nome de template sanitizado
    $templateName = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $templateName) ?: 'hello_world');
    $templateName = trim($templateName, '_');

    $clients = get_target_clients_for_campaign($type);
    if (!$clients) {
        return 0;
    }

    $cfg = wa_settings();
    $url = 'https://graph.facebook.com/v19.0/' . rawurlencode($cfg['phone_number_id']) . '/messages';
    $count = 0;
    $logFile = __DIR__ . '/../data/whatsapp_log.txt';
    $timestamp = date('Y-m-d H:i:s');

    foreach ($clients as $c) {
        $phone = preg_replace('/\D+/', '', (string)($c['phone'] ?? '')) ?: '';
        if ($phone === '') {
            continue;
        }
        if (!str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'pt_BR'],
            ],
        ];

        $ok = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $cfg['access_token'],
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
            ]);
            $response = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $ok = $code >= 200 && $code < 300;
            $logMsg = "[$timestamp] HTTP $code | Tipo: $type | Cliente: {$c['name']} | Telefone: $phone | Template: $templateName | Resp: " . substr((string)$response, 0, 300) . "\n";
        } else {
            $logMsg = "[$timestamp] ERRO | curl indisponível | Cliente: {$c['name']} | Telefone: $phone\n";
        }

        file_put_contents($logFile, $logMsg, FILE_APPEND);
        if ($ok) {
            $count++;
        }
    }

    return $count;
}

function get_target_clients_for_campaign(string $type): array
{
    $allUsers = store_read('users');
    $clients = array_filter($allUsers, fn($u) => ($u['role'] ?? '') === 'cliente' && !empty($u['phone']));

    if ($type === 'promocional') {
        return array_values($clients);
    }

    if ($type === 'aniversariantes') {
        $currentMonth = date('m');
        return array_values(array_filter($clients, function ($c) use ($currentMonth) {
            $bd = $c['birth_date'] ?? '';
            if (!$bd) {
                return false;
            }
            return substr($bd, 5, 2) === $currentMonth;
        }));
    }

    if ($type === 'inativos') {
        $allAppts = appointments_enriched();
        $cut = date('Y-m-d', strtotime('-45 days'));

        $phonesLastDate = [];
        foreach ($allAppts as $a) {
            $p = $a['client_phone'];
            if (!isset($phonesLastDate[$p]) || $a['date'] > $phonesLastDate[$p]) {
                $phonesLastDate[$p] = $a['date'];
            }
        }

        return array_values(array_filter($clients, function ($c) use ($phonesLastDate, $cut) {
            $lastDate = $phonesLastDate[$c['phone']] ?? null;
            return $lastDate && $lastDate < $cut;
        }));
    }

    return [];
}
