<?php
/** Fragmento da agenda do barbeiro (poll). */
$inProgress = count(array_filter($pending, fn($a) => ($a['status'] ?? '') === 'em_andamento'));
$waiting = count($pending) - $inProgress;

$nextAppointment = null;
foreach ($pending as $candidate) {
    if (($candidate['status'] ?? '') === 'em_andamento') {
        $nextAppointment = $candidate;
        break;
    }
}
if (!$nextAppointment) {
    $now = date('H:i');
    foreach ($pending as $candidate) {
        if ($date !== $today || (string)$candidate['time'] >= $now) {
            $nextAppointment = $candidate;
            break;
        }
    }
}
$nextAppointment ??= $pending[0] ?? null;
?>

<section class="bb-kpis bb-kpis--4" aria-label="Resumo do dia">
  <div class="bb-kpi"><span>Na fila</span><strong><?= $waiting ?></strong></div>
  <div class="bb-kpi bb-kpi--progress"><span>Em andamento</span><strong><?= $inProgress ?></strong></div>
  <div class="bb-kpi"><span>Concluídos</span><strong><?= (int)$dayStats['cuts'] ?></strong></div>
  <div class="bb-kpi bb-kpi--accent"><span>Faturado</span><strong class="bb-money"><?= e(money((float)$dayStats['total'])) ?></strong></div>
</section>

<?php if ($nextAppointment):
    $nextStatus = (string)($nextAppointment['status'] ?? 'agendado');
    $nextBasePrice = (float)$nextAppointment['price'] - appointment_products_total($nextAppointment);
    if ($nextBasePrice < 0) {
        $nextBasePrice = (float)$nextAppointment['price'];
    }
