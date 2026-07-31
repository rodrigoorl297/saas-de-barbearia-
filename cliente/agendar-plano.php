<?php
/**
 * Agendar usando plano/assinatura ativa (estilo agendas.link).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$client = current_client();
if (!$client) {
    flash('warning', 'Entre na conta para agendar com seu plano.');
    redirect(url('cliente/agendamentos.php?step=telefone'));
}

$planId = (int)($_GET['plan_id'] ?? ($_POST['plan_id'] ?? 0));
$sub = null;
foreach (client_subscriptions((int)$client['id']) as $s) {
    if ($planId > 0 && (int)$s['plan_id'] === $planId) {
        $sub = $s;
        break;
    }
}
if (!$sub && $planId === 0) {
    $subs = client_subscriptions((int)$client['id']);
    $sub = $subs[0] ?? null;
    if ($sub) {
        $planId = (int)$sub['plan_id'];
    }
}
if (!$sub) {
    flash('warning', 'Você não tem este plano ativo.');
    redirect(url('cliente/conta.php'));
}

$plan = find_plan((int)$sub['plan_id']);
if (!$plan) {
    flash('danger', 'Plano não encontrado.');
    redirect(url('cliente/conta.php'));
}

/** Serviço incluso no plano */
function plan_included_service(array $plan): ?array
{
    if (!empty($plan['service_id'])) {
        return find_service((int)$plan['service_id']);
    }
    $label = strtolower((string)($plan['benefit_label'] ?? 'corte'));
    $services = active_services();
    foreach ($services as $s) {
        $name = strtolower((string)$s['name']);
        if ($name !== '' && (str_contains($label, $name) || str_contains($name, explode(' ', $label)[0] ?? ''))) {
            return $s;
        }
    }
    foreach ($services as $s) {
        if (stripos((string)$s['name'], 'corte') !== false) {
            return $s;
        }
    }
    return $services[0] ?? null;
}

$included = plan_included_service($plan);
if (!$included) {
    flash('danger', 'Nenhum serviço vinculado a este plano. Cadastre serviços no painel.');
    redirect(url('cliente/conta.php'));
}

$extraIds = array_values(array_filter(array_map('intval', $_SESSION['plan_booking']['extras'] ?? [])));
if (isset($_GET['add_extra'])) {
    $eid = (int)$_GET['add_extra'];
    if ($eid > 0 && $eid !== (int)$included['id'] && !in_array($eid, $extraIds, true)) {
        $extraIds[] = $eid;
        $_SESSION['plan_booking']['extras'] = $extraIds;
    }
    redirect(url('cliente/agendar-plano.php?plan_id=' . $planId . '&date=' . urlencode($_GET['date'] ?? '') . '&barber_id=' . (int)($_GET['barber_id'] ?? 0)));
}
if (isset($_GET['remove_extra'])) {
    $rid = (int)$_GET['remove_extra'];
    $extraIds = array_values(array_filter($extraIds, fn($id) => $id !== $rid));
    $_SESSION['plan_booking']['extras'] = $extraIds;
    redirect(url('cliente/agendar-plano.php?plan_id=' . $planId));
}

$extrasSelected = services_by_ids($extraIds);
$allServiceIds = array_merge([(int)$included['id']], $extraIds);
$duration = max(60, array_sum(array_map(fn($s) => (int)$s['duration_min'], array_merge([$included], $extrasSelected))));

$barbers = active_barbers();
$barberId = (int)($_GET['barber_id'] ?? ($_SESSION['plan_booking']['barber_id'] ?? 0));
$barberIds = array_map(fn($b) => (int)$b['id'], $barbers);
if (!in_array($barberId, $barberIds, true)) {
    $barberId = $barberIds[0] ?? 0;
}
$_SESSION['plan_booking']['barber_id'] = $barberId;
$barber = $barberId ? find_user_by_id($barberId) : null;

$week = max(0, (int)($_GET['week'] ?? 0));
$today = date('Y-m-d');
$date = $_GET['date'] ?? '';
$diasNomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$dias = [];
for ($i = 0; $i < 6; $i++) {
    $ts = strtotime('+' . (($week * 6) + $i) . ' days');
    $ymd = date('Y-m-d', $ts);
    $slotsDay = $barberId ? available_slots($barberId, $ymd, $duration) : [];
    $esgotado = $ymd < $today || count($slotsDay) === 0;
    $dias[] = [
        'ymd' => $ymd,
        'dm' => date('d/m', $ts),
        'nome' => $diasNomes[(int)date('w', $ts)],
        'esgotado' => $esgotado,
        'slots' => $slotsDay,
    ];
}
if ($date === '' || $date < $today) {
    foreach ($dias as $d) {
        if (!$d['esgotado']) {
            $date = $d['ymd'];
            break;
        }
    }
    if ($date === '') {
        $date = $dias[0]['ymd'] ?? $today;
    }
}

