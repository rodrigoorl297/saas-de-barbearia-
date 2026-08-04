<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$booking = $_SESSION['booking'] ?? [];
if (empty($booking['services']) || empty($booking['barber_id'])) {
    redirect(url('cliente/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date'], $_POST['time']) && !isset($_POST['confirm'])) {
    $_SESSION['booking']['date'] = $_POST['date'];
    $_SESSION['booking']['time'] = $_POST['time'];
    if (!empty($_POST['barber_id'])) {
        $_SESSION['booking']['barber_id'] = (int) $_POST['barber_id'];
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

                $refCode = trim((string)($_POST['referral_code'] ?? ''));
                if ($refCode !== '') {
                    loyalty_register_referral($phone, $name, $refCode);
                }
            }
        }
    }

    if ($error === null && !empty($clientId)) {
        $barberId = (int)$booking['barber_id'];
        $lock = acquire_barber_agenda_lock($barberId);
        if ($lock === false) {
            $error = 'Não foi possível confirmar o horário. Tente novamente.';
        } else {
            try {
                // Revalida dentro da trava: o horário escolhido na tela anterior pode
                // já ter sido reservado por outro cliente enquanto este confirmava.
                $cursor = strtotime($booking['date'] . ' ' . $booking['time']);
                $toSave = [];
                foreach ($services as $svc) {
                    $price = (float)$svc['price'];
                    if (!empty($booking['from_plan']) && (int)$svc['id'] === $planServiceId) {
                        $price = 0;
                    }
                    $slotTime = date('H:i', $cursor);
                    $slotDuration = max(15, (int)$svc['duration_min']);
                    if (appointment_slot_conflicts($barberId, $booking['date'], $slotTime, $slotDuration)) {
                        $error = 'Esse horário acabou de ser reservado por outra pessoa. Escolha outro horário.';
                        break;
                    }
                    $toSave[] = [
                        'client_id' => $clientId,
                        'client_name' => $name,
                        'client_phone' => $phone,
                        'barber_id' => $barberId,
                        'service_id' => (int)$svc['id'],
                        'date' => $booking['date'],
                        'time' => $slotTime,
                        'status' => 'agendado',
                        'notes' => !empty($booking['from_plan']) ? 'Plano #' . (int)($booking['plan_id'] ?? 0) : null,
                        'price' => $price,
                    ];
                    $cursor += ($slotDuration * 60);
                }

                if ($error === null) {
                    foreach ($toSave as $appt) {
                        save_appointment($appt);
                    }
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        if ($error === null) {
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

            $clientUser = find_user_by_id($clientId);
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
  <a class="voltar-link" href="<?= e($backUrl) ?>">← Voltar</a>
  <h1 class="page-title">Confirmar agendamento</h1>

  <div class="resumo-card">
    <div class="linha"><span>Serviço</span><strong><?= e(implode(', ', array_column($services, 'name'))) ?></strong></div>
    <?php if (!empty($booking['from_plan'])): ?>
      <div class="linha"><span>Plano</span><strong>Incluso / extras à parte</strong></div>
    <?php endif; ?>
    <div class="linha"><span>Profissional</span><strong><?= e($barber['name'] ?? '') ?></strong></div>
    <div class="linha"><span>Data</span><strong><?= e(date('d/m/Y', strtotime($booking['date']))) ?></strong></div>
    <div class="linha"><span>Horário</span><strong><?= e($booking['time']) ?></strong></div>
    <div class="total"><?= e(money($total)) ?></div>
  </div>

  <?php if ($error): ?><div class="alert-as danger"><?= e($error) ?></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="confirm" value="1">
    <?php if ($loggedClient): ?>
      <p class="page-sub">Confirmando como <strong><?= e($loggedClient['name']) ?></strong></p>
      <button class="btn-confirmar" type="submit">Confirmar agendamento</button>
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
        <small style="opacity:.7;font-size:12px">Use essa senha depois em “Ver mais” no histórico.</small>
      </div>
      <?php if (loyalty_referral_bonus() > 0): ?>
        <div class="telefone-cliente">
          <label>Código de indicação (opcional)</label>
          <input class="input-telefone" type="text" name="referral_code" maxlength="12" placeholder="Ex: JOAO3" value="<?= e($_POST['referral_code'] ?? '') ?>" style="text-transform:uppercase">
          <small style="opacity:.7;font-size:12px">Veio por indicação de um amigo? Vocês dois ganham <?= loyalty_referral_bonus() ?> pontos.</small>
        </div>
      <?php endif; ?>
      <button class="btn-confirmar" type="submit">Confirmar agendamento</button>
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
