<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['barbeiro']);
$today = date('Y-m-d');
$date = $_GET['date'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'], $_POST['id'])) {
    $id = (int) $_POST['id'];
    $status = (string) $_POST['status'];
    $allowed = ['agendado', 'confirmado', 'concluido', 'cancelado', 'faltou'];
    if (in_array($status, $allowed, true)) {
        foreach (store_read('appointments') as $a) {
            if ((int)$a['id'] === $id && (int)$a['barber_id'] === (int)$user['id']) {
                $a['status'] = $status;
                if ($status === 'concluido') {
                    $client = find_user_by_id((int)($a['client_id'] ?? 0));
                    $a['client_name'] = $client['name'] ?? ($a['client_name'] ?? 'Cliente');
                    sync_appointment_cash($a, (int)$user['id']);
                }
                save_appointment($a);
                flash('success', 'Atualizado: ' . status_label($status));
                break;
            }
        }
    }
    redirect(url('barbeiro/?date=' . urlencode($date)));
}

$rows = appointments_enriched(fn($a) => (int)$a['barber_id'] === (int)$user['id'] && $a['date'] === $date);
usort($rows, fn($a, $b) => strcmp((string)$a['time'], (string)$b['time']));
$pending = array_values(array_filter($rows, fn($a) => !in_array($a['status'] ?? '', ['concluido', 'cancelado', 'faltou'], true)));
$done = array_values(array_filter($rows, fn($a) => in_array($a['status'] ?? '', ['concluido', 'cancelado', 'faltou'], true)));

barber_shell_start($date === $today ? 'Hoje' : 'Agenda', 'hoje');
?>
<form class="bb-date" method="get" action="<?= e(url('barbeiro/')) ?>">
  <label>
    <span>Dia</span>
    <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
  </label>
  <?php if ($date !== $today): ?>
    <a class="bb-link" href="<?= e(url('barbeiro/')) ?>">Voltar p/ hoje</a>
  <?php endif; ?>
</form>

<div class="bb-kpis">
  <div class="bb-kpi"><span>Na fila</span><strong><?= count($pending) ?></strong></div>
  <div class="bb-kpi"><span>Finalizados</span><strong><?= count($done) ?></strong></div>
</div>

<?php if (!$rows): ?>
  <div class="bb-empty">Nenhum cliente neste dia.</div>
<?php else: ?>
  <div class="bb-list">
    <?php foreach ($pending as $a):
      $phone = preg_replace('/\D+/', '', (string)($a['client_phone'] ?? ''));
      $wa = $phone !== '' ? 'https://wa.me/55' . $phone : '';
    ?>
      <article class="bb-card">
        <div class="bb-card-top">
          <div class="bb-time"><?= e($a['time']) ?></div>
          <div class="bb-status bb-status--<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></div>
        </div>
        <div class="bb-card-name"><?= e($a['client_name']) ?></div>
        <div class="bb-card-meta"><?= e($a['service_name']) ?> · <?= e(money((float)$a['price'])) ?></div>
        <?php if ($wa): ?>
          <a class="bb-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a>
        <?php endif; ?>
        <div class="bb-actions">
          <?php if (($a['status'] ?? '') !== 'confirmado'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="confirmado"><button type="submit" class="bb-btn bb-btn--ghost">Confirmou</button></form>
          <?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="concluido"><button type="submit" class="bb-btn bb-btn--ok">Concluir</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="status" value="faltou"><button type="submit" class="bb-btn bb-btn--warn">Faltou</button></form>
        </div>
      </article>
    <?php endforeach; ?>

    <?php if ($done): ?>
      <h2 class="bb-sec">Já finalizados</h2>
      <?php foreach ($done as $a): ?>
        <article class="bb-card bb-card--muted">
          <div class="bb-card-top">
            <div class="bb-time"><?= e($a['time']) ?></div>
            <div class="bb-status bb-status--<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></div>
          </div>
          <div class="bb-card-name"><?= e($a['client_name']) ?></div>
          <div class="bb-card-meta"><?= e($a['service_name']) ?> · <?= e(money((float)$a['price'])) ?></div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php barber_shell_end('hoje'); ?>
