<?php
declare(strict_types=1);

/**
 * Camada MySQL (1 conexão = 1 barbearia / 1 banco).
 * Tabelas em português; store_* continua com nomes antigos do JSON.
 */

function db_enabled(): bool
{
    return env_bool('DB_ENABLED', false) && env_str('DB_NAME', '') !== '';
}

function db_table_map(): array
{
    return [
        'users' => 'usuarios',
        'settings' => 'configuracoes',
        'services' => 'servicos',
        'stock' => 'produtos',
        'stock_history' => 'produtos_historico',
        'appointments' => 'agendamentos',
        'cash_entries' => 'caixa',
        'plans' => 'planos',
        'subscriptions' => 'assinaturas',
        'charges' => 'cobrancas',
        'client_cards' => 'cartoes_cliente',
        'campaigns' => 'campanhas',
        'loyalty' => 'fidelidade',
        'goals' => 'metas',
        'notifications' => 'avisos',
    ];
}

function db_pdo(): ?PDO
{
    static $pdo = null;
    static $failed = false;
    if ($failed) {
        return null;
    }
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!db_enabled()) {
        return null;
    }
    $host = env_str('DB_HOST', 'localhost');
    $name = env_str('DB_NAME', '');
    $user = env_str('DB_USER', '');
    $pass = env_str('DB_PASS', '');
    $port = env_str('DB_PORT', '3306');
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        $failed = true;
        error_log('Barba Flow DB: ' . $e->getMessage());
        return null;
    }
}

function db_physical_table(string $logical): string
{
    $map = db_table_map();
    return $map[$logical] ?? $logical;
}

function db_is_json_blob_table(string $logical): bool
{
    return in_array($logical, [
        'client_cards', 'campaigns', 'loyalty', 'goals', 'notifications',
    ], true);
}

function db_row_from_sql(string $logical, array $row): array
{
    if (db_is_json_blob_table($logical)) {
        $data = json_decode((string)($row['data_json'] ?? '{}'), true);
        if (!is_array($data)) {
            $data = [];
        }
        if (!isset($data['id']) && isset($row['id'])) {
            $data['id'] = (int)$row['id'];
        }
        return $data;
    }

    if ($logical === 'stock_history') {
        return [
            'id' => (int)$row['id'],
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'qty' => (int)$row['qty'],
            'delta' => (int)$row['delta'],
            'type' => $row['type'],
            'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
            'note' => $row['note'] ?? '',
            'date' => $row['created_at'] ?? ($row['date'] ?? ''),
        ];
    }

    if ($logical === 'appointments') {
        $ids = $row['service_ids'] ?? null;
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : null;
        }
        $products = $row['products_json'] ?? ($row['products'] ?? null);
        if (is_string($products)) {
            $decoded = json_decode($products, true);
            $products = is_array($decoded) ? $decoded : [];
        }
        $row['service_ids'] = $ids;
        $row['products'] = is_array($products) ? $products : [];
        unset($row['products_json']);
        $row['id'] = (int)$row['id'];
        $row['barber_id'] = (int)$row['barber_id'];
        $row['client_id'] = $row['client_id'] !== null ? (int)$row['client_id'] : null;
        $row['service_id'] = $row['service_id'] !== null ? (int)$row['service_id'] : null;
        $row['price'] = (float)$row['price'];
        return $row;
    }

    if ($logical === 'subscriptions') {
        return [
            'id' => (int)$row['id'],
            'client_id' => isset($row['client_id']) ? (int)$row['client_id'] : 0,
            'client_phone' => $row['client_phone'] ?? '',
            'plan_id' => isset($row['plan_id']) ? (int)$row['plan_id'] : 0,
            'status' => $row['status'] ?? 'active',
            'usage_count' => (int)($row['usage_count'] ?? 0),
            'started_at' => $row['started_at'] ?? '',
            'renews_at' => $row['renews_at'] ?? '',
            'card_id' => isset($row['card_id']) ? (int)$row['card_id'] : 0,
            'mp_payment_id' => $row['mp_payment_id'] ?? '',
            'created_at' => $row['created_at'] ?? '',
        ];
    }

    if ($logical === 'charges') {
        return [
            'id' => (int)$row['id'],
            'client_id' => isset($row['client_id']) ? (int)$row['client_id'] : 0,
            'plan_id' => isset($row['plan_id']) ? (int)$row['plan_id'] : 0,
            'plan_name' => $row['plan_name'] ?? '',
            'amount' => (float)($row['amount'] ?? 0),
            'card_last4' => $row['card_last4'] ?? '----',
            'card_brand' => $row['card_brand'] ?? '',
            'date' => $row['date'] ?? '',
            'status' => $row['status'] ?? 'proxima',
            'mp_payment_id' => $row['mp_payment_id'] ?? null,
            'mp_status' => $row['mp_status'] ?? null,
        ];
    }

    if ($logical === 'plans') {
        $images = $row['images'] ?? null;
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : ['', '', ''];
        }
        $row['images'] = $images;
        $row['id'] = (int)$row['id'];
        $row['price'] = (float)$row['price'];
        $row['active'] = (int)$row['active'];
        return $row;
    }

    // Cast comuns
    if (isset($row['id'])) {
        $row['id'] = (int)$row['id'];
    }
    foreach (['active', 'qty', 'min_qty', 'duration_min', 'sort_order', 'slot_minutes', 'lunch_enabled', 'setup_completed', 'loyalty_prata_min', 'loyalty_ouro_min'] as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null) {
            $row[$k] = (int)$row[$k];
        }
    }
    foreach (['price', 'cost', 'amount', 'commission_rate', 'target', 'loyalty_points_per_real'] as $k) {
        if (array_key_exists($k, $row) && $row[$k] !== null) {
            $row[$k] = (float)$row[$k];
        }
    }
    return $row;
}

