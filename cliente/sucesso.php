<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$shop = settings();
render_head('Sucesso', true);
client_shell_start('agendamentos');
?>
<div class="sucesso-box">
  <div class="sucesso-icon">✓</div>
  <h1 class="page-title">Agendamento confirmado!</h1>
  <p class="page-sub">Te esperamos na <?= e($shop['shop_name']) ?>.</p>
  <a class="btn-confirmar" href="<?= e(url('cliente/agendamentos.php')) ?>" style="display:inline-block;width:auto;padding:12px 28px;margin:8px">Ver agendamentos</a>
  <div style="margin-top:12px">
    <a class="voltar-link" href="<?= e(url('cliente/')) ?>">Agendar outro</a>
  </div>
</div>
<?php client_shell_end('agendamentos'); ?>
