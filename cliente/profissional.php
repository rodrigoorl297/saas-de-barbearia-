<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['services'])) {
    $ids = array_values(array_filter(array_map('intval', $_POST['services'] ?? [])));
    if (!$ids) {
        flash('warning', 'Selecione pelo menos um serviço.');
        redirect(url('cliente/'));
    }
    $_SESSION['booking']['services'] = $ids;
}

$serviceIds = array_map('intval', $_SESSION['booking']['services'] ?? []);
if (!$serviceIds) {
    redirect(url('cliente/'));
}

// Remover / adicionar serviço
if (isset($_GET['remove_service'])) {
    $rid = (int) $_GET['remove_service'];
    $serviceIds = array_values(array_filter($serviceIds, fn($id) => $id !== $rid));
    if (!$serviceIds) {
        unset($_SESSION['booking']);
        redirect(url('cliente/'));
    }
    $_SESSION['booking']['services'] = $serviceIds;
    redirect(url('cliente/profissional.php'));
}
if (isset($_GET['add_service'])) {
    $aid = (int) $_GET['add_service'];
    if ($aid > 0 && !in_array($aid, $serviceIds, true)) {
        $serviceIds[] = $aid;
        $_SESSION['booking']['services'] = $serviceIds;
    }
    redirect(url('cliente/profissional.php'));
}

$barbers = active_barbers();
if (!$barbers) {
    flash('danger', 'Nenhum barbeiro disponível.');
    redirect(url('cliente/'));
}

$barberId = (int)($_GET['barber_id'] ?? ($_SESSION['booking']['barber_id'] ?? 0));
$barberIds = array_map(fn($b) => (int)$b['id'], $barbers);
if (!in_array($barberId, $barberIds, true)) {
    $barberId = $barberIds[0];
}
$_SESSION['booking']['barber_id'] = $barberId;
$barber = find_user_by_id($barberId);

$services = services_by_ids($serviceIds);
$duration = max(60, array_sum(array_map(fn($s) => (int)$s['duration_min'], $services)));
$primary = $services[0] ?? null;

$week = max(0, (int)($_GET['week'] ?? 0));
$date = $_GET['date'] ?? '';
$today = date('Y-m-d');

$diasNomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$dias = [];
for ($i = 0; $i < 6; $i++) {
    $ts = strtotime('+' . (($week * 6) + $i) . ' days');
    $ymd = date('Y-m-d', $ts);
    $slotsDay = available_slots($barberId, $ymd, $duration);
    // passado = esgotado
    $esgotado = $ymd < $today || count($slotsDay) === 0;
    $dias[] = [
        'ymd' => $ymd,
        'label' => date('d/m', $ts) . ' ' . $diasNomes[(int)date('w', $ts)],
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
$selectedDayEsgotado = true;
foreach ($dias as $d) {
    if ($d['ymd'] === $date) {
        $slots = $d['slots'];
        $selectedDayEsgotado = $d['esgotado'];
        break;
    }
}
if ($selectedDayEsgotado) {
    $slots = [];
}

$extrasAll = array_values(array_filter(
    active_services(),
    fn($s) => !in_array((int)$s['id'], $serviceIds, true)
));
shuffle($extrasAll);
$extras = array_slice($extrasAll, 0, 3);

$thumbs = [
    'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?w=300&h=300&fit=crop',
    'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=300&h=300&fit=crop',
    'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=300&h=300&fit=crop',
];

$barberPhoto = media_url($barber['avatar'] ?? '');
$baseQs = http_build_query(array_filter(['week' => $week ?: null]));

render_head('Agendar', true);
client_shell_start('agendar');
?>
<div class="page-inner booking-flow">
  <?php render_flash_client(); ?>

  <div class="booking-sheet">
    <div class="booking-top">
      <a class="booking-back" href="<?= e(url('cliente/')) ?>" aria-label="Voltar">
        <?= icon_svg('back', 18) ?>
      </a>
      <div class="booking-barber-hero">
        <?php if ($barberPhoto): ?>
          <img src="<?= e($barberPhoto) ?>" alt="<?= e($barber['name'] ?? '') ?>">
        <?php else: ?>
          <span><?= e(initials($barber['name'] ?? 'B')) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="booking-svc-stack">
      <?php foreach ($services as $svc): ?>
        <div class="booking-svc-chip">
          <span><?= e(str_upper($svc['name'])) ?></span>
          <a href="?remove_service=<?= (int)$svc['id'] ?>&week=<?= $week ?>&date=<?= e($date) ?>&barber_id=<?= $barberId ?>" aria-label="Remover"><?= icon_svg('trash', 15) ?></a>
        </div>
        <?php if (!empty($svc['description'])): ?>
          <div class="booking-svc-desc"><?= e($svc['description']) ?></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div class="booking-days-wrap">
      <a class="booking-day-nav <?= $week <= 0 ? 'disabled' : '' ?>"
         href="<?= $week <= 0 ? '#' : ('?week=' . ($week - 1) . '&barber_id=' . $barberId) ?>"
         aria-label="Dias anteriores">‹</a>

      <div class="booking-days-grid">
        <?php foreach ($dias as $d):
            $href = '?week=' . $week . '&barber_id=' . $barberId . '&date=' . urlencode($d['ymd']);
        ?>
          <?php if ($d['esgotado']): ?>
            <div class="booking-day esgotado">
              <span class="esgotado-tag">Esgotado</span>
              <span class="booking-day-label"><?= e($d['label']) ?></span>
            </div>
          <?php else: ?>
            <a class="booking-day <?= $d['ymd'] === $date ? 'active' : '' ?>" href="<?= e($href) ?>">
              <span class="booking-day-label"><?= e($d['label']) ?></span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <a class="booking-day-nav" href="?week=<?= $week + 1 ?>&barber_id=<?= $barberId ?>" aria-label="Próximos dias">›</a>
    </div>

    <button type="button" class="booking-barber-select" id="btn-open-barbers">
      <span class="booking-barber-mini">
        <?php if ($barberPhoto): ?>
          <img src="<?= e($barberPhoto) ?>" alt="">
        <?php else: ?>
          <span class="mini-fallback"><?= e(initials($barber['name'] ?? 'B')) ?></span>
        <?php endif; ?>
      </span>
      <span class="booking-barber-text">
        <span class="booking-barber-label">Profissional</span>
        <span class="booking-barber-name"><?= e($barber['name'] ?? 'barbeiro') ?></span>
      </span>
      <span class="booking-barber-chev"><?= icon_svg('chevron', 16) ?></span>
    </button>

    <?php if ($extrasAll): ?>
      <div class="booking-addons">
        <div class="booking-addons-header">
          <div class="booking-addons-title">Adicione também:</div>
          <button type="button" class="booking-addons-ver-todos" id="btn-open-extras">Ver todos</button>
        </div>
        <div class="booking-addons-track">
          <?php foreach ($extras as $i => $ex):
              $img = !empty($ex['image_url']) ? media_url($ex['image_url']) : $thumbs[$i % count($thumbs)];
          ?>
            <a class="booking-addon-card" href="?add_service=<?= (int)$ex['id'] ?>&week=<?= $week ?>&date=<?= e($date) ?>&barber_id=<?= $barberId ?>">
              <div class="booking-addon-img">
                <img src="<?= e($img) ?>" alt="<?= e($ex['name']) ?>" loading="lazy">
                <span class="booking-addon-plus">+</span>
              </div>
              <div class="booking-addon-meta">
                <strong><?= e(money((float)$ex['price'])) ?></strong>
                <span><?= e($ex['name']) ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Modal: todos os serviços disponíveis -->
      <dialog id="extras-modal" class="barber-modal">
        <div class="barber-modal-inner">
          <h2>Serviços disponíveis</h2>
          <div class="extras-modal-list">
            <?php foreach ($extrasAll as $i => $ex):
                $img = !empty($ex['image_url']) ? media_url($ex['image_url']) : $thumbs[$i % count($thumbs)];
            ?>
              <a class="extras-modal-item" href="?add_service=<?= (int)$ex['id'] ?>&week=<?= $week ?>&date=<?= e($date) ?>&barber_id=<?= $barberId ?>">
                <div class="extras-modal-img">
                  <img src="<?= e($img) ?>" alt="<?= e($ex['name']) ?>" loading="lazy">
                </div>
                <div class="extras-modal-info">
                  <strong><?= e($ex['name']) ?></strong>
                  <span><?= e(money((float)$ex['price'])) ?> · <?= (int)$ex['duration_min'] ?> min</span>
                </div>
                <span class="extras-modal-plus">+</span>
              </a>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn-ghost-as" id="btn-close-extras">Fechar</button>
        </div>
      </dialog>
    <?php endif; ?>

    <form method="post" action="<?= e(url('cliente/confirmar.php')) ?>" id="form-hora">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="barber_id" value="<?= (int)$barberId ?>">
      <div class="booking-slots">
        <?php if ($selectedDayEsgotado || !$slots): ?>
          <div class="alert-as warning" style="width:100%">Sem horários disponíveis neste dia com este barbeiro.</div>
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

<dialog id="barber-modal" class="barber-modal">
  <div class="barber-modal-inner">
    <h2>Selecione o profissional:</h2>
    <div class="barber-modal-list">
      <?php foreach ($barbers as $b):
          $photo = media_url($b['avatar'] ?? '');
          $href = '?week=' . $week . '&barber_id=' . (int)$b['id'] . '&date=' . urlencode($date);
      ?>
        <a class="barber-modal-item <?= (int)$b['id'] === $barberId ? 'active' : '' ?>" href="<?= e($href) ?>">
          <span class="barber-modal-avatar">
            <?php if ($photo): ?>
              <img src="<?= e($photo) ?>" alt="<?= e($b['name']) ?>">
            <?php else: ?>
              <?= e(initials($b['name'])) ?>
            <?php endif; ?>
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
      <button type="button" class="botao-avancar" id="btn-avancar-hora">Avançar</button>
    </div>
  </div>
</div>

<script>
(() => {
  const modal = document.getElementById('barber-modal');
  document.getElementById('btn-open-barbers')?.addEventListener('click', () => modal?.showModal());
  document.getElementById('btn-close-barbers')?.addEventListener('click', () => modal?.close());
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) modal.close();
  });

  const wrap = document.getElementById('botao-avancar-wrapper');
  document.querySelectorAll('.booking-slot input').forEach((input) => {
    input.addEventListener('change', () => {
      document.querySelectorAll('.booking-slot').forEach((l) => l.classList.remove('active'));
      input.closest('.booking-slot')?.classList.add('active');
      wrap?.classList.add('show');
    });
  });
  document.getElementById('btn-avancar-hora')?.addEventListener('click', () => {
    const form = document.getElementById('form-hora');
    if (!form?.querySelector('input[name=time]:checked')) return;
    form.requestSubmit();
  });

  // Modal extras
  const extrasModal = document.getElementById('extras-modal');
  document.getElementById('btn-open-extras')?.addEventListener('click', () => extrasModal?.showModal());
  document.getElementById('btn-close-extras')?.addEventListener('click', () => extrasModal?.close());
  extrasModal?.addEventListener('click', (e) => { if (e.target === extrasModal) extrasModal.close(); });
})();
</script>
<?php client_shell_end('agendar'); ?>