function db_fetch_all(string $logical): array
{
    $pdo = db_pdo();
    if (!$pdo) {
        return [];
    }
    $table = db_physical_table($logical);
    $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
    $rows = $stmt ? $stmt->fetchAll() : [];
    $out = [];
    foreach ($rows as $row) {
        $out[] = db_row_from_sql($logical, $row);
    }
    return $out;
}

function db_replace_all(string $logical, array $rows): void
{
    $pdo = db_pdo();
    if (!$pdo) {
        throw new RuntimeException('MySQL indisponível.');
    }
    $table = db_physical_table($logical);
    $safe = str_replace('`', '``', $table);

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM `' . $safe . '`');

        if ($logical === 'settings') {
            $row = $rows[0] ?? null;
            if ($row) {
                db_upsert_settings($pdo, $row);
            }
            $pdo->commit();
            return;
        }

        foreach ($rows as $row) {
            db_insert_row($pdo, $logical, $safe, $row);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function db_upsert_settings(PDO $pdo, array $row): void
{
    $sql = 'REPLACE INTO configuracoes (
        id, shop_name, slug, phone, address, instagram, maps_url, logo_url,
        primary_color, accent_color, open_time, close_time, lunch_start, lunch_end,
        slot_minutes, lunch_enabled, commission_rate, setup_completed,
        mp_public_key, mp_access_token, wa_phone_number_id, wa_access_token,
        loyalty_prata_min, loyalty_ouro_min, loyalty_points_per_real, loyalty_rewards_json
      ) VALUES (
        1, :shop_name, :slug, :phone, :address, :instagram, :maps_url, :logo_url,
        :primary_color, :accent_color, :open_time, :close_time, :lunch_start, :lunch_end,
        :slot_minutes, :lunch_enabled, :commission_rate, :setup_completed,
        :mp_public_key, :mp_access_token, :wa_phone_number_id, :wa_access_token,
        :loyalty_prata_min, :loyalty_ouro_min, :loyalty_points_per_real, :loyalty_rewards_json
      )';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':shop_name' => (string)($row['shop_name'] ?? 'Minha Barbearia'),
        ':slug' => (string)($row['slug'] ?? 'minha-barbearia'),
        ':phone' => $row['phone'] ?? null,
        ':address' => $row['address'] ?? null,
        ':instagram' => $row['instagram'] ?? null,
        ':maps_url' => $row['maps_url'] ?? null,
        ':logo_url' => $row['logo_url'] ?? null,
        ':primary_color' => $row['primary_color'] ?? '#11172f',
        ':accent_color' => $row['accent_color'] ?? '#c9a227',
        ':open_time' => $row['open_time'] ?? '08:00',
        ':close_time' => $row['close_time'] ?? '20:00',
        ':lunch_start' => $row['lunch_start'] ?? '12:00',
        ':lunch_end' => $row['lunch_end'] ?? '13:00',
        ':slot_minutes' => (int)($row['slot_minutes'] ?? 60),
        ':lunch_enabled' => (int)($row['lunch_enabled'] ?? 1),
        ':commission_rate' => (float)($row['commission_rate'] ?? 0.4),
        ':setup_completed' => (int)($row['setup_completed'] ?? 0),
        ':mp_public_key' => $row['mp_public_key'] ?? null,
        ':mp_access_token' => $row['mp_access_token'] ?? null,
        ':wa_phone_number_id' => $row['wa_phone_number_id'] ?? null,
        ':wa_access_token' => $row['wa_access_token'] ?? null,
        ':loyalty_prata_min' => (int)($row['loyalty_prata_min'] ?? 100),
        ':loyalty_ouro_min' => (int)($row['loyalty_ouro_min'] ?? 200),
        ':loyalty_points_per_real' => (float)($row['loyalty_points_per_real'] ?? 1),
        ':loyalty_rewards_json' => isset($row['loyalty_rewards_json'])
            ? (is_string($row['loyalty_rewards_json']) ? $row['loyalty_rewards_json'] : json_encode($row['loyalty_rewards_json'], JSON_UNESCAPED_UNICODE))
            : '[]',
    ]);
}

