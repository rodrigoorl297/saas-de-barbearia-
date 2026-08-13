<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);
$month = date('Y-m');
$goals = ensure_default_goals();
$all = appointments_enriched(fn($a) => str_starts_with($a['date'], $month) && $a['status'] !== 'cancelado');

$fatByBarber = [];
foreach ($all as $a) {
    $bid = (int)$a['barber_id'];
    $fatByBarber[$bid] = ($fatByBarber[$bid] ?? 0) + (float)$a['price'];
}
$lojaFat = array_sum($fatByBarber);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $target = (float)($_POST['target'] ?? 0);
    $goals = ensure_default_goals();
    foreach ($goals as $i => $g) {
        if ((int)$g['id'] === $id) {
            $goals[$i]['target'] = $target;
            break;
        }
    }
    store_write('goals', $goals);
    flash('success', 'Meta atualizada.');
    redirect(url('dono/metas.php'));
}

admin_layout_start('Metas', 'dono', 'metas');
?>
<div class="row g-3 mb-3">
  <div class="col-12">
    <p class="text-secondary mb-0">Acompanhe o faturamento do mês e ajuste as metas da loja e de cada barbeiro.</p>
  </div>
</div>
<div class="row g-3">
  <?php if (!$goals): ?>
    <div class="col-12">
      <div class="card-soft p-4 text-center text-secondary">Nenhuma meta ainda. Cadastre barbeiros para gerar metas individuais.</div>
    </div>
  <?php endif; ?>
  <?php foreach ($goals as $g):
      $isLoja = ($g['type'] ?? '') === 'loja' || empty($g['barber_id']);
      $nome = $isLoja ? 'Meta da loja' : (find_user_by_id((int)$g['barber_id'])['name'] ?? 'Barbeiro');
      $atual = $isLoja ? $lojaFat : ($fatByBarber[(int)$g['barber_id']] ?? 0);
      $target = (float)$g['target'];
      $pct = $target > 0 ? min(100, ($atual / $target) * 100) : 0;
  ?>
  <div class="col-md-6">
    <div class="card-soft p-3">
      <div class="d-flex justify-content-between mb-2">
        <strong><?= e($nome) ?></strong>
        <span class="text-secondary"><?= e($month) ?></span>
      </div>
      <div class="mb-1"><?= e(money($atual)) ?> / <?= e(money($target)) ?></div>
      <div class="progress mb-3" style="height:8px;background:rgba(255,255,255,.08)">
        <div class="progress-bar" style="width:<?= number_format($pct, 1) ?>%;background:#c9a227"></div>
      </div>
      <form method="post" class="d-flex gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
        <input type="number" step="0.01" name="target" class="form-control form-control-sm" value="<?= e((string)$target) ?>">
        <button class="btn btn-sm btn-accent" type="submit">Salvar</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php admin_layout_end(); ?>
