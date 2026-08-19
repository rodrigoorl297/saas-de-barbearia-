<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'join_waitlist') {
        $user = current_user();
        if (!$user) {
            flash('warning', 'Você precisa estar logado para entrar na fila.');
            redirect(url('login.php'));
        }
        $waitlist = store_read('waitlist') ?: [];
        $bId = (int)($_POST['barber_id'] ?? 0);
        $dt = $_POST['date'] ?? date('Y-m-d');
        $waitlist[] = [
            'id' => uniqid(),
            'client_id' => $user['id'],
            'client_name' => $user['name'],
            'client_phone' => $user['phone'] ?? '',
            'barber_id' => $bId,
            'date' => $dt,
            'created_at' => date('c')
        ];
        store_write('waitlist', $waitlist);
        flash('success', 'Você entrou na fila de espera. Avisaremos se abrir vaga!');
        $qs = [];
        if ($bId) $qs['barber_id'] = $bId;
        if ($dt) $qs['date'] = $dt;
        redirect(url('cliente/profissional.php' . ($qs ? '?' . http_build_query($qs) : '')));
    }
    if (isset($_POST['services'])) {
        $ids = array_values(array_filter(array_map('intval', $_POST['services'] ?? [])));
        if (!$ids) {
            flash('warning', 'Selecione pelo menos um serviço.');
            redirect(url('cliente/'));
        }
        $_SESSION['booking']['services'] = $ids;
    }
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

$week = 0; // Travado na semana atual
$date = $_GET['date'] ?? '';
$today = date('Y-m-d');

$hojeW = (int)date('w'); // 0 = Domingo, 1 = Segunda, etc.
$diasAteDomingo = $hojeW === 0 ? 0 : 7 - $hojeW;
$ultimoDiaSemana = date('Y-m-d', strtotime("+$diasAteDomingo days"));

