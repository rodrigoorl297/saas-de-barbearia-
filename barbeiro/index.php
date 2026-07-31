<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['barbeiro']);
$today = date('Y-m-d');
$date = $_GET['date'] ?? ($_POST['date'] ?? $today);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today;
}
$partial = isset($_GET['partial']) && $_GET['partial'] === '1';

$sellableProducts = array_values(array_filter(
    store_read('stock'),
    fn($p) => (!isset($p['active']) || !empty($p['active'])) && (float)($p['price'] ?? 0) > 0 && (int)($p['qty'] ?? 0) > 0
));
usort($sellableProducts, fn($a, $b) => strcmp((string)$a['name'], (string)$b['name']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'status';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'finalize') {
        foreach (store_read('appointments') as $a) {
            if ((int)$a['id'] !== $id || (int)$a['barber_id'] !== (int)$user['id']) {
                continue;
            }

            $oldProductsTotal = appointment_products_total($a);
            $servicePart = max(0, (float)($a['price'] ?? 0) - $oldProductsTotal);
            $added = [];
            $qtys = $_POST['prod_qty'] ?? [];
            if (!is_array($qtys)) {
                $qtys = [];
            }
            $stockById = [];
            foreach (store_read('stock') as $p) {
                $stockById[(int)$p['id']] = $p;
            }

            foreach ($qtys as $pid => $qtyRaw) {
                $pid = (int)$pid;
                $qty = max(0, (int)$qtyRaw);
                if ($pid < 1 || $qty < 1) {
                    continue;
                }
                $p = $stockById[$pid] ?? null;
                if (!$p || (float)($p['price'] ?? 0) <= 0) {
                    continue;
                }
                $err = stock_consume($pid, $qty, 'out_venda', (int)$user['id'], 'Atendimento #' . $id, false);
                if ($err) {
                    flash('danger', $err);
                    redirect(url('barbeiro/?date=' . urlencode($date)));
                }
                $unit = (float)$p['price'];
                $added[] = [
                    'id' => $pid,
                    'name' => (string)$p['name'],
                    'qty' => $qty,
                    'unit_price' => $unit,
                    'total' => $unit * $qty,
                ];
            }

            $products = array_merge(appointment_products($a), $added);
            $prodTotal = 0.0;
            foreach ($products as $item) {
                $prodTotal += (float)($item['total'] ?? 0);
            }
            $a['products'] = $products;
            $a['price'] = $servicePart + $prodTotal;
            $a['status'] = 'concluido';
            $client = find_user_by_id((int)($a['client_id'] ?? 0));
            if ($client) {
                $a['client_name'] = $client['name'] ?? $a['client_name'];
            }
            save_appointment($a);
            sync_appointment_cash($a, (int)$user['id']);
            sync_appointment_loyalty($a);
            $msg = 'Atendimento concluído · ' . money((float)$a['price']);
            if ($added) {
                $msg .= ' (com ' . count($added) . ' produto(s))';
            }
            flash('success', $msg);
            redirect(url('barbeiro/?date=' . urlencode($date)));
        }
        flash('danger', 'Atendimento não encontrado.');
        redirect(url('barbeiro/?date=' . urlencode($date)));
    }

    if (isset($_POST['status'], $_POST['id'])) {
        $status = (string) $_POST['status'];
        $allowed = ['agendado', 'confirmado', 'cancelado', 'faltou'];
        if (in_array($status, $allowed, true)) {
            foreach (store_read('appointments') as $a) {
                if ((int)$a['id'] === $id && (int)$a['barber_id'] === (int)$user['id']) {
                    $a['status'] = $status;
                    save_appointment($a);
                    flash('success', 'Atualizado: ' . status_label($status));
                    break;
                }
            }
        }
        redirect(url('barbeiro/?date=' . urlencode($date)));
    }
}

$rows = appointments_enriched(fn($a) => (int)$a['barber_id'] === (int)$user['id'] && $a['date'] === $date);
usort($rows, fn($a, $b) => strcmp((string)$a['time'], (string)$b['time']));
$pending = array_values(array_filter($rows, fn($a) => !in_array($a['status'] ?? '', ['concluido', 'cancelado', 'faltou'], true)));
$done = array_values(array_filter($rows, fn($a) => in_array($a['status'] ?? '', ['concluido', 'cancelado', 'faltou'], true)));

if ($partial) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    require __DIR__ . '/_hoje_body.php';
    exit;
}

barber_shell_start($date === $today ? 'Hoje' : 'Agenda', 'hoje');
?>
<div class="bb-date">
  <form method="get" action="<?= e(url('barbeiro/')) ?>">
    <label>Dia
      <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
    </label>
  </form>
  <div class="bb-live-tools">
    <?php if ($date !== $today): ?>
      <a class="bb-link" href="<?= e(url('barbeiro/')) ?>">Hoje</a>
    <?php endif; ?>
    <button type="button" class="bb-link bb-refresh-btn" id="bb-refresh-now">Atualizar</button>
  </div>
</div>
<p class="bb-live-hint" id="bb-live-hint">Atualiza sozinho a cada 20s</p>

