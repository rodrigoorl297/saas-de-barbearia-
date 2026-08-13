<?php
declare(strict_types=1);

/**
 * Backup completo dos dados da barbearia em um único JSON pra download.
 * Funciona igual em modo MySQL ou JSON local, porque só usa store_read().
 */
require_once __DIR__ . '/../config/config.php';

$user = require_role(['dono']);

$tables = [
    'settings', 'users', 'services', 'appointments', 'stock', 'stock_history',
    'cash_entries', 'campaigns', 'goals', 'loyalty', 'client_cards',
    'notifications', 'plans', 'subscriptions', 'charges',
];

$data = [
    'exported_at' => date('c'),
    'shop_slug'   => shop_slug(),
    'shop_name'   => shop_brand_name(),
    'tables'      => [],
];
foreach ($tables as $t) {
    $data['tables'][$t] = store_read($t);
}

$filename = 'backup-' . shop_slug() . '-' . date('Y-m-d_His') . '.json';
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen((string)$json));
header('Cache-Control: no-store');
echo $json;
