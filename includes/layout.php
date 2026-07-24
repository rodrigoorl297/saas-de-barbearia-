<?php
declare(strict_types=1);

function render_head(string $title, bool $client = false, ?string $bodyClass = null): void
{
    $brand = $client ? shop_brand_name() : product_name();
    $full = $title ? ($title . ' · ' . $brand) : $brand;
    $logo = $client ? shop_logo_path() : product_logo_path();
    if ($logo === '') {
        $logo = $client ? '' : product_logo_path();
    }
    $logoHref = $logo !== '' ? media_url($logo) : media_url(product_logo_path());
    $body = $bodyClass ?? ($client ? 'is-client' : 'is-admin');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="<?= $body === 'is-barber' ? '#0b0d12' : '#11172f' ?>">
  <title><?= e($full) ?></title>
  <link rel="icon" href="<?= e($logoHref) ?>" type="image/png">
  <link rel="apple-touch-icon" href="<?= e($logoHref) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700&display=swap" rel="stylesheet">
  <?php if (!$client && $body !== 'is-barber'): ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php endif; ?>
  <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e($body) ?>">
<?php
}

function render_flash_client(): void
{
    $flash = get_flash();
    if (!$flash) return;
    $type = $flash['type'] === 'danger' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success');
    echo '<div class="alert-as ' . e($type) . '">' . e($flash['message']) . '</div>';
}

function render_flash(): void
{
    $flash = get_flash();
    if (!$flash) return;
    echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show m-3" role="alert">'
        . e($flash['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

function render_scripts(bool $client = false): void
{
    echo '<script>window.__CSRF__=' . json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) . ';</script>';
    echo '<script src="' . e(url('assets/js/csrf.js')) . '"></script>';
    if (!$client) {
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo '<script src="' . e(url('assets/js/admin.js')) . '"></script>';
    }
    echo '</body></html>';
}

function client_shell_start(string $active = 'agendar'): void
{
    $shop = settings();
    $ig = normalize_external_url((string)($shop['instagram'] ?? ''), 'instagram');
    $maps = normalize_external_url((string)($shop['maps_url'] ?? ''));
    if ($maps === '' && !empty($shop['address'])) {
        $maps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string)$shop['address']);
    }
    ?>
<div class="app-container">
  <div class="header-app">
    <?php
      $headerLogo = shop_logo_path();
      $shopName = shop_brand_name();
    ?>
    <?php if ($headerLogo !== ''): ?>
      <a class="imagem-header imagem-header--logo" href="<?= e(url('cliente/')) ?>" title="<?= e($shopName) ?>">
        <img src="<?= e(media_url($headerLogo)) ?>" alt="<?= e($shopName) ?>">
      </a>
    <?php else: ?>
      <a class="imagem-header" href="<?= e(url('cliente/')) ?>" title="<?= e($shopName) ?>"><?= e(str_upper($shopName)) ?></a>
    <?php endif; ?>
    <div class="header-social">
      <?php if ($ig !== ''): ?>
        <a
          class="icon-link-header"
          href="<?= e($ig) ?>"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Abrir Instagram"
          title="Instagram"
          data-external="1"
        >
          <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm10 2H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm-5 3.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 0 1 12 7.5zm0 2A2.5 2.5 0 1 0 14.5 12 2.5 2.5 0 0 0 12 9.5zM17.5 6.75a1 1 0 1 1-1 1 1 1 0 0 1 1-1z"/></svg>
        </a>
      <?php endif; ?>
      <?php if ($maps !== ''): ?>
        <a
          class="icon-link-header"
          href="<?= e($maps) ?>"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Abrir Localização"
          title="Localização"
          data-external="1"
        >
          <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/></svg>
        </a>
      <?php endif; ?>
    </div>
  </div>
  <div class="conteudo-centralizado">
<?php
}

function client_shell_end(string $active = 'agendar'): void
{
    client_bottom_nav($active);
    echo '</div></div>';
    render_scripts(true);
}

