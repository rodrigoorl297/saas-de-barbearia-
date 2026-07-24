<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['barbeiro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $type = ($_POST['type'] ?? '') === 'out_venda' ? 'out_venda' : 'out_uso';
    $err = stock_consume($id, $qty, $type, (int)$user['id']);
    if ($err) {
        flash('danger', $err);
    } else {
        flash('success', $type === 'out_venda' ? 'Venda lançada.' : 'Uso lançado no estoque.');
    }
    redirect(url('barbeiro/produtos.php'));
}

$items = array_values(array_filter(store_read('stock'), fn($i) => !isset($i['active']) || !empty($i['active'])));
usort($items, fn($a, $b) => strcmp((string)$a['name'], (string)$b['name']));

$mine = array_values(array_filter(
    store_read('stock_history'),
    fn($h) => (int)($h['user_id'] ?? 0) === (int)$user['id']
));
usort($mine, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));
$mine = array_slice($mine, 0, 12);

barber_shell_start('Produtos', 'produtos');
?>
<p class="bb-lead">Lance o que vendeu ou usou no atendimento. O estoque baixa na hora.</p>

<?php if (!$items): ?>
  <div class="bb-empty">Nenhum produto no estoque. Peça ao dono para cadastrar.</div>
<?php else: ?>
  <div class="bb-list">
    <?php foreach ($items as $p): ?>
      <article class="bb-card">
        <div class="bb-card-top">
          <div class="bb-card-name" style="margin:0"><?= e($p['name']) ?></div>
          <div class="bb-stock"><?= (int)$p['qty'] ?> un.</div>
        </div>
        <div class="bb-card-meta">
          <?php if ((float)($p['price'] ?? 0) > 0): ?>
            Venda <?= e(money((float)$p['price'])) ?>
          <?php else: ?>
            Só uso interno
          <?php endif; ?>
        </div>
        <form method="post" class="bb-prod-form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <label class="bb-qty">
            Qtd
            <input type="number" name="qty" min="1" max="<?= max(1, (int)$p['qty']) ?>" value="1" inputmode="numeric">
          </label>
          <div class="bb-actions">
            <?php if ((float)($p['price'] ?? 0) > 0 && (int)$p['qty'] > 0): ?>
              <button class="bb-btn bb-btn--ok" type="submit" name="type" value="out_venda">Vendi</button>
            <?php endif; ?>
            <button class="bb-btn bb-btn--ghost" type="submit" name="type" value="out_uso" <?= (int)$p['qty'] < 1 ? 'disabled' : '' ?>>Usei</button>
          </div>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($mine): ?>
  <h2 class="bb-sec">Seus últimos lançamentos</h2>
  <div class="bb-list">
    <?php foreach ($mine as $h): ?>
      <div class="bb-mini">
        <strong><?= e($h['product_name'] ?? '') ?></strong>
        <span><?= ($h['type'] ?? '') === 'out_venda' ? 'Venda' : 'Uso' ?> · <?= (int)($h['qty'] ?? 0) ?> un.</span>
        <small><?= e($h['date'] ?? '') ?></small>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php barber_shell_end('produtos'); ?>
