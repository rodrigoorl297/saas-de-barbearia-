<?php
declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\DotEnv;

DotEnv::load(__DIR__ . '/../.env');

session_start();

define('APP_NAME', DotEnv::getString('PRODUCT_NAME', 'Barba Flow'));
/** Marca fixa do software (painel). Não vem das Configurações da barbearia. */
define('PRODUCT_NAME', DotEnv::getString('PRODUCT_NAME', 'Barba Flow'));
define('PRODUCT_LOGO', DotEnv::getString('PRODUCT_LOGO', 'assets/img/logo-barba-flow.png'));
define('APP_SLUG', DotEnv::getString('APP_SLUG', 'barbaflow'));
define('APP_URL', DotEnv::getString('APP_URL', ''));
define('DB_PATH', __DIR__ . '/../data/suprema.db');
define('TIMEZONE', DotEnv::getString('TIMEZONE', 'America/Sao_Paulo'));
/** Fail-safe: sem APP_ENV definido no .env, assume produção. */
define('APP_ENV', DotEnv::getString('APP_ENV', 'production'));
/**
 * Em produção deve ser false. Ative só para demos com CLIENT_DEMO_OPEN=true no .env.
 * Trava adicional: mesmo que alguém deixe CLIENT_DEMO_OPEN=true por engano no .env
 * de produção, a flag nunca fica ativa quando APP_ENV=production.
 */
define('CLIENT_DEMO_OPEN', APP_ENV !== 'production' && DotEnv::getBool('CLIENT_DEMO_OPEN', false));
/**
 * Chave para criptografar em repouso os tokens de Mercado Pago/WhatsApp
 * salvos em Configurações (base64 de 32 bytes). Gere com:
 *   php -r "echo base64_encode(random_bytes(32));"
 * Sem essa chave, os tokens continuam sendo salvos em texto plano (comportamento
 * anterior a esta correção) — configure em produção assim que possível.
 */
define('APP_KEY', DotEnv::getString('APP_KEY', ''));
define('MP_PUBLIC_KEY', DotEnv::getString('MP_PUBLIC_KEY', ''));
define('MP_ACCESS_TOKEN', DotEnv::getString('MP_ACCESS_TOKEN', ''));
define('WA_PHONE_NUMBER_ID', DotEnv::getString('WA_PHONE_NUMBER_ID', ''));
define('WA_ACCESS_TOKEN', DotEnv::getString('WA_ACCESS_TOKEN', ''));



date_default_timezone_set(TIMEZONE);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/mercadopago.php';
require_once __DIR__ . '/../includes/email.php';

// Instala o banco automaticamente na primeira visita
ensure_database();

// CSRF em todos os POST (exceto APIs de pagamento)
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$csrfExempt = str_contains($scriptName, '/api/mp-');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$csrfExempt) {
    verify_csrf();
}

// /cliente/... → /{slug}/... (acesso direto à pasta física)
if (str_contains($scriptName, '/cliente/') && empty($_SERVER['BARBAFLOW_SLUG_ROUTE'])) {
    ensure_client_slug_url();
}
