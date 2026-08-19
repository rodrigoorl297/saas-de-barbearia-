<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $cur = $id > 0 ? find_service($id) : null;
        if ($id > 0 && !$cur) {
            flash('danger', 'Serviço não encontrado.');
            redirect(url('dono/servicos.php'));
        }
        $image = $cur['image_url'] ?? '';
        $uploaded = upload_image($_FILES['image'] ?? [], 'servicos');
        if ($uploaded) {
            $image = $uploaded;
        }

        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'price' => max(0, (float)($_POST['price'] ?? 0)),
            'duration_min' => max(15, (int)($_POST['duration_min'] ?? 60)),
            'active' => isset($_POST['active']) ? 1 : 0,
            'image_url' => $image,
            'sort_order' => (int)($cur['sort_order'] ?? 99),
        ];
        if ($payload['name'] !== '') {
            if ($id > 0) {
                $payload['id'] = $id;
            }
            save_service($payload);
            flash('success', 'Serviço salvo.');
        }
    }
    if ($action === 'delete') {
        $svc = find_service((int)$_POST['id']);
        if ($svc) {
            $svc['active'] = 0;
            save_service($svc);
            flash('success', 'Serviço desativado.');
        }
    }
    redirect(url('dono/servicos.php'));
}

$services = store_read('services');
usort($services, fn($a, $b) => ((int)$b['active'] <=> (int)$a['active']) ?: ((int)$a['sort_order'] <=> (int)$b['sort_order']));
$edit = isset($_GET['edit']) ? find_service((int)$_GET['edit']) : null;
$ativos = count(array_filter($services, fn($s) => !empty($s['active'])));

admin_layout_start('Serviços', 'dono', 'servicos');
?>
<div class="stock-page">
  <div class="stock-toolbar">
    <div>
      <h2 class="stock-heading">Catálogo de serviços</h2>
      <p class="stock-sub">Aparecem no app do cliente quando estão ativos.</p>
    </div>
    <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#serviceModal">+ Novo serviço</button>
  </div>

  <div class="stock-kpis">
    <div class="stock-kpi">
      <span class="stock-kpi-label">Total</span>
      <strong class="stock-kpi-value"><?= count($services) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Ativos</span>
      <strong class="stock-kpi-value"><?= $ativos ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Inativos</span>
      <strong class="stock-kpi-value"><?= count($services) - $ativos ?></strong>
    </div>
  </div>

  <div class="row g-3">
    <?php if (!$services): ?>
      <div class="col-12">
        <div class="stock-panel">
          <div class="stock-empty">
            <p>Nenhum serviço cadastrado.</p>
            <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#serviceModal">Cadastrar primeiro serviço</button>
          </div>
        </div>
      </div>
    <?php endif; ?>
    <?php foreach ($services as $s): ?>
      <div class="col-sm-6 col-xl-4">
        <div class="card-soft overflow-hidden h-100">
          <div style="height:140px;background:#0f172a;overflow:hidden">
            <?php if (!empty($s['image_url'])): ?>
              <img src="<?= e(media_url($s['image_url'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
              <div class="h-100 d-flex align-items-center justify-content-center text-white-50 fw-bold fs-3"><?= e(str_upper(str_cut($s['name'], 0, 1))) ?></div>
            <?php endif; ?>
          </div>
          <div class="p-3">
            <div class="d-flex justify-content-between gap-2 align-items-start">
              <strong><?= e($s['name']) ?></strong>
              <?= !empty($s['active']) ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Off</span>' ?>
            </div>
            <div class="small text-secondary mt-1"><?= e($s['description'] ?? '') ?></div>
            <div class="mt-2 fw-bold"><?= e(money((float)$s['price'])) ?> · <?= (int)$s['duration_min'] ?> min</div>
            <div class="mt-2 d-flex gap-1 flex-wrap">
              <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$s['id'] ?>">Editar</a>
              <?php if (!empty($s['active'])): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Desativar serviço?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit">Desativar</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><?= $edit ? 'Editar serviço' : 'Novo serviço' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <?php if (!empty($edit['image_url'])): ?>
            <img class="image-upload-current" src="<?= e(media_url($edit['image_url'])) ?>" alt="Foto atual">
          <?php endif; ?>
          <div class="js-image-upload">
            <label class="form-label">Foto do serviço</label>
            <input type="file" name="image" class="form-control" accept="image/*" aria-label="Imagem do serviço">
          </div>
          <div>
            <label class="form-label">Nome</label>
            <input name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>" aria-label="Nome do serviço">
          </div>
          <div>
            <label class="form-label">Descrição</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Ex: Degradê, low fade, acabamento na navalha..." aria-label="Descrição do serviço"><?= e($edit['description'] ?? '') ?></textarea>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Preço (R$)</label>
              <input type="number" step="0.01" min="0" name="price" class="form-control" required value="<?= e((string)($edit['price'] ?? '')) ?>" aria-label="Preço do serviço">
            </div>
            <div class="col-6">
              <label class="form-label">Tempo (min)</label>
              <input type="number" name="duration_min" class="form-control" value="<?= e((string)($edit['duration_min'] ?? 60)) ?>" min="15" step="15" aria-label="Duração do serviço em minutos">
            </div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="active" id="active" <?= !isset($edit['active']) || $edit['active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="active">Ativo no catálogo do cliente</label>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="<?= e(url('dono/servicos.php')) ?>">Cancelar</a>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <?php endif; ?>
            <button class="btn btn-accent" type="submit">Salvar serviço</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($edit): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('serviceModal');
  if (el && window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();
});
</script>
<?php endif; ?>
<?php admin_layout_end(); ?>
