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
<header class="admin-page-header">
  <div><span class="admin-eyebrow">Desempenho</span><h1>Metas do mês</h1><p>Acompanhe o faturamento e ajuste os objetivos da loja e de cada profissional.</p></div>
</header>
<div class="goal-grid">
  <?php if (!$goals): ?>
    <div class="empty-state goal-empty"><strong>Nenhuma meta configurada</strong><p>Cadastre profissionais para gerar metas individuais e acompanhar o desempenho.</p><a class="btn btn-accent" href="<?= e(url('dono/barbeiros.php')) ?>">Cadastrar profissional</a></div>
  <?php endif; ?>
  <?php foreach ($goals as $g):
      $isLoja = ($g['type'] ?? '') === 'loja' || empty($g['barber_id']);
      $nome = $isLoja ? 'Meta da loja' : (find_user_by_id((int)$g['barber_id'])['name'] ?? 'Barbeiro');
      $atual = $isLoja ? $lojaFat : ($fatByBarber[(int)$g['barber_id']] ?? 0);
      $target = (float)$g['target'];
      $pct = $target > 0 ? min(100, ($atual / $target) * 100) : 0;
  ?>
  <article class="goal-card">
      <div class="goal-card-header"><div><span><?= $isLoja ? 'Negócio' : 'Profissional' ?></span><h2><?= e($nome) ?></h2></div><span class="goal-period"><?= e(date('m/Y')) ?></span></div>
      <div class="goal-values"><div><span>Realizado</span><strong><?= e(money($atual)) ?></strong></div><div><span>Objetivo</span><strong><?= e(money($target)) ?></strong></div></div>
      <svg class="goal-chart" viewBox="0 0 100 10" preserveAspectRatio="none" role="img" aria-label="<?= number_format($pct, 0) ?>% da meta atingida">
        <rect class="chart-track" x="0" y="0" width="100" height="10" rx="5"/>
        <rect class="chart-fill" x="0" y="0" width="<?= number_format($pct, 1, '.', '') ?>" height="10" rx="5"/>
      </svg>
      <div class="goal-progress-copy"><strong><?= number_format($pct, 0) ?>%</strong><span><?= $pct >= 100 ? 'Meta alcançada' : 'do objetivo mensal' ?></span></div>
      <form method="post" class="goal-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
        <label><span>Atualizar objetivo</span><input type="number" step="0.01" name="target" class="form-control form-control-sm" value="<?= e((string)$target) ?>"></label>
        <button class="btn btn-sm btn-accent" type="submit">Salvar</button>
      </form>
  </article>
  <?php endforeach; ?>
</div>
<?php admin_layout_end(); ?>
