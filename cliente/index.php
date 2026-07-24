<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$shop = settings();
$services = active_services();
$selected = $_SESSION['booking']['services'] ?? [];

$thumbs = [
    'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?w=300&h=300&fit=crop',
    'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=300&h=300&fit=crop',
    'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=300&h=300&fit=crop',
    'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=300&h=300&fit=crop',
    'https://images.unsplash.com/photo-1585747860715-2ba37e788b70?w=300&h=300&fit=crop',
    'https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=300&h=300&fit=crop',
];

render_head('Agendar', true);
client_shell_start('agendar');
$shopName = shop_brand_name();
$logoUrl = shop_logo_path();
?>
<div class="agendar-container">
  <div class="logo-historico">
    <?php if ($logoUrl !== ''): ?>
      <div class="img-agendar img-agendar--photo" aria-label="<?= e($shopName) ?>">
        <img src="<?= e(media_url($logoUrl)) ?>" alt="<?= e($shopName) ?>">
      </div>
    <?php else: ?>
      <div class="img-agendar" aria-label="<?= e($shopName) ?>">
        <?= e(str_upper(str_cut($shopName, 0, 2))) ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="shop-name-client"><?= e(str_upper($shopName)) ?></div>

  <div class="card-titulo-container">
    <div class="titulo-container">
      <span>Escolha um serviço para agendar</span>
    </div>
  </div>

  <?php render_flash_client(); ?>

  <form id="form-servicos" action="<?= e(url('cliente/profissional.php')) ?>" method="post">
    <div class="card-servico-container">
      <?php foreach ($services as $i => $svc): ?>
        <?php
          $checked = in_array((int)$svc['id'], array_map('intval', $selected), true);
          $img = !empty($svc['image_url']) ? media_url($svc['image_url']) : $thumbs[$i % count($thumbs)];
        ?>
        <div class="card-servico">
          <div class="card-servico-imagem">
            <img src="<?= e($img) ?>" alt="<?= e($svc['name']) ?>" loading="lazy">
          </div>
          <div class="card-servico-detalhes">
            <div class="servico-detalhes">
              <div class="card-servico-cabecalho">
                <h3 class="titulo-servico"><?= e(str_upper($svc['name'])) ?></h3>
              </div>
              <span class="preco"><?= e(money((float)$svc['price'])) ?></span>
              <?php if ((int)$svc['duration_min'] > 0): ?>
                <span class="tempo"><?= (int)$svc['duration_min'] ?> min</span>
              <?php endif; ?>
              <?php if (!empty($svc['description'])): ?>
                <span class="descricao"><?= e($svc['description']) ?></span>
              <?php endif; ?>
            </div>
            <label class="checkbox-circular-container" onclick="event.stopPropagation()">
              <input class="checkbox-circular-input" type="checkbox" name="services[]" value="<?= (int)$svc['id'] ?>" <?= $checked ? 'checked' : '' ?>>
              <span class="checkbox-circular-custom"></span>
            </label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </form>
</div>

<div class="botao-avancar-wrapper <?= $selected ? 'show' : '' ?>" id="botao-avancar-wrapper">
  <div class="botao-avancar-inner">
    <div class="botao-avancar-container">
      <button type="button" class="botao-avancar">Avançar</button>
    </div>
  </div>
</div>

<script src="<?= e(url('assets/js/cliente.js')) ?>"></script>
<?php client_shell_end('agendar'); ?>
