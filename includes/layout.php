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
    $cssFile = dirname(__DIR__) . '/assets/css/app.css';
    $cssVer = is_file($cssFile) ? (string) filemtime($cssFile) : (string) time();
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=League+Spartan:wght@500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="manifest" href="<?= e(url('manifest.json')) ?>">
  <?php if (!$client): ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php endif; ?>
  <link href="<?= e(url('assets/css/app.css')) ?>?v=<?= e($cssVer) ?>" rel="stylesheet">
  <?php $brand = brand_colors(); ?>
  <style>:root{--brand-primary:<?= e($brand['primary']) ?>;--brand-accent:<?= e($brand['accent']) ?>}</style>
</head>
<body class="<?= e($body) ?>">
<a class="skip-link" href="#main-content">Pular para o conteúdo principal</a>
<?php
}

function render_scripts(bool $client = false, bool $barber = false): void
{
    echo '<script>window.__CSRF__=' . json_encode(csrf_token(), JSON_UNESCAPED_SLASHES) . ';</script>';
    echo '<script src="' . e(url('assets/js/csrf.js')) . '"></script>';
    echo '<script src="' . e(url('assets/js/a11y.js')) . '"></script>';
    if (!$client) {
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
        if (!$barber) {
            echo '<script src="' . e(url('assets/js/admin.js')) . '"></script>';
        }
    }
    echo '<script>if("serviceWorker" in navigator){navigator.serviceWorker.register("' . e(url('sw.js')) . '").catch(console.error);}</script>';
    echo '</body></html>';
}

function render_flash_client(): void
{
    $flash = get_flash();
    if (!$flash) return;
    $type = $flash['type'] === 'danger' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success');
    echo '<div class="alert-as ' . e($type) . '" role="' . ($type === 'danger' ? 'alert' : 'status') . '" aria-live="polite">' . e($flash['message']) . '</div>';
}

