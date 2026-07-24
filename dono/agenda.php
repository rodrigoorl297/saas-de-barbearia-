<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);
$date = $_POST['date'] ?? ($_GET['date'] ?? date('Y-m-d'));
$view = ($_POST['view'] ?? ($_GET['view'] ?? 'agenda')) === 'historico' ? 'historico' : 'agenda';
$servicesCatalog = active_services();
$agendaUrl = static function (string $d, string $v = 'agenda') {
    return url('dono/agenda.php?date=' . urlencode($d) . '&view=' . urlencode($v));
};

// ── POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'status';
    $id     = (int)($_POST['id'] ?? 0);

    $all = store_read('appointments');
    foreach ($all as &$a) {
        if ((int)$a['id'] !== $id) {
            continue;
        }

        if ($action === 'finalize' || $action === 'update_services') {
            $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['service_ids'] ?? []))));
            if (!$ids && !empty($a['service_id'])) {
                $ids = [(int)$a['service_id']];
            }
            $picked = services_by_ids($ids);
            if (!$picked) {
                flash('danger', 'Selecione ao menos um serviço.');
                redirect($agendaUrl($date, $view));
            }
            $price = array_sum(array_map(fn($s) => (float)$s['price'], $picked));
            $a['service_ids'] = $ids;
            $a['service_id'] = $ids[0];
            $a['price'] = $price;
            $doFinish = $action === 'finalize' || !empty($_POST['also_finish']) || ($a['status'] ?? '') === 'concluido';
            if ($doFinish || $action === 'finalize') {
                $a['status'] = 'concluido';
                save_appointment($a);
                sync_appointment_cash($a, (int)$user['id']);
                flash('success', 'Serviços atualizados e faturados: ' . money($price));
                // Concluído sai da agenda e vai para o histórico
                redirect($agendaUrl($date, 'historico'));
            } else {
                save_appointment($a);
                flash('success', 'Serviços atualizados. Total agora: ' . money($price));
            }
        } elseif ($action === 'status') {
            $st = $_POST['status'] ?? '';
            // Concluído só pelo modal de finalização (permite extras)
            if ($st === 'concluido') {
                flash('warning', 'Para concluir, use + Serviços e finalizar.');
                redirect($agendaUrl($date, $view));
            }
            $a['status'] = $st;
            save_appointment($a);
            flash('success', 'Status atualizado.');
        } elseif ($action === 'barber') {
            $bid = (int)($_POST['barber_id'] ?? 0);
            if ($bid > 0) {
                $a['barber_id'] = $bid;
                save_appointment($a);
                flash('success', 'Barbeiro atualizado.');
            }
        }
        break;
    }
    redirect($agendaUrl($date, $view));
}

// ── dados ────────────────────────────────────────────────────────
$barbers = active_barbers();
$rows    = appointments_enriched(fn($a) => $a['date'] === $date);
$activeRows = array_values(array_filter($rows, fn($a) => !in_array($a['status'] ?? '', ['concluido', 'cancelado', 'faltou'], true)));
$historyRows = array_values(array_filter($rows, fn($a) => in_array($a['status'] ?? '', ['concluido', 'cancelado', 'faltou'], true)));
usort($historyRows, fn($a, $b) => strcmp($b['time'], $a['time']));

$byBarber = [];
foreach ($barbers as $b) {
    $byBarber[(int)$b['id']] = ['barber' => $b, 'cards' => []];
}
foreach ($activeRows as $a) {
    $bid = (int)$a['barber_id'];
    if (!isset($byBarber[$bid])) {
        continue;
    }
    $byBarber[$bid]['cards'][] = $a;
}
foreach ($byBarber as &$col) {
    usort($col['cards'], fn($a, $b) => strcmp($a['time'], $b['time']));
}
unset($col);

$total = count($rows);
$pendentes = count($activeRows);
$concluidos = count(array_filter($rows, fn($a) => $a['status'] === 'concluido'));
$faturado = array_sum(array_map(fn($a) => $a['status'] === 'concluido' ? (float)$a['price'] : 0, $rows));

