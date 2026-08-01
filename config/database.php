<?php
declare(strict_types=1);

/**
 * Armazenamento: MySQL (produção/escala) com fallback JSON local.
 */
require_once __DIR__ . '/mysql.php';

function data_dir(): string
{
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function store_path(string $table): string
{
    return data_dir() . '/' . $table . '.json';
}

function store_read(string $table): array
{
    if (db_enabled() && db_pdo()) {
        return db_fetch_all($table);
    }
    $path = store_path($table);
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function store_write(string $table, array $rows): void
{
    $rows = array_values($rows);
    if (db_enabled() && db_pdo()) {
        db_replace_all($table, $rows);
        return;
    }
    file_put_contents(
        store_path($table),
        json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function store_next_id(string $table): int
{
    if (db_enabled() && db_pdo()) {
        return db_next_id($table);
    }
    $path = store_path($table);
    $rows = [];
    if (is_file($path)) {
        $raw = file_get_contents($path);
        $decoded = json_decode($raw ?: '[]', true);
        $rows = is_array($decoded) ? $decoded : [];
    }
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int) ($row['id'] ?? 0));
    }
    return $max + 1;
}

function ensure_database(): void
{
    if (db_enabled()) {
        db_install_schema_if_needed();
        db_migrate_from_json_if_empty();
        if (function_exists('sync_shop_slug_from_name')) {
            sync_shop_slug_from_name();
        }
        return;
    }

    if (!is_file(store_path('users'))) {
        seed_database();
    }
    ensure_extra_tables();
    if (function_exists('sync_shop_slug_from_name')) {
        sync_shop_slug_from_name();
    }
}

function ensure_extra_tables(): void
{
    // Garante campos de horário mesmo em instalação antiga
    $settings = store_read('settings');
    if ($settings) {
        $s = $settings[0];
        $changed = false;
        $defaults = [
            'open_time' => '08:00',
            'close_time' => '20:00',
            'lunch_start' => '12:00',
            'lunch_end' => '13:00',
            'slot_minutes' => 60,
            'lunch_enabled' => 1,
            'instagram' => 'https://instagram.com/barbaflow',
            'maps_url' => 'https://maps.google.com/?q=Barba+Flow',
            'logo_url' => 'assets/img/logo-barba-flow.png',
            'mp_public_key' => '',
            'mp_access_token' => '',
            'commission_rate' => 0.40,
            'setup_completed' => 1,
            'wa_phone_number_id' => '',
            'wa_access_token' => '',
            'loyalty_prata_min' => 100,
            'loyalty_ouro_min' => 200,
            'loyalty_points_per_real' => 1,
            'loyalty_rewards_json' => '[]',
        ];
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $s)) {
                $s[$k] = $v;
                $changed = true;
            }
        }
        // Se ainda estiver no padrão antigo de 30 min, sobe para 1h
        if ((int)($s['slot_minutes'] ?? 30) === 30 && !isset($settings[0]['lunch_start'])) {
            $s['slot_minutes'] = 60;
            $changed = true;
        }
        if ($changed) {
            store_write('settings', [$s]);
        }
    }

    if (!is_file(store_path('stock'))) {
        store_write('stock', [
            ['id' => 1, 'name' => 'Pomada modeladora', 'sku' => 'POM-01', 'qty' => 24, 'min_qty' => 5, 'cost' => 18, 'price' => 45, 'active' => 1],
            ['id' => 2, 'name' => 'Óleo para barba', 'sku' => 'OLE-01', 'qty' => 12, 'min_qty' => 4, 'cost' => 15, 'price' => 38, 'active' => 1],
            ['id' => 3, 'name' => 'Shampoo masculino', 'sku' => 'SHA-01', 'qty' => 3, 'min_qty' => 6, 'cost' => 22, 'price' => 55, 'active' => 1],
            ['id' => 4, 'name' => 'Lâmina descartável', 'sku' => 'LAM-01', 'qty' => 80, 'min_qty' => 20, 'cost' => 1.5, 'price' => 0, 'active' => 1],
        ]);
    }
    if (!is_file(store_path('goals'))) {
        store_write('goals', [
            ['id' => 1, 'barber_id' => 2, 'month' => date('Y-m'), 'target' => 8000, 'type' => 'faturamento'],
            ['id' => 2, 'barber_id' => 3, 'month' => date('Y-m'), 'target' => 7000, 'type' => 'faturamento'],
            ['id' => 3, 'barber_id' => 4, 'month' => date('Y-m'), 'target' => 6500, 'type' => 'faturamento'],
            ['id' => 4, 'barber_id' => null, 'month' => date('Y-m'), 'target' => 30000, 'type' => 'loja'],
        ]);
    }
    if (!is_file(store_path('loyalty'))) {
        store_write('loyalty', [
            ['id' => 1, 'client_phone' => '11999990001', 'client_name' => 'João Silva', 'points' => 120, 'tier' => 'Prata'],
            ['id' => 2, 'client_phone' => '11999990002', 'client_name' => 'Pedro Santos', 'points' => 80, 'tier' => 'Bronze'],
            ['id' => 3, 'client_phone' => '11999990003', 'client_name' => 'Carlos Lima', 'points' => 210, 'tier' => 'Ouro'],
        ]);
    }
    if (!is_file(store_path('campaigns'))) {
        store_write('campaigns', [
            ['id' => 1, 'name' => 'Volta VIP', 'type' => 'inativos', 'channel' => 'whatsapp', 'status' => 'rascunho', 'message' => 'Oi! Faz tempo que não te vemos. Agende com 10% OFF esta semana.', 'created_at' => date('c')],
            ['id' => 2, 'name' => 'Aniversariantes', 'type' => 'aniversariantes', 'channel' => 'whatsapp', 'status' => 'ativa', 'message' => 'Feliz aniversário! Ganhe sobrancelha grátis no seu mês.', 'created_at' => date('c')],
        ]);
    }

    if (!is_file(store_path('plans'))) {
        store_write('plans', [
            [
                'id' => 1,
                'name' => 'Clube Suprema',
                'price' => 63,
                'interval' => 'Mês',
                'headline' => 'Corte ilimitado o mês todo',
                'description' => 'Corte quantas vezes quiser durante 30 dias',
                'benefit_label' => 'Corte plano',
                'usage_limit' => null,
                'images' => ['', '', ''],
                'recommended' => 1,
                'active' => 1,
                'sort_order' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Clube Pai e Filho',
                'price' => 100,
                'interval' => 'Mês',
                'headline' => 'Pai e filho o mês todo com o corte em dia',
                'description' => 'Pai e filho com corte o mês todo, quantas vezes quiser',
                'benefit_label' => 'Corte plano',
                'usage_limit' => null,
                'images' => ['', '', ''],
                'recommended' => 0,
                'active' => 1,
                'sort_order' => 2,
            ],
            [
                'id' => 3,
                'name' => 'Plano Mensal',
                'price' => 50,
                'interval' => 'Mês',
                'headline' => 'Pix ou dinheiro',
                'description' => 'Plano de assinatura mensal com cortes inclusos',
                'benefit_label' => 'Corte plano',
                'usage_limit' => 4,
                'images' => ['', '', ''],
                'recommended' => 0,
                'active' => 1,
                'sort_order' => 3,
            ],
        ]);
    }
    if (!is_file(store_path('subscriptions'))) {
        store_write('subscriptions', []);
    }
    if (!is_file(store_path('client_cards'))) {
        store_write('client_cards', []);
    }
    if (!is_file(store_path('charges'))) {
        store_write('charges', []);
    }
    if (!is_file(store_path('notifications'))) {
        store_write('notifications', []);
    }
}