<div id="bb-live" data-date="<?= e($date) ?>">
<?php require __DIR__ . '/_hoje_body.php'; ?>
</div>

<div class="offcanvas offcanvas-bottom bb-sheet" tabindex="-1" id="finishSheet">
  <div class="bb-sheet-head">
    <div>
      <h2 class="bb-sheet-title">Concluir</h2>
      <div class="bb-sheet-sub" id="finishClient">—</div>
    </div>
    <button type="button" class="bb-sheet-close" data-bs-dismiss="offcanvas" aria-label="Fechar">×</button>
  </div>
  <div class="bb-sheet-body">
    <form method="post" id="finishForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="finalize">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="id" id="finishId" value="">
      <input type="hidden" name="base_price" id="finishBase" value="0">

      <p class="bb-sheet-meta">Serviço: <strong id="finishService">—</strong></p>
      <p class="bb-sheet-meta">Serviços: <strong class="bb-money" id="finishServiceTotal">R$ 0,00</strong></p>

      <?php if ($sellableProducts): ?>
        <h3 class="bb-sec" style="margin-top:8px">Cliente comprou?</h3>
        <div class="bb-finish-list">
          <?php foreach ($sellableProducts as $p): ?>
            <div class="bb-finish-row">
              <div class="bb-finish-info">
                <strong><?= e($p['name']) ?></strong>
                <span><?= e(money((float)$p['price'])) ?> · <?= (int)$p['qty'] ?> un.</span>
              </div>
              <input
                type="number"
                class="finish-qty"
                name="prod_qty[<?= (int)$p['id'] ?>]"
                min="0"
                max="<?= (int)$p['qty'] ?>"
                value="0"
                inputmode="numeric"
                data-price="<?= (float)$p['price'] ?>"
              >
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="bb-live-hint">Sem produtos à venda no estoque.</p>
      <?php endif; ?>

      <div class="bb-finish-total">
        <span>Total</span>
        <strong class="bb-money" id="finishTotal">R$ 0,00</strong>
      </div>
      <button class="bb-btn bb-btn--ok bb-btn--block" type="submit">Finalizar e faturar</button>
    </form>
  </div>
</div>

<script>
(() => {
  const box = document.getElementById('bb-live');
  const hint = document.getElementById('bb-live-hint');
  const btn = document.getElementById('bb-refresh-now');
  const sheet = document.getElementById('finishSheet');
  const money = (n) => 'R$ ' + Number(n).toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+,)/g, '$1.');

  function recalc() {
    const base = parseFloat(document.getElementById('finishBase').value || '0') || 0;
    let extra = 0;
    document.querySelectorAll('.finish-qty').forEach((el) => {
      const q = parseInt(el.value || '0', 10) || 0;
      const p = parseFloat(el.getAttribute('data-price') || '0') || 0;
      extra += q * p;
    });
    const svc = document.getElementById('finishServiceTotal');
    if (svc) svc.textContent = money(base);
    document.getElementById('finishTotal').textContent = money(base + extra);
  }

  document.querySelectorAll('.finish-qty').forEach((el) => el.addEventListener('input', recalc));

  if (sheet) {
    sheet.addEventListener('show.bs.offcanvas', (ev) => {
      const t = ev.relatedTarget;
      if (!t) return;
      document.getElementById('finishId').value = t.getAttribute('data-id') || '';
      document.getElementById('finishClient').textContent = t.getAttribute('data-client') || '—';
      document.getElementById('finishService').textContent = t.getAttribute('data-service') || '—';
      document.getElementById('finishBase').value = t.getAttribute('data-price') || '0';
      document.querySelectorAll('.finish-qty').forEach((el) => { el.value = '0'; });
      recalc();
    });
  }

  if (!box) return;
  const date = box.getAttribute('data-date') || '';
  const url = <?= json_encode(url('barbeiro/')) ?> + '?partial=1&date=' + encodeURIComponent(date);
  let busy = false;
  async function tick(manual) {
    if (busy) return;
    if (document.hidden && !manual) return;
    if (sheet && sheet.classList.contains('show')) return;
    const ae = document.activeElement;
    if (!manual && ae && (ae.tagName === 'INPUT' || ae.tagName === 'SELECT' || ae.tagName === 'TEXTAREA')) return;
    busy = true;
    try {
      const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
      if (res.redirected) { location.reload(); return; }
      const html = await res.text();
      if (html && html.indexOf('bb-kpis') !== -1) box.innerHTML = html;
      if (hint) {
        const n = new Date();
        hint.textContent = 'Atualiza sozinho · ' + String(n.getHours()).padStart(2,'0') + ':' + String(n.getMinutes()).padStart(2,'0') + ':' + String(n.getSeconds()).padStart(2,'0');
      }
    } catch (e) {
      if (hint) hint.textContent = 'Falha ao atualizar';
    } finally { busy = false; }
  }
  if (btn) btn.addEventListener('click', () => tick(true));
  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(false); });
  setInterval(() => tick(false), 20000);
})();
</script>
<?php barber_shell_end('hoje'); ?>