function db_insert_row(PDO $pdo, string $logical, string $safeTable, array $row): void
{
    if (db_is_json_blob_table($logical)) {
        $id = (int)($row['id'] ?? 0);
        $payload = $row;
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '` (id, data_json) VALUES (:id, :data)');
        $stmt->execute([
            ':id' => $id > 0 ? $id : null,
            ':data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        return;
    }

    if ($logical === 'users') {
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '`
            (id, name, email, phone, password, role, avatar, birth_date, active, created_at)
            VALUES (:id, :name, :email, :phone, :password, :role, :avatar, :birth_date, :active, :created_at)');
        $birth = $row['birth_date'] ?? null;
        if (is_string($birth) && $birth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
            $ts = strtotime($birth);
            $birth = $ts ? date('Y-m-d', $ts) : null;
        }
        if ($birth === '') {
            $birth = null;
        }
        $stmt->execute([
            ':id' => (int)($row['id'] ?? 0) ?: null,
            ':name' => (string)($row['name'] ?? ''),
            ':email' => $row['email'] ?? null,
            ':phone' => $row['phone'] ?? null,
            ':password' => (string)($row['password'] ?? ''),
            ':role' => (string)($row['role'] ?? 'cliente'),
            ':avatar' => $row['avatar'] ?? null,
            ':birth_date' => $birth,
            ':active' => (int)($row['active'] ?? 1),
            ':created_at' => isset($row['created_at']) ? date('Y-m-d H:i:s', strtotime((string)$row['created_at']) ?: time()) : date('Y-m-d H:i:s'),
        ]);
        return;
    }

    if ($logical === 'services') {
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '`
            (id, name, description, duration_min, price, active, sort_order, image_url)
            VALUES (:id, :name, :description, :duration_min, :price, :active, :sort_order, :image_url)');
        $stmt->execute([
            ':id' => (int)($row['id'] ?? 0) ?: null,
            ':name' => (string)($row['name'] ?? ''),
            ':description' => $row['description'] ?? null,
            ':duration_min' => (int)($row['duration_min'] ?? 30),
            ':price' => (float)($row['price'] ?? 0),
            ':active' => (int)($row['active'] ?? 1),
            ':sort_order' => (int)($row['sort_order'] ?? 0),
            ':image_url' => $row['image_url'] ?? null,
        ]);
        return;
    }

    if ($logical === 'stock') {
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '`
            (id, name, sku, qty, min_qty, cost, price, active)
            VALUES (:id, :name, :sku, :qty, :min_qty, :cost, :price, :active)');
        $stmt->execute([
            ':id' => (int)($row['id'] ?? 0) ?: null,
            ':name' => (string)($row['name'] ?? ''),
            ':sku' => $row['sku'] ?? null,
            ':qty' => (int)($row['qty'] ?? 0),
            ':min_qty' => (int)($row['min_qty'] ?? 0),
            ':cost' => (float)($row['cost'] ?? 0),
            ':price' => (float)($row['price'] ?? 0),
            ':active' => (int)($row['active'] ?? 1),
        ]);
        return;
    }

    if ($logical === 'stock_history') {
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '`
            (id, product_id, product_name, qty, delta, type, user_id, note, created_at)
            VALUES (:id, :product_id, :product_name, :qty, :delta, :type, :user_id, :note, :created_at)');
        $created = (string)($row['date'] ?? $row['created_at'] ?? date('Y-m-d H:i:s'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $created)) {
            $created = date('Y-m-d H:i:s');
        }
        $stmt->execute([
            ':id' => (int)($row['id'] ?? 0) ?: null,
            ':product_id' => (int)($row['product_id'] ?? 0),
            ':product_name' => (string)($row['product_name'] ?? ''),
            ':qty' => (int)($row['qty'] ?? 0),
            ':delta' => (int)($row['delta'] ?? 0),
            ':type' => (string)($row['type'] ?? ''),
            ':user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
            ':note' => $row['note'] ?? null,
            ':created_at' => $created,
        ]);
        return;
    }

    if ($logical === 'appointments') {
        $serviceIds = $row['service_ids'] ?? null;
        $products = $row['products'] ?? [];
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '`
            (id, client_id, client_name, client_phone, barber_id, service_id, service_ids, products_json, date, time, status, notes, price, created_at)
            VALUES (:id, :client_id, :client_name, :client_phone, :barber_id, :service_id, :service_ids, :products_json, :date, :time, :status, :notes, :price, :created_at)');
        $stmt->execute([
            ':id' => (int)($row['id'] ?? 0) ?: null,
            ':client_id' => isset($row['client_id']) && $row['client_id'] !== null && $row['client_id'] !== '' ? (int)$row['client_id'] : null,
            ':client_name' => $row['client_name'] ?? null,
            ':client_phone' => $row['client_phone'] ?? null,
            ':barber_id' => (int)($row['barber_id'] ?? 0),
            ':service_id' => isset($row['service_id']) ? (int)$row['service_id'] : null,
            ':service_ids' => $serviceIds ? json_encode(array_values($serviceIds), JSON_UNESCAPED_UNICODE) : null,
            ':products_json' => $products ? json_encode(array_values($products), JSON_UNESCAPED_UNICODE) : null,
            ':date' => (string)($row['date'] ?? date('Y-m-d')),
            ':time' => (string)($row['time'] ?? '09:00'),
            ':status' => (string)($row['status'] ?? 'agendado'),
            ':notes' => $row['notes'] ?? null,
            ':price' => (float)($row['price'] ?? 0),
            ':created_at' => isset($row['created_at']) ? date('Y-m-d H:i:s', strtotime((string)$row['created_at']) ?: time()) : date('Y-m-d H:i:s'),
        ]);
        return;
    }

    if ($logical === 'cash_entries') {
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '`
            (id, type, category, description, amount, appointment_id, created_by, created_at, updated_at)
            VALUES (:id, :type, :category, :description, :amount, :appointment_id, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            ':id' => (int)($row['id'] ?? 0) ?: null,
            ':type' => (string)($row['type'] ?? 'entrada'),
            ':category' => (string)($row['category'] ?? 'servico'),
            ':description' => (string)($row['description'] ?? ''),
            ':amount' => (float)($row['amount'] ?? 0),
            ':appointment_id' => isset($row['appointment_id']) ? (int)$row['appointment_id'] : null,
            ':created_by' => isset($row['created_by']) ? (int)$row['created_by'] : null,
            ':created_at' => isset($row['created_at']) ? date('Y-m-d H:i:s', strtotime((string)$row['created_at']) ?: time()) : date('Y-m-d H:i:s'),
            ':updated_at' => isset($row['updated_at']) ? date('Y-m-d H:i:s', strtotime((string)$row['updated_at']) ?: time()) : null,
        ]);
        return;
    }

    if ($logical === 'subscriptions') {
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '`
            (id, client_id, client_phone, plan_id, status, usage_count, started_at, renews_at, card_id, mp_payment_id, created_at)
            VALUES (:id, :client_id, :client_phone, :plan_id, :status, :usage_count, :started_at, :renews_at, :card_id, :mp_payment_id, :created_at)');
        $stmt->execute([
            ':id' => (int)($row['id'] ?? 0) ?: null,
            ':client_id' => (int)($row['client_id'] ?? 0),
            ':client_phone' => $row['client_phone'] ?? null,
            ':plan_id' => (int)($row['plan_id'] ?? 0),
            ':status' => (string)($row['status'] ?? 'active'),
            ':usage_count' => (int)($row['usage_count'] ?? 0),
            ':started_at' => $row['started_at'] ?: null,
            ':renews_at' => $row['renews_at'] ?: null,
            ':card_id' => isset($row['card_id']) && $row['card_id'] ? (int)$row['card_id'] : null,
            ':mp_payment_id' => $row['mp_payment_id'] ?? null,
            ':created_at' => isset($row['created_at']) && $row['created_at']
                ? date('Y-m-d H:i:s', strtotime((string)$row['created_at']) ?: time())
                : date('Y-m-d H:i:s'),
        ]);
        return;
    }

    if ($logical === 'charges') {
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '`
            (id, client_id, plan_id, plan_name, amount, card_last4, card_brand, date, status, mp_payment_id, mp_status)
            VALUES (:id, :client_id, :plan_id, :plan_name, :amount, :card_last4, :card_brand, :date, :status, :mp_payment_id, :mp_status)');
        $stmt->execute([
            ':id' => (int)($row['id'] ?? 0) ?: null,
            ':client_id' => (int)($row['client_id'] ?? 0),
            ':plan_id' => (int)($row['plan_id'] ?? 0),
            ':plan_name' => $row['plan_name'] ?? null,
            ':amount' => (float)($row['amount'] ?? 0),
            ':card_last4' => $row['card_last4'] ?? null,
            ':card_brand' => $row['card_brand'] ?? null,
            ':date' => (string)($row['date'] ?? date('Y-m-d')),
            ':status' => (string)($row['status'] ?? 'proxima'),
            ':mp_payment_id' => $row['mp_payment_id'] ?? null,
            ':mp_status' => $row['mp_status'] ?? null,
        ]);
        return;
    }

    if ($logical === 'plans') {
        $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '`
            (id, name, price, `interval`, headline, description, benefit_label, usage_limit, images, active)
            VALUES (:id, :name, :price, :interval, :headline, :description, :benefit_label, :usage_limit, :images, :active)');
        $stmt->execute([
            ':id' => (int)($row['id'] ?? 0) ?: null,
            ':name' => (string)($row['name'] ?? ''),
            ':price' => (float)($row['price'] ?? 0),
            ':interval' => (string)($row['interval'] ?? 'Mês'),
            ':headline' => $row['headline'] ?? null,
            ':description' => $row['description'] ?? null,
            ':benefit_label' => $row['benefit_label'] ?? null,
            ':usage_limit' => $row['usage_limit'] ?? null,
            ':images' => json_encode($row['images'] ?? ['', '', ''], JSON_UNESCAPED_UNICODE),
            ':active' => (int)($row['active'] ?? 1),
        ]);
        return;
    }

    // Fallback: guarda JSON bruto numa coluna data_json se existir
    $stmt = $pdo->prepare('INSERT INTO `' . $safeTable . '` (id, data_json) VALUES (:id, :data)');
    $stmt->execute([
        ':id' => (int)($row['id'] ?? 0) ?: null,
        ':data' => json_encode($row, JSON_UNESCAPED_UNICODE),
    ]);
}

