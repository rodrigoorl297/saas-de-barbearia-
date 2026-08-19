<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['barbeiro']);
$services = active_services();
$today = date('Y-m-d');
$barberId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = preg_replace('/\D+/', '', (string)($_POST['phone'] ?? ''));
        $birth = trim($_POST['birth_date'] ?? '');
        $serviceIdsInput = $_POST['service_ids'] ?? [];
        $serviceIds = is_array($serviceIdsInput)
            ? array_values(array_unique(array_filter(array_map('intval', $serviceIdsInput))))
            : [];
        $pickedServices = services_by_ids($serviceIds);
        $serviceIds = array_values(array_map(static fn($service) => (int)$service['id'], $pickedServices));
        $serviceId = (int)($serviceIds[0] ?? 0);
        $date = trim($_POST['date'] ?? $today);
        $time = trim($_POST['time'] ?? '');
        $wantBook = !empty($_POST['also_book']);
        $wantNow = !empty($_POST['cut_now']);

        if ($name === '' || strlen($phone) < 10) {
            flash('danger', 'Informe nome e telefone válidos (com DDD).');
            redirect(url('barbeiro/clientes.php'));
        }

        if ($wantBook && (!$pickedServices || $time === '')) {
            flash('danger', 'Para agendar, selecione ao menos um serviço e o horário.');
            redirect(url('barbeiro/clientes.php' . ($id > 0 ? '?edit=' . $id : '')));
        }
        
        if ($wantNow && !$pickedServices) {
            flash('danger', 'Para iniciar o atendimento, selecione ao menos um serviço.');
            redirect(url('barbeiro/clientes.php' . ($id > 0 ? '?edit=' . $id : '')));
        }

        try {
            $client = upsert_client([
                'id' => $id,
                'name' => $name,
                'phone' => $phone,
                'birth_date' => $birth,
            ]);
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
            redirect(url('barbeiro/clientes.php'));
        }

        $msg = $id > 0
            ? 'Cliente atualizado.'
            : 'Cliente cadastrado. Senha inicial = telefone.';

        if ($wantBook || $wantNow) {
            $servicesTotal = array_sum(array_map(static fn($service) => (float)($service['price'] ?? 0), $pickedServices));
            $serviceNames = implode(' + ', array_map(static fn($service) => (string)$service['name'], $pickedServices));
            
            if ($wantNow) {
                $nowTime = date('H:i');
                $new = save_appointment([
                    'client_id' => (int)$client['id'],
                    'client_name' => $client['name'],
                    'client_phone' => $client['phone'],
                    'barber_id' => $barberId,
                    'service_id' => $serviceId,
                    'service_ids' => $serviceIds,
                    'date' => $today,
                    'time' => $nowTime,
                    'status' => 'em_andamento',
                    'notes' => 'Walk-in',
                    'price' => $servicesTotal,
                    'products' => [],
                ]);
                flash('success', $msg . ' Atendimento iniciado e marcado como Em andamento. Total: ' . money($servicesTotal) . '.');
                redirect(url('barbeiro/?date=' . urlencode($today)));
            } else {
                $booked = book_services_for_client((int)$client['id'], $barberId, $serviceIds, $date, $time);
                if (is_string($booked)) {
                    flash('warning', $msg . ' Agendamento não criado: ' . $booked);
                    redirect(url('barbeiro/clientes.php'));
                }
                $msg .= ' Agendado: ' . ($serviceNames ?: 'serviço') . ' em ' . date('d/m/Y', strtotime($date)) . ' às ' . normalize_time($time) . '. Total: ' . money($servicesTotal) . '.';
            }
        }
        flash('success', $msg);
    }
    redirect(url('barbeiro/clientes.php'));
}

$q = trim((string)($_GET['q'] ?? ''));
$appointments = store_read('appointments');
$clients = array_values(array_filter(store_read('users'), fn($u) => ($u['role'] ?? '') === 'cliente' && !empty($u['active'])));

