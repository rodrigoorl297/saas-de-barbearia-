<?php
declare(strict_types=1);

/**
 * Dispara os lembretes de amanhã pelo WhatsApp. Feito pra rodar 1x por dia.
 *
 * Uso via cron real (VPS):
 *   0 18 * * * php /caminho/do/site/scripts/lembretes.php
 *
 * Uso via "cron por URL" (hospedagem compartilhada, cPanel etc.):
 *   https://seusite.com/scripts/lembretes.php?token=SEU_TOKEN
 *   (o token fica em Configurações, seção WhatsApp)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/whatsapp.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $token = (string)($_GET['token'] ?? '');
    if ($token === '' || !hash_equals(cron_secret(), $token)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
        exit;
    }
}

$result = send_appointment_reminders();

if ($isCli) {
    echo "Lembretes enviados: {$result['sent']} | ignorados: {$result['skipped']} | erros: {$result['errors']}\n";
} else {
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
}