$statusColors = [
    'agendado'   => '#6366f1',
    'confirmado' => '#10b981',
    'concluido'  => '#22c55e',
    'cancelado'  => '#ef4444',
    'faltou'     => '#f59e0b',
];
$statusLabels = [
    'agendado'   => 'Agendado',
    'confirmado' => 'Confirmado',
    'concluido'  => 'Concluído',
    'cancelado'  => 'Cancelado',
    'faltou'     => 'Faltou',
];

admin_layout_start('Agenda', 'dono', 'agenda');
?>

<div class="agenda-header-bar">
  <form class="agenda-date-form" method="get">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <label class="agenda-date-label">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
    </label>
  </form>
  <div class="agenda-view-tabs">
    <a class="agenda-view-tab <?= $view === 'agenda' ? 'active' : '' ?>" href="<?= e($agendaUrl($date, 'agenda')) ?>">Agenda</a>
    <a class="agenda-view-tab <?= $view === 'historico' ? 'active' : '' ?>" href="<?= e($agendaUrl($date, 'historico')) ?>">
      Histórico
      <?php if ($concluidos > 0): ?><span class="agenda-view-count"><?= $concluidos ?></span><?php endif; ?>
    </a>
  </div>
  <div class="agenda-stats">
    <div class="agenda-stat">
      <span class="agenda-stat-val"><?= $pendentes ?></span>
      <span class="agenda-stat-lbl">Na fila</span>
    </div>
    <div class="agenda-stat">
      <span class="agenda-stat-val"><?= $concluidos ?></span>
      <span class="agenda-stat-lbl">Concluídos</span>
    </div>
    <div class="agenda-stat accent">
      <span class="agenda-stat-val"><?= money($faturado) ?></span>
      <span class="agenda-stat-lbl">Faturado</span>
    </div>
  </div>
</div>

<?php if ($view === 'agenda'): ?>
<p class="small text-secondary mb-3 px-1">Ao finalizar, o atendimento sai da agenda e vai para o <strong>Histórico</strong>. Use <strong>+ Serviços</strong> para incluir o que o cliente pediu na hora.</p>

