<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = require_role(['dono']);
$month = date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_rule') {
    $pct = (float)str_replace(',', '.', (string)($_POST['commission_percent'] ?? '40'));
    $pct = max(0, min(100, $pct));
    save_settings(['commission_rate' => round($pct / 100, 4)]);
    flash('success', 'Regra de comissão atualizada.');
    redirect(url('dono/financeiro/comissoes.php'));
}

$shop = settings();
$rate = (float)($shop['commission_rate'] ?? 0.40);
if ($rate < 0) {
    $rate = 0;
}
if ($rate > 1) {
    // permite salvar como 40 ou 0.40
    $rate = min(1, $rate / 100);
}
$percent = round($rate * 100, 2);

$all = appointments_enriched(fn($a) => str_starts_with($a['date'], $month) && in_array($a['status'], ['concluido', 'em_andamento', 'confirmado', 'agendado'], true));

$map = [];
foreach ($all as $a) {
    $bid = (int)$a['barber_id'];
    if (!isset($map[$bid])) {
        $map[$bid] = ['name' => $a['barber_name'], 'fat' => 0.0, 'qtd' => 0];
    }
    $map[$bid]['fat'] += (float)$a['price'];
    $map[$bid]['qtd']++;
}
$rows = array_values($map);
usort($rows, fn($a, $b) => $b['fat'] <=> $a['fat']);
$totalFat = array_sum(array_column($rows, 'fat'));
$totalCom = $totalFat * $rate;

admin_layout_start('Comissões', 'dono', 'comissoes');
?>
<div class="stock-page">
  <div class="stock-toolbar">
    <div>
      <h2 class="stock-heading">Comissões do mês <?= e($month) ?></h2>
      <p class="stock-sub">Regra atual: <strong><?= e(rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',')) ?>%</strong> sobre o faturamento gerado</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#commissionRuleModal">Editar regra</button>
    </div>
  </div>

  <div class="stock-kpis">
    <div class="stock-kpi">
      <span class="stock-kpi-label">Faturamento</span>
      <strong class="stock-kpi-value"><?= e(money($totalFat)) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Comissões</span>
      <strong class="stock-kpi-value"><?= e(money($totalCom)) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Regra</span>
      <strong class="stock-kpi-value"><?= e(rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',')) ?>%</strong>
    </div>
  </div>

  <div class="stock-panel">
    <div class="table-responsive">
      <table class="table stock-table align-middle mb-0">
        <thead><tr><th>Barbeiro</th><th>Atendimentos</th><th>Faturamento</th><th>Comissão</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['name']) ?></td>
            <td><?= (int)$r['qtd'] ?></td>
            <td><?= e(money($r['fat'])) ?></td>
            <td class="fw-bold"><?= e(money($r['fat'] * $rate)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="4" class="text-secondary">Sem dados no mês.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="commissionRuleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Regra de comissão</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" class="vstack gap-3">
          <input type="hidden" name="action" value="save_rule">
          <div>
            <label class="form-label">Percentual sobre o faturamento (%)</label>
          <input type="number" name="commission_percent" class="form-control" min="0" max="100" step="0.1" required value="<?= e((string)$percent) ?>" aria-label="Percentual de comissão">
            <div class="form-text">Ex.: 40 = 40% do valor gerado por cada barbeiro.</div>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-accent" type="submit">Salvar regra</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php admin_layout_end(); ?>
