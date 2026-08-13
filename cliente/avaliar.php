<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$client = current_client();
if (!$client) {
    redirect(url('cliente/agendamentos.php'));
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(url('cliente/agendamentos.php'));
}

$appointments = store_read('appointments');
$appointment = null;
foreach ($appointments as $a) {
    if ((int)$a['id'] === $id) {
        $appointment = $a;
        break;
    }
}

// Verifica se existe, pertence ao cliente e está concluído
$phoneNorm = preg_replace('/\D+/', '', (string)$client['phone']);
$clientPhoneMatch = preg_replace('/\D+/', '', (string)($appointment['client_phone'] ?? ''));

if (!$appointment || $clientPhoneMatch !== $phoneNorm || $appointment['status'] !== 'concluido') {
    flash('danger', 'Agendamento inválido ou não concluído.');
    redirect(url('cliente/agendamentos.php'));
}

// Se já foi avaliado
if (!empty($appointment['rating'])) {
    flash('warning', 'Este atendimento já foi avaliado.');
    redirect(url('cliente/agendamentos.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));

    if ($rating >= 1 && $rating <= 5) {
        $appointment['rating'] = $rating;
        $appointment['rating_comment'] = $comment;
        $appointment['rated_at'] = date('Y-m-d H:i:s');

        // Atualiza na base
        foreach ($appointments as $k => $a) {
            if ((int)$a['id'] === $id) {
                $appointments[$k] = $appointment;
                break;
            }
        }
        store_write('appointments', $appointments);

        $shop = settings();
        if ($rating === 5 && !empty($shop['google_my_business_url'])) {
            flash('success', 'Ficamos felizes com as 5 estrelas! Ajude-nos avaliando no Google também!');
            redirect(normalize_external_url($shop['google_my_business_url']));
        } else {
            flash('success', 'Obrigado pela sua avaliação!');
            redirect(url('cliente/agendamentos.php'));
        }
    } else {
        $error = 'Por favor, selecione de 1 a 5 estrelas.';
    }
}

// Enriquecer dados para exibir nome do serviço e barbeiro
$services = store_read('services');
$users = store_read('users');

$serviceName = 'Serviço';
if (!empty($appointment['service_id'])) {
    foreach ($services as $s) {
        if ((int)$s['id'] === (int)$appointment['service_id']) {
            $serviceName = $s['name'];
            break;
        }
    }
} elseif (!empty($appointment['service_ids']) && is_array($appointment['service_ids'])) {
    $sid = $appointment['service_ids'][0];
    foreach ($services as $s) {
        if ((int)$s['id'] === (int)$sid) {
            $serviceName = $s['name'];
            break;
        }
    }
}

$barberName = 'Barbeiro';
if (!empty($appointment['barber_id'])) {
    foreach ($users as $u) {
        if ((int)$u['id'] === (int)$appointment['barber_id']) {
            $barberName = $u['name'];
            break;
        }
    }
}

render_head('Avaliar Atendimento', true);
client_shell_start('agendamentos');
?>

<div class="page-inner">
  <h1 class="page-title">Avaliar Atendimento</h1>
  <div class="historico-divider"></div>

  <?php if (!empty($error)): ?>
    <div class="alert-as danger"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="hist-card" style="margin-bottom: 24px;">
    <div class="hist-card-top">
      <strong><?= e(date('d/m/Y', strtotime($appointment['date']))) ?> · <?= e(substr($appointment['time'], 0, 2)) ?>h</strong>
      <span class="badge-as ok">Concluído</span>
    </div>
    <div class="hist-card-body">
      <div><?= e($serviceName) ?></div>
      <div class="hist-card-foot" style="margin-top: 8px;">
        <span><span class="user-mini"><?= icon_svg('user', 14) ?></span> <?= e($barberName) ?></span>
      </div>
    </div>
  </div>

  <form method="post" class="modal-as-card" style="background: transparent; padding: 0;">
    <p style="text-align: center; margin-bottom: 16px; color: #e8eaf0;">Como foi o seu atendimento?</p>
    
    <div class="rating-stars" style="display: flex; justify-content: center; gap: 8px; flex-direction: row-reverse; margin-bottom: 24px;">
      <?php for ($i = 5; $i >= 1; $i--): ?>
        <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" style="display: none;" required>
        <label for="star<?= $i ?>" style="cursor: pointer; color: #444;">
          <svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
          </svg>
        </label>
      <?php endfor; ?>
    </div>

    <style>
      .rating-stars label:hover,
      .rating-stars label:hover ~ label,
      .rating-stars input:checked + label,
      .rating-stars input:checked ~ label {
        color: #c9a227 !important;
      }
    </style>

    <label class="modal-label">Comentário (opcional):</label>
    <textarea name="comment" class="input-telefone" rows="3" placeholder="Conte-nos o que achou..." style="resize: none;"></textarea>

    <button class="btn-confirmar" type="submit" style="margin-top: 16px;">Enviar Avaliação</button>
    <a class="voltar-link" href="<?= e(url('cliente/agendamentos.php')) ?>" style="margin-top:16px; display:inline-block">Cancelar</a>
  </form>
</div>

<?php client_shell_end('agendamentos'); ?>