function client_bottom_nav(string $active = 'agendar'): void
{
    // Ícones no estilo agendas.link: calendário | histórico (relógio+seta) | perfil
    $items = [
        'agendar' => [
            'cliente/',
            '<svg viewBox="0 0 448 512" fill="currentColor" width="24" height="24"><path d="M128 0c17.7 0 32 14.3 32 32V64H288V32c0-17.7 14.3-32 32-32s32 14.3 32 32V64h32c35.3 0 64 28.7 64 64v16 48V448c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V192 144 128C0 92.7 28.7 64 64 64h32V32c0-17.7 14.3-32 32-32zM64 192V448H384V192H64zm80 96c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H160c-8.8 0-16-7.2-16-16V288zm112 0c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V288z"/></svg>',
        ],
        'agendamentos' => [
            'cliente/agendamentos.php',
            '<svg viewBox="0 0 512 512" fill="currentColor" width="24" height="24"><path d="M256 0a256 256 0 1 1 0 512A256 256 0 1 1 256 0zM232 120V256c0 8 4 15.5 10.7 20l96 64c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2V120c0-13.3-10.7-24-24-24s-24 10.7-24 24z"/></svg>',
        ],
        'conta' => [
            'cliente/conta.php',
            '<svg viewBox="0 0 448 512" fill="currentColor" width="24" height="24"><path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"/></svg>',
        ],
    ];
    // Ícone do meio: histórico com seta circular (estilo agendas.link)
    $items['agendamentos'][1] = '<svg viewBox="0 0 512 512" fill="currentColor" width="26" height="26"><path d="M75 75L41 41C25.9 25.9 0 36.6 0 57.9V168c0 13.3 10.7 24 24 24H134.1c21.4 0 32.1-25.9 17-41l-32.7-32.7C155 85.5 203 64 256 64c106 0 192 86 192 192s-86 192-192 192c-40.8 0-78.6-12.7-109.7-34.4c-14.5-10.1-34.4-6.6-44.6 7.9s-6.6 34.4 7.9 44.6C151.2 485 201.7 504 256 504c141.4 0 256-114.6 256-256S397.4 0 256 0C169.2 0 90.8 42.9 41.9 106.7L75 75zM256 128c-13.3 0-24 10.7-24 24V248c0 8 4 15.5 10.7 20l72 48c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2V152c0-13.3-10.7-24-24-24z"/></svg>';

    echo '<nav class="menu-wrapper" aria-label="Menu principal"><div class="menu-container">';
    foreach ($items as $key => [$href, $svg]) {
        $cls = $active === $key ? 'menu-item active' : 'menu-item';
        echo '<a class="' . $cls . '" href="' . e(url($href)) . '"><span class="menu-icon-wrap">' . $svg . '</span></a>';
    }
    echo '</div></nav>';
}

