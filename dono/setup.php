<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);

// Se já concluiu o setup, vai ao painel
if (!empty(settings()['setup_completed'])) {
    redirect(url('dono/'));
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopName = trim($_POST['shop_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? (string)($user['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if ($shopName === '') {
        $error = 'Informe o nome da barbearia (app do cliente).';
    } elseif (strlen($password) < 6) {
        $error = 'A nova senha precisa ter pelo menos 6 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'As senhas não conferem.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido para o dono.';
    } else {
        save_user([
            'id' => (int)$user['id'],
            'name' => trim($_POST['owner_name'] ?? (string)$user['name']) ?: (string)$user['name'],
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'dono',
            'active' => 1,
        ]);
        save_settings([
            'shop_name' => $shopName,
            'phone' => $phone,
            'setup_completed' => 1,
        ]);
        flash('success', 'Barbearia pronta! Altere os demais dados quando quiser em Configurações.');
        redirect(url('dono/'));
    }
}

$shop = settings();
render_head('Configuração inicial');
?>
<div class="login-wrap">
  <div class="card-soft login-card" style="max-width:520px">
    <div class="text-center mb-3">
      <img class="login-brand-logo mx-auto mb-3" src="<?= e(media_url(product_logo_path())) ?>" alt="<?= e(product_name()) ?>">
      <h1 class="h4 mb-1">Configuração inicial</h1>
      <p class="text-secondary mb-0">Defina a loja do cliente e a senha do dono para começar a vender.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="vstack gap-3">
      <?= csrf_field() ?>
      <div>
        <label class="form-label">Nome no app do cliente</label>
        <input name="shop_name" class="form-control" required value="<?= e($_POST['shop_name'] ?? ($shop['shop_name'] ?? '')) ?>" placeholder="Ex: Barbearia do João">
        <div class="form-text">O link do app será gerado a partir deste nome (ex.: /barbearia-do-joao/).</div>
      </div>
      <div>
        <label class="form-label">Telefone da loja</label>
        <input name="phone" class="form-control" value="<?= e($_POST['phone'] ?? ($shop['phone'] ?? '')) ?>">
      </div>
      <div>
        <label class="form-label">Seu nome</label>
        <input name="owner_name" class="form-control" required value="<?= e($_POST['owner_name'] ?? ($user['name'] ?? '')) ?>">
      </div>
      <div>
        <label class="form-label">E-mail de acesso (dono)</label>
        <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? ($user['email'] ?? '')) ?>">
      </div>
      <div>
        <label class="form-label">Nova senha</label>
        <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password">
      </div>
      <div>
        <label class="form-label">Confirmar senha</label>
        <input type="password" name="password2" class="form-control" required minlength="6" autocomplete="new-password">
      </div>
      <button class="btn btn-accent w-100" type="submit">Concluir e abrir painel</button>
    </form>
  </div>
</div>
<?php render_scripts(false); ?>
