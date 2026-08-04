<?php
declare(strict_types=1);

/**
 * Suite de testes mínima, sem dependências (sem Composer/PHPUnit — o projeto não
 * usa nenhum dos dois de verdade hoje, ver composer.json). Cobre as funções puras
 * de maior risco identificadas na revisão de QA: criptografia de segredos, slug,
 * normalização de horário, tradução de status e mascaramento de telefone em log.
 *
 * Rodar: php tests/run.php
 * Não toca em data/ nem em sessão — só carrega as funções, não o bootstrap completo.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Rode via linha de comando: php tests/run.php');
}

require __DIR__ . '/../autoload.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/whatsapp.php';

if (!defined('APP_KEY')) {
    define('APP_KEY', base64_encode(str_repeat('k', 32)));
}
// Só para satisfazer data_dir(); as funções testadas aqui não leem o disco.
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/../data/suprema.db');
}
require __DIR__ . '/../config/database.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
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

echo "== secret_encrypt / secret_decrypt ==\n";
$plain = 'APP_USR-segredo-teste-123';
$enc = secret_encrypt($plain);
check('valor criptografado é diferente do original', $enc !== $plain);
check('valor criptografado tem o prefixo enc:v1:', str_starts_with($enc, 'enc:v1:'));
check('decrypt reverte para o valor original', secret_decrypt($enc) === $plain);
check('string vazia não é criptografada', secret_encrypt('') === '');
check('valor legado sem prefixo é devolvido como está', secret_decrypt('token-legado-texto-plano') === 'token-legado-texto-plano');
check('duas criptografias do mesmo valor geram saídas diferentes (nonce aleatório)', secret_encrypt($plain) !== secret_encrypt($plain));

echo "== slugify ==\n";
check('remove acentos e espaços', slugify('Barbearia São João') === 'barbearia-sao-joao');
check('nome vazio vira "barbearia"', slugify('') === 'barbearia');
check('slug que colide com rota reservada ganha prefixo loja-', slugify('cliente') === 'loja-cliente');

echo "== normalize_time ==\n";
check('HH:MM válido passa direto', normalize_time('08:30') === '08:30');
check('HH:MM:SS é truncado para HH:MM', normalize_time('08:30:00') === '08:30');
check('valor inválido usa o fallback', normalize_time('não é hora', '09:00') === '09:00');

echo "== status_label ==\n";
check('status conhecido traduz para rótulo em pt-BR', status_label('confirmado') === 'Confirmado');
check('status desconhecido é devolvido sem alteração', status_label('xyz') === 'xyz');

echo "== wa_mask_phone (log de campanhas) ==\n";
check('mantém DDI+DDD e os 2 últimos dígitos, mascara o meio', wa_mask_phone('5511987654321') === '5511*******21');
check('telefone curto vira só asteriscos', wa_mask_phone('999') === '***');

echo "== fidelidade: loyalty_format_mult ==\n";
check('1.5 vira "1,5"', loyalty_format_mult(1.5) === '1,5');
check('1.0 vira "1" (sem casas sobrando)', loyalty_format_mult(1.0) === '1');
check('1.25 mantém as duas casas', loyalty_format_mult(1.25) === '1,25');

echo "== fidelidade: código de indicação ==\n";
$codeJoao = loyalty_referral_code_for_client(['id' => 7, 'name' => 'João Silva']);
check('código usa só o primeiro nome + id em base36', $codeJoao === 'JOAO-7');
check('sobrenome não entra no código', loyalty_referral_code_for_client(['id' => 3, 'name' => 'Ana Souza']) === 'ANA-3');
check('acentos viram letras simples', loyalty_referral_code_for_client(['id' => 5, 'name' => 'Ção']) === 'CAO-5');
check('id alto vira base36', loyalty_referral_code_for_client(['id' => 42, 'name' => 'Ana']) === 'ANA-16');
check('nome vazio usa prefixo genérico', loyalty_referral_code_for_client(['id' => 1, 'name' => '']) === 'CLI-1');
check('nome trocado não muda o sufixo que identifica o dono', str_ends_with(loyalty_referral_code_for_client(['id' => 7, 'name' => 'Outro Nome']), '-7'));

echo "== fidelidade: loyalty_last_earn_date ==\n";
$membroComGanhos = ['history' => [
    ['date' => '2026-01-10T10:00:00-03:00', 'delta' => 10],
    ['date' => '2026-03-05T10:00:00-03:00', 'delta' => -50],
    ['date' => '2026-02-20T10:00:00-03:00', 'delta' => 30],
]];
check('pega o ganho mais recente, ignorando resgates', loyalty_last_earn_date($membroComGanhos) === '2026-02-20T10:00:00-03:00');
check('membro só com resgates não tem data de ganho', loyalty_last_earn_date(['history' => [['date' => '2026-03-05T10:00:00-03:00', 'delta' => -50]]]) === null);
check('membro sem histórico devolve null', loyalty_last_earn_date([]) === null);

echo "== App\\DotEnv ==\n";
putenv('TEST_BOOL_TRUE=true');
putenv('TEST_BOOL_FALSE=0');
check('getBool reconhece "true" como verdadeiro', \App\DotEnv::getBool('TEST_BOOL_TRUE') === true);
check('getBool reconhece "0" como falso', \App\DotEnv::getBool('TEST_BOOL_FALSE') === false);
check('getBool usa o default quando a variável não existe', \App\DotEnv::getBool('TEST_BOOL_AUSENTE', true) === true);

echo "\n$passed passaram, $failed falharam.\n";
exit($failed > 0 ? 1 : 0);
