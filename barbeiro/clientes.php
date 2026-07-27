<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['barbeiro']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = preg_replace('/\D+/', '', (string)($_POST['phone'] ?? ''));
        $birth = trim($_POST['birth_date'] ?? '');

        if ($name === '' || strlen($phone) < 10) {
            flash('danger', 'Informe nome e telefone válidos (com DDD).');
            redirect(url('barbeiro/clientes.php'));
        }

        $existing = find_client_by_phone($phone);
        if ($id < 1 && $existing) {
            $id = (int)$existing['id'];
        }
        if ($id > 0) {
            $cur = find_user_by_id($id);
            if (!$cur || ($cur['role'] ?? '') !== 'cliente') {
                flash('danger', 'Cliente não encontrado.');
                redirect(url('barbeiro/clientes.php'));
            }
            save_user([
                'id' => $id,
                'name' => $name,
                'phone' => $phone,
                'birth_date' => $birth !== '' ? $birth : ($cur['birth_date'] ?? null),
                'email' => $cur['email'] ?? null,
                'password' => $cur['password'] ?? password_hash($phone, PASSWORD_DEFAULT),
                'role' => 'cliente',
                'active' => 1,
                'avatar' => $cur['avatar'] ?? null,
            ]);
            flash('success', $existing && (int)($_POST['id'] ?? 0) < 1 ? 'Cliente já existia — dados atualizados.' : 'Cliente atualizado.');
        } else {
            save_user([
                'name' => $name,
                'phone' => $phone,
                'birth_date' => $birth !== '' ? $birth : null,
                'email' => null,
                'password' => password_hash($phone, PASSWORD_DEFAULT),
                'role' => 'cliente',
                'active' => 1,
                'avatar' => null,
            ]);
            flash('success', 'Cliente cadastrado. Senha inicial = telefone.');
        }
    }
    redirect(url('barbeiro/clientes.php'));
}

$q = trim((string)($_GET['q'] ?? ''));
$appointments = store_read('appointments');
$clients = array_values(array_filter(store_read('users'), fn($u) => ($u['role'] ?? '') === 'cliente' && !empty($u['active'])));

foreach ($clients as &$c) {
    $phone = preg_replace('/\D+/', '', (string)($c['phone'] ?? ''));
    $related = array_filter(
        $appointments,
        fn($a) => (int)($a['client_id'] ?? 0) === (int)$c['id']
            || preg_replace('/\D+/', '', (string)($a['client_phone'] ?? '')) === $phone
    );
    $c['visits'] = count($related);
    $dates = array_column($related, 'date');
    rsort($dates);
    $c['last_visit'] = $dates[0] ?? null;
}
unset($c);

if ($q !== '') {
    $needle = mb_strtolower($q);
    $clients = array_values(array_filter($clients, function ($c) use ($needle) {
        $hay = mb_strtolower(($c['name'] ?? '') . ' ' . ($c['phone'] ?? ''));
        return str_contains($hay, $needle);
    }));
}

usort($clients, fn($a, $b) => strcmp((string)($b['last_visit'] ?? ''), (string)($a['last_visit'] ?? '')));

$edit = null;
if (isset($_GET['edit'])) {
    $tmp = find_user_by_id((int)$_GET['edit']);
    if ($tmp && ($tmp['role'] ?? '') === 'cliente') {
        $edit = $tmp;
    }
}

barber_shell_start('Clientes', 'clientes');
?>
<p class="bb-lead">Cadastre clientes da loja. A senha inicial fica o telefone.</p>

<div class="bb-date" style="margin-bottom:12px">
  <form method="get" action="<?= e(url('barbeiro/clientes.php')) ?>" style="flex:1">
    <label>Buscar
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nome ou telefone" enterkeyhint="search">
    </label>
  </form>
  <button type="button" class="bb-btn bb-btn--ok" style="min-height:44px;padding:.55rem .9rem;align-self:flex-end" data-bs-toggle="offcanvas" data-bs-target="#clientSheet">+ Novo</button>
</div>

<?php if (!$clients): ?>
  <div class="bb-empty"><?= $q !== '' ? 'Nenhum resultado.' : 'Nenhum cliente ainda. Cadastre o primeiro.' ?></div>
<?php else: ?>
  <div class="bb-list">
    <?php foreach ($clients as $c): ?>
      <article class="bb-card">
        <div class="bb-card-top">
          <div class="bb-card-name" style="margin:0"><?= e($c['name']) ?></div>
          <span class="bb-stock"><?= (int)$c['visits'] ?> visita<?= (int)$c['visits'] === 1 ? '' : 's' ?></span>
        </div>
        <div class="bb-card-meta"><?= e($c['phone'] ?? '') ?></div>
        <?php if (!empty($c['last_visit'])): ?>
          <div class="bb-card-meta" style="margin-top:-6px">Última: <?= e(date('d/m/Y', strtotime((string)$c['last_visit']))) ?></div>
        <?php endif; ?>
        <div class="bb-actions">
          <a class="bb-btn bb-btn--ghost" style="text-align:center;text-decoration:none;display:grid;place-items:center" href="<?= e(url('barbeiro/clientes.php?edit=' . (int)$c['id'])) ?>">Editar</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="offcanvas offcanvas-bottom bb-sheet" tabindex="-1" id="clientSheet">
  <div class="bb-sheet-head">
    <div>
      <h2 class="bb-sheet-title"><?= $edit ? 'Editar cliente' : 'Novo cliente' ?></h2>
      <div class="bb-sheet-sub">Nome e WhatsApp</div>
    </div>
    <button type="button" class="bb-sheet-close" data-bs-dismiss="offcanvas" aria-label="Fechar">×</button>
  </div>
  <div class="bb-sheet-body">
    <form method="post" class="bb-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <label>
        <span>Nome</span>
        <input name="name" required value="<?= e($edit['name'] ?? '') ?>" autocomplete="name">
      </label>
      <label>
        <span>Telefone (WhatsApp)</span>
        <input name="phone" required value="<?= e($edit['phone'] ?? '') ?>" inputmode="tel" placeholder="11999999999" autocomplete="tel">
      </label>
      <label>
        <span>Nascimento (opcional)</span>
        <input type="date" name="birth_date" value="<?= e($edit['birth_date'] ?? '') ?>">
      </label>
      <?php if (!$edit): ?>
        <p class="bb-live-hint">Senha inicial do app do cliente = telefone.</p>
      <?php endif; ?>
      <button class="bb-btn bb-btn--ok bb-btn--block" type="submit"><?= $edit ? 'Salvar' : 'Cadastrar cliente' ?></button>
      <?php if ($edit): ?>
        <a class="bb-btn bb-btn--ghost bb-btn--block" style="text-align:center;text-decoration:none" href="<?= e(url('barbeiro/clientes.php')) ?>">Cancelar</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($edit): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('clientSheet');
  if (el && window.bootstrap) bootstrap.Offcanvas.getOrCreateInstance(el).show();
});
</script>
<?php endif; ?>
<?php barber_shell_end('clientes'); ?>
