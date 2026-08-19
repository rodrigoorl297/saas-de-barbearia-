<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$booking = $_SESSION['booking'] ?? [];
$bookingServiceIds = isset($booking['services']) && is_array($booking['services'])
    ? array_values(array_unique(array_filter(array_map('intval', $booking['services']))))
    : [];
if (!$bookingServiceIds || empty($booking['barber_id'])) {
    redirect(url('cliente/'));
}
$booking['services'] = $bookingServiceIds;
$_SESSION['booking']['services'] = $bookingServiceIds;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date'], $_POST['time']) && !isset($_POST['confirm'])) {
    $_SESSION['booking']['date'] = $_POST['date'];
    $_SESSION['booking']['time'] = $_POST['time'];
    if (!empty($_POST['barber_id'])) {
        $_SESSION['booking']['barber_id'] = (int) $_POST['barber_id'];
    }
    
    // Trava de segurança: apenas até domingo da semana atual
    $hojeW = (int)date('w');
    $diasAteDomingo = $hojeW === 0 ? 0 : 7 - $hojeW;
    $ultimoDiaSemana = date('Y-m-d', strtotime("+$diasAteDomingo days"));
    
    $selectedDate = (string)$_SESSION['booking']['date'];
    if (!is_valid_iso_date($selectedDate) || $selectedDate < date('Y-m-d') || $selectedDate > $ultimoDiaSemana) {
        unset($_SESSION['booking']['date']);
        flash('danger', 'A data selecionada não é permitida.');
        redirect(url('cliente/profissional.php'));
    }
    
    $booking = $_SESSION['booking'];
}

if (empty($booking['date']) || empty($booking['time'])) {
    if (!empty($booking['from_plan'])) {
        redirect(url('cliente/agendar-plano.php?plan_id=' . (int)($booking['plan_id'] ?? 0)));
    }
    redirect(url('cliente/profissional.php'));
}

$services = services_by_ids($booking['services']);
if (!$services) {
    unset($_SESSION['booking']);
    flash('warning', 'Os serviços selecionados não estão mais disponíveis.');
    redirect(url('cliente/'));
}
$booking['services'] = array_values(array_map(static fn($service) => (int)$service['id'], $services));
$_SESSION['booking']['services'] = $booking['services'];
$planServiceId = (int)($booking['plan_service_id'] ?? 0);
$total = 0.0;
foreach ($services as $s) {
    if (!empty($booking['from_plan']) && (int)$s['id'] === $planServiceId) {
        continue; // incluso no plano
    }
    $total += (float)$s['price'];
}
$barber = find_user_by_id((int)$booking['barber_id']);
$loggedClient = current_client();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if ($loggedClient) {
        $name = $loggedClient['name'] ?? 'Cliente';
        $phone = preg_replace('/\D+/', '', (string)($loggedClient['phone'] ?? ''));
        $clientId = (int)$loggedClient['id'];
    } else {
        $name = trim($_POST['client_name'] ?? '');
        $phone = preg_replace('/\D+/', '', $_POST['client_phone'] ?? '');
        $senha = preg_replace('/\D+/', '', $_POST['client_password'] ?? '');

        if ($name === '' || strlen($phone) < 10) {
            $error = 'Informe nome e telefone válidos.';
        } elseif (strlen($senha) < 4) {
            $error = 'Crie uma senha numérica com pelo menos 4 dígitos.';
        } else {
            $existing = find_client_by_phone($phone);
            if ($existing) {
                $clientId = (int)$existing['id'];
                save_user([
                    'id' => $clientId,
                    'name' => $name,
                    'phone' => $phone,
                    'password' => password_hash($senha, PASSWORD_DEFAULT),
                    'role' => 'cliente',
                    'active' => 1,
                    'email' => $existing['email'] ?? null,
                    'avatar' => $existing['avatar'] ?? null,
                ]);
            } else {
                $client = save_user([
                    'name' => $name,
                    'email' => null,
                    'phone' => $phone,
                    'password' => password_hash($senha, PASSWORD_DEFAULT),
                    'role' => 'cliente',
                    'avatar' => null,
                    'active' => 1,
                ]);
                $clientId = (int)$client['id'];
            }
        }
    }

    if ($error === null && !empty($clientId)) {
        if (client_already_booked_on_date((int)$clientId, (string)$booking['date'], (string)$phone)) {
            $error = 'Você já tem agendamento neste dia. Só é permitido um horário por dia.';
        }
    }

    if ($error === null && !empty($clientId)) {
        $appointment = book_services_for_client(
            (int)$clientId,
            (int)$booking['barber_id'],
            $booking['services'],
            (string)$booking['date'],
            (string)$booking['time']
        );

        if (is_string($appointment)) {
            $error = $appointment;
        } else {
            if (!empty($booking['from_plan'])) {
                $appointment['notes'] = 'Plano #' . (int)($booking['plan_id'] ?? 0);
                $appointment['price'] = $total;
                save_appointment($appointment);
            }

            if (!empty($booking['from_plan']) && !empty($booking['subscription_id'])) {
                $subs = store_read('subscriptions');
                foreach ($subs as $i => $row) {
                    if ((int)$row['id'] === (int)$booking['subscription_id']) {
                        $subs[$i]['usage_count'] = (int)($row['usage_count'] ?? 0) + 1;
                        break;
                    }
                }
                store_write('subscriptions', $subs);
            }

            $clientUser = find_user_by_id((int)$clientId);
            if ($clientUser) {
                login_client($clientUser);
            }
            unset($_SESSION['booking'], $_SESSION['plan_booking']);
            flash('success', 'Agendamento confirmado!');
            redirect(url('cliente/sucesso.php'));
        }
    }
}

