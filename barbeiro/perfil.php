<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['barbeiro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($name !== '') {
        save_user([
            'id' => (int)$user['id'],
            'name' => $name,
            'phone' => $phone,
            'email' => $user['email'],
            'role' => 'barbeiro',
            'active' => 1,
            'password' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : $user['password'],
            'avatar' => $user['avatar'] ?? null,
        ]);
        flash('success', 'Conta atualizada.');
        redirect(url('barbeiro/perfil.php'));
    }
}

$user = current_user();
barber_shell_start('Conta', 'conta');
?>
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
    <span>Nova senha (opcional)</span>
    <input type="password" name="password" autocomplete="new-password">
  </label>
  <button class="bb-btn bb-btn--ok bb-btn--block" type="submit">Salvar</button>
</form>
<a class="bb-btn bb-btn--ghost bb-btn--block" href="<?= e(url('logout.php')) ?>" style="margin-top:.75rem;text-align:center;text-decoration:none">Sair da conta</a>
<?php barber_shell_end('conta'); ?>