function seed_database(): void
{
    store_write('settings', [[
        'id' => 1,
        'shop_name' => 'Minha Barbearia',
        'slug' => 'minha-barbearia',
        'phone' => '',
        'address' => '',
        'instagram' => '',
        'maps_url' => '',
        'logo_url' => '',
        'primary_color' => '#11172f',
        'accent_color' => '#c9a227',
        'open_time' => '08:00',
        'close_time' => '20:00',
        'lunch_start' => '12:00',
        'lunch_end' => '13:00',
        'slot_minutes' => 60,
        'lunch_enabled' => 1,
        'setup_completed' => 0,
        'commission_rate' => 0.4,
        'mp_public_key' => '',
        'mp_access_token' => '',
        'wa_phone_number_id' => '',
        'wa_access_token' => '',
    ]]);

    $passDono = password_hash('dono123', PASSWORD_DEFAULT);
    $passBarbeiro = password_hash('barbeiro123', PASSWORD_DEFAULT);

    store_write('users', [
        ['id' => 1, 'name' => 'Administrador', 'email' => 'admin@loja.local', 'phone' => null, 'password' => $passDono, 'role' => 'dono', 'avatar' => null, 'active' => 1, 'created_at' => date('c')],
        ['id' => 2, 'name' => 'Barbeiro 1', 'email' => 'barbeiro1@loja.local', 'phone' => '', 'password' => $passBarbeiro, 'role' => 'barbeiro', 'avatar' => null, 'active' => 1, 'created_at' => date('c')],
    ]);

    $services = [
        ['Corte', 'Degradê / americano / low fade / mid fade / moicano', 35, 30],
        ['Corte Social', 'Corte social clássico', 30, 30],
        ['Barba', 'Barba completa com navalha', 25, 25],
        ['Sobrancelha na navalha', 'Design de sobrancelha', 5, 10],
        ['Corte + Barba + Sobrancelha', 'Combo completo', 65, 70],
        ['Hidratação simples', 'Hidratação capilar', 20, 20],
        ['Limpeza de pele simples', 'Limpeza facial básica', 20, 25],
        ['Pigmentação', 'Pigmentação capilar', 25, 30],
        ['Relaxamento (Alisamento)', 'Alisamento masculino', 65, 90],
        ['Luzes + corte e sobrancelha', 'Mechas com corte', 130, 100],
        ['Pezinho / bigode / cavanhaque', 'Acabamento rápido', 10, 15],
        ['Corte Kids', 'Corte infantil até 12 anos', 30, 25],
    ];

    $svcRows = [];
    foreach ($services as $i => $s) {
        $svcRows[] = [
            'id' => $i + 1,
            'name' => $s[0],
            'description' => $s[1],
            'price' => $s[2],
            'duration_min' => $s[3],
            'image_url' => '',
            'active' => 1,
            'sort_order' => $i + 1,
        ];
    }
    store_write('services', $svcRows);

    // Instalação limpa — sem agendamentos/caixa de demonstração
    store_write('appointments', []);
    store_write('cash_entries', []);
}

