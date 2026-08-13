<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $ok = $sent !== '' && hash_equals(csrf_token(), $sent);
    if ($ok) {
        return;
    }
    http_response_code(419);
    if (str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Token de segurança inválido. Recarregue a página.']);
        exit;
    }
    flash('danger', 'Sessão expirada ou token inválido. Tente novamente.');
    $ref = $_SERVER['HTTP_REFERER'] ?? url('login.php');
    redirect($ref);
}

function money(float|int|string $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function normalize_time(?string $time, string $fallback = '09:00'): string
{
    $time = trim((string) $time);
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time;
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return substr($time, 0, 5);
    }
    return $fallback;
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** Token fixo pra chamar scripts/lembretes.php via URL (cron de hospedagem compartilhada). */
function cron_secret(): string
{
    $s = settings();
    $token = trim((string)($s['cron_secret'] ?? ''));
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        save_settings(['cron_secret' => $token]);
    }
    return $token;
}

function settings(bool $reload = false): array
{
    static $settings = null;
    if ($reload) {
        $settings = null;
    }
    if ($settings === null) {
        $rows = store_read('settings');
        $settings = $rows[0] ?? [];
    }
    return $settings;
}

/**
 * Garante URL absoluta para links externos (Instagram, Maps…).
 * Mantém o link salvo; só remove parâmetros de compartilhamento do Instagram (igsh),
 * que costumam expirar e abrir página inexistente.
 */
function normalize_external_url(string $raw, string $kind = ''): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    // Só @usuario ou usuario → perfil Instagram
    if ($kind === 'instagram' && preg_match('/^@?([A-Za-z0-9._]{1,30})$/', $raw, $m)) {
        return 'https://www.instagram.com/' . $m[1] . '/';
    }

    // Já é URL completa
    if (!preg_match('#^https?://#i', $raw)) {
        $raw = 'https://' . ltrim($raw, '/');
    }

    if ($kind === 'instagram' && stripos($raw, 'instagram.com') !== false) {
        $parts = parse_url($raw);
        $path = trim((string)($parts['path'] ?? '/'), '/');
        // pega só o username do path (ignora /reel/ /p/ etc. se for perfil simples)
        $segments = $path !== '' ? explode('/', $path) : [];
        $user = $segments[0] ?? '';
        if ($user !== '' && !in_array(strtolower($user), ['p', 'reel', 'stories', 'explore', 'direct'], true)) {
            return 'https://www.instagram.com/' . $user . '/';
        }
    }

    return $raw;
}

function reserved_path_segments(): array
{
    return [
        'cliente', 'dono', 'barbeiro', 'painel', 'api', 'financeiro', 'marketing', 'fidelidade',
        'assets', 'uploads', 'data', 'scratch', 'config', 'includes', 'vendor', 'login', 'router',
    ];
}

/** Gera slug de URL a partir do nome da barbearia. */
function slugify(string $name): string
{
    $s = trim($name);
    if ($s === '') {
        return 'barbearia';
    }
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') {
            $s = $t;
        }
    }
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
        'Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','Ä'=>'a',
        'É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
        'Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i',
        'Ó'=>'o','Ò'=>'o','Õ'=>'o','Ô'=>'o','Ö'=>'o',
        'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u',
        'Ç'=>'c','Ñ'=>'n',
    ];
    $s = strtr($s, $map);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'barbearia';
    }
    if (in_array($s, reserved_path_segments(), true)) {
        $s = 'loja-' . $s;
    }
    return $s;
}

function shop_slug(): string
{
    $slug = trim((string)(settings()['slug'] ?? ''));
    if ($slug !== '' && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        if (!in_array($slug, reserved_path_segments(), true)) {
            return $slug;
        }
    }
    return slugify(shop_brand_name());
}

/** URL pública do app do cliente: /{slug}/... */
function client_public_url(string $path = ''): string
{
    $base = base_path();
    $slug = shop_slug();
    $path = ltrim($path, '/');

    if ($path === 'cliente' || str_starts_with($path, 'cliente?')) {
        $path = ltrim(substr($path, strlen('cliente')), '/');
    } elseif (str_starts_with($path, 'cliente/')) {
        $path = substr($path, strlen('cliente/'));
    }

    $prefix = ($base === '' ? '' : $base) . '/' . $slug;
    if ($path === '') {
        return $prefix . '/';
    }
    return $prefix . '/' . $path;
}

