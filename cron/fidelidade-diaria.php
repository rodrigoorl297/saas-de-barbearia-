<?php
declare(strict_types=1);

/**
 * Rotinas diárias do programa de fidelidade. Rode uma vez por dia via cron da
 * hospedagem, ex.: 0 7 * * * php /caminho/do/site/cron/fidelidade-diaria.php
 *
 * Faz duas coisas, ambas idempotentes (reexecutar no mesmo dia não duplica):
 *  - credita o presente de aniversário (uma vez por ano por cliente);
 *  - expira pontos de quem passou do prazo sem pontuar e avisa quem está perto.
 *
 * Tudo é opcional: com os campos zerados em Fidelidade > Regras, nada acontece.
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
$lock = fopen($lockDir . '/cron-fidelidade.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Já existe uma execução em andamento. Abortando.\n");
    exit(1);
}

echo '[' . date('c') . "] Iniciando rotinas de fidelidade\n";

$birthdays = loyalty_run_birthday_bonus();
if (loyalty_birthday_bonus() > 0) {
    echo "  Aniversariantes premiados hoje: $birthdays\n";
} else {
    echo "  Bônus de aniversário desativado.\n";
}

[$expired, $warned] = loyalty_run_expiration();
if (loyalty_expire_days() > 0) {
    echo "  Clientes com pontos expirados: $expired\n";
    echo "  Clientes avisados de expiração próxima: $warned\n";
} else {
    echo "  Expiração de pontos desativada.\n";
}

echo '[' . date('c') . "] Concluído.\n";

flock($lock, LOCK_UN);
fclose($lock);