function find_user_by_id(int $id): ?array
{
    foreach (store_read('users') as $u) {
        if ((int) $u['id'] === $id) {
            return $u;
        }
    }
    return null;
}

function find_user_by_email(string $email): ?array
{
    foreach (store_read('users') as $u) {
        if (strcasecmp((string) ($u['email'] ?? ''), $email) === 0) {
            return $u;
        }
    }
    return null;
}

function save_user(array $user): array
{
    $rows = store_read('users');
    if (empty($user['id'])) {
        $user['id'] = store_next_id('users');
        $user['created_at'] = date('c');
        $rows[] = $user;
    } else {
        foreach ($rows as $i => $row) {
            if ((int) $row['id'] === (int) $user['id']) {
                $rows[$i] = array_merge($row, $user);
                $user = $rows[$i];
                break;
            }
        }
    }
    store_write('users', $rows);
    return $user;
}

function find_service(int $id): ?array
{
    foreach (store_read('services') as $s) {
        if ((int) $s['id'] === $id) {
            return $s;
        }
    }
    return null;
}

function save_service(array $service): array
{
    $rows = store_read('services');
    if (empty($service['id'])) {
        $service['id'] = store_next_id('services');
        $rows[] = $service;
    } else {
        foreach ($rows as $i => $row) {
            if ((int) $row['id'] === (int) $service['id']) {
                $rows[$i] = array_merge($row, $service);
                $service = $rows[$i];
                break;
            }
        }
    }
    store_write('services', $rows);
    return $service;
}

function save_appointment(array $a): array
{
    $rows = store_read('appointments');
    if (empty($a['id'])) {
        $a['id'] = store_next_id('appointments');
        $a['created_at'] = date('c');
        $rows[] = $a;
    } else {
        foreach ($rows as $i => $row) {
            if ((int) $row['id'] === (int) $a['id']) {
                $rows[$i] = array_merge($row, $a);
                $a = $rows[$i];
                break;
            }
        }
    }
    store_write('appointments', $rows);
    return $a;
}

