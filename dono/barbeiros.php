<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $active = isset($_POST['active']) ? 1 : 0;

        $work_start = normalize_time((string)($_POST['work_start'] ?? ''), '08:00');
        $work_end = normalize_time((string)($_POST['work_end'] ?? ''), '20:00');
        $lunch_start = normalize_time((string)($_POST['lunch_start'] ?? ''), '12:00');
        $lunch_end = normalize_time((string)($_POST['lunch_end'] ?? ''), '13:00');
        $work_days = isset($_POST['work_days']) && is_array($_POST['work_days'])
            ? array_values(array_unique(array_filter(array_map('intval', $_POST['work_days']), static fn($day) => $day >= 0 && $day <= 6)))
            : [1, 2, 3, 4, 5, 6];

        if ($name && $email) {
            $cur = $id > 0 ? find_user_by_id($id) : null;
            if ($id > 0 && (!$cur || ($cur['role'] ?? '') !== 'barbeiro')) {
                flash('danger', 'Barbeiro não encontrado.');
                redirect(url('dono/barbeiros.php'));
            }
            $avatar = $cur['avatar'] ?? null;
            $uploaded = upload_image($_FILES['avatar'] ?? [], 'barbeiros');
            if ($uploaded) {
                $avatar = $uploaded;
            }

            $payload = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => 'barbeiro',
                'active' => $active,
                'avatar' => $avatar,
                'work_start' => $work_start,
                'work_end' => $work_end,
                'lunch_start' => $lunch_start,
                'lunch_end' => $lunch_end,
                'work_days' => $work_days,
            ];
            if ($id > 0) {
                $payload['id'] = $id;
                $payload['password'] = ($password !== '')
                    ? password_hash($password, PASSWORD_DEFAULT)
                    : ($cur['password'] ?? '');
                if ($password !== '') {
                    // Dono redefiniu a senha manualmente: barbeiro precisa trocá-la no próximo acesso.
                    $payload['must_change_password'] = 1;
                }
            } else {
                $payload['password'] = password_hash($password !== '' ? $password : 'barbeiro123', PASSWORD_DEFAULT);
                if ($password === '') {
                    // Sem senha customizada, caiu na senha padrão previsível — força troca no primeiro login.
                    $payload['must_change_password'] = 1;
                }
            }
            save_user($payload);
            flash('success', 'Barbeiro salvo com sucesso.');
        }
    }
    redirect(url('dono/barbeiros.php'));
}

$barbers = array_values(array_filter(store_read('users'), fn($u) => ($u['role'] ?? '') === 'barbeiro'));
usort($barbers, fn($a, $b) => ((int)$b['active'] <=> (int)$a['active']) ?: strcmp($a['name'], $b['name']));
$edit = null;
if (isset($_GET['edit'])) {
    $tmp = find_user_by_id((int)$_GET['edit']);
    if ($tmp && ($tmp['role'] ?? '') === 'barbeiro') {
        $edit = $tmp;
    }
}
$ativos = count(array_filter($barbers, fn($b) => !empty($b['active'])));