foreach ($clients as &$c) {
    $phone = preg_replace('/\D+/', '', (string)($c['phone'] ?? ''));
    $related = array_filter(
        $appointments,
        fn($a) => (int)($a['client_id'] ?? 0) === (int)$c['id']
            || preg_replace('/\D+/', '', (string)($a['client_phone'] ?? '')) === $phone
    );
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

$formDate = is_valid_iso_date((string)($_GET['date'] ?? '')) ? (string)$_GET['date'] : $today;
$formService = (int)($_GET['service_id'] ?? ($services[0]['id'] ?? 0));
$formDuration = 60;
foreach ($services as $s) {
    if ((int)$s['id'] === $formService) {
        $formDuration = max(15, (int)($s['duration_min'] ?? 60));
        break;
    }
}
$formSlots = available_slots($barberId, $formDate, $formDuration);
$openSheet = $edit || isset($_GET['service_id']) || isset($_GET['date']) || isset($_GET['new']);

barber_shell_start('Clientes', 'clientes');
?>
<p class="bb-lead">Cadastre o cliente, escolha o serviço e agende com você.</p>

<div class="bb-date" style="margin-bottom:12px">
  <form method="get" action="<?= e(url('barbeiro/clientes.php')) ?>" style="flex:1">
    <label>Buscar
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nome ou telefone" enterkeyhint="search">
    </label>
  </form>
  <a class="bb-btn bb-btn--ok" style="min-height:44px;padding:.55rem .9rem;align-self:flex-end;text-decoration:none;display:grid;place-items:center" href="<?= e(url('barbeiro/clientes.php?new=1')) ?>">+ Novo</a>
</div>

<?php if (!$clients): ?>
  <div class="bb-empty"><?= $q !== '' ? 'Nenhum resultado.' : 'Nenhum cliente ainda. Cadastre o primeiro.' ?></div>
<?php else: ?>
  <div class="bb-list">
    <?php foreach ($clients as $c): ?>
      <article class="bb-card">
        <div class="bb-card-top">
          <div class="bb-card-name" style="margin:0"><?= e($c['name']) ?></div>
          <span class="bb-stock"><?= (int)$c['visits'] ?> visita<?= (int)$c['visits'] === 1 ? '' : 's' ?></span>
        </div>
        <div class="bb-card-meta"><?= e($c['phone'] ?? '') ?></div>
        <?php if (!empty($c['last_visit'])): ?>
          <div class="bb-card-meta" style="margin-top:-6px">Última: <?= e(date('d/m/Y', strtotime((string)$c['last_visit']))) ?></div>
        <?php endif; ?>
        <div class="bb-actions">
          <a class="bb-btn bb-btn--ghost" style="text-align:center;text-decoration:none;display:grid;place-items:center" href="<?= e(url('barbeiro/clientes.php?edit=' . (int)$c['id'])) ?>">Ficha / Agendar</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="offcanvas offcanvas-bottom bb-sheet" tabindex="-1" id="clientSheet" aria-labelledby="clientSheetTitle">
  <div class="bb-sheet-head">
    <div>
      <h2 class="bb-sheet-title" id="clientSheetTitle"><?= $edit ? 'Editar cliente' : 'Novo cliente' ?></h2>
      <div class="bb-sheet-sub">Dados + serviço com você</div>
    </div>
    <button type="button" class="bb-sheet-close" data-bs-dismiss="offcanvas" aria-label="Fechar">×</button>
  </div>
  <div class="bb-sheet-body">
    <form method="post" class="bb-form" id="clientForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <label>
        <span>Nome</span>
        <input name="name" required value="<?= e($edit['name'] ?? '') ?>" autocomplete="name">
      </label>
      <label>
        <span>Telefone (WhatsApp)</span>
        <input name="phone" required value="<?= e($edit['phone'] ?? '') ?>" inputmode="tel" placeholder="11999999999" autocomplete="tel">
      </label>
      <label>
        <span>Nascimento (opcional)</span>
        <input type="date" name="birth_date" value="<?= e($edit['birth_date'] ?? '') ?>">
      </label>

      <div class="bb-tabs" role="tablist">
        <button type="button" class="bb-tab active" data-tab="save" role="tab">Cadastro</button>
        <button type="button" class="bb-tab" data-tab="book" role="tab">Agendar</button>
        <button type="button" class="bb-tab" data-tab="now" role="tab">Iniciar corte</button>
      </div>
      <input type="hidden" name="also_book" id="alsoBookHidden" value="0">
      <input type="hidden" name="cut_now" id="cutNowHidden" value="0">
      <p class="bb-tab-hint" id="tabHint">Salva só os dados do cliente.</p>

      <div id="bookFields" class="bb-book-hidden">
        <fieldset class="bb-service-fieldset">
          <legend>Serviços</legend>
          <p class="bb-live-hint">Marque tudo que será realizado.</p>
          <div class="bb-service-picker">
            <?php foreach ($services as $s): ?>
              <label class="bb-service-option">
                <input
                  type="checkbox"
                  name="service_ids[]"
                  value="<?= (int)$s['id'] ?>"
                  data-price="<?= e((string)$s['price']) ?>"
                  data-duration="<?= max(15, (int)($s['duration_min'] ?? 30)) ?>"
                  class="bb-service-input"
                  <?= $formService === (int)$s['id'] ? 'checked' : '' ?>
                >
                <span class="bb-service-copy">
                  <strong><?= e($s['name']) ?></strong>
                  <small><?= max(15, (int)($s['duration_min'] ?? 30)) ?> min</small>
                </span>
                <span class="bb-service-price"><?= e(money((float)$s['price'])) ?></span>
              </label>
            <?php endforeach; ?>
            <?php if (!$services): ?>
              <p class="bb-live-hint">Nenhum serviço ativo. Solicite o cadastro ao responsável.</p>
            <?php endif; ?>
          </div>
          <div class="bb-service-summary" aria-live="polite">
            <span id="serviceSelectionMeta">Nenhum serviço selecionado</span>
            <strong id="serviceSelectionTotal">Total: R$ 0,00</strong>
          </div>
        </fieldset>
        <label>
          <span>Barbeiro</span>
          <input value="<?= e($user['name'] ?? 'Você') ?>" disabled>
        </label>
        <label>
          <span>Data</span>
          <input type="date" name="date" id="bookDate" value="<?= e($formDate) ?>">
        </label>
        <label>
          <span>Horário</span>
          <select name="time" id="bookTime">
            <option value="">Selecione…</option>
            <?php foreach ($formSlots as $slot): ?>
              <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <p class="bb-live-hint">Horários passados de hoje não aparecem. Troque a data se precisar.</p>
        <?php if (!$formSlots): ?>
          <p class="bb-live-hint" style="color:#fca5a5">Sem horários livres neste dia. Troque a data.</p>
        <?php endif; ?>
      </div>

      <?php if (!$edit): ?>
        <p class="bb-live-hint">Senha inicial do app do cliente = telefone.</p>
      <?php endif; ?>
      <button class="bb-btn bb-btn--ok bb-btn--block" type="submit" id="submitBtn"><?= $edit ? 'Salvar' : 'Cadastrar cliente' ?></button>
      <?php if ($edit): ?>
        <a class="bb-btn bb-btn--ghost bb-btn--block" style="text-align:center;text-decoration:none" href="<?= e(url('barbeiro/clientes.php')) ?>">Cancelar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<style>
.bb-book-hidden { display: none !important; }
.bb-tabs {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 6px;
  margin: 14px 0 8px;
  padding: 4px;
  border-radius: 14px;
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.08);
}
.bb-tab {
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: #9aa3b5;
  font: inherit;
  font-size: .82rem;
  font-weight: 600;
  padding: .65rem .35rem;
  cursor: pointer;
}
.bb-tab.active {
  background: #1a1f2b;
  color: #fff;
  box-shadow: 0 1px 0 rgba(255,255,255,.06);
}
.bb-tab[data-tab="now"].active { color: #4ade80; }
.bb-tab-hint {
  margin: 0 0 12px;
  color: #9aa3b5;
  font-size: .82rem;
  line-height: 1.4;
}
.bb-form select {
  width: 100%;
  box-sizing: border-box;
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,.12);
  background: #12151d;
  color: #fff;
  padding: .9rem 1rem;
  font: inherit;
  min-height: 48px;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const sheet = document.getElementById('clientSheet');
  const tabs = document.querySelectorAll('.bb-tab');
  const alsoBookHidden = document.getElementById('alsoBookHidden');
  const cutNowHidden = document.getElementById('cutNowHidden');
  const book = document.getElementById('bookFields');
  const serviceInputs = Array.from(document.querySelectorAll('.bb-service-input'));
  const serviceMeta = document.getElementById('serviceSelectionMeta');
  const serviceTotal = document.getElementById('serviceSelectionTotal');
  const date = document.getElementById('bookDate');
  const time = document.getElementById('bookTime');
  const tabHint = document.getElementById('tabHint');
  const submitBtn = document.getElementById('submitBtn');
  const slotsUrl = <?= json_encode(url('api/slots.php')) ?>;
  const barberId = <?= (int)$barberId ?>;
  const moneyFormat = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  let activeTab = 'save';

  const hints = {
    save: 'Salva só os dados do cliente.',
    book: 'Escolha serviço, data e horário para agendar.',
    now: 'Inicia o atendimento como Em andamento. Quando terminar, toque em Finalizar corte.',
  };
  const submitLabels = {
    save: <?= json_encode($edit ? 'Salvar' : 'Cadastrar cliente') ?>,
    book: <?= json_encode($edit ? 'Salvar e agendar' : 'Cadastrar e agendar') ?>,
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

    book.classList.toggle('bb-book-hidden', !on);

    if (date && time) {
        date.closest('label').style.display = isNow ? 'none' : 'block';
        time.closest('label').style.display = isNow ? 'none' : 'block';
    }

    [date, time].forEach((el) => {
      if (!el) return;
      if (isBook) el.setAttribute('required', 'required');
      else el.removeAttribute('required');
    });

    updateServiceSummary();
    if (isBook) loadSlots();
  }

  function selectedServices() {
    return serviceInputs.filter((input) => input.checked);
  }

  function updateServiceSummary() {
    const selected = selectedServices();
    const total = selected.reduce((sum, input) => sum + Number(input.dataset.price || 0), 0);
    const duration = selected.reduce((sum, input) => sum + Number(input.dataset.duration || 0), 0);
    const count = selected.length;
    if (serviceMeta) {
      serviceMeta.textContent = count
        ? `${count} serviço${count === 1 ? '' : 's'} · ${duration} min`
        : 'Nenhum serviço selecionado';
    }
    if (serviceTotal) serviceTotal.textContent = `Total: ${moneyFormat.format(total)}`;
    if (serviceInputs[0]) {
      serviceInputs[0].setCustomValidity(activeTab !== 'save' && count === 0 ? 'Selecione ao menos um serviço.' : '');
    }
  }

  async function loadSlots() {
    if (!time) return;
    const d = date?.value || '';
    const selected = selectedServices();
    if (!d || !selected.length) {
      time.innerHTML = '<option value=\"\">Selecione…</option>';
      return;
    }
    time.innerHTML = '<option value=\"\">Carregando…</option>';
    try {
      const q = new URLSearchParams({ barber_id: String(barberId), date: d });
      selected.forEach((input) => q.append('service_ids[]', input.value));
      const res = await fetch(slotsUrl + '?' + q.toString(), { credentials: 'same-origin', cache: 'no-store' });
      const data = await res.json();
      const slots = Array.isArray(data.slots) ? data.slots : [];
      if (!slots.length) {
        time.innerHTML = '<option value=\"\">Sem horários neste dia</option>';
        return;
      }
      let html = '<option value=\"\">Selecione…</option>';
      slots.forEach((s) => { html += '<option value=\"' + s + '\">' + s + '</option>'; });
      time.innerHTML = html;
    } catch (e) {
      time.innerHTML = '<option value=\"\">Falha ao carregar</option>';
    }
  }

  tabs.forEach((btn) => btn.addEventListener('click', () => setTab(btn.dataset.tab || 'save')));
  serviceInputs.forEach((input) => input.addEventListener('change', () => {
      updateServiceSummary();
      if (activeTab === 'book') loadSlots();
  }));
  [date].forEach((el) => el && el.addEventListener('change', () => {
      if (activeTab === 'book') loadSlots();
  }));
  setTab('save');

  <?php if ($edit || isset($_GET['new'])): ?>
  if (sheet && window.bootstrap) bootstrap.Offcanvas.getOrCreateInstance(sheet).show();
  <?php endif; ?>
});
</script>
<?php barber_shell_end('clientes'); ?>
