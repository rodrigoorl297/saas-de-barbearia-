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
        $wantBook = !empty($_POST['also_book']);
        $wantNow = !empty($_POST['cut_now']);

        if ($name === '' || strlen($phone) < 10) {
            flash('danger', 'Nome e telefone (com DDD) são obrigatórios.');
            redirect(url('dono/clientes.php'));
        }

        if ($wantBook && ($serviceId < 1 || $barberId < 1 || $time === '')) {
            flash('danger', 'Para agendar, informe serviço, barbeiro e horário.');
            redirect(url('dono/clientes.php' . ($id > 0 ? '?edit=' . $id : '')));
        }
        
        if ($wantNow && ($serviceId < 1 || $barberId < 1)) {
            flash('danger', 'Para registrar o corte, informe serviço e barbeiro.');
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
        if ($wantBook || $wantNow) {
            $svc = find_service($serviceId);
            $barber = find_user_by_id($barberId);
            
            if ($wantNow) {
                $nowTime = date('H:i');
                $new = save_appointment([
                    'client_id' => (int)$client['id'],
                    'client_name' => $client['name'],
                    'client_phone' => $client['phone'],
                    'barber_id' => $barberId,
                    'service_id' => $serviceId,
                    'service_ids' => [$serviceId],
                    'date' => $today,
                    'time' => $nowTime,
                    'status' => 'em_andamento',
                    'notes' => 'Walk-in',
                    'price' => (float)($svc['price'] ?? 0),
                    'products' => [],
                ]);
                flash('success', $msg . ' Atendimento iniciado e marcado como Em andamento.');
                redirect(url('dono/agenda.php?date=' . urlencode($today)));
            } else {
                $booked = book_service_for_client((int)$client['id'], $barberId, $serviceId, $date, $time);
                if (is_string($booked)) {
                    flash('warning', $msg . ' Agendamento não criado: ' . $booked);
                    redirect(url('dono/clientes.php'));
                }
                $msg .= ' Agendado: ' . ($svc['name'] ?? 'serviço') . ' com ' . ($barber['name'] ?? 'barbeiro') . ' em ' . date('d/m/Y', strtotime($date)) . ' às ' . normalize_time($time) . '.';
                flash('success', $msg);
                redirect(url('dono/agenda.php?date=' . urlencode($date)));
            }
        }
        flash('success', $msg);
    }
    redirect(url('dono/clientes.php'));
}

$q = trim((string)($_GET['q'] ?? ''));
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