?>
  <section class="bb-next" data-status="<?= e($nextStatus) ?>" aria-labelledby="bb-next-title">
    <div class="bb-next-label"><span><?= $nextStatus === 'em_andamento' ? 'Atendimento atual' : 'Próximo atendimento' ?></span><span class="bb-status bb-status--<?= e($nextStatus) ?>"><?= e(status_label($nextStatus)) ?></span></div>
    <div class="bb-next-main">
      <time datetime="<?= e($nextAppointment['date'] . 'T' . $nextAppointment['time']) ?>"><?= e(substr((string)$nextAppointment['time'], 0, 5)) ?></time>
      <div><h2 id="bb-next-title"><?= e($nextAppointment['client_name']) ?></h2><p><?= e($nextAppointment['service_name']) ?></p></div>
      <strong class="bb-next-price"><?= e(money($nextBasePrice)) ?></strong>
    </div>
    <div class="bb-next-actions">
      <?php if ($nextStatus === 'agendado'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$nextAppointment['id'] ?>"><input type="hidden" name="status" value="confirmado"><button class="bb-btn bb-btn--confirm bb-btn--block" type="submit">Confirmar chegada</button></form>
      <?php elseif ($nextStatus === 'confirmado'): ?>
        <form method="post" data-bb-confirm="Iniciar o atendimento de <?= e($nextAppointment['client_name']) ?> agora?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$nextAppointment['id'] ?>"><input type="hidden" name="status" value="em_andamento"><button class="bb-btn bb-btn--start bb-btn--block" type="submit">Iniciar atendimento</button></form>
      <?php elseif ($nextStatus === 'em_andamento'): ?>
        <button type="button" class="bb-btn bb-btn--ok bb-btn--block" data-bs-toggle="offcanvas" data-bs-target="#finishSheet" aria-controls="finishSheet" aria-haspopup="dialog" data-id="<?= (int)$nextAppointment['id'] ?>" data-client="<?= e($nextAppointment['client_name']) ?>" data-service="<?= e($nextAppointment['service_name']) ?>" data-price="<?= e((string)$nextBasePrice) ?>">Finalizar atendimento</button>
      <?php endif; ?>
      <a class="bb-next-secondary" href="#appointment-<?= (int)$nextAppointment['id'] ?>">Ver na agenda</a>
    </div>
  </section>
<?php endif; ?>

<?php if (!$rows): ?>
  <div class="bb-empty"><span><?= icon_svg('calendar', 24) ?></span><strong>Agenda livre neste dia</strong><p>Nenhum cliente agendado até o momento.</p></div>
<?php else: ?>
  <section class="bb-agenda-section" aria-labelledby="bb-agenda-title">
    <div class="bb-section-heading"><div><span>Agenda do dia</span><h2 id="bb-agenda-title"><?= count($pending) ?> atendimento<?= count($pending) === 1 ? '' : 's' ?> pendente<?= count($pending) === 1 ? '' : 's' ?></h2></div></div>
    <div class="bb-list bb-timeline">
      <?php foreach ($pending as $a):
        $st = (string)($a['status'] ?? 'agendado');
        $prods = appointment_products($a);
        $basePrice = (float)$a['price'] - appointment_products_total($a);
        if ($basePrice < 0) {
            $basePrice = (float)$a['price'];
        }
      ?>
        <article class="bb-card" id="appointment-<?= (int)$a['id'] ?>" data-status="<?= e($st) ?>">
          <span class="bb-timeline-dot" aria-hidden="true"></span>
          <div class="bb-card-top">
            <time class="bb-time" datetime="<?= e($a['date'] . 'T' . $a['time']) ?>"><?= e(substr((string)$a['time'], 0, 5)) ?></time>
            <span class="bb-status bb-status--<?= e($st) ?>"><?= e(status_label($st)) ?></span>
          </div>
          <div class="bb-card-name"><?= e($a['client_name']) ?></div>
          <dl class="bb-card-facts">
            <div><dt><?= icon_svg('scissors', 14) ?> Serviço</dt><dd><?= e($a['service_name']) ?></dd></div>
            <div><dt><?= icon_svg('wallet', 14) ?> Valor</dt><dd class="bb-card-price"><?= e(money($basePrice)) ?></dd></div>
          </dl>
          <?php if ($prods): ?>
            <div class="bb-card-prods" aria-label="Produtos adicionados">
              <?php foreach ($prods as $pr): ?><span>+ <?= e($pr['name'] ?? 'Produto') ?> × <?= (int)($pr['qty'] ?? 0) ?></span><?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="bb-actions">
            <?php if ($st === 'agendado'): ?>
              <form method="post" class="bb-action-primary"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="confirmado"><button class="bb-btn bb-btn--confirm" type="submit">Confirmar chegada</button></form>
            <?php elseif ($st === 'confirmado'): ?>
              <form method="post" class="bb-action-primary" data-bb-confirm="Iniciar o atendimento de <?= e($a['client_name']) ?> agora?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="em_andamento"><button class="bb-btn bb-btn--start" type="submit">Iniciar atendimento</button></form>
            <?php elseif ($st === 'em_andamento'): ?>
              <button type="button" class="bb-btn bb-btn--ok bb-action-primary" data-bs-toggle="offcanvas" data-bs-target="#finishSheet" aria-controls="finishSheet" aria-haspopup="dialog" data-id="<?= (int)$a['id'] ?>" data-client="<?= e($a['client_name']) ?>" data-service="<?= e($a['service_name']) ?>" data-price="<?= e((string)$basePrice) ?>">Finalizar atendimento</button>
            <?php endif; ?>
            <?php if ($st !== 'em_andamento'): ?>
              <form method="post" class="bb-action-secondary" data-bb-confirm="Marcar <?= e($a['client_name']) ?> como falta? Essa ação libera o horário."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="faltou"><button class="bb-btn bb-btn--warn" type="submit">Marcar falta</button></form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($done): ?>
    <section class="bb-agenda-section bb-agenda-section--done" aria-labelledby="bb-done-title">
      <div class="bb-section-heading"><div><span>Encerrados</span><h2 id="bb-done-title">Atendimentos finalizados</h2></div><strong><?= count($done) ?></strong></div>
      <div class="bb-list bb-timeline bb-timeline--done">
        <?php foreach ($done as $a):
          $prods = appointment_products($a);
          $doneStatus = (string)$a['status'];
        ?>
          <article class="bb-card bb-card--muted" data-status="<?= e($doneStatus) ?>">
            <span class="bb-timeline-dot" aria-hidden="true"></span>
            <div class="bb-card-top"><time class="bb-time" datetime="<?= e($a['date'] . 'T' . $a['time']) ?>"><?= e(substr((string)$a['time'], 0, 5)) ?></time><span class="bb-status bb-status--<?= e($doneStatus) ?>"><?= e(status_label($doneStatus)) ?></span></div>
            <div class="bb-card-name"><?= e($a['client_name']) ?></div>
            <dl class="bb-card-facts"><div><dt><?= icon_svg('scissors', 14) ?> Serviço</dt><dd><?= e($a['service_name']) ?></dd></div><div><dt><?= icon_svg('wallet', 14) ?> Total</dt><dd class="bb-card-price"><?= e(money((float)$a['price'])) ?></dd></div></dl>
            <?php if ($prods): ?><div class="bb-card-prods"><?php foreach ($prods as $pr): ?><span>+ <?= e($pr['name'] ?? 'Produto') ?> × <?= (int)($pr['qty'] ?? 0) ?></span><?php endforeach; ?></div><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>