function render_flash(): void
{
    $flash = get_flash();
    if (!$flash) return;
    echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show m-3" role="alert">'
        . e($flash['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar mensagem"></button></div>';
}

/** Stepper visual da jornada pública. Não altera a sessão nem o fluxo. */
function client_booking_stepper(int $current): void
{
    $current = max(1, min(4, $current));
    $steps = [
        1 => ['Serviço', url('cliente/')],
        2 => ['Profissional', url('cliente/profissional.php#profissional')],
        3 => ['Horário', url('cliente/profissional.php#horarios')],
        4 => ['Confirmar', null],
    ];
    ?>
    <nav class="client-stepper" aria-label="Etapas do agendamento">
      <ol>
        <?php foreach ($steps as $number => [$label, $href]):
            $state = $number < $current ? 'is-complete' : ($number === $current ? 'is-current' : 'is-upcoming');
            $canLink = $href !== null && $number <= $current;
        ?>
          <li class="<?= e($state) ?>" <?= $number === $current ? 'aria-current="step"' : '' ?>>
            <?php if ($canLink): ?><a href="<?= e($href) ?>"><?php else: ?><span><?php endif; ?>
              <span class="client-step-number"><?= $number < $current ? icon_svg('check', 13) : $number ?></span>
              <span class="client-step-label"><?= e($label) ?></span>
            <?php if ($canLink): ?></a><?php else: ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>
    <?php
}

/** Resumo somente visual da seleção persistida durante o agendamento. */
function client_booking_summary(string $title = 'Sua seleção'): void
{
    $booking = $_SESSION['booking'] ?? [];
    $serviceIds = array_values(array_filter(array_map('intval', $booking['services'] ?? [])));
    if (!$serviceIds) {
        return;
    }
    $services = services_by_ids($serviceIds);
    if (!$services) {
        return;
    }
    $duration = array_sum(array_map(fn($service) => (int)($service['duration_min'] ?? 0), $services));
    $total = array_sum(array_map(fn($service) => (float)($service['price'] ?? 0), $services));
    $barber = !empty($booking['barber_id']) ? find_user_by_id((int)$booking['barber_id']) : null;
    ?>
    <aside class="client-selection-summary" aria-label="Resumo da seleção">
      <div class="client-selection-head">
        <span><?= e($title) ?></span>
        <a href="<?= e(url('cliente/')) ?>">Editar serviços</a>
      </div>
      <div class="client-selection-services"><?= e(implode(' + ', array_column($services, 'name'))) ?></div>
      <div class="client-selection-meta">
        <span><?= icon_svg('calendar', 15) ?> <?= $duration ?> min</span>
        <strong><?= e(money($total)) ?></strong>
      </div>
      <?php if ($barber): ?>
        <div class="client-selection-extra">Com <?= e($barber['name'] ?? 'profissional') ?></div>
      <?php endif; ?>
    </aside>
    <?php
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
  <main class="conteudo-centralizado" id="main-content" tabindex="-1">
<?php
}

function client_shell_end(string $active = 'agendar'): void
{
    client_bottom_nav($active);
    echo '</main></div>';
    render_scripts(true);
}

function client_bottom_nav(string $active = 'agendar'): void
{
    $items = [
        'agendar' => [
            'cliente/',
            'Agendar',
            '<svg viewBox="0 0 448 512" fill="currentColor" width="24" height="24"><path d="M128 0c17.7 0 32 14.3 32 32V64H288V32c0-17.7 14.3-32 32-32s32 14.3 32 32V64h32c35.3 0 64 28.7 64 64v16 48V448c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V192 144 128C0 92.7 28.7 64 64 64h32V32c0-17.7 14.3-32 32-32zM64 192V448H384V192H64zm80 96c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H160c-8.8 0-16-7.2-16-16V288zm112 0c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V288z"/></svg>',
        ],
        'agendamentos' => [
            'cliente/agendamentos.php',
            'Agendamentos',
            '<svg viewBox="0 0 512 512" fill="currentColor" width="24" height="24"><path d="M256 0a256 256 0 1 1 0 512A256 256 0 1 1 256 0zM232 120V256c0 8 4 15.5 10.7 20l96 64c11 7.4 25.9 4.4 33.3-6.7s4.4-25.9-6.7-33.3L280 243.2V120c0-13.3-10.7-24-24-24s-24 10.7-24 24z"/></svg>',
        ],
        'conta' => [
            'cliente/conta.php',
            'Conta',
            '<svg viewBox="0 0 448 512" fill="currentColor" width="24" height="24"><path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7C448 383.8 368.2 304 269.7 304H178.3z"/></svg>',
        ],
    ];

    echo '<nav class="menu-wrapper" aria-label="Menu principal"><div class="menu-container">';
    foreach ($items as $key => [$href, $label, $svg]) {
        $cls = $active === $key ? 'menu-item active' : 'menu-item';
        echo '<a class="' . $cls . '" href="' . e(url($href)) . '"' . ($active === $key ? ' aria-current="page"' : '') . '>';
        $decorativeIcon = str_replace('<svg ', '<svg aria-hidden="true" ', $svg);
        echo '<span class="menu-icon-wrap">' . $decorativeIcon . '</span><span class="menu-label">' . e($label) . '</span></a>';
    }
    echo '</div></nav>';
}

/** Ícones lineares consistentes da navegação administrativa. */
function admin_nav_icon(string $name, int $size = 20): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'agenda' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
        'clientes' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'barbeiros' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M19 8v6M16 11h6"/>',
        'servicos' => '<circle cx="6" cy="7" r="3"/><circle cx="6" cy="17" r="3"/><path d="m8.6 8.5 11.4 7.5M8.6 15.5 20 8M14 12l6 4M14 12l6-4"/>',
        'estoque' => '<path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="m3 8 9 5 9-5M3 12l9 5 9-5M3 16l9 5 9-5"/>',
        'pdv' => '<path d="M3 3h2l2.4 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
        'metas' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'financeiro' => '<path d="M3 3v18h18"/><path d="m7 16 4-5 3 3 6-8"/>',
        'comissoes' => '<circle cx="12" cy="12" r="9"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8M12 6v12"/>',
        'planos' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h2"/>',
        'marketing' => '<path d="m3 11 15-5v12L3 13v-2Z"/><path d="M11.6 15.9 13 21H8l-1.6-6.7M18 10a3 3 0 0 1 0 4"/>',
        'fidelidade' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
        'config' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1v.1h-4V21a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1-.4h-.1v-4H3A1.7 1.7 0 0 0 4.6 8.5a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1v-.1h4V3A1.7 1.7 0 0 0 15.5 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.1.4.3.7.6 1 .3.2.7.4 1 .4h.1v4H21a1.7 1.7 0 0 0-1.6.6Z"/>',
        'link-cliente' => '<path d="M14 3h7v7M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
        'perfil' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>',
        'collapse' => '<path d="m15 18-6-6 6-6"/>',
    ];
    $path = $paths[$name] ?? $paths['dashboard'];
    return '<svg class="admin-nav-svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
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
            'pdv' => ['dono/pdv.php', 'PDV Caixa'],
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
    $contexts = [
        'dashboard' => 'Visão geral e prioridades do negócio',
        'agenda' => 'Agenda e fluxo de atendimentos',
        'clientes' => 'Relacionamento e histórico de clientes',
        'barbeiros' => 'Equipe, horários e desempenho',
        'servicos' => 'Catálogo, preços e duração',
        'estoque' => 'Produtos, níveis e alertas',
        'pdv' => 'Venda rápida e fechamento de caixa',
        'metas' => 'Objetivos e evolução mensal',
        'financeiro' => 'Receitas, despesas e resultado',
        'comissoes' => 'Apuração da equipe',
        'planos' => 'Assinaturas e recorrência',
        'marketing' => 'Campanhas e comunicação',
        'fidelidade' => 'Pontos, níveis e recompensas',
        'config' => 'Preferências e identidade da barbearia',
        'perfil' => 'Dados e preferências pessoais',
    ];
    $context = $contexts[$active] ?? 'Controle e organização da operação';
    ?>
<div class="d-flex admin-layout">
  <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="brand">
      <div class="brand-badge brand-badge--logo">
        <img src="<?= e(media_url(product_logo_path())) ?>" alt="<?= e(product_name()) ?>">
      </div>
      <div class="brand-copy">
        <div class="brand-name" id="appSidebarLabel"><?= e(str_upper(product_name())) ?></div>
        <small class="brand-context"><?= $role === 'dono' ? 'Painel do Dono' : 'Painel do Barbeiro' ?></small>
      </div>
      <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Fechar menu"></button>
    </div>
    <nav class="nav flex-column sidebar-nav" aria-label="Navegação do painel">
      <?php foreach ($groups as $groupLabel => $links): ?>
        <div class="sidebar-group-label"><span><?= e($groupLabel) ?></span></div>
        <?php foreach ($links as $key => $item):
            $href = $item[0];
            $label = $item[1];
            $blank = !empty($item[2]);
        ?>
          <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>" <?= $blank ? 'target="_blank" rel="noopener"' : '' ?> title="<?= e($label) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-link-icon"><?= admin_nav_icon($key) ?></span>
            <span class="sidebar-link-label"><?= e($label) ?></span>
            <?php if ($blank): ?><span class="sidebar-external" aria-hidden="true">↗</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <div class="sidebar-footer">
        <a class="nav-link" href="<?= e(url('logout.php')) ?>" title="Sair">
          <span class="sidebar-link-icon"><?= admin_nav_icon('logout') ?></span>
          <span class="sidebar-link-label">Sair</span>
        </a>
      </div>
    </nav>
  </aside>
  <div class="admin-main">
    <div class="topbar">
      <div class="topbar-heading">
        <button type="button" class="btn-menu-toggle d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Abrir menu">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
        <button type="button" class="sidebar-collapse-toggle d-none d-lg-inline-grid" id="sidebarCollapse" aria-label="Recolher menu lateral" aria-expanded="true" title="Recolher menu">
          <?= admin_nav_icon('collapse', 18) ?>
        </button>
        <div class="topbar-title-wrap">
          <div class="topbar-title"><?= e($title) ?></div>
          <div class="topbar-context"><?= e($context) ?></div>
        </div>
      </div>
      <div class="topbar-actions">
        <?php if ($role === 'dono'): ?>
          <a class="topbar-action-link d-none d-md-inline-flex" href="<?= e(url('dono/agenda.php')) ?>"><?= admin_nav_icon('agenda', 17) ?><span>Agenda</span></a>
        <?php endif; ?>
        <a class="topbar-action-link d-none d-sm-inline-flex" href="<?= e(url('cliente/')) ?>" target="_blank" rel="noopener"><?= admin_nav_icon('link-cliente', 17) ?><span>App do cliente</span></a>
        <div class="dropdown">
          <button class="topbar-user" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir menu do usuário">
            <span class="topbar-avatar"><?= e(initials($user['name'] ?? 'U')) ?></span>
            <span class="topbar-user-copy d-none d-sm-grid"><strong><?= e($user['name'] ?? '') ?></strong><small><?= $role === 'dono' ? 'Dono' : 'Barbeiro' ?></small></span>
            <span class="topbar-user-chevron" aria-hidden="true">⌄</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end admin-user-menu">
            <?php if ($role === 'dono'): ?><li><a class="dropdown-item" href="<?= e(url('dono/configuracoes.php')) ?>"><?= admin_nav_icon('config', 17) ?> Configurações</a></li><?php endif; ?>
            <li><a class="dropdown-item" href="<?= e(url('logout.php')) ?>"><?= admin_nav_icon('logout', 17) ?> Sair</a></li>
          </ul>
        </div>
      </div>
    </div>
    <?php render_flash(); ?>
    <main class="admin-content" id="main-content" tabindex="-1">
<?php
}