function save_cash_entry(array $e): array
{
    $rows = store_read('cash_entries');
    $e['id'] = store_next_id('cash_entries');
    $e['created_at'] = date('c');
    $rows[] = $e;
    store_write('cash_entries', $rows);
    return $e;
}

/**
 * Baixa estoque (venda/uso) e registra histórico. Retorna null se ok, ou mensagem de erro.
 */
function stock_consume(int $productId, int $qty, string $type, int $userId, string $note = '', bool $recordCash = true): ?string
{
    if ($qty < 1 || !in_array($type, ['out_venda', 'out_uso'], true)) {
        return 'Quantidade ou tipo inválido.';
    }
    $rows = store_read('stock');
    $found = false;
    $prodName = '';
    $unitPrice = 0.0;
    foreach ($rows as $i => $r) {
        if ((int)($r['id'] ?? 0) !== $productId) {
            continue;
        }
        $found = true;
        $prodName = (string)($r['name'] ?? '');
        $unitPrice = (float)($r['price'] ?? 0);
        $newQty = (int)$r['qty'] - $qty;
        if ($newQty < 0) {
            return 'Estoque insuficiente (' . (int)$r['qty'] . ' disponível).';
        }
        $rows[$i]['qty'] = $newQty;
        break;
    }
    if (!$found) {
        return 'Produto não encontrado.';
    }
    store_write('stock', $rows);

    $history = store_read('stock_history');
    $history[] = [
        'id' => store_next_id('stock_history'),
        'product_id' => $productId,
        'product_name' => $prodName,
        'qty' => $qty,
        'delta' => -$qty,
        'type' => $type,
        'user_id' => $userId,
        'note' => $note,
        'date' => date('Y-m-d H:i:s'),
    ];
    store_write('stock_history', $history);

    if ($recordCash && $type === 'out_venda' && $unitPrice > 0) {
        save_cash_entry([
            'type' => 'entrada',
            'category' => 'produto',
            'description' => 'Venda produto: ' . $prodName . ' x' . $qty,
            'amount' => $unitPrice * $qty,
            'created_by' => $userId,
        ]);
    }

    return null;
}

/** Produtos vendidos no atendimento. */
function appointment_products(array $a): array
{
    $p = $a['products'] ?? null;
    if ($p === null && isset($a['products_json'])) {
        $p = $a['products_json'];
    }
    if (is_string($p)) {
        $p = json_decode($p, true);
    }
    return is_array($p) ? array_values($p) : [];
}

function appointment_products_total(array $a): float
{
    $sum = 0.0;
    foreach (appointment_products($a) as $item) {
        if (isset($item['total'])) {
            $sum += (float)$item['total'];
        } else {
            $sum += (float)($item['unit_price'] ?? 0) * (int)($item['qty'] ?? 0);
        }
    }
    return $sum;
}

function cash_entry_for_appointment(int $appointmentId): ?array
{
    foreach (store_read('cash_entries') as $e) {
        if ((int)($e['appointment_id'] ?? 0) === $appointmentId) {
            return $e;
        }
    }
    return null;
}

/**
 * Atualiza ou cria lançamento de caixa do atendimento concluído.
 */
function sync_appointment_cash(array $appointment, int $createdBy): void
{
    $id = (int)($appointment['id'] ?? 0);
    if ($id <= 0) {
        return;
    }
    $amount = (float)($appointment['price'] ?? 0);
    $desc = 'Atendimento #' . $id . ' — ' . ($appointment['client_name'] ?? 'Cliente');
    $existing = cash_entry_for_appointment($id);
    if ($existing) {
        $rows = store_read('cash_entries');
        foreach ($rows as $i => $e) {
            if ((int)($e['id'] ?? 0) === (int)$existing['id']) {
                $rows[$i]['amount'] = $amount;
                $rows[$i]['description'] = $desc;
                $rows[$i]['updated_at'] = date('c');
                break;
            }
        }
        store_write('cash_entries', $rows);
        return;
    }
    save_cash_entry([
        'type' => 'entrada',
        'category' => 'servico',
        'description' => $desc,
        'amount' => $amount,
        'appointment_id' => $id,
        'created_by' => $createdBy,
    ]);
}

