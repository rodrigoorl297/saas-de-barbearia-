<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/whatsapp.php';

$user = require_role(['dono']);
$today = date('Y-m-d');
$month = date('Y-m');

$checklist = [
    ['done' => (bool)active_services(), 'label' => 'Cadastrar ao menos 1 serviço', 'url' => url('dono/servicos.php')],
    ['done' => (bool)active_barbers(), 'label' => 'Cadastrar ao menos 1 barbeiro', 'url' => url('dono/barbeiros.php')],
    ['done' => evo_configured(), 'label' => 'Conectar o WhatsApp da barbearia', 'url' => url('dono/configuracoes.php')],
    ['done' => trim((string)(settings()['logo_url'] ?? '')) !== '', 'label' => 'Colocar a logo no app do cliente', 'url' => url('dono/configuracoes.php')],
];
$checklistDone = count(array_filter($checklist, fn($c) => $c['done']));
$checklistTotal = count($checklist);

$all = appointments_enriched();
$hoje = array_filter($all, fn($a) => $a['date'] === $today && $a['status'] !== 'cancelado');
$mes = array_filter($all, fn($a) => str_starts_with($a['date'], $month) && $a['status'] !== 'cancelado');

$faturamentoDia = array_sum(array_map(fn($a) => (float)$a['price'], $hoje));
$faturamentoMes = array_sum(array_map(fn($a) => (float)$a['price'], $mes));
$atendimentos = count($hoje);
$ticket = $atendimentos > 0 ? $faturamentoDia / $atendimentos : 0;