render_head('Confirmar', true);
client_shell_start('agendar');
$backUrl = !empty($booking['from_plan'])
    ? url('cliente/agendar-plano.php?plan_id=' . (int)($booking['plan_id'] ?? 0) . '&date=' . urlencode($booking['date']))
    : url('cliente/profissional.php?date=' . urlencode($booking['date']) . '&barber_id=' . (int)$booking['barber_id']);
?>
<div class="page-inner">
  <?php client_booking_stepper(4); ?>
  <a class="voltar-link" href="<?= e($backUrl) ?>"><?= icon_svg('back', 16) ?> Voltar e editar</a>
  <header class="client-page-intro client-page-intro--compact">
    <span class="client-eyebrow">Última etapa</span>
    <h1>Revise seu agendamento</h1>
    <p>Confira os detalhes antes de finalizar.</p>
  </header>

  <section class="resumo-card confirmation-summary" aria-label="Resumo completo do agendamento">
    <div class="confirmation-services">
      <span class="confirmation-label">Serviços</span>
      <?php foreach ($services as $service): ?>
        <div class="confirmation-service-row">
          <span class="confirmation-service-icon" aria-hidden="true"><?= icon_svg('scissors', 17) ?></span>
          <span><strong><?= e($service['name']) ?></strong><small><?= (int)$service['duration_min'] ?> min</small></span>
          <strong><?= !empty($booking['from_plan']) && (int)$service['id'] === $planServiceId ? 'Incluso' : e(money((float)$service['price'])) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($booking['from_plan'])): ?>
      <div class="linha"><span>Plano</span><strong>Incluso / extras à parte</strong></div>
    <?php endif; ?>
    <div class="confirmation-details-grid">
      <div><span><?= icon_svg('user', 17) ?> Profissional</span><strong><?= e($barber['name'] ?? '') ?></strong></div>
      <div><span><?= icon_svg('calendar', 17) ?> Data e horário</span><strong><?= e(date('d/m/Y', strtotime($booking['date']))) ?> às <?= e($booking['time']) ?></strong></div>
    </div>
    <div class="confirmation-total"><span>Total</span><strong><?= e(money($total)) ?></strong></div>
  </section>

  <?php if ($error): ?><div class="alert-as danger" role="alert"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="confirmation-form">
    <input type="hidden" name="confirm" value="1">
    <?php if ($loggedClient): ?>
      <p class="page-sub">Confirmando como <strong><?= e($loggedClient['name']) ?></strong></p>
      <button class="btn-confirmar" type="submit"><span>Confirmar agendamento</span><small><?= e($booking['time']) ?> · <?= e(money($total)) ?></small></button>
    <?php else: ?>
      <div class="nome-cliente">
        <label>Seu nome</label>
        <input class="input-nome" type="text" name="client_name" required value="<?= e($_POST['client_name'] ?? ($_SESSION['client_name'] ?? '')) ?>">
      </div>
      <div class="telefone-cliente">
        <label>WhatsApp / Telefone</label>
        <input class="input-telefone" type="tel" name="client_phone" required inputmode="numeric" placeholder="11999999999" value="<?= e($_POST['client_phone'] ?? ($_SESSION['client_phone'] ?? '')) ?>">
      </div>
      <div class="telefone-cliente">
        <label>Senha (somente números)</label>
        <div class="senha-wrap">
          <input class="input-telefone" type="password" name="client_password" id="client_password" required inputmode="numeric" pattern="[0-9]{4,}" placeholder="Digite apenas números" value="<?= e($_POST['client_password'] ?? '') ?>">
          <button type="button" class="eye-btn" id="toggle-senha" aria-label="Mostrar senha"><?= icon_svg('eye', 16) ?></button>
        </div>
        <small class="form-help">Use essa senha depois em “Ver mais” no histórico.</small>
      </div>
      <button class="btn-confirmar" type="submit"><span>Confirmar agendamento</span><small><?= e($booking['time']) ?> · <?= e(money($total)) ?></small></button>
    <?php endif; ?>
  </form>
</div>
<script>
document.getElementById('toggle-senha')?.addEventListener('click', () => {
  const i = document.getElementById('client_password');
  if (i) i.type = i.type === 'password' ? 'text' : 'password';
});
</script>
<?php client_shell_end('agendar'); ?>