$slots = [];
$dayEsgotado = true;
foreach ($dias as $d) {
    if ($d['ymd'] === $date) {
        $slots = $d['slots'];
        $dayEsgotado = $d['esgotado'];
        break;
    }
}

$addonCatalog = array_values(array_filter(
    active_services(),
    fn($s) => (int)$s['id'] !== (int)$included['id'] && !in_array((int)$s['id'], $extraIds, true)
));

$barberPhoto = $barber ? media_url($barber['avatar'] ?? '') : '';
$used = (int)($sub['usage_count'] ?? 0);
$limit = array_key_exists('usage_limit', $plan) && $plan['usage_limit'] !== null ? (string)$plan['usage_limit'] : '∞';

// Confirmar horário → sessão de booking e ir para confirmar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['time'], $_POST['date'])) {
    $_SESSION['booking'] = [
        'services' => $allServiceIds,
        'barber_id' => $barberId,
        'date' => $_POST['date'],
        'time' => $_POST['time'],
        'from_plan' => 1,
        'plan_id' => (int)$plan['id'],
        'subscription_id' => (int)$sub['id'],
        'plan_service_id' => (int)$included['id'],
    ];
    redirect(url('cliente/confirmar.php'));
}

$thumbs = [
    'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?w=300&h=300&fit=crop',
    'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=300&h=300&fit=crop',
];

render_head('Agendar plano', true);
client_shell_start('conta');
?>
<div class="plan-book-overlay">
  <div class="plan-book-modal">
    <a class="booking-back plan-book-back" href="<?= e(url('cliente/conta.php')) ?>" aria-label="Voltar"><?= icon_svg('back', 18) ?></a>

    <h1 class="plan-book-title">Plano/Pacote selecionado:</h1>

    <div class="plan-book-selected">
      <strong><?= e($plan['name']) ?></strong>
      <a class="plan-book-vermais" href="<?= e(url('cliente/conta.php')) ?>">Ver mais</a>
    </div>
    <div class="plan-book-dot"></div>

    <label class="plan-book-svc <?= !empty($_GET['svc_off']) ? '' : 'checked' ?>">
      <span class="plan-book-svc-avatar">
        <?php
          $svcImg = !empty($included['image_url']) ? media_url($included['image_url']) : '';
        ?>
        <?php if ($svcImg): ?>
          <img src="<?= e($svcImg) ?>" alt="">
        <?php else: ?>
          <?= e(initials($included['name'])) ?>
        <?php endif; ?>
      </span>
      <span class="plan-book-svc-text">
        <strong><?= e($plan['benefit_label'] ?? $included['name']) ?></strong>
        <small><?= (int)$included['duration_min'] ?>min · incluso no plano (<?= $used ?> de <?= e($limit) ?>)</small>
      </span>
      <span class="plan-book-check"><i><?= icon_svg('check', 14) ?></i></span>
    </label>

    <div class="plan-book-extras-label">Serviços extras adicionados:</div>
    <?php if ($extrasSelected): ?>
      <div class="plan-book-extras-list">
        <?php foreach ($extrasSelected as $ex): ?>
          <div class="plan-book-extra-chip">
            <span><?= e($ex['name']) ?> · <?= e(money((float)$ex['price'])) ?></span>
            <a href="?plan_id=<?= $planId ?>&remove_extra=<?= (int)$ex['id'] ?>&date=<?= e($date) ?>&barber_id=<?= $barberId ?>">remover</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <button type="button" class="plan-book-add-btn" id="btn-open-extras">Adicionar serviços +</button>

    <?php if ($barber): ?>
      <button type="button" class="booking-barber-select" id="btn-open-barbers" style="margin-top:14px">
        <span class="booking-barber-mini">
          <?php if ($barberPhoto): ?><img src="<?= e($barberPhoto) ?>" alt=""><?php else: ?><span class="mini-fallback"><?= e(initials($barber['name'])) ?></span><?php endif; ?>
        </span>
        <span class="booking-barber-name"><?= e(strtolower($barber['name'])) ?></span>
        <span class="booking-barber-chev"><?= icon_svg('chevron', 16) ?></span>
      </button>
    <?php endif; ?>

    <div class="plan-book-days-wrap">
      <a class="booking-day-nav <?= $week <= 0 ? 'disabled' : '' ?>" href="<?= $week <= 0 ? '#' : ('?plan_id=' . $planId . '&week=' . ($week - 1) . '&barber_id=' . $barberId) ?>">‹</a>
      <div class="plan-book-days">
        <?php foreach ($dias as $d):
            $href = '?plan_id=' . $planId . '&week=' . $week . '&barber_id=' . $barberId . '&date=' . urlencode($d['ymd']);
        ?>
          <?php if ($d['esgotado']): ?>
            <div class="plan-book-day esgotado">
              <strong><?= e($d['dm']) ?></strong>
              <span><?= e($d['nome']) ?></span>
            </div>
          <?php else: ?>
            <a class="plan-book-day <?= $d['ymd'] === $date ? 'active' : '' ?>" href="<?= e($href) ?>">
              <strong><?= e($d['dm']) ?></strong>
              <span><?= e($d['nome']) ?></span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <a class="booking-day-nav" href="?plan_id=<?= $planId ?>&week=<?= $week + 1 ?>&barber_id=<?= $barberId ?>">›</a>
    </div>

    <form method="post" id="form-plan-hora">
      <input type="hidden" name="plan_id" value="<?= $planId ?>">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <div class="booking-slots">
        <?php if ($dayEsgotado || !$slots): ?>
          <div class="alert-as warning" style="width:100%">Sem horários neste dia.</div>
        <?php else: ?>
          <?php foreach ($slots as $slot): ?>
            <label class="booking-slot">
              <input type="radio" name="time" value="<?= e($slot) ?>" required>
              <span><?= e($slot) ?></span>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<dialog id="extras-modal" class="barber-modal">
  <div class="barber-modal-inner">
    <h2>Adicionar serviços</h2>
    <div class="plan-extras-grid">
      <?php if (!$addonCatalog): ?>
        <p class="page-sub">Nenhum extra disponível.</p>
      <?php else: ?>
        <?php foreach ($addonCatalog as $i => $ex):
            $img = !empty($ex['image_url']) ? media_url($ex['image_url']) : $thumbs[$i % count($thumbs)];
        ?>
          <a class="plan-extra-pick" href="?plan_id=<?= $planId ?>&add_extra=<?= (int)$ex['id'] ?>&date=<?= e($date) ?>&barber_id=<?= $barberId ?>&week=<?= $week ?>">
            <img src="<?= e($img) ?>" alt="">
            <div>
              <strong><?= e($ex['name']) ?></strong>
              <span><?= e(money((float)$ex['price'])) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <button type="button" class="btn-ghost-as" id="btn-close-extras">Fechar</button>
  </div>