function db_next_id(string $logical): int
{
    $pdo = db_pdo();
    if (!$pdo) {
        return 1;
    }
    $table = db_physical_table($logical);
    $safe = str_replace('`', '``', $table);
    $stmt = $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 AS n FROM `' . $safe . '`');
    $n = $stmt ? (int)$stmt->fetchColumn() : 1;
    return max(1, $n);
}

function db_tables_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
        return $stmt && $stmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        return false;
    }
}

function db_install_schema_if_needed(): void
{
    $pdo = db_pdo();
    if (!$pdo) {
        return;
    }
    if (!db_tables_ready($pdo)) {
        $schemaFile = dirname(__DIR__) . '/sql/barbearia_schema.sql';
        if (!is_file($schemaFile)) {
            return;
        }
        $sql = file_get_contents($schemaFile);
        if ($sql === false || $sql === '') {
            return;
        }
        $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
        $parts = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (str_starts_with(strtoupper($part), 'SET ')) {
                $pdo->exec($part);
                continue;
            }
            $pdo->exec($part);
        }
    }
    // Colunas extras em instalações já criadas
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM agendamentos LIKE 'products_json'")->fetch();
        if (!$cols) {
            $pdo->exec('ALTER TABLE agendamentos ADD COLUMN products_json JSON NULL AFTER service_ids');
        }
    } catch (Throwable $e) {
        // ignore
    }
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'birth_date'")->fetch();
        if (!$cols) {
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN birth_date DATE NULL AFTER avatar');
        }
    } catch (Throwable $e) {
        // ignore
    }
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM configuracoes LIKE 'loyalty_prata_min'")->fetch();
        if (!$cols) {
            $pdo->exec("ALTER TABLE configuracoes
                ADD COLUMN loyalty_prata_min INT NOT NULL DEFAULT 100,
                ADD COLUMN loyalty_ouro_min INT NOT NULL DEFAULT 200,
                ADD COLUMN loyalty_points_per_real DECIMAL(6,2) NOT NULL DEFAULT 1.00,
                ADD COLUMN loyalty_rewards_json JSON NULL");
        }
    } catch (Throwable $e) {
        // ignore
    }

    db_migrate_blob_table_to_columns($pdo, 'assinaturas', [
        'client_id' => 'INT UNSIGNED NULL',
        'client_phone' => 'VARCHAR(32) NULL',
        'plan_id' => 'INT UNSIGNED NULL',
        'status' => 'VARCHAR(20) NULL',
        'usage_count' => 'INT NULL',
        'started_at' => 'DATE NULL',
        'renews_at' => 'DATE NULL',
        'card_id' => 'INT UNSIGNED NULL',
        'mp_payment_id' => 'VARCHAR(64) NULL',
    ]);
    db_migrate_blob_table_to_columns($pdo, 'cobrancas', [
        'client_id' => 'INT UNSIGNED NULL',
        'plan_id' => 'INT UNSIGNED NULL',
        'plan_name' => 'VARCHAR(120) NULL',
        'amount' => 'DECIMAL(10,2) NULL',
        'card_last4' => 'VARCHAR(4) NULL',
        'card_brand' => 'VARCHAR(40) NULL',
        'date' => 'DATE NULL',
        'status' => 'VARCHAR(20) NULL',
        'mp_payment_id' => 'VARCHAR(64) NULL',
        'mp_status' => 'VARCHAR(40) NULL',
    ]);
}