if ($q !== '') {
    $needle = mb_strtolower($q);
    $clients = array_values(array_filter($clients, function ($c) use ($needle) {
        $hay = mb_strtolower(($c['name'] ?? '') . ' ' . ($c['phone'] ?? ''));
        return str_contains($hay, $needle);
    }));
}

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
  <div class="stock-toolbar d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <div>
      <h2 class="stock-heading">Clientes</h2>
      <p class="stock-sub">Cadastre o cliente e já agende serviço + barbeiro.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <form method="get" action="<?= e(url('dono/clientes.php')) ?>" class="d-flex m-0">
        <input type="search" name="q" value="<?= e($q) ?>" class="form-control admin-search-field" placeholder="Buscar nome ou telefone" aria-label="Buscar clientes">
        <button type="submit" class="btn btn-ghost ms-2" aria-label="Buscar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
      </form>
      <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#clientModal">+ Novo cliente</button>
    </div>
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
                <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$c['id'] ?>">Ficha / Agendar</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$clients): ?>
          <tr><td colspan="6"><div class="empty-state table-empty"><strong>Nenhum cliente cadastrado</strong><p>Comece cadastrando o primeiro cliente para acompanhar visitas e agendamentos.</p><button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#clientModal">Cadastrar cliente</button></div></td></tr>
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
          <?= csrf_field() ?>
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
          <ul class="nav nav-tabs client-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link active" data-tab="save" role="tab">Cadastro</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link" data-tab="book" role="tab">Agendar</button>
            </li>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link text-success" data-tab="now" role="tab">Iniciar corte</button>
            </li>
          </ul>
          <input type="hidden" name="also_book" id="alsoBookHidden" value="0">
          <input type="hidden" name="cut_now" id="cutNowHidden" value="0">
          <p class="form-text mb-2" id="tabHint">Salva só os dados do cliente.</p>

          <div id="bookFields" class="d-none">
            <div>
              <label class="form-label">Tipo de serviço</label>
              <select name="service_id" id="bookService" class="form-select">
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
              <select name="barber_id" id="bookBarber" class="form-select">
                <option value="">Selecione…</option>
                <?php foreach ($barbers as $b): ?>
                  <option value="<?= (int)$b['id'] ?>" <?= $formBarber === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!$barbers): ?>
                <div class="form-text text-danger">Cadastre um barbeiro antes de agendar.</div>
              <?php endif; ?>
            </div>
            <div class="row g-2 mt-1">
              <div class="col-6">
                <label class="form-label">Data</label>
                <input type="date" name="date" id="bookDate" class="form-control" value="<?= e($formDate) ?>">
              </div>
              <div class="col-6">
                <label class="form-label">Horário</label>
                <select name="time" id="bookTime" class="form-select">
                  <option value="">Selecione…</option>
                  <?php foreach ($formSlots as $slot): ?>
                    <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-text mt-1">Horários passados de hoje não aparecem. Escolha outra data se precisar.</div>
            <?php if (!$formSlots && $barbers): ?>
              <div class="form-text text-danger mt-1">Sem horários livres neste dia/barbeiro. Troque a data ou o barbeiro.</div>
            <?php endif; ?>
          </div>

          <div class="d-flex gap-2 justify-content-end">
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="<?= e(url('dono/clientes.php')) ?>">Cancelar</a>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <?php endif; ?>
            <button class="btn btn-accent" type="submit" id="submitBtn"><?= $edit ? 'Salvar' : 'Salvar cliente' ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('clientModal');
  const tabs = document.querySelectorAll('.client-tabs .nav-link');
  const alsoBookHidden = document.getElementById('alsoBookHidden');
  const cutNowHidden = document.getElementById('cutNowHidden');
  const book = document.getElementById('bookFields');
  const svc = document.getElementById('bookService');
  const barber = document.getElementById('bookBarber');
  const date = document.getElementById('bookDate');
  const time = document.getElementById('bookTime');
  const tabHint = document.getElementById('tabHint');
  const submitBtn = document.getElementById('submitBtn');
  const slotsUrl = <?= json_encode(url('api/slots.php')) ?>;
  let activeTab = 'save';

  const hints = {
    save: 'Salva só os dados do cliente.',
    book: 'Escolha serviço, barbeiro, data e horário para agendar.',
    now: 'Inicia o atendimento como Em andamento. Quando terminar, finalize pela agenda.',
  };
  const submitLabels = {
    save: <?= json_encode($edit ? 'Salvar' : 'Salvar cliente') ?>,
    book: <?= json_encode($edit ? 'Salvar e agendar' : 'Salvar e agendar') ?>,
    now: 'Iniciar atendimento',
  };

  function setTab(tab) {
    activeTab = tab;
    tabs.forEach((btn) => btn.classList.toggle('active', btn.dataset.tab === tab));
    if (alsoBookHidden) alsoBookHidden.value = tab === 'book' ? '1' : '0';
    if (cutNowHidden) cutNowHidden.value = tab === 'now' ? '1' : '0';
    if (tabHint) tabHint.textContent = hints[tab] || hints.save;
    if (submitBtn) submitBtn.textContent = submitLabels[tab] || submitLabels.save;
    toggleBook();
  }

  function toggleBook() {
    if (!book) return;
    const isBook = activeTab === 'book';
    const isNow = activeTab === 'now';
    const on = isBook || isNow;

    book.classList.toggle('d-none', !on);

    if (date && time) {
        date.closest('.col-6').classList.toggle('d-none', isNow);
        time.closest('.col-6').classList.toggle('d-none', isNow);
    }

    [svc, barber].forEach((el) => {
      if (!el) return;
      if (on) el.setAttribute('required', 'required');
      else el.removeAttribute('required');
    });

    [date, time].forEach((el) => {
      if (!el) return;
      if (isBook) el.setAttribute('required', 'required');
      else el.removeAttribute('required');
    });

    if (isBook) loadSlots();
  }

  async function loadSlots() {
    if (!time) return;
    const bid = barber?.value || '';
    const d = date?.value || '';
    const sid = svc?.value || '';
    if (!bid || !d) {
      time.innerHTML = '<option value=\"\">Selecione…</option>';
      return;
    }
    time.innerHTML = '<option value=\"\">Carregando…</option>';
    try {
      const q = new URLSearchParams({ barber_id: bid, date: d, service_id: sid });
      const res = await fetch(slotsUrl + '?' + q.toString(), { credentials: 'same-origin', cache: 'no-store' });
      const data = await res.json();
      const slots = Array.isArray(data.slots) ? data.slots : [];
      let html = '<option value=\"\">Selecione…</option>';
      slots.forEach((s) => { html += '<option value=\"' + s + '\">' + s + '</option>'; });
      time.innerHTML = html;
      if (!slots.length) {
        time.innerHTML = '<option value=\"\">Sem horários neste dia</option>';
      }
    } catch (e) {
      time.innerHTML = '<option value=\"\">Falha ao carregar</option>';
    }
  }

  tabs.forEach((btn) => btn.addEventListener('click', () => setTab(btn.dataset.tab || 'save')));
  [svc, barber, date].forEach((el) => el && el.addEventListener('change', () => {
      if (activeTab === 'book') loadSlots();
  }));
  setTab('save');

  <?php if ($edit): ?>
  if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
  <?php endif; ?>
});
</script>
<?php admin_layout_end(); ?>
