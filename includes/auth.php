<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $found = find_user_by_id((int) $_SESSION['user_id']);
        $user = ($found && !empty($found['active'])) ? $found : null;
        if (!$user) {
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function login_url_for(?string $role = null): string
{
    return match ($role) {
        'dono' => url('dono/login.php'),
        'barbeiro' => url('barbeiro/login.php'),
        default => url('login.php'),
    };
}

function require_role(array $roles): array
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        flash('danger', 'Faça login para continuar.');
        $target = count($roles) === 1 ? login_url_for($roles[0]) : login_url_for(null);
        redirect($target);
    }

    // Primeiro acesso: forçar setup comercial
    if (($user['role'] ?? '') === 'dono' && in_array('dono', $roles, true)) {
        $setupDone = !empty(settings()['setup_completed']);
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $onSetup = str_ends_with($script, '/dono/setup.php');
        if (!$setupDone && !$onSetup) {
            redirect(url('dono/setup.php'));
        }
    }

    return $user;
}

function login_is_locked(): bool
{
    $until = (int)($_SESSION['login_locked_until'] ?? 0);
    return $until > time();
}

function login_register_failure(): void
{
    $attempts = (int)($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_attempts'] = $attempts;
    if ($attempts >= 5) {
        $_SESSION['login_locked_until'] = time() + 15 * 60;
        $_SESSION['login_attempts'] = 0;
    }
}

function attempt_login(string $email, string $password, ?string $expectedRole = null): ?array
{
    if (login_is_locked()) {
        return null;
    }
    $user = find_user_by_email(trim($email));
    if (!$user || empty($user['active']) || !in_array($user['role'], ['dono', 'barbeiro'], true)) {
        login_register_failure();
        return null;
    }
    if ($expectedRole !== null && ($user['role'] ?? '') !== $expectedRole) {
        login_register_failure();
        return null;
    }
    if (!password_verify($password, (string) $user['password'])) {
        login_register_failure();
        return null;
    }
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
    return $user;
}

function panel_home_for(string $role): string
{
    return match ($role) {
        'dono' => url('dono/'),
        'barbeiro' => url('barbeiro/'),
        default => url('cliente/'),
    };
}

function find_client_by_phone(string $phone): ?array
{
    $phone = preg_replace('/\D+/', '', $phone);
    foreach (store_read('users') as $u) {
        if (($u['role'] ?? '') === 'cliente' && preg_replace('/\D+/', '', (string)($u['phone'] ?? '')) === $phone) {
            return $u;
        }
    }
    return null;
}

function current_client(): ?array
{
    if (empty($_SESSION['client_id'])) {
        return null;
    }
    $u = find_user_by_id((int) $_SESSION['client_id']);
    if (!$u || ($u['role'] ?? '') !== 'cliente' || empty($u['active'])) {
        unset($_SESSION['client_id'], $_SESSION['client_phone'], $_SESSION['client_name']);
        return null;
    }
    return $u;
}

function login_client(array $user): void
{
    $_SESSION['client_id'] = (int) $user['id'];
    $_SESSION['client_phone'] = preg_replace('/\D+/', '', (string) ($user['phone'] ?? ''));
    $_SESSION['client_name'] = $user['name'] ?? '';
}

function logout_client(): void
{
    unset($_SESSION['client_id'], $_SESSION['client_phone'], $_SESSION['client_name']);
}

function attempt_client_login(string $phone, string $password): ?array
{
    $phone = preg_replace('/\D+/', '', $phone);
    $password = trim($password);

    // Modo demo: qualquer número + qualquer senha libera o acesso
    if (defined('CLIENT_DEMO_OPEN') && CLIENT_DEMO_OPEN) {
        if ($phone === '' || $password === '') {
            return null;
        }
        $user = find_client_by_phone($phone);
        if (!$user) {
            $user = save_user([
                'name' => 'Cliente Demo',
                'email' => null,
                'phone' => $phone,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'cliente',
                'avatar' => null,
                'active' => 1,
            ]);
        }
        return $user;
    }

    $user = find_client_by_phone($phone);
    if (!$user || empty($user['password'])) {
        return null;
    }
    if (!password_verify($password, (string) $user['password'])) {
        return null;
    }
    return $user;
}
