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
/** Em produção deve ser false. Ative só para demos com CLIENT_DEMO_OPEN=true no .env */
define('CLIENT_DEMO_OPEN', DotEnv::getBool('CLIENT_DEMO_OPEN', false));
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