admin_layout_start('Barbeiros', 'dono', 'barbeiros');
?>
<div class="stock-page">
  <div class="stock-toolbar">
    <div>
      <h2 class="stock-heading">Equipe de barbeiros</h2>
      <p class="stock-sub">Fotos, horários e disponibilidade do dia.</p>
    </div>
    <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#barberModal">+ Novo barbeiro</button>
  </div>

  <div class="stock-kpis">
    <div class="stock-kpi">
      <span class="stock-kpi-label">Total</span>
      <strong class="stock-kpi-value"><?= count($barbers) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Ativos</span>
      <strong class="stock-kpi-value"><?= $ativos ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Inativos</span>
      <strong class="stock-kpi-value"><?= count($barbers) - $ativos ?></strong>
    </div>
  </div>

  <?php if (!$barbers): ?>
    <div class="stock-panel">
      <div class="stock-empty">
        <p>Nenhum barbeiro cadastrado.</p>
        <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#barberModal">Cadastrar primeiro barbeiro</button>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($barbers as $b):
          $slots = !empty($b['active']) ? available_slots((int)$b['id'], $today, 60) : [];
          $photo = media_url($b['avatar'] ?? '');
      ?>
        <div class="col-md-6">
          <div class="card-soft p-3 h-100">
            <div class="d-flex gap-3 align-items-start">
              <div style="width:64px;height:64px;border-radius:50%;overflow:hidden;background:#0f172a;display:grid;place-items:center;flex-shrink:0;font-weight:700;color:#c9a227">
                <?php if ($photo): ?>
                  <img src="<?= e($photo) ?>" alt="<?= e($b['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                <?php else: ?>
                  <?= e(initials($b['name'])) ?>
                <?php endif; ?>
              </div>
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex justify-content-between gap-2 flex-wrap">
                  <div>
                    <div class="fw-bold"><?= e($b['name']) ?></div>
                    <div class="small text-secondary"><?= e($b['email']) ?></div>
                  </div>
                  <div class="d-flex gap-1 align-items-start">
                    <?= !empty($b['active']) ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' ?>
                    <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$b['id'] ?>">Editar</a>
                  </div>
                </div>
                <div class="mt-2 small text-secondary">Livres hoje (<?= e(date('d/m')) ?>):</div>
                <div class="d-flex flex-wrap gap-1 mt-1">
                  <?php if (!$slots): ?>
                    <span class="badge text-bg-secondary">Sem horários</span>
                  <?php else: ?>
                    <?php foreach (array_slice($slots, 0, 8) as $slot): ?>
                      <span class="badge text-bg-success"><?= e($slot) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($slots) > 8): ?>
                      <span class="badge text-bg-light text-dark">+<?= count($slots) - 8 ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="barberModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><?= $edit ? 'Editar barbeiro' : 'Novo barbeiro' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

          <?php if (!empty($edit['avatar'])): ?>
            <img class="image-upload-current image-upload-current--round" src="<?= e(media_url($edit['avatar'])) ?>" alt="Foto atual">
          <?php endif; ?>

          <div class="js-image-upload">
            <label class="form-label">Foto</label>
            <input type="file" name="avatar" class="form-control" accept="image/*" aria-label="Foto do barbeiro">
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Nome</label>
              <input name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>" aria-label="Nome do barbeiro">
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail (login)</label>
              <input type="email" name="email" class="form-control" required value="<?= e($edit['email'] ?? '') ?>" aria-label="E-mail do barbeiro">
            </div>
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Telefone</label>
              <input name="phone" class="form-control" value="<?= e($edit['phone'] ?? '') ?>" aria-label="Telefone do barbeiro">
            </div>
            <div class="col-md-6">
              <label class="form-label">Senha <?= $edit ? '(vazio = manter)' : '' ?></label>
              <input type="password" name="password" class="form-control" <?= $edit ? '' : 'required' ?> aria-label="Senha do barbeiro">
            </div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="active" id="active" <?= !isset($edit['active']) || $edit['active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="active">Ativo (aparece no app do cliente)</label>
          </div>

          <hr class="my-1">
          <div class="fw-semibold">Horários de trabalho</div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Início</label>
              <input type="time" name="work_start" class="form-control" value="<?= e($edit['work_start'] ?? '08:00') ?>" aria-label="Início do expediente">
            </div>
            <div class="col-6">
              <label class="form-label">Fim</label>
              <input type="time" name="work_end" class="form-control" value="<?= e($edit['work_end'] ?? '20:00') ?>" aria-label="Fim do expediente">
            </div>
            <div class="col-6">
              <label class="form-label">Almoço início</label>
              <input type="time" name="lunch_start" class="form-control" value="<?= e($edit['lunch_start'] ?? '12:00') ?>" aria-label="Início do almoço">
            </div>
            <div class="col-6">
              <label class="form-label">Almoço fim</label>
              <input type="time" name="lunch_end" class="form-control" value="<?= e($edit['lunch_end'] ?? '13:00') ?>" aria-label="Fim do almoço">
            </div>
          </div>
          <div>
            <label class="form-label">Dias de trabalho</label>
            <div class="d-flex flex-wrap gap-2">
              <?php
              $wdays = $edit['work_days'] ?? [1, 2, 3, 4, 5, 6];
              $dayNames = [0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
              foreach ($dayNames as $dVal => $dName):
              ?>
                <label class="form-check mb-0">
                  <input type="checkbox" name="work_days[]" value="<?= $dVal ?>" class="form-check-input" <?= in_array($dVal, $wdays, true) ? 'checked' : '' ?>>
                  <span class="form-check-label small"><?= $dName ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="d-flex gap-2 justify-content-end">
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="<?= e(url('dono/barbeiros.php')) ?>">Cancelar</a>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <?php endif; ?>
            <button class="btn btn-accent" type="submit">Salvar barbeiro</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($edit): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('barberModal');
  if (el && window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();
});
</script>
<?php endif; ?>
<?php admin_layout_end(); ?>