<div class="agenda-board">
  <?php foreach ($byBarber as $col): ?>
    <?php
      $b = $col['barber'];
      $cards = $col['cards'];
      $photo = media_url($b['avatar'] ?? '');
    ?>
    <div class="agenda-col">
      <div class="agenda-col-header">
        <div class="agenda-col-avatar">
          <?php if ($photo): ?>
            <img src="<?= e($photo) ?>" alt="<?= e($b['name']) ?>">
          <?php else: ?>
            <span><?= e(initials($b['name'])) ?></span>
          <?php endif; ?>
        </div>
        <div class="agenda-col-info">
          <strong><?= e($b['name']) ?></strong>
          <span><?= count($cards) ?> na fila</span>
        </div>
        <div class="agenda-col-badge"><?= count($cards) ?></div>
      </div>

      <div class="agenda-cards">
        <?php if (!$cards): ?>
          <div class="agenda-empty">Nenhum pendente</div>
        <?php endif; ?>

        <?php foreach ($cards as $a):
          $sc = $statusColors[$a['status']] ?? '#6b7280';
          $sl = $statusLabels[$a['status']] ?? $a['status'];
          $sidList = !empty($a['service_ids']) ? $a['service_ids'] : [(int)($a['service_id'] ?? 0)];
          $sidList = array_values(array_filter(array_map('intval', $sidList)));
          $isCancel = ($a['status'] ?? '') === 'cancelado';
        ?>
          <div class="agenda-card" data-status="<?= e($a['status']) ?>">
            <div class="agenda-card-top">
              <span class="agenda-card-time"><?= e($a['time']) ?></span>
              <span class="agenda-card-status" style="--sc:<?= $sc ?>;"><?= $sl ?></span>
            </div>

            <div class="agenda-card-client">
              <strong><?= e($a['client_name']) ?></strong>
              <a href="tel:<?= e($a['client_phone']) ?>" class="agenda-card-phone"><?= e($a['client_phone']) ?></a>
            </div>

            <div class="agenda-card-service">
              <span><?= e($a['service_name'] ?? '—') ?></span>
              <strong><?= money((float)$a['price']) ?></strong>
            </div>

            <?php if (!$isCancel): ?>
              <button
                type="button"
                class="btn btn-sm btn-accent w-100 agenda-add-svc-btn"
                data-bs-toggle="modal"
                data-bs-target="#finalizeModal"
                data-apt-id="<?= (int)$a['id'] ?>"
                data-apt-client="<?= e($a['client_name']) ?>"
                data-apt-services="<?= e(implode(',', $sidList)) ?>"
                data-apt-done="0"
              >+ Serviços e finalizar</button>
            <?php endif; ?>

            <div class="agenda-card-actions">
              <form method="post" class="agenda-form-inline">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="view" value="agenda">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <select name="status" class="agenda-select" onchange="agendaOnStatus(this)" data-prev="<?= e($a['status']) ?>">
                  <?php foreach ($statusLabels as $sv => $slv): ?>
                    <?php if ($sv === 'concluido') continue; ?>
                    <option value="<?= $sv ?>" <?= $a['status'] === $sv ? 'selected' : '' ?>><?= $slv ?></option>
                  <?php endforeach; ?>
                </select>
              </form>

              <form method="post" class="agenda-form-inline">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="view" value="agenda">
                <input type="hidden" name="action" value="barber">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <select name="barber_id" class="agenda-select agenda-select-barber" onchange="this.form.submit()" title="Trocar barbeiro">
                  <?php foreach ($barbers as $bx): ?>
                    <option value="<?= (int)$bx['id'] ?>" <?= (int)$bx['id'] === (int)$a['barber_id'] ? 'selected' : '' ?>>
                      ✂ <?= e($bx['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<p class="small text-secondary mb-3 px-1">Histórico do dia <?= e(date('d/m/Y', strtotime($date))) ?>. Concluídos somem da agenda e ficam aqui — ainda dá para ajustar serviços e o faturado.</p>

<div class="stock-panel">
  <?php if (!$historyRows): ?>
    <div class="stock-empty">
      <p>Nenhum atendimento no histórico deste dia.</p>
      <a class="btn btn-accent" href="<?= e($agendaUrl($date, 'agenda')) ?>">Voltar à agenda</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table stock-table align-middle mb-0">
        <thead>
          <tr>
            <th>Hora</th>
            <th>Cliente</th>
            <th>Barbeiro</th>
            <th>Serviços</th>
            <th>Valor</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($historyRows as $a):
          $sidList = !empty($a['service_ids']) ? $a['service_ids'] : [(int)($a['service_id'] ?? 0)];
          $sidList = array_values(array_filter(array_map('intval', $sidList)));
          $isDone = ($a['status'] ?? '') === 'concluido';
          $sc = $statusColors[$a['status']] ?? '#6b7280';
          $sl = $statusLabels[$a['status']] ?? $a['status'];
        ?>
          <tr>
            <td class="fw-bold"><?= e($a['time']) ?></td>
            <td>
              <?= e($a['client_name']) ?>
              <div class="small text-secondary"><?= e($a['client_phone']) ?></div>
            </td>
            <td><?= e($a['barber_name'] ?? '—') ?></td>
            <td><?= e($a['service_name'] ?? '—') ?></td>
            <td class="fw-bold"><?= money((float)$a['price']) ?></td>
            <td><span class="agenda-card-status" style="--sc:<?= $sc ?>;"><?= e($sl) ?></span></td>
            <td class="text-end">
              <?php if ($isDone): ?>
                <button
                  type="button"
                  class="btn btn-sm btn-accent"
                  data-bs-toggle="modal"
                  data-bs-target="#finalizeModal"
                  data-apt-id="<?= (int)$a['id'] ?>"
                  data-apt-client="<?= e($a['client_name']) ?>"
                  data-apt-services="<?= e(implode(',', $sidList)) ?>"
                  data-apt-done="1"
                >Ajustar</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="finalizeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="finalizeTitle">Adicionar serviços</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="small text-secondary mb-2">Cliente: <strong id="finalizeClient">—</strong></p>
        <p class="small mb-3" style="color:#0f172a">Marque tudo que foi feito. Se pediu algo na hora (barba, sobrancelha…), marque aqui também.</p>
        <form method="post" class="vstack gap-3" id="finalizeForm">
          <input type="hidden" name="date" value="<?= e($date) ?>">
          <input type="hidden" name="view" id="finalizeView" value="<?= e($view) ?>">
          <input type="hidden" name="id" id="finalizeId" value="">
          <div class="finalize-services" id="finalizeServices">
            <?php foreach ($servicesCatalog as $svc): ?>
              <label class="finalize-service-item">
                <input
                  type="checkbox"
                  name="service_ids[]"
                  value="<?= (int)$svc['id'] ?>"
                  data-price="<?= e((string)$svc['price']) ?>"
                  class="form-check-input finalize-svc"
                >
                <span class="finalize-service-name"><?= e($svc['name']) ?></span>
                <span class="finalize-service-price"><?= e(money((float)$svc['price'])) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="d-flex justify-content-between align-items-center finalize-total">
            <span>Total</span>
            <strong id="finalizeTotal">R$ 0,00</strong>
          </div>
          <div class="d-flex flex-wrap gap-2 justify-content-end">
            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-ghost" type="submit" name="action" value="update_services" id="finalizeSaveOnly">Só salvar serviços</button>
            <button class="btn btn-accent" type="submit" name="action" value="finalize" id="finalizeSubmit">Finalizar e faturar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function agendaOnStatus(sel) {
  if (sel.value === 'concluido') {
    sel.value = sel.dataset.prev || 'agendado';
    return;
  }
  sel.form.submit();
}

function moneyBr(v) {
  return 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function updateFinalizeTotal() {
  let total = 0;
  document.querySelectorAll('.finalize-svc:checked').forEach((el) => {
    total += parseFloat(el.dataset.price || '0') || 0;
  });
  const out = document.getElementById('finalizeTotal');
  if (out) out.textContent = moneyBr(total);
}

document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('finalizeModal');
  if (!modal) return;

  modal.addEventListener('show.bs.modal', (ev) => {
    const btn = ev.relatedTarget;
    if (!btn) return;
    const id = btn.getAttribute('data-apt-id') || '';
    const client = btn.getAttribute('data-apt-client') || '—';
    const selected = (btn.getAttribute('data-apt-services') || '').split(',').filter(Boolean);
    const done = btn.getAttribute('data-apt-done') === '1';

    document.getElementById('finalizeId').value = id;
    document.getElementById('finalizeClient').textContent = client;
    document.getElementById('finalizeTitle').textContent = done ? 'Ajustar serviços' : 'Adicionar serviços';
    document.getElementById('finalizeView').value = done ? 'historico' : 'agenda';

    const saveOnly = document.getElementById('finalizeSaveOnly');
    const submitBtn = document.getElementById('finalizeSubmit');
    if (done) {
      saveOnly.classList.add('d-none');
      submitBtn.textContent = 'Salvar e atualizar faturado';
      submitBtn.value = 'update_services';
    } else {
      saveOnly.classList.remove('d-none');
      submitBtn.textContent = 'Finalizar e faturar';
      submitBtn.value = 'finalize';
    }

    document.querySelectorAll('.finalize-svc').forEach((el) => {
      el.checked = selected.includes(el.value);
    });
    updateFinalizeTotal();
  });

  document.querySelectorAll('.finalize-svc').forEach((el) => {
    el.addEventListener('change', updateFinalizeTotal);
  });
});
</script>
<?php admin_layout_end(); ?>
