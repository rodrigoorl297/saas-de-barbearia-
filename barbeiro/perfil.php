<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['barbeiro']);
$today = date('Y-m-d');
$forcarTrocaSenha = !empty($user['must_change_password']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($forcarTrocaSenha && $password === '') {
        flash('danger', 'Defina uma nova senha para continuar.');
        redirect(url('barbeiro/perfil.php?trocar_senha=1'));
    }
    if ($name !== '') {
        $payload = [
            'id' => (int)$user['id'],
            'name' => $name,
            'phone' => $phone,
            'email' => $user['email'],
            'role' => 'barbeiro',
            'active' => 1,
            'password' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : $user['password'],
            'avatar' => $user['avatar'] ?? null,
        ];
        if ($password !== '') {
            $payload['must_change_password'] = 0;
        }
        save_user($payload);
        flash('success', 'Dados atualizados.');
        redirect(url('barbeiro/perfil.php'));
    }
}

$user = current_user();
$mineToday = appointments_enriched(fn($a) => (int)$a['barber_id'] === (int)$user['id'] && ($a['date'] ?? '') === $today);
$dayStats = barber_daily_stats((int)$user['id'], $today);
$openToday = count(array_filter($mineToday, fn($a) => !in_array($a['status'] ?? '', ['concluido', 'cancelado', 'faltou'], true)));
$avatar = trim((string)($user['avatar'] ?? ''));
$initials = '';
foreach (preg_split('/\s+/', trim((string)($user['name'] ?? 'B'))) as $part) {
    if ($part !== '') {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    if (mb_strlen($initials) >= 2) {
        break;
    }
}
if ($initials === '') {
    $initials = 'B';
}

barber_shell_start('', 'conta');
?>
<?php if ($forcarTrocaSenha): ?>
  <div class="alert alert-danger m-3" role="alert">
    Por segurança, defina uma senha nova antes de continuar usando o painel.
  </div>
<?php endif; ?>
<section class="bb-profile">
  <div class="bb-profile-card">
    <button
      type="button"
      class="bb-gear"
      data-bs-toggle="offcanvas"
      data-bs-target="#accountSettings"
      aria-label="Configurações da conta"
      title="Configurações"
    >
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="2"/>
        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.2.6.7 1 1.5 1.1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
      </svg>
    </button>

    <div class="bb-avatar">
      <?php if ($avatar !== ''): ?>
        <img src="<?= e(media_url($avatar)) ?>" alt="">
      <?php else: ?>
        <span><?= e($initials) ?></span>
      <?php endif; ?>
    </div>

    <h2 class="bb-profile-name"><?= e($user['name'] ?? 'Barbeiro') ?></h2>
    <p class="bb-profile-role">Barbeiro · <?= e(product_name()) ?></p>
    <p class="bb-profile-mail"><?= e($user['email'] ?? '') ?></p>
  </div>

  <div class="bb-kpis bb-kpis--3">
    <div class="bb-kpi"><span>Na fila hoje</span><strong><?= (int)$openToday ?></strong></div>
    <div class="bb-kpi"><span>Cortes hoje</span><strong><?= (int)$dayStats['cuts'] ?></strong></div>
    <div class="bb-kpi bb-kpi--accent"><span>Faturado</span><strong class="bb-money"><?= e(money((float)$dayStats['total'])) ?></strong></div>
  </div>

  <div class="bb-list">
    <button
      type="button"
      class="bb-menu-row"
      data-bs-toggle="offcanvas"
      data-bs-target="#accountSettings"
    >
      <span class="bb-menu-ico" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="2"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.2.6.7 1 1.5 1.1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z" stroke="currentColor" stroke-width="1.6"/></svg>
      </span>
      <span class="bb-menu-text">
        <strong>Dados da conta</strong>
        <small>Nome, telefone e senha</small>
      </span>
      <span class="bb-menu-chev" aria-hidden="true">›</span>
    </button>

    <a class="bb-menu-row" href="<?= e(url('barbeiro/')) ?>">
      <span class="bb-menu-ico" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="2"/></svg>
      </span>
      <span class="bb-menu-text">
        <strong>Agenda de hoje</strong>
        <small>Ver clientes na fila</small>
      </span>
      <span class="bb-menu-chev" aria-hidden="true">›</span>
    </a>
  </div>

  <a class="bb-btn bb-btn--ghost bb-btn--block bb-logout" href="<?= e(url('logout.php')) ?>">Sair da conta</a>
</section>

<div class="offcanvas offcanvas-bottom bb-sheet" tabindex="-1" id="accountSettings" aria-labelledby="accountSettingsTitle">
  <div class="bb-sheet-head">
    <div>
      <h2 class="bb-sheet-title" id="accountSettingsTitle">Dados da conta</h2>
      <div class="bb-sheet-sub">Ajuste suas informações</div>
    </div>
    <button type="button" class="bb-sheet-close" data-bs-dismiss="offcanvas" aria-label="Fechar">×</button>
  </div>
  <div class="bb-sheet-body">
    <form method="post" class="bb-form">
      <?= csrf_field() ?>
      <label>
        <span>Nome</span>
        <input name="name" required value="<?= e($user['name'] ?? '') ?>">
      </label>
      <label>
        <span>E-mail</span>
        <input value="<?= e($user['email'] ?? '') ?>" disabled>
      </label>
      <label>
        <span>Telefone</span>
        <input name="phone" value="<?= e($user['phone'] ?? '') ?>" inputmode="tel">
      </label>
      <label>
        <span>Nova senha <?= $forcarTrocaSenha ? '(obrigatória)' : '(opcional)' ?></span>
        <input type="password" name="password" autocomplete="new-password" <?= $forcarTrocaSenha ? 'required' : '' ?> placeholder="<?= $forcarTrocaSenha ? 'Digite sua nova senha' : 'Deixe em branco para manter' ?>">
      </label>
      <button class="bb-btn bb-btn--ok bb-btn--block" type="submit">Salvar alterações</button>
    </form>
  </div>
</div>
<?php if ($forcarTrocaSenha): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('accountSettings');
  if (el && window.bootstrap) {
    new bootstrap.Offcanvas(el).show();
  }
});
</script>
<?php endif; ?>
<?php barber_shell_end('conta'); ?>