/** Faixas de nível do fidelidade (configuráveis na aba Fidelidade). */
function loyalty_thresholds(): array
{
    $cfg = settings();
    return [
        'prata' => max(1, (int)($cfg['loyalty_prata_min'] ?? 100)),
        'ouro' => max(1, (int)($cfg['loyalty_ouro_min'] ?? 200)),
    ];
}

function loyalty_tier_for_points(int $points): string
{
    $t = loyalty_thresholds();
    if ($points >= $t['ouro']) {
        return 'Ouro';
    }
    if ($points >= $t['prata']) {
        return 'Prata';
    }
    return 'Bronze';
}

function loyalty_points_per_real(): float
{
    return max(0, (float)(settings()['loyalty_points_per_real'] ?? 1));
}

function loyalty_rewards(): array
{
    $raw = settings()['loyalty_rewards_json'] ?? '[]';
    $rows = is_array($raw) ? $raw : json_decode((string)$raw ?: '[]', true);
    return is_array($rows) ? $rows : [];
}

function save_loyalty_rewards(array $rewards): void
{
    save_settings(['loyalty_rewards_json' => json_encode(array_values($rewards), JSON_UNESCAPED_UNICODE)]);
}

function find_loyalty_member(int $id): ?array
{
    foreach (store_read('loyalty') as $m) {
        if ((int)($m['id'] ?? 0) === $id) {
            return $m;
        }
    }
    return null;
}

function find_loyalty_member_by_phone(string $phone): ?array
{
    $phone = preg_replace('/\D+/', '', $phone);
    foreach (store_read('loyalty') as $m) {
        if (preg_replace('/\D+/', '', (string)($m['client_phone'] ?? '')) === $phone && $phone !== '') {
            return $m;
        }
    }
    return null;
}

/**
 * Ajusta pontos de um cliente (cria o membro se necessário) e registra no histórico.
 */
function loyalty_add_points(string $phone, string $name, int $delta, string $reason, array $meta = []): array
{
    $phone = preg_replace('/\D+/', '', $phone);
    $rows = store_read('loyalty');
    $idx = null;
    foreach ($rows as $i => $m) {
        if (preg_replace('/\D+/', '', (string)($m['client_phone'] ?? '')) === $phone) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        $rows[] = [
            'id' => store_next_id('loyalty'),
            'client_phone' => $phone,
            'client_name' => $name,
            'points' => 0,
            'tier' => 'Bronze',
            'history' => [],
        ];
        $idx = count($rows) - 1;
    }

    $points = max(0, (int)($rows[$idx]['points'] ?? 0) + $delta);
    $rows[$idx]['points'] = $points;
    $rows[$idx]['tier'] = loyalty_tier_for_points($points);
    $history = $rows[$idx]['history'] ?? [];
    $history[] = array_merge([
        'date' => date('c'),
        'delta' => $delta,
        'reason' => $reason,
    ], $meta);
    $rows[$idx]['history'] = array_slice($history, -50);

    store_write('loyalty', $rows);
    return $rows[$idx];
}

/** Concede pontos automaticamente ao concluir um agendamento (idempotente). */
function sync_appointment_loyalty(array $appointment): void
{
    $id = (int)($appointment['id'] ?? 0);
    $phone = preg_replace('/\D+/', '', (string)($appointment['client_phone'] ?? ''));
    if ($id <= 0 || $phone === '') {
        return;
    }

    $member = find_loyalty_member_by_phone($phone);
    foreach (($member['history'] ?? []) as $h) {
        if (($h['type'] ?? '') === 'agendamento' && (int)($h['appointment_id'] ?? 0) === $id) {
            return;
        }
    }

    $points = (int)floor((float)($appointment['price'] ?? 0) * loyalty_points_per_real());
    if ($points <= 0) {
        return;
    }

    loyalty_add_points($phone, (string)($appointment['client_name'] ?? ''), $points, 'Agendamento concluído', [
        'type' => 'agendamento',
        'appointment_id' => $id,
    ]);
}

