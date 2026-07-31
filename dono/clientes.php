<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);
$services = active_services();
$barbers = active_barbers();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $barberId = (int)($_POST['barber_id'] ?? 0);
        $date = trim($_POST['date'] ?? $today);
        $time = trim($_POST['time'] ?? '');
        $wantBook = $id < 1 || !empty($_POST['also_book']);

        if ($name === '' || strlen($phone) < 10) {
            flash('danger', 'Nome e telefone (com DDD) são obrigatórios.');
            redirect(url('dono/clientes.php'));
        }

        if ($wantBook && ($serviceId < 1 || $barberId < 1 || $time === '')) {
            flash('danger', 'Para agendar, informe serviço, barbeiro e horário.');
            redirect(url('dono/clientes.php' . ($id > 0 ? '?edit=' . $id : '')));
        }

        try {
            $client = upsert_client([
                'id' => $id,
                'name' => $name,
                'phone' => $phone,
                'birth_date' => $birth_date,
            ]);
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
            redirect(url('dono/clientes.php'));
        }

        $msg = $id > 0 ? 'Cliente atualizado.' : 'Cliente cadastrado.';
        if ($wantBook) {
            $booked = book_service_for_client((int)$client['id'], $barberId, $serviceId, $date, $time);
            if (is_string($booked)) {
                flash('warning', $msg . ' Agendamento não criado: ' . $booked);
                redirect(url('dono/clientes.php'));
            }
            $svc = find_service($serviceId);
            $barber = find_user_by_id($barberId);
            $msg .= ' Agendado: ' . ($svc['name'] ?? 'serviço') . ' com ' . ($barber['name'] ?? 'barbeiro') . ' em ' . date('d/m/Y', strtotime($date)) . ' às ' . normalize_time($time) . '.';
        }
        flash('success', $msg);
    }
    redirect(url('dono/clientes.php'));
}

$appointments = store_read('appointments');
$clients = array_values(array_filter(store_read('users'), fn($u) => ($u['role'] ?? '') === 'cliente'));

foreach ($clients as &$c) {
    $phone = $c['phone'] ?? '';
    $related = array_filter($appointments, fn($a) => (int)($a['client_id'] ?? 0) === (int)$c['id'] || ($a['client_phone'] ?? '') === $phone);
    $c['visits'] = count($related);
    $dates = array_column($related, 'date');
    rsort($dates);
    $c['last_visit'] = $dates[0] ?? null;
}
unset($c);
usort($clients, fn($a, $b) => strcmp((string)($b['last_visit'] ?? ''), (string)($a['last_visit'] ?? '')));

$edit = null;
if (isset($_GET['edit'])) {
    $tmp = find_user_by_id((int)$_GET['edit']);
    if ($tmp && ($tmp['role'] ?? '') === 'cliente') {
        $edit = $tmp;
    }
}

$formBarber = (int)($_GET['barber_id'] ?? ($barbers[0]['id'] ?? 0));
$formDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? (string)$_GET['date'] : $today;
$formService = (int)($_GET['service_id'] ?? ($services[0]['id'] ?? 0));
$formDuration = 60;
foreach ($services as $s) {
    if ((int)$s['id'] === $formService) {
        $formDuration = max(15, (int)($s['duration_min'] ?? 60));
        break;
    }
}
$formSlots = $formBarber > 0 ? available_slots($formBarber, $formDate, $formDuration) : [];