$proximos = array_values(array_filter($all, fn($a) => $a['date'] >= $today && in_array($a['status'], ['agendado','confirmado','em_andamento'], true)));
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
<div class="owner-dashboard">
  <?php if ($checklistDone < $checklistTotal):
      $checklistPct = $checklistTotal > 0 ? round(($checklistDone / $checklistTotal) * 100) : 0;
  ?>
    <section class="onboarding-panel" aria-labelledby="onboarding-title">
      <div class="onboarding-header">
        <div>
          <span class="admin-eyebrow">Configuração inicial</span>
          <h2 id="onboarding-title">Prepare sua operação</h2>
          <p><?= $checklistDone ?> de <?= $checklistTotal ?> etapas concluídas</p>
        </div>
        <svg class="onboarding-chart" viewBox="0 0 100 8" preserveAspectRatio="none" role="img" aria-label="<?= $checklistPct ?>% concluído">
          <rect class="chart-track" x="0" y="0" width="100" height="8" rx="4"/>
          <rect class="chart-fill" x="0" y="0" width="<?= $checklistPct ?>" height="8" rx="4"/>
        </svg>
      </div>
      <div class="onboarding-list">
        <?php foreach ($checklist as $c): ?>
          <a href="<?= e($c['url']) ?>" class="onboarding-item <?= $c['done'] ? 'is-done' : 'is-pending' ?>">
            <span class="checklist-mark <?= $c['done'] ? 'is-done' : '' ?>"><?= $c['done'] ? icon_svg('check', 13) : '' ?></span>
            <span><?= e($c['label']) ?></span>
            <span class="onboarding-arrow" aria-hidden="true">→</span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="dashboard-kpi-grid" aria-label="Indicadores principais">
    <article class="dashboard-kpi dashboard-kpi--revenue">
      <div class="dashboard-kpi-head"><span class="dashboard-kpi-icon"><?= icon_svg('wallet', 21) ?></span><span class="dashboard-kpi-label">Faturamento hoje</span></div>
      <strong class="dashboard-kpi-value"><?= e(money($faturamentoDia)) ?></strong>
      <span class="dashboard-kpi-note">Ticket médio de <?= e(money($ticket)) ?></span>
    </article>
    <article class="dashboard-kpi dashboard-kpi--month">
      <div class="dashboard-kpi-head"><span class="dashboard-kpi-icon"><?= icon_svg('chart', 21) ?></span><span class="dashboard-kpi-label">Faturamento no mês</span></div>
      <strong class="dashboard-kpi-value"><?= e(money($faturamentoMes)) ?></strong>
      <span class="dashboard-kpi-note"><?= count($mes) ?> serviços contabilizados</span>
    </article>
    <article class="dashboard-kpi dashboard-kpi--appointments">
      <div class="dashboard-kpi-head"><span class="dashboard-kpi-icon"><?= icon_svg('calendar', 21) ?></span><span class="dashboard-kpi-label">Atendimentos hoje</span></div>
      <strong class="dashboard-kpi-value"><?= $atendimentos ?></strong>
      <span class="dashboard-kpi-note"><?= count($proximos) ?> próximos na agenda</span>
    </article>
    <article class="dashboard-kpi dashboard-kpi--alert <?= $lowStock > 0 ? 'has-alert' : '' ?>">
      <div class="dashboard-kpi-head"><span class="dashboard-kpi-icon"><?= icon_svg('alert', 21) ?></span><span class="dashboard-kpi-label">Alertas de estoque</span></div>
      <strong class="dashboard-kpi-value"><?= $lowStock ?></strong>
      <a class="dashboard-kpi-link" href="<?= e(url('dono/estoque.php')) ?>"><?= $lowStock > 0 ? 'Revisar itens críticos' : 'Estoque sob controle' ?> →</a>
    </article>
  </section>

  <div class="dashboard-main-grid">
    <section class="dashboard-panel dashboard-schedule" aria-labelledby="next-appointments-title">
      <header class="dashboard-panel-header">
        <div>
          <span class="admin-eyebrow">Agenda</span>
          <h2 id="next-appointments-title">Próximos agendamentos</h2>
          <p>Clientes e horários que precisam da sua atenção.</p>
        </div>
        <a href="<?= e(url('dono/agenda.php')) ?>" class="btn btn-sm btn-ghost">Ver agenda <span aria-hidden="true">→</span></a>
      </header>

      <?php if ($proximos): ?>
        <div class="appointments-list">
          <?php foreach ($proximos as $a): ?>
            <article class="appointment-card">
              <time class="apt-time" datetime="<?= e($a['date'] . 'T' . $a['time']) ?>">
                <strong><?= e(substr($a['time'], 0, 5)) ?></strong>
                <span><?= e(date('d/m', strtotime($a['date']))) ?></span>
              </time>
              <div class="apt-details">
                <div class="apt-client"><span class="avatar-sm" aria-hidden="true"><?= e(initials($a['client_name'])) ?></span><strong><?= e($a['client_name']) ?></strong></div>
                <div class="apt-service"><span><?= e($a['service_name']) ?></span><small><?= e($a['barber_name']) ?></small></div>
              </div>
              <div class="apt-status"><?= status_badge($a['status']) ?></div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state dashboard-empty">
          <strong>Nenhum agendamento futuro</strong>
          <p>Abra a agenda para registrar um atendimento ou compartilhe o app com seus clientes.</p>
          <a class="btn btn-accent" href="<?= e(url('dono/agenda.php')) ?>">Abrir agenda</a>
        </div>
      <?php endif; ?>
    </section>

    <section class="dashboard-panel dashboard-ranking" aria-labelledby="ranking-title">
      <header class="dashboard-panel-header">
        <div>
          <span class="admin-eyebrow">Desempenho</span>
          <h2 id="ranking-title">Ranking do mês</h2>
          <p>Faturamento por profissional.</p>
        </div>
        <a href="<?= e(url('dono/metas.php')) ?>" class="panel-icon-link" aria-label="Ver metas" title="Ver metas"><?= icon_svg('chart', 18) ?></a>
      </header>

      <?php if ($ranking): ?>
        <ol class="ranking-list">
          <?php
          $maxFat = $ranking[0]['fat'] ?? 1;
          if ($maxFat <= 0) $maxFat = 1;
          foreach ($ranking as $i => $r):
              $percent = min(100, round(($r['fat'] / $maxFat) * 100));
          ?>
            <li class="ranking-item <?= $i === 0 ? 'is-leader' : '' ?>">
              <div class="ranking-row">
                <span class="ranking-pos"><?= $i + 1 ?></span>
                <div class="ranking-person"><strong><?= e($r['name']) ?></strong><small><?= (int)$r['qtd'] ?> atendimentos</small></div>
                <strong class="ranking-val"><?= e(money((float)$r['fat'])) ?></strong>
              </div>
              <svg class="ranking-chart" viewBox="0 0 100 6" preserveAspectRatio="none" role="img" aria-label="<?= $percent ?>% do maior faturamento">
                <rect class="chart-track" x="0" y="0" width="100" height="6" rx="3"/>
                <rect class="chart-fill" x="0" y="0" width="<?= $percent ?>" height="6" rx="3"/>
              </svg>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <div class="empty-state dashboard-empty"><strong>Sem dados de desempenho</strong><p>O ranking aparecerá após os primeiros atendimentos do mês.</p></div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php admin_layout_end(); ?>