function save_settings(array $data): void
{
    // Nunca permitir sobrescrever a marca do produto via settings
    unset($data['product_name'], $data['product_logo'], $data['PRODUCT_NAME'], $data['PRODUCT_LOGO']);

    // Só garante https:// se a pessoa colar sem protocolo (não reescreve o link salvo)
    if (array_key_exists('instagram', $data)) {
        $ig = trim((string)$data['instagram']);
        if ($ig !== '' && !preg_match('#^https?://#i', $ig) && preg_match('/^@?([A-Za-z0-9._]{1,30})$/', $ig, $m)) {
            $ig = 'https://www.instagram.com/' . $m[1] . '/';
        } elseif ($ig !== '' && !preg_match('#^https?://#i', $ig)) {
            $ig = 'https://' . ltrim($ig, '/');
        }
        $data['instagram'] = $ig;
    }
    if (array_key_exists('maps_url', $data)) {
        $maps = trim((string)$data['maps_url']);
        if ($maps !== '' && !preg_match('#^https?://#i', $maps)) {
            $maps = 'https://' . ltrim($maps, '/');
        }
        $data['maps_url'] = $maps;
    }

    $rows = store_read('settings');
    $current = $rows[0] ?? ['id' => 1];

    // Slug do link público = nome da barbearia
    $shopName = array_key_exists('shop_name', $data)
        ? (string) $data['shop_name']
        : (string) ($current['shop_name'] ?? 'barbearia');
    $data['slug'] = slugify($shopName);

    store_write('settings', [array_merge($current, $data, ['id' => 1])]);
    settings(true);
}

/** Alinha slug ao nome atual (instalações antigas). */
function sync_shop_slug_from_name(): void
{
    $current = settings();
    $expected = slugify((string) ($current['shop_name'] ?? 'barbearia'));
    if (($current['slug'] ?? '') === $expected) {
        return;
    }
    $rows = store_read('settings');
    $row = $rows[0] ?? ['id' => 1];
    $row['slug'] = $expected;
    $row['id'] = 1;
    store_write('settings', [$row]);
    settings(true);
}