admin_layout_start('Clientes', 'dono', 'clientes');
?>
<div class="stock-page">
  <div class="stock-toolbar">
    <div>
      <h2 class="stock-heading">Clientes</h2>
      <p class="stock-sub">Cadastre o cliente e já agende serviço + barbeiro.</p>
    </div>
    <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#clientModal">+ Novo cliente</button>
  </div>

  <div class="stock-panel">
    <div class="stock-panel-head">
      <div>
        <strong>Lista de clientes</strong>
        <span> · <?= count($clients) ?> registro(s)</span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table stock-table align-middle mb-0">
        <thead>
          <tr>
            <th class="col-prod">Nome</th>
            <th>Telefone</th>
            <th>Nascimento</th>
            <th class="col-num">Visitas</th>
            <th>Última visita</th>
            <th class="col-actions text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($clients as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?></strong></td>
            <td><?= e($c['phone'] ?? '') ?></td>
            <td><?= !empty($c['birth_date']) ? e(date('d/m/Y', strtotime($c['birth_date']))) : '—' ?></td>
            <td><?= (int)$c['visits'] ?></td>
            <td><?= $c['last_visit'] ? e(date('d/m/Y', strtotime($c['last_visit']))) : '—' ?></td>
            <td>
              <div class="stock-actions">
                <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$c['id'] ?>">Editar</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$clients): ?>
          <tr><td colspan="6" class="text-secondary text-center py-4">Nenhum cliente ainda.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><?= $edit ? 'Editar cliente' : 'Novo cliente' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" class="vstack gap-3" id="clientForm">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div>
            <label class="form-label">Nome</label>
            <input name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>">
          </div>
          <div>
            <label class="form-label">Telefone (WhatsApp)</label>
            <input name="phone" class="form-control" required value="<?= e($edit['phone'] ?? '') ?>" placeholder="Ex: 11999999999">
            <?php if (!$edit): ?>
              <div class="form-text">Senha inicial do cliente = telefone.</div>
            <?php endif; ?>
          </div>
          <div>
            <label class="form-label">Data de nascimento (opcional)</label>
            <input type="date" name="birth_date" class="form-control" value="<?= e($edit['birth_date'] ?? '') ?>">
          </div>

          <hr class="my-1">
          <?php if ($edit): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="also_book" value="1" id="alsoBook">
              <label class="form-check-label" for="alsoBook">Também agendar atendimento</label>
            </div>
          <?php endif; ?>

          <div id="bookFields" class="<?= $edit ? 'd-none' : '' ?>">
            <div>
              <label class="form-label">Tipo de serviço</label>
              <select name="service_id" id="bookService" class="form-select" <?= $edit ? '' : 'required' ?>>
                <option value="">Selecione…</option>
                <?php foreach ($services as $s): ?>
                  <option value="<?= (int)$s['id'] ?>" data-duration="<?= (int)$s['duration_min'] ?>" <?= $formService === (int)$s['id'] ? 'selected' : '' ?>>
                    <?= e($s['name']) ?> · <?= e(money((float)$s['price'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mt-3">
              <label class="form-label">Barbeiro</label>
              <select name="barber_id" id="bookBarber" class="form-select" <?= $edit ? '' : 'required' ?>>
                <option value="">Selecione…</option>
                <?php foreach ($barbers as $b): ?>
                  <option value="<?= (int)$b['id'] ?>" <?= $formBarber === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-6">
                <label class="form-label">Data</label>
                <input type="date" name="date" id="bookDate" class="form-control" value="<?= e($formDate) ?>" <?= $edit ? '' : 'required' ?>>
              </div>
              <div class="col-6">
                <label class="form-label">Horário</label>
                <select name="time" id="bookTime" class="form-select" <?= $edit ? '' : 'required' ?>>
                  <option value="">Selecione…</option>
                  <?php foreach ($formSlots as $slot): ?>
                    <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <?php if (!$formSlots): ?>
              <div class="form-text text-danger mt-1">Sem horários livres neste dia/barbeiro. Troque a data ou o barbeiro.</div>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2 justify-content-end">
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="<?= e(url('dono/clientes.php')) ?>">Cancelar</a>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <?php endif; ?>
            <button class="btn btn-accent" type="submit"><?= $edit ? 'Salvar' : 'Salvar e agendar' ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('clientModal');
  const also = document.getElementById('alsoBook');
  const book = document.getElementById('bookFields');
  const svc = document.getElementById('bookService');
  const barber = document.getElementById('bookBarber');
  const date = document.getElementById('bookDate');
  const time = document.getElementById('bookTime');

  function toggleBook() {
    if (!book) return;
    const on = !also || also.checked;
    book.classList.toggle('d-none', !on);
    [svc, barber, date, time].forEach((el) => {
      if (!el) return;
      if (on) el.setAttribute('required', 'required');
      else el.removeAttribute('required');
    });
  }
  if (also) also.addEventListener('change', toggleBook);
  toggleBook();

  function reloadSlots() {
    const params = new URLSearchParams(window.location.search);
    if (svc?.value) params.set('service_id', svc.value);
    if (barber?.value) params.set('barber_id', barber.value);
    if (date?.value) params.set('date', date.value);
    <?php if ($edit): ?>
    params.set('edit', '<?= (int)$edit['id'] ?>');
    <?php endif; ?>
    const q = params.toString();
    window.location.href = <?= json_encode(url('dono/clientes.php')) ?> + (q ? ('?' + q) : '');
  }
  [svc, barber, date].forEach((el) => el && el.addEventListener('change', reloadSlots));

  <?php if ($edit || isset($_GET['barber_id']) || isset($_GET['date']) || isset($_GET['service_id'])): ?>
  if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
  <?php endif; ?>
});
</script>
<?php admin_layout_end(); ?>
