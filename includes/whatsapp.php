<?php
declare(strict_types=1);

/**
 * Disparo de WhatsApp via Evolution API (Baileys).
 * Cada barbearia conecta o próprio número escaneando um QR Code em
 * Configurações — não precisa de aprovação de template (Meta Business),
 * então o texto digitado na campanha é enviado literalmente.
 */

require_once __DIR__ . '/EvolutionClient.php';

function evo_settings(): array
{
    $s = settings();
    return [
        'api_url'  => trim((string)($s['evo_api_url'] ?? '')),
        'api_key'  => trim((string)($s['evo_api_key'] ?? '')),
        'instance' => trim((string)($s['evo_instance'] ?? '')) ?: evo_default_instance(),
    ];
}

/** Nome de instância estável por barbearia (evita colisão entre lojas no mesmo servidor Evolution). */
function evo_default_instance(): string
{
    return 'loja-' . shop_slug();
}

function evo_configured(): bool
{
    $c = evo_settings();
    return $c['api_url'] !== '' && $c['api_key'] !== '';
}

function evo_client(): EvolutionClient
{
    $c = evo_settings();
    return new EvolutionClient($c['api_url'], $c['api_key']);
}

/** Compat com o restante do painel (marketing.php chama wa_configured()/wa_settings()). */
function wa_configured(): bool
{
    return evo_configured();
}

function wa_settings(): array
{
    return evo_settings();
}

/** Estado da conexão do WhatsApp da barbearia: open | connecting | close | erro. */
function evo_connection_status(): string
{
    if (!evo_configured()) {
        return 'nao_configurado';
    }
    try {
        $c = evo_settings();
        $resp = evo_client()->connectionState($c['instance']);
        return EvolutionClient::parseConnectionState($resp);
    } catch (Throwable $e) {
        return 'erro';
    }
}

/**
 * Substitui variáveis simples na mensagem da campanha.
 * Hoje suporta {nome} — dá pra crescer sem mexer em quem chama.
 */
function evo_personalize(string $message, array $client): string
{
    $nome = trim((string)($client['name'] ?? ''));
    $primeiroNome = $nome !== '' ? explode(' ', $nome)[0] : 'cliente';
    return strtr($message, [
        '{nome}' => $nome !== '' ? $nome : 'cliente',
        '{primeiro_nome}' => $primeiroNome,
    ]);
}

/**
 * Dispara a campanha para o público alvo. Retorna quantas mensagens saíram com sucesso.
 * Loga cada envio (sucesso ou falha) em data/whatsapp_log.txt.
 */
function send_whatsapp_campaign(array $campaign): int
{
    if (!evo_configured()) {
        return 0;
    }
    $message = trim((string)($campaign['message'] ?? ''));
    if ($message === '') {
        return 0;
    }

    $type = $campaign['type'] ?? 'promocional';
    $clients = get_target_clients_for_campaign($type);
    if (!$clients) {
        return 0;
    }

    // Trava de segurança: disparo manual, um clique por vez — evita mandar milhares numa tacada
    // só e o número da barbearia ser bloqueado pelo WhatsApp por comportamento de spam.
    $clients = array_slice($clients, 0, 300);

    // Com a pausa entre envios, uma lista grande passa fácil do limite padrão de execução do PHP.
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    $cfg = evo_settings();
    $client = evo_client();
    $logFile = __DIR__ . '/../data/whatsapp_log.txt';
    $count = 0;

    foreach ($clients as $c) {
        $phone = preg_replace('/\D+/', '', (string)($c['phone'] ?? '')) ?: '';
        $timestamp = date('Y-m-d H:i:s');
        if ($phone === '') {
            continue;
        }

        $text = evo_personalize($message, $c);
        try {
            $client->sendText($cfg['instance'], $phone, $text);
            $count++;
            file_put_contents($logFile, "[$timestamp] OK | Cliente: {$c['name']} | Telefone: $phone\n", FILE_APPEND);
        } catch (Throwable $e) {
            file_put_contents($logFile, "[$timestamp] ERRO | Cliente: {$c['name']} | Telefone: $phone | " . $e->getMessage() . "\n", FILE_APPEND);
        }

        // Pequena pausa entre envios — comportamento mais humano, reduz risco de bloqueio.
        usleep(450000);
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

/**
 * Lembrete automático dos agendamentos de amanhã — pra chamar via cron/scripts/lembretes.php.
 * Marca reminder_sent_at pra nunca lembrar o mesmo atendimento duas vezes.
 */
function send_appointment_reminders(): array
{
    $result = ['sent' => 0, 'skipped' => 0, 'errors' => 0];
    if (!evo_configured()) {
        return $result;
    }

    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $targets = appointments_enriched(function ($a) use ($tomorrow) {
        return ($a['date'] ?? '') === $tomorrow
            && in_array($a['status'] ?? '', ['agendado', 'confirmado'], true)
            && empty($a['reminder_sent_at']);
    });
    if (!$targets) {
        return $result;
    }

    $cfg = evo_settings();
    $client = evo_client();
    $shopName = shop_brand_name();
    $logFile = __DIR__ . '/../data/whatsapp_log.txt';

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    foreach ($targets as $a) {
        $phone = preg_replace('/\D+/', '', (string)($a['client_phone'] ?? '')) ?: '';
        $timestamp = date('Y-m-d H:i:s');
        if ($phone === '') {
            $result['skipped']++;
            continue;
        }

        $hora = substr((string)($a['time'] ?? ''), 0, 5);
        $text = "Oi {$a['client_name']}! Passando pra lembrar do seu horário amanhã às {$hora} na {$shopName}"
            . (!empty($a['service_name']) ? " ({$a['service_name']})" : '')
            . ". Até lá! ✂️";

        try {
            $client->sendText($cfg['instance'], $phone, $text);
            save_appointment(['id' => (int)$a['id'], 'reminder_sent_at' => date('c')]);
            $result['sent']++;
            file_put_contents($logFile, "[$timestamp] LEMBRETE OK | Cliente: {$a['client_name']} | Telefone: $phone\n", FILE_APPEND);
        } catch (Throwable $e) {
            $result['errors']++;
            file_put_contents($logFile, "[$timestamp] LEMBRETE ERRO | Cliente: {$a['client_name']} | Telefone: $phone | " . $e->getMessage() . "\n", FILE_APPEND);
        }

        usleep(450000);
    }

    return $result;
}