function absolute_url(string $pathOrUrl): string
{
    if (preg_match('#^https?://#i', $pathOrUrl)) {
        return $pathOrUrl;
    }
    $path = str_starts_with($pathOrUrl, '/') ? $pathOrUrl : '/' . $pathOrUrl;
    $app = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    if ($app !== '') {
        return $app . $path;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host . $path;
}

function client_app_absolute_url(): string
{
    return absolute_url(client_public_url(''));
}

/** Se a URL ainda for /cliente/..., redireciona para /{slug}/... */
function ensure_client_slug_url(): void
{
    // Rota pública /{slug}/ já reescrita pelo router — não redirecionar
    if (!empty($_SERVER['BARBAFLOW_SLUG_ROUTE'])) {
        return;
    }

    $uri = str_replace('\\', '/', (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''));
    $base = base_path();
    $clientePrefix = ($base === '' ? '' : $base) . '/cliente';

    if ($uri !== $clientePrefix && !str_starts_with($uri, $clientePrefix . '/')) {
        return;
    }

    $rest = substr($uri, strlen($clientePrefix));
    if ($rest === false || $rest === '' || $rest === '/') {
        $target = client_public_url('');
    } else {
        $target = client_public_url(ltrim($rest, '/'));
    }
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($qs !== '') {
        $target .= (str_contains($target, '?') ? '&' : '?') . $qs;
    }
    redirect($target);
}

function base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($script));

    // Sobe pastas até sair de cliente/dono/barbeiro/painel/api/financeiro/etc.
    // Também sobe o segmento do slug público (/{slug}/...).
    $staffRoots = ['cliente', 'dono', 'barbeiro', 'painel', 'api', 'financeiro', 'marketing', 'fidelidade'];
    $shopSlug = null;
    while ($dir !== '' && $dir !== '/' && $dir !== '\\' && $dir !== '.') {
        $base = basename($dir);
        $isStaff = in_array($base, $staffRoots, true);
        if (!$isStaff) {
            if ($shopSlug === null) {
                $shopSlug = function_exists('shop_slug') ? shop_slug() : '';
            }
            if ($shopSlug === '' || $base !== $shopSlug) {
                break;
            }
        }
        $parent = str_replace('\\', '/', dirname($dir));
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    if ($dir === '\\' || $dir === '.' || $dir === '/') {
        $dir = '';
    }

    return rtrim($dir, '/');
}

function url(string $path = ''): string
{
    $base = base_path();
    $path = ltrim($path, '/');

    // App do cliente público usa /{slug}/ em vez de /cliente/
    if ($path === 'cliente' || str_starts_with($path, 'cliente/') || str_starts_with($path, 'cliente?')) {
        return client_public_url($path);
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

function media_url(?string $path): string
{
    if (!$path) {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return url(ltrim($path, '/'));
}

/**
 * Marca fixa do software (painel / login staff).
 * Sempre lê das constantes — nunca de settings/Configurações.
 */
function product_name(): string
{
    return (defined('PRODUCT_NAME') && PRODUCT_NAME !== '')
        ? PRODUCT_NAME
        : 'Barba Flow';
}

function product_logo_path(): string
{
    $path = (defined('PRODUCT_LOGO') && PRODUCT_LOGO !== '')
        ? PRODUCT_LOGO
        : 'assets/img/logo-barba-flow.png';
    // Garante que uploads da barbearia nunca substituam a marca do produto
    if (str_starts_with(str_replace('\\', '/', $path), 'uploads/')) {
        return 'assets/img/logo-barba-flow.png';
    }
    return $path;
}

/** Marca da barbearia — só app do cliente (Configurações). */
function shop_brand_name(): string
{
    $name = trim((string)(settings()['shop_name'] ?? ''));
    return $name !== '' ? $name : 'Barbearia';
}

function shop_logo_path(): string
{
    return trim((string)(settings()['logo_url'] ?? ''));
}

function str_cut(string $value, int $start, int $length = 1): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length);
    }
    return substr($value, $start, $length);
}

function str_upper(string $value): string
{
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($value, 'UTF-8');
    }
    return strtoupper($value);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = str_cut($parts[0] ?? '', 0, 1);
    $last = str_cut($parts[count($parts) - 1] ?? '', 0, 1);
    return str_upper($first . ($last !== $first ? $last : ''));
}

function status_label(string $status): string
{
    return match ($status) {
        'agendado' => 'Agendado',
        'confirmado' => 'Confirmado',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'faltou' => 'Faltou',
        default => $status,
    };
}

function status_badge(string $status): string
{
    $class = match ($status) {
        'agendado' => 'warning',
        'confirmado' => 'info',
        'concluido' => 'success',
        'cancelado' => 'secondary',
        'faltou' => 'danger',
        default => 'secondary',
    };
    return '<span class="badge text-bg-' . $class . '">' . e(status_label($status)) . '</span>';
}

