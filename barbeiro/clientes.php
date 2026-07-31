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
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $date = trim($_POST['date'] ?? $today);
        $time = trim($_POST['time'] ?? '');
        $wantBook = $id < 1 || !empty($_POST['also_book']);

        if ($name === '' || strlen($phone) < 10) {
            flash('danger', 'Informe nome e telefone válidos (com DDD).');
            redirect(url('barbeiro/clientes.php'));
        }

        if ($wantBook && ($serviceId < 1 || $time === '')) {
            flash('danger', 'Para agendar, escolha o serviço e o horário.');
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

        if ($wantBook) {
            $booked = book_service_for_client((int)$client['id'], $barberId, $serviceId, $date, $time);
            if (is_string($booked)) {
                flash('warning', $msg . ' Agendamento não criado: ' . $booked);
                redirect(url('barbeiro/clientes.php'));
            }
            $svc = find_service($serviceId);
            $msg .= ' Agendado: ' . ($svc['name'] ?? 'serviço') . ' em ' . date('d/m/Y', strtotime($date)) . ' às ' . normalize_time($time) . '.';
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

$formDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? (string)$_GET['date'] : $today;
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
          <a class="bb-btn bb-btn--ghost" style="text-align:center;text-decoration:none;display:grid;place-items:center" href="<?= e(url('barbeiro/clientes.php?edit=' . (int)$c['id'])) ?>">Editar</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="offcanvas offcanvas-bottom bb-sheet" tabindex="-1" id="clientSheet">
  <div class="bb-sheet-head">
    <div>
      <h2 class="bb-sheet-title"><?= $edit ? 'Editar cliente' : 'Novo cliente' ?></h2>
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

      <?php if ($edit): ?>
        <label class="bb-check">
          <input type="checkbox" name="also_book" value="1" id="alsoBook">
          <span>Também agendar atendimento comigo</span>
        </label>
      <?php endif; ?>

      <div id="bookFields" class="<?= $edit ? 'bb-book-hidden' : '' ?>">
        <label>
          <span>Tipo de serviço</span>
          <select name="service_id" id="bookService" <?= $edit ? '' : 'required' ?>>
            <option value="">Selecione…</option>
            <?php foreach ($services as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $formService === (int)$s['id'] ? 'selected' : '' ?>>
                <?= e($s['name']) ?> · <?= e(money((float)$s['price'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          <span>Barbeiro</span>
          <input value="<?= e($user['name'] ?? 'Você') ?>" disabled>
        </label>
        <label>
          <span>Data</span>
          <input type="date" name="date" id="bookDate" value="<?= e($formDate) ?>" <?= $edit ? '' : 'required' ?>>
        </label>
        <label>
          <span>Horário</span>
          <select name="time" id="bookTime" <?= $edit ? '' : 'required' ?>>
            <option value="">Selecione…</option>
            <?php foreach ($formSlots as $slot): ?>
              <option value="<?= e($slot) ?>"><?= e($slot) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php if (!$formSlots): ?>
          <p class="bb-live-hint" style="color:#fca5a5">Sem horários livres neste dia. Troque a data.</p>
        <?php endif; ?>
      </div>

      <?php if (!$edit): ?>
        <p class="bb-live-hint">Senha inicial do app do cliente = telefone.</p>
      <?php endif; ?>
      <button class="bb-btn bb-btn--ok bb-btn--block" type="submit"><?= $edit ? 'Salvar' : 'Cadastrar e agendar' ?></button>
      <?php if ($edit): ?>
        <a class="bb-btn bb-btn--ghost bb-btn--block" style="text-align:center;text-decoration:none" href="<?= e(url('barbeiro/clientes.php')) ?>">Cancelar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<style>
.bb-book-hidden { display: none !important; }
.bb-check {
  display: flex !important;
  flex-direction: row !important;
  align-items: center;
  gap: 10px;
  color: #e8eaf0;
  font-size: .95rem;
}
.bb-check input { width: 20px; height: 20px; accent-color: #c9a227; }
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
  const also = document.getElementById('alsoBook');
  const book = document.getElementById('bookFields');
  const svc = document.getElementById('bookService');
  const date = document.getElementById('bookDate');
  const time = document.getElementById('bookTime');

  function toggleBook() {
    if (!book) return;
    const on = !also || also.checked;
    book.classList.toggle('bb-book-hidden', !on);
    [svc, date, time].forEach((el) => {
      if (!el) return;
      if (on) el.setAttribute('required', 'required');
      else el.removeAttribute('required');
    });
  }
  if (also) also.addEventListener('change', toggleBook);
  toggleBook();

  function reloadSlots() {
    const params = new URLSearchParams();
    <?php if ($edit): ?>
    params.set('edit', '<?= (int)$edit['id'] ?>');
    <?php else: ?>
    params.set('new', '1');
    <?php endif; ?>
    if (svc?.value) params.set('service_id', svc.value);
    if (date?.value) params.set('date', date.value);
    window.location.href = <?= json_encode(url('barbeiro/clientes.php')) ?> + '?' + params.toString();
  }
  [svc, date].forEach((el) => el && el.addEventListener('change', reloadSlots));

  <?php if ($openSheet): ?>
  if (sheet && window.bootstrap) bootstrap.Offcanvas.getOrCreateInstance(sheet).show();
  <?php endif; ?>
});
</script>
<?php barber_shell_end('clientes'); ?>