function admin_layout_start(string $title, string $role, string $active): void
{
    $user = current_user();
    render_head($title);

    // PARTE 2 — Painel da equipe
    // Dono: controle total | Barbeiro: só a própria agenda
    $donoGroups = [
        'Gestão' => [
            'dashboard' => ['dono/', 'Dashboard'],
            'agenda' => ['dono/agenda.php', 'Agenda'],
            'clientes' => ['dono/clientes.php', 'Clientes'],
            'barbeiros' => ['dono/barbeiros.php', 'Barbeiros'],
        ],
        'Operação' => [
            'servicos' => ['dono/servicos.php', 'Serviços'],
            'estoque' => ['dono/estoque.php', 'Estoque'],
            'metas' => ['dono/metas.php', 'Metas'],
        ],
        'Financeiro' => [
            'financeiro' => ['dono/financeiro.php', 'Visão geral'],
            'comissoes' => ['dono/financeiro/comissoes.php', 'Comissões'],
        ],
        'Crescimento' => [
            'planos' => ['dono/planos.php', 'Planos'],
            'marketing' => ['dono/marketing.php', 'Marketing'],
            'fidelidade' => ['dono/fidelidade.php', 'Fidelidade'],
        ],
        'Sistema' => [
            'config' => ['dono/configuracoes.php', 'Configurações'],
            'link-cliente' => ['cliente/', 'Ver app do cliente', true],
        ],
    ];

    $barbeiroLinks = [
        'Minha área' => [
            'dashboard' => ['barbeiro/', 'Hoje'],
            'agenda' => ['barbeiro/agenda.php', 'Minha Agenda'],
            'perfil' => ['barbeiro/perfil.php', 'Perfil'],
        ],
    ];
    $groups = $role === 'dono' ? $donoGroups : $barbeiroLinks;
    ?>
<div class="d-flex admin-layout">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-badge brand-badge--logo">
        <img src="<?= e(media_url(product_logo_path())) ?>" alt="<?= e(product_name()) ?>">
      </div>
      <div>
        <div class="brand-name"><?= e(str_upper(product_name())) ?></div>
        <small class="text-secondary" style="font-size: 0.7rem;"><?= $role === 'dono' ? 'Painel do Dono · Controle total' : 'Painel do Barbeiro' ?></small>
      </div>
    </div>
    <nav class="nav flex-column py-2">
      <?php foreach ($groups as $groupLabel => $links): ?>
        <div class="px-3 pt-3 pb-1 small text-uppercase text-secondary" style="letter-spacing:.04em;opacity:.7"><?= e($groupLabel) ?></div>
        <?php foreach ($links as $key => $item):
            $href = $item[0];
            $label = $item[1];
            $blank = !empty($item[2]);
        ?>
          <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>" <?= $blank ? 'target="_blank"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <a class="nav-link mt-2" href="<?= e(url('logout.php')) ?>">Sair</a>
    </nav>
  </aside>
  <div class="admin-main">
    <div class="topbar">
      <div>
        <div class="fw-bold"><?= e($title) ?></div>
        <small class="text-secondary">Olá, <?= e($user['name'] ?? '') ?> · <?= $role === 'dono' ? 'Dono' : 'Barbeiro' ?></small>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-ghost" href="<?= e(url('cliente/')) ?>" target="_blank">App do cliente</a>
      </div>
    </div>
    <?php render_flash(); ?>
    <div class="p-3 p-md-4">
<?php
}

function admin_layout_end(): void
{
    echo '</div></div></div>';
    render_scripts(false);
}

/** Shell mobile do barbeiro — sem sidebar do dono. */
function barber_shell_start(string $title, string $active = 'hoje'): void
{
    $user = current_user();
    render_head($title, false, 'is-barber');
    ?>
<div class="bb-app">
  <header class="bb-top">
    <div class="bb-top-brand">
      <img src="<?= e(media_url(product_logo_path())) ?>" alt="">
      <div>
        <strong><?= e(str_upper(product_name())) ?></strong>
        <span><?= e($user['name'] ?? 'Barbeiro') ?></span>
      </div>
    </div>
    <a class="bb-top-out" href="<?= e(url('logout.php')) ?>">Sair</a>
  </header>
  <?php
    $flash = get_flash();
    if ($flash):
        $cls = ($flash['type'] ?? '') === 'danger' ? 'bb-flash--bad' : (($flash['type'] ?? '') === 'warning' ? 'bb-flash--warn' : 'bb-flash--ok');
  ?>
    <div class="bb-flash <?= e($cls) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>
  <main class="bb-main">
    <h1 class="bb-title"><?= e($title) ?></h1>
<?php
}

function barber_shell_end(string $active = 'hoje'): void
{
    $items = [
        'hoje' => ['barbeiro/', 'Hoje', '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>'],
        'produtos' => ['barbeiro/produtos.php', 'Produtos', '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16l-1.2 12.2a2 2 0 0 1-2 1.8H7.2a2 2 0 0 1-2-1.8L4 7z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg>'],
        'conta' => ['barbeiro/perfil.php', 'Conta', '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>'],
    ];
    echo '</main><nav class="bb-nav" aria-label="Menu barbeiro">';
    foreach ($items as $key => [$href, $label, $svg]) {
        $cls = $active === $key ? 'bb-nav-item active' : 'bb-nav-item';
        echo '<a class="' . $cls . '" href="' . e(url($href)) . '"><span class="bb-nav-ico">' . $svg . '</span><span>' . e($label) . '</span></a>';
    }
    echo '</nav></div>';
    render_scripts(true);
}

