<?php
/**
 * Front controller: /{slug}/... → cliente/...
 * Uso local: php -S localhost:8080 router.php
 * Produção (Apache): .htaccess encaminha paths inexistentes para cá.
 */
declare(strict_types=1);

$uriPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$uriPath = rawurldecode($uriPath);
$uriPath = str_replace('\\', '/', $uriPath);
if ($uriPath === '') {
    $uriPath = '/';
}

$root = __DIR__;
$filePath = $root . $uriPath;

// Raiz: deixa o index.php do projeto responder
if ($uriPath === '/') {
    return false;
}

// Arquivo estático ou pasta real existente: deixa o servidor servir
if ($uriPath !== '/' && file_exists($filePath)) {
    if (is_file($filePath)) {
        return false;
    }
    // diretório com index — servidor built-in trata; Apache já cobriu com -d
    if (is_dir($filePath) && is_file(rtrim($filePath, '/\\') . '/index.php')) {
        return false;
    }
}

// Marca rota pública e SCRIPT_NAME neutro antes do bootstrap
// (o server built-in coloca o path pedido em SCRIPT_NAME, ex.: /{slug}/...)
$_SERVER['BARBAFLOW_SLUG_ROUTE'] = '1';
$_SERVER['SCRIPT_NAME'] = '/router.php';

require_once $root . '/config/config.php';

$slug = shop_slug();
$base = base_path();
$basePrefix = $base === '' ? '' : $base;

// Redirect /cliente/... → /{slug}/...
$clientePrefix = $basePrefix . '/cliente';
if ($uriPath === $clientePrefix || str_starts_with($uriPath, $clientePrefix . '/')) {
    unset($_SERVER['BARBAFLOW_SLUG_ROUTE']);
    $rest = substr($uriPath, strlen($clientePrefix));
    if ($rest === false || $rest === '' || $rest === '/') {
        $target = client_public_url('');
    } else {
        $target = client_public_url(ltrim($rest, '/'));
    }
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($qs !== '') {
        $target .= (str_contains($target, '?') ? '&' : '?') . $qs;
    }
    header('Location: ' . $target, true, 302);
    exit;
}

// /{slug}/... → include cliente/...
$slugPrefix = $basePrefix . '/' . $slug;
if ($uriPath === $slugPrefix || $uriPath === $slugPrefix . '/' || str_starts_with($uriPath, $slugPrefix . '/')) {
    $rest = substr($uriPath, strlen($slugPrefix));
    if ($rest === false || $rest === '' || $rest === '/') {
        $rest = '/index.php';
    }
    $candidate = $root . '/cliente' . $rest;
    if (is_dir($candidate)) {
        $candidate = rtrim($candidate, '/\\') . '/index.php';
        $rest = rtrim($rest, '/') . '/index.php';
    }
    if (!is_file($candidate)) {
        http_response_code(404);
        echo 'Página não encontrada.';
        exit;
    }

    $_SERVER['SCRIPT_NAME'] = $basePrefix . '/cliente' . $rest;
    $_SERVER['SCRIPT_FILENAME'] = $candidate;
    require $candidate;
    exit;
}

unset($_SERVER['BARBAFLOW_SLUG_ROUTE']);

http_response_code(404);
echo 'Página não encontrada.';
exit;