function admin_layout_end(): void
{
    echo '</main></div></div>';
    render_scripts(false);
}

/** Shell mobile do barbeiro. */
function barber_shell_start(string $title, string $active = 'hoje'): void
{
    $user = current_user();
    $selectedDate = (string)($_GET['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        $selectedDate = date('Y-m-d');
    }
    $selectedTs = strtotime($selectedDate) ?: time();
    $weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $dateLabel = $weekdays[(int)date('w', $selectedTs)] . ', ' . date('d/m', $selectedTs);
    render_head($title, false, 'is-barber');
    ?>
<div class="bb-app">
  <header class="bb-top">
    <div class="bb-top-brand">
      <img src="<?= e(media_url(product_logo_path())) ?>" alt="" width="40" height="40">
      <div class="bb-top-copy">
        <strong><?= e($user['name'] ?? 'Barbeiro') ?></strong>
        <span><?= e($dateLabel) ?> · <?= e(product_name()) ?></span>
      </div>
    </div>
    <div class="bb-top-actions">
      <?php if ($active === 'hoje'): ?><span class="bb-update-status" id="bb-update-status"><i aria-hidden="true"></i><span>Ao vivo</span></span><?php endif; ?>
      <a class="bb-top-out" href="<?= e(url('logout.php')) ?>" aria-label="Sair da conta">Sair</a>
    </div>
  </header>
  <?php
    $flash = get_flash();
    if ($flash):
      $cls = match ($flash['type'] ?? '') {
          'danger' => 'bb-flash--bad',
          'warning' => 'bb-flash--warn',
          default => 'bb-flash--ok',
      };
  ?>
    <div class="bb-flash <?= e($cls) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>
  <main class="bb-main" id="main-content" tabindex="-1">
    <?php if (trim($title) !== ''): ?>
      <h1 class="bb-title <?= $active === 'hoje' ? 'bb-title--today' : '' ?>"><?= e($title) ?></h1>
    <?php endif; ?>
<?php
}

function barber_shell_end(string $active = 'hoje'): void
{
    $icons = [
        'hoje' => '<svg class="bb-nav-svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        'clientes' => '<svg class="bb-nav-svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        'produtos' => '<svg class="bb-nav-svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M3 6h18" stroke="currentColor" stroke-width="2"/><path d="M16 10a4 4 0 0 1-8 0" stroke="currentColor" stroke-width="2"/></svg>',
        'conta' => '<svg class="bb-nav-svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/></svg>',
    ];
    $items = [
        'hoje' => ['barbeiro/', 'Hoje'],
        'clientes' => ['barbeiro/clientes.php', 'Clientes'],
        'produtos' => ['barbeiro/produtos.php', 'Produtos'],
        'conta' => ['barbeiro/perfil.php', 'Conta'],
    ];
    echo '</main>';
    echo '<nav class="bb-nav" aria-label="Menu barbeiro">';
    foreach ($items as $key => [$href, $label]) {
        $on = $active === $key ? ' active' : '';
        echo '<a class="bb-nav-item' . $on . '" href="' . e(url($href)) . '"' . ($active === $key ? ' aria-current="page"' : '') . '>';
        echo '<span class="bb-nav-ico">' . ($icons[$key] ?? '') . '</span>';
        echo '<span class="bb-nav-label">' . e($label) . '</span></a>';
    }
    echo '</nav></div>';
    render_scripts(false, true);
}