function active_services(): array
{
    $rows = array_filter(store_read('services'), fn($s) => !empty($s['active']));
    usort($rows, fn($a, $b) => ((int)$a['sort_order'] <=> (int)$b['sort_order']) ?: strcmp($a['name'], $b['name']));
    return array_values($rows);
}

function active_barbers(): array
{
    $rows = array_filter(store_read('users'), fn($u) => ($u['role'] ?? '') === 'barbeiro' && !empty($u['active']));
    usort($rows, fn($a, $b) => strcmp($a['name'], $b['name']));
    return array_values($rows);
}

function services_by_ids(array $ids): array
{
    $ids = array_map('intval', $ids);
    $out = [];
    foreach (store_read('services') as $s) {
        if (in_array((int)$s['id'], $ids, true) && !empty($s['active'])) {
            $out[] = $s;
        }
    }
    return $out;
}

function appointments_enriched(?callable $filter = null): array
{
    $services = [];
    foreach (store_read('services') as $s) {
        $services[(int)$s['id']] = $s;
    }
    $users = [];
    foreach (store_read('users') as $u) {
        $users[(int)$u['id']] = $u;
    }

    $rows = store_read('appointments');
    $out = [];
    foreach ($rows as $a) {
        $ids = [];
        if (!empty($a['service_ids']) && is_array($a['service_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $a['service_ids'])));
        }
        if (!$ids && !empty($a['service_id'])) {
            $ids = [(int)$a['service_id']];
        }
        $names = [];
        $duration = 0;
        foreach ($ids as $sid) {
            $names[] = $services[$sid]['name'] ?? 'Serviço';
            $duration += (int)($services[$sid]['duration_min'] ?? 30);
        }
        $a['service_ids'] = $ids;
        $a['service_name'] = $names ? implode(' + ', $names) : 'Serviço';
        $a['barber_name'] = $users[(int)$a['barber_id']]['name'] ?? 'Barbeiro';
        $a['duration_min'] = max(15, $duration ?: 30);
        if ($filter && !$filter($a)) {
            continue;
        }
        $out[] = $a;
    }
    usort($out, function ($a, $b) {
        $d = strcmp($a['date'], $b['date']);
        return $d !== 0 ? $d : strcmp($a['time'], $b['time']);
    });
    return $out;
}

function available_slots(int $barberId, string $date, int $durationMin = 60): array
{
    $cfg = settings();

    $blocked = trim((string)($cfg['blocked_days'] ?? ''));
    if ($blocked !== '') {
        $blockedList = explode(',', $blocked);
        if (in_array($date, $blockedList, true)) {
            return [];
        }
    }

    $barber = find_user_by_id($barberId);
    if (!$barber || ($barber['role'] ?? '') !== 'barbeiro') {
        return [];
    }

    // Verifica dia de trabalho
    $dayOfWeek = (int)date('w', strtotime($date));
    $workDays = $barber['work_days'] ?? [1,2,3,4,5,6];
    if (is_string($workDays)) {
        $decoded = json_decode($workDays, true);
        $workDays = is_array($decoded) ? $decoded : [1,2,3,4,5,6];
    }
    if (!is_array($workDays) || !in_array($dayOfWeek, $workDays, true)) {
        return [];
    }

    $open = normalize_time($barber['work_start'] ?? $cfg['open_time'] ?? '08:00', '08:00');
    $close = normalize_time($barber['work_end'] ?? $cfg['close_time'] ?? '20:00', '20:00');
    $step = max(15, (int) ($cfg['slot_minutes'] ?? 60)); // padrão 1 hora
    $lunchOn = !empty($cfg['lunch_enabled']);
    $lunchStart = normalize_time($barber['lunch_start'] ?? $cfg['lunch_start'] ?? '12:00', '12:00');
    $lunchEnd = normalize_time($barber['lunch_end'] ?? $cfg['lunch_end'] ?? '13:00', '13:00');

    $busy = appointments_enriched(function ($a) use ($barberId, $date) {
        return (int)$a['barber_id'] === $barberId
            && $a['date'] === $date
            && in_array($a['status'], ['agendado', 'confirmado', 'concluido'], true);
    });

    $slots = [];
    $start = strtotime($date . ' ' . $open);
    $end = strtotime($date . ' ' . $close);
    $lunchFrom = strtotime($date . ' ' . $lunchStart);
    $lunchTo = strtotime($date . ' ' . $lunchEnd);

    for ($t = $start; $t + ($durationMin * 60) <= $end; $t += $step * 60) {
        $slot = date('H:i', $t);
        $slotEnd = $t + ($durationMin * 60);

        // Bloqueia horário de almoço
        if ($lunchOn && $t < $lunchTo && $slotEnd > $lunchFrom) {
            continue;
        }

        $conflict = false;
        foreach ($busy as $b) {
            $bStart = strtotime($date . ' ' . $b['time']);
            $bEnd = $bStart + ((int)$b['duration_min'] * 60);
            if ($t < $bEnd && $slotEnd > $bStart) {
                $conflict = true;
                break;
            }
        }
        if ($conflict) {
            continue;
        }
        if ($date === date('Y-m-d') && $t < time()) {
            continue;
        }
        $slots[] = $slot;
    }
    return $slots;
}

/**
 * Cria ou atualiza cliente (role=cliente). Retorna o usuário salvo.
 */
function upsert_client(array $data): array
{
    $id = (int)($data['id'] ?? 0);
    $name = trim((string)($data['name'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string)($data['phone'] ?? ''));
    $birth = trim((string)($data['birth_date'] ?? ''));
    if ($birth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
        $ts = strtotime($birth);
        $birth = $ts ? date('Y-m-d', $ts) : '';
    }

    if ($id < 1) {
        $existing = find_client_by_phone($phone);
        if ($existing) {
            $id = (int)$existing['id'];
        }
    }

    if ($id > 0) {
        $cur = find_user_by_id($id);
        if (!$cur || ($cur['role'] ?? '') !== 'cliente') {
            throw new InvalidArgumentException('Cliente não encontrado.');
        }
        return save_user([
            'id' => $id,
            'name' => $name,
            'phone' => $phone,
            'birth_date' => $birth !== '' ? $birth : ($cur['birth_date'] ?? null),
            'email' => $cur['email'] ?? null,
            'password' => $cur['password'] ?? password_hash($phone, PASSWORD_DEFAULT),
            'role' => 'cliente',
            'active' => 1,
            'avatar' => $cur['avatar'] ?? null,
        ]);
    }

    return save_user([
        'name' => $name,
        'phone' => $phone,
        'birth_date' => $birth !== '' ? $birth : null,
        'email' => null,
        'password' => password_hash($phone, PASSWORD_DEFAULT),
        'role' => 'cliente',
        'active' => 1,
        'avatar' => null,
    ]);
}

/**
 * Cliente já tem horário no dia? (agendado/confirmado/concluído bloqueiam; cancelado/faltou liberam).
 */
function client_already_booked_on_date(int $clientId, string $date, string $phone = ''): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $phone = preg_replace('/\D+/', '', $phone);
    $blocking = ['agendado', 'confirmado', 'concluido'];

    foreach (store_read('appointments') as $a) {
        if (($a['date'] ?? '') !== $date) {
            continue;
        }
        if (!in_array((string)($a['status'] ?? ''), $blocking, true)) {
            continue;
        }
        $sameId = $clientId > 0 && (int)($a['client_id'] ?? 0) === $clientId;
        $samePhone = $phone !== ''
            && preg_replace('/\D+/', '', (string)($a['client_phone'] ?? '')) === $phone;
        if ($sameId || $samePhone) {
            return true;
        }
    }

    return false;
}

/**
 * Agenda serviço para um cliente. Retorna o agendamento ou mensagem de erro.
 * @return array|string
 */
function book_service_for_client(int $clientId, int $barberId, int $serviceId, string $date, string $time)
{
    $client = find_user_by_id($clientId);
    if (!$client || ($client['role'] ?? '') !== 'cliente') {
        return 'Cliente inválido.';
    }
    $barber = find_user_by_id($barberId);
    if (!$barber || ($barber['role'] ?? '') !== 'barbeiro' || empty($barber['active'])) {
        return 'Barbeiro inválido.';
    }
    $service = find_service($serviceId);
    if (!$service || empty($service['active'])) {
        return 'Serviço inválido.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return 'Data inválida.';
    }
    $time = normalize_time($time, '');
    if ($time === '') {
        return 'Horário inválido.';
    }

    $phone = preg_replace('/\D+/', '', (string)($client['phone'] ?? ''));
    if (client_already_booked_on_date($clientId, $date, $phone)) {
        return 'Este cliente já tem agendamento neste dia. Só é permitido um horário por dia.';
    }

    $duration = max(15, (int)($service['duration_min'] ?? 30));
    $slots = available_slots($barberId, $date, $duration);
    if (!in_array($time, $slots, true)) {
        return 'Horário indisponível para este barbeiro/serviço.';
    }

    return save_appointment([
        'client_id' => $clientId,
        'client_name' => $client['name'] ?? '',
        'client_phone' => preg_replace('/\D+/', '', (string)($client['phone'] ?? '')),
        'barber_id' => $barberId,
        'service_id' => $serviceId,
        'service_ids' => [$serviceId],
        'date' => $date,
        'time' => $time,
        'status' => 'agendado',
        'notes' => null,
        'price' => (float)($service['price'] ?? 0),
        'products' => [],
    ]);
}