$diasNomes = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$dias = [];
for ($i = 0; $i < 6; $i++) {
    $ts = strtotime('+' . $i . ' days');
    $ymd = date('Y-m-d', $ts);
    $slotsDay = [];
    
    // passado = esgotado; futuro além de domingo = bloqueado
    $foraDoLimite = $ymd > $ultimoDiaSemana;
    if (!$foraDoLimite && $ymd >= $today) {
        $slotsDay = available_slots($barberId, $ymd, $duration);
    }
    
    $esgotado = $ymd < $today || $foraDoLimite || count($slotsDay) === 0;
    
    $dias[] = [
        'ymd' => $ymd,
        'label' => date('d/m', $ts) . ' ' . $diasNomes[(int)date('w', $ts)],
        'esgotado' => $esgotado,
        'slots' => $slotsDay,
        'foraDoLimite' => $foraDoLimite
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

$barberPhoto = media_url($barber['avatar'] ?? '');
$baseQs = http_build_query(array_filter(['week' => $week ?: null]));

render_head('Agendar', true);
client_shell_start('agendar');
?>
<div class="page-inner booking-flow">
  <?php client_booking_stepper(3); ?>
  <?php render_flash_client(); ?>
  <?php client_booking_summary(); ?>

  <div class="booking-sheet">
    <div class="booking-top">
      <a class="booking-back" href="<?= e(url('cliente/')) ?>" aria-label="Voltar">
        <?= icon_svg('back', 18) ?>
      </a>
      <div class="booking-barber-hero" aria-hidden="true">
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

    <section class="booking-section" id="profissional" aria-labelledby="professional-title">
      <div class="booking-section-heading">
        <span class="client-eyebrow">Etapa 2</span>
        <h1 id="professional-title">Escolha o profissional</h1>
        <p>Toque para ver todos os profissionais disponíveis.</p>
      </div>

      <button type="button" class="booking-barber-select" id="btn-open-barbers" aria-haspopup="dialog" aria-controls="barber-modal">
        <span class="booking-barber-mini">
          <?php if ($barberPhoto): ?>
            <img src="<?= e($barberPhoto) ?>" alt="">
          <?php else: ?>
            <span class="mini-fallback"><?= e(initials($barber['name'] ?? 'B')) ?></span>
          <?php endif; ?>
        </span>
        <span class="booking-barber-text">
          <span class="booking-barber-label">Profissional selecionado</span>
          <span class="booking-barber-name"><?= e($barber['name'] ?? 'barbeiro') ?></span>
        </span>
        <span class="booking-barber-chev"><?= icon_svg('chevron', 16) ?></span>
      </button>
    </section>

    <section class="booking-section" id="horarios" aria-labelledby="schedule-title">
      <div class="booking-section-heading">
        <span class="client-eyebrow">Etapa 3</span>
        <h2 id="schedule-title">Escolha o dia e o horário</h2>
        <p>Horários indisponíveis aparecem bloqueados.</p>
      </div>

    <div class="booking-days-wrap">
      <span class="booking-day-nav is-hidden" aria-hidden="true">‹</span>

      <div class="booking-days-grid">
        <?php foreach ($dias as $d):
            $href = '?week=' . $week . '&barber_id=' . $barberId . '&date=' . urlencode($d['ymd']);
        ?>
          <?php if ($d['esgotado']): ?>
            <div class="booking-day esgotado" aria-disabled="true">
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

      <span class="booking-day-nav is-hidden" aria-hidden="true">›</span>
    </div>

    <?php if ($extrasAll): ?>
      <div class="booking-addons">
        <div class="booking-addons-header">
          <div class="booking-addons-title">Adicione também:</div>
          <button type="button" class="booking-addons-ver-todos" id="btn-open-extras" aria-haspopup="dialog" aria-controls="extras-modal">Ver todos</button>
        </div>
        <div class="booking-addons-track">
          <?php foreach ($extras as $ex):
              $img = !empty($ex['image_url']) ? media_url($ex['image_url']) : '';
          ?>
            <a class="booking-addon-card" href="?add_service=<?= (int)$ex['id'] ?>&week=<?= $week ?>&date=<?= e($date) ?>&barber_id=<?= $barberId ?>">
              <div class="booking-addon-img">
                <?php if ($img): ?>
                  <img src="<?= e($img) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="service-visual-fallback" aria-hidden="true"><?= icon_svg('scissors', 22) ?><b><?= e(initials($ex['name'])) ?></b></span>
                <?php endif; ?>
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
      <dialog id="extras-modal" class="barber-modal" aria-labelledby="extras-modal-title">
        <div class="barber-modal-inner">
          <h2 id="extras-modal-title">Serviços disponíveis</h2>
          <div class="extras-modal-list">
            <?php foreach ($extrasAll as $ex):
                $img = !empty($ex['image_url']) ? media_url($ex['image_url']) : '';
            ?>
              <a class="extras-modal-item" href="?add_service=<?= (int)$ex['id'] ?>&week=<?= $week ?>&date=<?= e($date) ?>&barber_id=<?= $barberId ?>">
                <div class="extras-modal-img">
                  <?php if ($img): ?>
                    <img src="<?= e($img) ?>" alt="" loading="lazy">
                  <?php else: ?>
                    <span class="service-visual-fallback" aria-hidden="true"><?= icon_svg('scissors', 20) ?><b><?= e(initials($ex['name'])) ?></b></span>
                  <?php endif; ?>
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

    <form method="post" action="" id="form-waitlist">
      <input type="hidden" name="action" value="join_waitlist">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="barber_id" value="<?= (int)$barberId ?>">
    </form>
    <form method="post" action="<?= e(url('cliente/confirmar.php')) ?>" id="form-hora">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <input type="hidden" name="barber_id" value="<?= (int)$barberId ?>">
      <div class="booking-slots">
        <?php if ($selectedDayEsgotado || !$slots): ?>
          <div class="client-empty-state client-empty-state--compact" role="status">
            <span class="client-empty-icon"><?= icon_svg('calendar', 24) ?></span>
            <strong>Agenda cheia neste dia</strong>
            <p>Escolha outra data ou entre na fila para ser avisado se surgir uma vaga.</p>
          </div>
          <button type="submit" form="form-waitlist" class="botao-avancar waitlist-button">Entrar na fila de espera</button>
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
    </section>
  </div>
</div>

<dialog id="barber-modal" class="barber-modal" aria-labelledby="barber-modal-title">
  <div class="barber-modal-inner">
    <h2 id="barber-modal-title">Selecione o profissional:</h2>
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
      <button type="button" class="botao-avancar" id="btn-avancar-hora">
        <span>Continuar</span>
        <small id="time-cta-context">Selecione um horário</small>
      </button>
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
      const context = document.getElementById('time-cta-context');
      if (context) context.textContent = `${input.value} · confirmar dados`;
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