</dialog>

<dialog id="barber-modal" class="barber-modal">
  <div class="barber-modal-inner">
    <h2>Selecione o profissional:</h2>
    <div class="barber-modal-list">
      <?php foreach ($barbers as $b):
          $photo = media_url($b['avatar'] ?? '');
          $href = '?plan_id=' . $planId . '&week=' . $week . '&barber_id=' . (int)$b['id'] . '&date=' . urlencode($date);
      ?>
        <a class="barber-modal-item <?= (int)$b['id'] === $barberId ? 'active' : '' ?>" href="<?= e($href) ?>">
          <span class="barber-modal-avatar">
            <?php if ($photo): ?><img src="<?= e($photo) ?>" alt=""><?php else: ?><?= e(initials($b['name'])) ?><?php endif; ?>
          </span>
          <span class="barber-modal-name"><?= e($b['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn-ghost-as" id="btn-close-barbers">Fechar</button>
  </div>
</dialog>

<div class="botao-avancar-wrapper" id="botao-avancar-wrapper">
  <div class="botao-avancar-inner">
    <div class="botao-avancar-container">
      <button type="button" class="botao-avancar" id="btn-avancar-plan">Avançar</button>
    </div>
  </div>
</div>

<script>
(() => {
  const extras = document.getElementById('extras-modal');
  const barbers = document.getElementById('barber-modal');
  document.getElementById('btn-open-extras')?.addEventListener('click', () => extras?.showModal());
  document.getElementById('btn-close-extras')?.addEventListener('click', () => extras?.close());
  document.getElementById('btn-open-barbers')?.addEventListener('click', () => barbers?.showModal());
  document.getElementById('btn-close-barbers')?.addEventListener('click', () => barbers?.close());

  const wrap = document.getElementById('botao-avancar-wrapper');
  document.querySelectorAll('.booking-slot input').forEach((input) => {
    input.addEventListener('change', () => {
      document.querySelectorAll('.booking-slot').forEach((l) => l.classList.remove('active'));
      input.closest('.booking-slot')?.classList.add('active');
      wrap?.classList.add('show');
    });
  });
  document.getElementById('btn-avancar-plan')?.addEventListener('click', () => {
    const form = document.getElementById('form-plan-hora');
    if (!form?.querySelector('input[name=time]:checked')) return;
    form.requestSubmit();
  });
})();
</script>
<?php client_shell_end('conta'); ?>