/**
 * Migra uma tabela que hoje só tem (id, data_json, created_at) para ter colunas
 * reais também, preenchidas a partir do data_json existente. NÃO remove a coluna
 * data_json nem falha a instalação inteira se algo der errado — aditivo e seguro
 * de re-executar. Feito assim (em vez de DROP COLUMN data_json) porque esta
 * migração não pôde ser testada contra um MySQL real antes de ser publicada;
 * o dono pode confirmar os dados nas colunas novas e remover data_json manualmente
 * depois, se quiser.
 */
function db_migrate_blob_table_to_columns(PDO $pdo, string $table, array $newColumns): void
{
    $safe = str_replace('`', '``', $table);
    try {
        $exists = $pdo->query("SHOW COLUMNS FROM `$safe` LIKE 'data_json'")->fetch();
        if (!$exists) {
            return; // tabela não existe ainda, ou já não tem data_json (já migrada e limpa)
        }
        $hasFirstNewCol = $pdo->query("SHOW COLUMNS FROM `$safe` LIKE '" . array_key_first($newColumns) . "'")->fetch();
        if ($hasFirstNewCol) {
            return; // já migrada
        }

        $alter = "ALTER TABLE `$safe` " . implode(', ', array_map(
            fn($col, $def) => "ADD COLUMN `$col` $def",
            array_keys($newColumns),
            $newColumns
        ));
        $pdo->exec($alter);

        $rows = $pdo->query("SELECT id, data_json FROM `$safe`")->fetchAll();
        $setClause = implode(', ', array_map(fn($col) => "`$col` = :$col", array_keys($newColumns)));
        $stmt = $pdo->prepare("UPDATE `$safe` SET $setClause WHERE id = :id");
        foreach ($rows as $row) {
            $data = json_decode((string)($row['data_json'] ?? '{}'), true);
            if (!is_array($data)) {
                $data = [];
            }
            $params = ['id' => (int)$row['id']];
            foreach (array_keys($newColumns) as $col) {
                $val = $data[$col] ?? null;
                $params[$col] = $val === '' ? null : $val;
            }
            try {
                $stmt->execute($params);
            } catch (Throwable $e) {
                error_log("Migração $table #$row[id]: " . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log("Migração $table: " . $e->getMessage());
    }
}

/**
 * Importa JSON local → MySQL (uma vez). Só roda se usuarios estiver vazio.
 */
function db_migrate_from_json_if_empty(): void
{
    $pdo = db_pdo();
    if (!$pdo || !db_tables_ready($pdo)) {
        return;
    }
    $count = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $map = db_table_map();
    foreach (array_keys($map) as $logical) {
        $path = data_dir() . '/' . $logical . '.json';
        if (!is_file($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        $rows = json_decode($raw ?: '[]', true);
        if (!is_array($rows) || !$rows) {
            continue;
        }
        try {
            db_replace_all($logical, $rows);
        } catch (Throwable $e) {
            error_log('Migrate ' . $logical . ': ' . $e->getMessage());
        }
    }
}
