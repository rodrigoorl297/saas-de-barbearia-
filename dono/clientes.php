<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');

        if ($name && $phone) {
            $payload = [
                'name' => $name,
                'phone' => $phone,
                'birth_date' => $birth_date,
                'role' => 'cliente',
                'active' => 1,
            ];

            if ($id > 0) {
                $cur = find_user_by_id($id);
                $payload['id'] = $id;
                $payload['password'] = $cur['password'] ?? '';
                $payload['email'] = $cur['email'] ?? null;
            } else {
                $payload['password'] = password_hash($phone, PASSWORD_DEFAULT);
            }

            save_user($payload);
            flash('success', 'Cliente salvo com sucesso.');
        } else {
            flash('danger', 'Nome e telefone são obrigatórios.');
        }
    }
    redirect(url('dono/clientes.php'));
}

$appointments = store_read('appointments');
$clients = array_values(array_filter(store_read('users'), fn($u) => ($u['role'] ?? '') === 'cliente'));

foreach ($clients as &$c) {
    $phone = $c['phone'] ?? '';
    $related = array_filter($appointments, fn($a) => (int)($a['client_id'] ?? 0) === (int)$c['id'] || ($a['client_phone'] ?? '') === $phone);
    $c['visits'] = count($related);
    $dates = array_column($related, 'date');
    rsort($dates);
    $c['last_visit'] = $dates[0] ?? null;
}
unset($c);
usort($clients, fn($a, $b) => strcmp((string)($b['last_visit'] ?? ''), (string)($a['last_visit'] ?? '')));

$edit = null;
if (isset($_GET['edit'])) {
    $tmp = find_user_by_id((int)$_GET['edit']);
    if ($tmp && ($tmp['role'] ?? '') === 'cliente') {
        $edit = $tmp;
    }
}

admin_layout_start('Clientes', 'dono', 'clientes');
?>
<div class="stock-page">
  <div class="stock-toolbar">
    <div>
      <h2 class="stock-heading">Clientes</h2>
      <p class="stock-sub">Base de clientes e histórico de visitas.</p>
    </div>
    <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#clientModal">+ Novo cliente</button>
  </div>

  <div class="stock-panel">
    <div class="stock-panel-head">
      <div>
        <strong>Lista de clientes</strong>
        <span> · <?= count($clients) ?> registro(s)</span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table stock-table align-middle mb-0">
        <thead>
          <tr>
            <th class="col-prod">Nome</th>
            <th>Telefone</th>
            <th>Nascimento</th>
            <th class="col-num">Visitas</th>
            <th>Última visita</th>
            <th class="col-actions text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($clients as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?></strong></td>
            <td><?= e($c['phone'] ?? '') ?></td>
            <td><?= !empty($c['birth_date']) ? e(date('d/m/Y', strtotime($c['birth_date']))) : '—' ?></td>
            <td><?= (int)$c['visits'] ?></td>
            <td><?= $c['last_visit'] ? e(date('d/m/Y', strtotime($c['last_visit']))) : '—' ?></td>
            <td>
              <div class="stock-actions">
                <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$c['id'] ?>">Editar</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$clients): ?>
          <tr><td colspan="6" class="text-secondary text-center py-4">Nenhum cliente ainda.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><?= $edit ? 'Editar cliente' : 'Novo cliente' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" class="vstack gap-3">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div>
            <label class="form-label">Nome</label>
            <input name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>">
          </div>
          <div>
            <label class="form-label">Telefone (WhatsApp)</label>
            <input name="phone" class="form-control" required value="<?= e($edit['phone'] ?? '') ?>" placeholder="Ex: 11999999999">
            <?php if (!$edit): ?>
              <div class="form-text">A senha inicial do cliente será o próprio telefone.</div>
            <?php endif; ?>
          </div>
          <div>
            <label class="form-label">Data de nascimento (opcional)</label>
            <input type="date" name="birth_date" class="form-control" value="<?= e($edit['birth_date'] ?? '') ?>">
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="<?= e(url('dono/clientes.php')) ?>">Cancelar</a>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <?php endif; ?>
            <button class="btn btn-accent" type="submit">Salvar cliente</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($edit): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('clientModal');
  if (el && window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();
});
</script>
<?php endif; ?>
<?php admin_layout_end(); ?>