function find_plan(int $id): ?array
{
    foreach (store_read('plans') as $p) {
        if ((int) $p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

function save_plan(array $plan): array
{
    $rows = store_read('plans');
    if (empty($plan['id'])) {
        $plan['id'] = store_next_id('plans');
        $rows[] = $plan;
    } else {
        foreach ($rows as $i => $row) {
            if ((int) $row['id'] === (int) $plan['id']) {
                $rows[$i] = array_merge($row, $plan);
                $plan = $rows[$i];
                break;
            }
        }
    }
    store_write('plans', $rows);
    return $plan;
}

function active_plans(): array
{
    $plans = array_values(array_filter(store_read('plans'), fn($p) => !empty($p['active'])));
    usort($plans, fn($a, $b) => ((int)($a['sort_order'] ?? 99) <=> (int)($b['sort_order'] ?? 99)));
    return $plans;
}

function save_subscription(array $sub): array
{
    $rows = store_read('subscriptions');
    if (empty($sub['id'])) {
        $sub['id'] = store_next_id('subscriptions');
        $sub['created_at'] = date('c');
        $rows[] = $sub;
    } else {
        foreach ($rows as $i => $row) {
            if ((int) $row['id'] === (int) $sub['id']) {
                $rows[$i] = array_merge($row, $sub);
                $sub = $rows[$i];
                break;
            }
        }
    }
    store_write('subscriptions', $rows);
    return $sub;
}

function client_subscriptions(int $clientId): array
{
    $subs = array_values(array_filter(
        store_read('subscriptions'),
        fn($s) => (int)($s['client_id'] ?? 0) === $clientId && ($s['status'] ?? '') === 'active'
    ));
    usort($subs, fn($a, $b) => strcmp((string)($a['renews_at'] ?? ''), (string)($b['renews_at'] ?? '')));
    return $subs;
}

function save_client_card(array $card): array
{
    $rows = store_read('client_cards');
    if (empty($card['id'])) {
        $card['id'] = store_next_id('client_cards');
        $rows[] = $card;
    } else {
        foreach ($rows as $i => $row) {
            if ((int) $row['id'] === (int) $card['id']) {
                $rows[$i] = array_merge($row, $card);
                $card = $rows[$i];
                break;
            }
        }
    }
    store_write('client_cards', $rows);
    return $card;
}

function delete_client_card(int $id, int $clientId): void
{
    $rows = array_values(array_filter(
        store_read('client_cards'),
        fn($c) => !((int)$c['id'] === $id && (int)($c['client_id'] ?? 0) === $clientId)
    ));
    store_write('client_cards', $rows);
}

function client_cards(int $clientId): array
{
    return array_values(array_filter(
        store_read('client_cards'),
        fn($c) => (int)($c['client_id'] ?? 0) === $clientId
    ));
}

function save_charge(array $charge): array
{
    $rows = store_read('charges');
    $charge['id'] = store_next_id('charges');
    $rows[] = $charge;
    store_write('charges', $rows);
    return $charge;
}

function client_charges(int $clientId): array
{
    $rows = array_values(array_filter(
        store_read('charges'),
        fn($c) => (int)($c['client_id'] ?? 0) === $clientId
    ));
    usort($rows, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));
    return $rows;
}

function client_notifications(int $clientId): array
{
    $rows = array_values(array_filter(
        store_read('notifications'),
        fn($n) => (int)($n['client_id'] ?? 0) === $clientId
    ));
    usort($rows, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
    return $rows;
}

/**
 * Cria avisos no app do cliente para o público da campanha (aniversário / inativos / base).
 */
function create_avisos_from_campaign(array $campaign): int
{
    require_once __DIR__ . '/../includes/whatsapp.php';
    $clients = get_target_clients_for_campaign((string)($campaign['type'] ?? 'promocional'));
    if (!$clients) {
        return 0;
    }

    $notifs = store_read('notifications');
    $nextId = 1;
    foreach ($notifs as $n) {
        $nextId = max($nextId, (int)($n['id'] ?? 0) + 1);
    }

    $message = trim((string)($campaign['message'] ?? ''));
    $title = trim((string)($campaign['name'] ?? 'Aviso'));
    $type = (string)($campaign['type'] ?? 'promocional');
    $count = 0;

    foreach ($clients as $c) {
        $cid = (int)($c['id'] ?? 0);
        if ($cid <= 0 || $message === '') {
            continue;
        }
        $notifs[] = [
            'id' => $nextId++,
            'client_id' => $cid,
            'campaign_id' => (int)($campaign['id'] ?? 0),
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'read' => 0,
            'created_at' => date('c'),
        ];
        $count++;
    }

    if ($count > 0) {
        store_write('notifications', $notifs);
    }

    return $count;
}

function subscribe_client_to_plan(array $client, array $plan, array $card, array $payment): array
{
    $renews = date('Y-m-d', strtotime('+1 month'));
    $last4 = (string)($card['last4'] ?? '----');
    $paymentId = (string)($payment['id'] ?? '');

    $sub = save_subscription([
        'client_id' => (int) $client['id'],
        'client_phone' => preg_replace('/\D+/', '', (string)($client['phone'] ?? '')),
        'plan_id' => (int) $plan['id'],
        'status' => 'active',
        'usage_count' => 0,
        'started_at' => date('Y-m-d'),
        'renews_at' => $renews,
        'card_id' => (int)($card['id'] ?? 0),
        'mp_payment_id' => $paymentId,
    ]);

    $charges = store_read('charges');
    foreach ($charges as $i => $c) {
        if ((int)($c['client_id'] ?? 0) === (int)$client['id']
            && (int)($c['plan_id'] ?? 0) === (int)$plan['id']
            && ($c['status'] ?? '') === 'proxima') {
            $charges[$i]['status'] = 'pago';
        }
    }
    store_write('charges', $charges);

    save_charge([
        'client_id' => (int) $client['id'],
        'plan_id' => (int) $plan['id'],
        'plan_name' => $plan['name'],
        'amount' => (float) $plan['price'],
        'card_last4' => $last4,
        'card_brand' => (string)($card['brand'] ?? ''),
        'date' => date('Y-m-d'),
        'status' => 'pago',
        'mp_payment_id' => $paymentId,
        'mp_status' => (string)($payment['status'] ?? 'approved'),
    ]);

    save_charge([
        'client_id' => (int) $client['id'],
        'plan_id' => (int) $plan['id'],
        'plan_name' => $plan['name'],
        'amount' => (float) $plan['price'],
        'card_last4' => $last4,
        'card_brand' => (string)($card['brand'] ?? ''),
        'date' => $renews,
        'status' => 'proxima',
        'mp_payment_id' => null,
        'mp_status' => null,
    ]);

    return $sub;
}
