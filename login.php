<?php
/**
 * Login unificado da equipe (dono e barbeiro).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/layout.php';

$user = current_user();
if ($user && in_array($user['role'], ['dono', 'barbeiro'], true)) {
    redirect(panel_home_for($user['role']));
}

$error = null;
if (login_is_locked()) {
    $error = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login_is_locked()) {
        $error = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $auth = attempt_login($email, $password, null);
        if ($auth) {
            login_user($auth);
            redirect(panel_home_for($auth['role']));
        }
        $error = 'E-mail ou senha inválidos.';
        if (login_is_locked()) {
            $error = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
        }
    }
}

render_head('Entrar');
?>
<div class="login-stage">
  <div class="login-stage-glow" aria-hidden="true"></div>

  <div class="login-shell login-shell--form">
    <header class="login-hero login-hero--compact">
      <img class="login-hero-logo" src="<?= e(media_url(product_logo_path())) ?>" alt="<?= e(product_name()) ?>">
      <p class="login-kicker">Sistema para barbearias</p>
      <h1 class="login-title"><?= e(str_upper(product_name())) ?></h1>
    </header>

    <?php if ($error): ?>
      <div class="login-alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="login-form">
      <?= csrf_field() ?>
      <label class="login-label">
        <span>E-mail</span>
        <input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" placeholder="voce@suaempresa.com">
      </label>
      <label class="login-label">
        <span>Senha</span>
        <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
      </label>
      <button class="login-submit" type="submit" <?= login_is_locked() ? 'disabled' : '' ?>>Entrar</button>
    </form>

    <footer class="login-foot">
      <a href="<?= e(url('cliente/')) ?>">Sou cliente — abrir agendamento</a>
    </footer>
  </div>
</div>
<?php render_scripts(false); ?>
