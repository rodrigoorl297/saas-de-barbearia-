<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$shop = settings();
$services = active_services();
$selectedInput = $_SESSION['booking']['services'] ?? [];
$selected = is_array($selectedInput) ? array_map('intval', $selectedInput) : [];

render_head('Agendar', true);
client_shell_start('agendar');
$shopName = shop_brand_name();
?>
<div class="agendar-container">
  <?php client_booking_stepper(1); ?>

  <header class="client-page-intro">
    <span class="client-eyebrow"><?= e($shopName) ?></span>
    <h1>Escolha seus serviços</h1>
    <p>Selecione uma ou mais opções. Você poderá revisar tudo antes de confirmar.</p>
  </header>

  <?php render_flash_client(); ?>

  <?php if (!$services): ?>
    <div class="client-empty-state" role="status">
      <span class="client-empty-icon"><?= icon_svg('scissors', 26) ?></span>
      <strong>Nenhum serviço disponível</strong>
      <p>Fale com a barbearia para consultar novas opções.</p>
    </div>
  <?php endif; ?>

  <form id="form-servicos" action="<?= e(url('cliente/profissional.php')) ?>" method="post">
    <div class="card-servico-container">
      <?php foreach ($services as $svc): ?>
        <?php
          $checked = in_array((int)$svc['id'], $selected, true);
          $img = !empty($svc['image_url']) ? media_url($svc['image_url']) : '';
        ?>
        <label class="card-servico <?= $checked ? 'is-selected' : '' ?>"
               data-price="<?= e((string)$svc['price']) ?>"
               data-duration="<?= (int)$svc['duration_min'] ?>">
          <input class="checkbox-circular-input" type="checkbox" name="services[]" value="<?= (int)$svc['id'] ?>" <?= $checked ? 'checked' : '' ?>>
          <div class="card-servico-imagem">
            <?php if ($img !== ''): ?>
              <img src="<?= e($img) ?>" alt="" loading="lazy">
            <?php else: ?>
              <span class="service-visual-fallback" aria-hidden="true">
                <?= icon_svg('scissors', 28) ?>
                <b><?= e(initials($svc['name'])) ?></b>
              </span>
            <?php endif; ?>
          </div>
          <div class="card-servico-detalhes">
            <div class="servico-detalhes">
              <div class="card-servico-cabecalho">
                <h2 class="titulo-servico"><?= e($svc['name']) ?></h2>
              </div>
              <div class="service-facts">
                <?php if ((int)$svc['duration_min'] > 0): ?><span class="tempo"><?= icon_svg('calendar', 14) ?> <?= (int)$svc['duration_min'] ?> min</span><?php endif; ?>
                <strong class="preco"><?= e(money((float)$svc['price'])) ?></strong>
              </div>
              <?php if (!empty($svc['description'])): ?>
                <span class="descricao"><?= e($svc['description']) ?></span>
              <?php endif; ?>
            </div>
            <span class="checkbox-circular-container" aria-hidden="true">
              <span class="checkbox-circular-custom"></span>
            </span>
          </div>
        </label>
      <?php endforeach; ?>
    </div>
  </form>

  <div class="client-live-summary" id="service-live-summary" aria-live="polite">
    <span id="service-summary-count">Nenhum serviço selecionado</span>
    <span id="service-summary-details"></span>
  </div>
</div>

<div class="botao-avancar-wrapper <?= $selected ? 'show' : '' ?>" id="botao-avancar-wrapper">
  <div class="botao-avancar-inner">
    <div class="botao-avancar-container">
      <button type="button" class="botao-avancar">
        <span>Continuar</span>
        <small id="cta-service-context">Selecione um serviço</small>
      </button>
    </div>
  </div>
</div>

<script src="<?= e(url('assets/js/cliente.js')) ?>"></script>
<?php client_shell_end('agendar'); ?>
