<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);
$today = date('Y-m-d');
$month = date('Y-m');

$all = appointments_enriched();
$hoje = array_filter($all, fn($a) => $a['date'] === $today && $a['status'] !== 'cancelado');
$mes = array_filter($all, fn($a) => str_starts_with($a['date'], $month) && $a['status'] !== 'cancelado');

$faturamentoDia = array_sum(array_map(fn($a) => (float)$a['price'], $hoje));
$faturamentoMes = array_sum(array_map(fn($a) => (float)$a['price'], $mes));
$atendimentos = count($hoje);
$ticket = $atendimentos > 0 ? $faturamentoDia / $atendimentos : 0;

$proximos = array_values(array_filter($all, fn($a) => $a['date'] >= $today && in_array($a['status'], ['agendado','confirmado'], true)));
$proximos = array_slice($proximos, 0, 8);

$rankingMap = [];
foreach ($mes as $a) {
    $bid = (int)$a['barber_id'];
    if (!isset($rankingMap[$bid])) {
        $rankingMap[$bid] = ['name' => $a['barber_name'], 'qtd' => 0, 'fat' => 0.0];
    }
    $rankingMap[$bid]['qtd']++;
    $rankingMap[$bid]['fat'] += (float)$a['price'];
}
$ranking = array_values($rankingMap);
usort($ranking, fn($a, $b) => $b['fat'] <=> $a['fat']);
$lowStock = count(array_filter(store_read('stock'), fn($i) => (int)$i['qty'] <= (int)$i['min_qty']));

admin_layout_start('Dashboard', 'dono', 'dashboard');
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="kpi-card premium">
      <div class="kpi-icon"><?= icon_svg('wallet', 24) ?? '💰' ?></div>
      <div class="kpi-info">
        <div class="label">Faturamento (Hoje)</div>
        <div class="value text-gradient-green"><?= e(money($faturamentoDia)) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi-card premium">
      <div class="kpi-icon"><?= icon_svg('chart', 24) ?? '📈' ?></div>
      <div class="kpi-info">
        <div class="label">Faturamento (Mês)</div>
        <div class="value text-gradient-blue"><?= e(money($faturamentoMes)) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi-card premium">
      <div class="kpi-icon"><?= icon_svg('calendar', 24) ?? '📅' ?></div>
      <div class="kpi-info">
        <div class="label">Atendimentos Hoje</div>
        <div class="value"><?= $atendimentos ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi-card premium <?= $lowStock > 0 ? 'alert-pulse' : '' ?>">
      <div class="kpi-icon"><?= icon_svg('alert', 24) ?? '⚠️' ?></div>
      <div class="kpi-info">
        <div class="label">Estoque Baixo</div>
        <div class="value"><?= $lowStock ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card-soft premium-panel p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h5 mb-0 fw-bold">Próximos Agendamentos</h2>
        <a href="<?= e(url('dono/agenda.php')) ?>" class="btn btn-sm btn-ghost">Ver Agenda Completa →</a>
      </div>
      
      <div class="appointments-list">
        <?php foreach ($proximos as $a): ?>
          <div class="appointment-card">
            <div class="apt-time">
              <strong><?= e(substr($a['time'], 0, 5)) ?></strong>
              <span><?= e(date('d/m', strtotime($a['date']))) ?></span>
            </div>
            <div class="apt-details">
              <div class="apt-client">
                <div class="avatar-sm"><?= e(initials($a['client_name'])) ?></div>
                <strong><?= e($a['client_name']) ?></strong>
              </div>
              <div class="apt-service text-secondary small">
                <?= e($a['service_name']) ?> com <span style="color:#818cf8"><?= e($a['barber_name']) ?></span>
              </div>
            </div>
            <div class="apt-status">
              <?= status_badge($a['status']) ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$proximos): ?>
          <div class="text-secondary text-center py-4">Nenhum agendamento futuro.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="col-lg-5">
    <div class="card-soft premium-panel p-4 h-100">
      <h2 class="h5 mb-4 fw-bold">Ranking do Mês</h2>
      
      <div class="ranking-list">
        <?php 
        $maxFat = $ranking[0]['fat'] ?? 1; // Para calcular a % da barra
        if ($maxFat <= 0) $maxFat = 1;
        $medals = ['🥇', '🥈', '🥉'];
        foreach ($ranking as $i => $r): 
          $percent = min(100, round(($r['fat'] / $maxFat) * 100));
        ?>
          <div class="ranking-item">
            <div class="d-flex justify-content-between align-items-end mb-1">
              <div class="ranking-name">
                <span class="ranking-pos"><?= $i < 3 ? $medals[$i] : '#'.($i+1) ?></span>
                <strong><?= e($r['name']) ?></strong>
                <span class="text-secondary small ms-2">(<?= (int)$r['qtd'] ?> atend.)</span>
              </div>
              <strong class="ranking-val"><?= e(money((float)$r['fat'])) ?></strong>
            </div>
            <div class="ranking-bar-bg">
              <div class="ranking-bar-fill <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) ?>" style="width: <?= $percent ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$ranking): ?><div class="text-secondary text-center py-4">Sem dados ainda.</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php admin_layout_end(); ?>
