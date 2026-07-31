<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/upload.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $cur = $id > 0 ? find_plan($id) : null;
        $images = $cur['images'] ?? ['', '', ''];
        if (!is_array($images)) {
            $images = ['', '', ''];
        }
        while (count($images) < 3) {
            $images[] = '';
        }

        for ($i = 0; $i < 3; $i++) {
            $uploaded = upload_image($_FILES['image_' . $i] ?? [], 'planos');
            if ($uploaded) {
                $images[$i] = $uploaded;
            }
            if (!empty($_POST['clear_image_' . $i])) {
                $images[$i] = '';
            }
        }

        $limitRaw = trim((string)($_POST['usage_limit'] ?? ''));
        $usageLimit = ($limitRaw === '' || strtolower($limitRaw) === 'inf' || $limitRaw === '∞')
            ? null
            : max(1, (int)$limitRaw);

        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'price' => (float)($_POST['price'] ?? 0),
            'interval' => trim($_POST['interval'] ?? 'Mês') ?: 'Mês',
            'headline' => trim($_POST['headline'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'benefit_label' => trim($_POST['benefit_label'] ?? 'Corte plano') ?: 'Corte plano',
            'usage_limit' => $usageLimit,
            'images' => array_values(array_slice($images, 0, 3)),
            'recommended' => isset($_POST['recommended']) ? 1 : 0,
            'active' => isset($_POST['active']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? ($cur['sort_order'] ?? 99)),
        ];

        if ($payload['name'] !== '') {
            if ($id > 0) {
                $payload['id'] = $id;
            }
            save_plan($payload);
            flash('success', 'Plano salvo.');
        }
    }

    if ($action === 'delete') {
        $plan = find_plan((int)$_POST['id']);
        if ($plan) {
            $plan['active'] = 0;
            save_plan($plan);
            flash('success', 'Plano desativado.');
        }
    }

    redirect(url('dono/planos.php'));
}

$plans = store_read('plans');
usort($plans, fn($a, $b) => ((int)$b['active'] <=> (int)$a['active']) ?: ((int)($a['sort_order'] ?? 99) <=> (int)($b['sort_order'] ?? 99)));
$edit = isset($_GET['edit']) ? find_plan((int)$_GET['edit']) : null;
$editImages = $edit['images'] ?? ['', '', ''];
if (!is_array($editImages)) {
    $editImages = ['', '', ''];
}
while (count($editImages) < 3) {
    $editImages[] = '';
}
$ativos = count(array_filter($plans, fn($p) => !empty($p['active'])));

admin_layout_start('Planos e pacotes', 'dono', 'planos');
?>
<div class="stock-page">
  <div class="stock-toolbar">
    <div>
      <h2 class="stock-heading">Planos e pacotes</h2>
      <p class="stock-sub">Assinaturas exibidas na conta do cliente.</p>
    </div>
    <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#planModal">+ Novo plano</button>
  </div>

  <div class="stock-kpis">
    <div class="stock-kpi">
      <span class="stock-kpi-label">Total</span>
      <strong class="stock-kpi-value"><?= count($plans) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Ativos</span>
      <strong class="stock-kpi-value"><?= $ativos ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Inativos</span>
      <strong class="stock-kpi-value"><?= count($plans) - $ativos ?></strong>
    </div>
  </div>

  <div class="row g-3">
    <?php if (!$plans): ?>
      <div class="col-12">
        <div class="stock-panel">
          <div class="stock-empty">
            <p>Nenhum plano criado ainda.</p>
            <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#planModal">Criar primeiro plano</button>
          </div>
        </div>
      </div>
    <?php endif; ?>
    <?php foreach ($plans as $p):
        $imgs = $p['images'] ?? [];
        $limit = array_key_exists('usage_limit', $p) && $p['usage_limit'] !== null ? (string)$p['usage_limit'] : 'ilimitado';
    ?>
      <div class="col-md-6 col-xl-4">
        <div class="card-soft overflow-hidden h-100">
          <div class="p-3 d-flex justify-content-between align-items-start gap-2" style="background:#f1f5f9;color:#0f172a">
            <strong><?= e($p['name']) ?></strong>
            <span class="badge text-bg-dark"><?= e(money((float)$p['price'])) ?> <?= e($p['interval'] ?? 'Mês') ?></span>
          </div>
          <div class="p-3">
            <?php if (!empty($p['recommended'])): ?>
              <span class="badge text-bg-warning mb-2">Recomendado</span>
            <?php endif; ?>
            <div class="d-flex gap-2 mb-2">
              <?php for ($i = 0; $i < 3; $i++): ?>
                <div style="width:44px;height:44px;border-radius:10px;background:#e2e8f0;overflow:hidden;display:grid;place-items:center;color:#64748b">
                  <?php if (!empty($imgs[$i])): ?>
                    <img src="<?= e(media_url($imgs[$i])) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
                  <?php else: ?><?= icon_svg('camera', 14) ?><?php endif; ?>
                </div>
              <?php endfor; ?>
            </div>
            <div class="fw-semibold"><?= e($p['headline'] ?? '') ?></div>
            <div class="small text-secondary mt-1"><?= e($p['description'] ?? '') ?></div>
            <div class="small mt-2 text-secondary"><?= e($p['benefit_label'] ?? 'Benefício') ?> · limite <?= e($limit) ?></div>
            <div class="mt-2 d-flex gap-2 align-items-center flex-wrap">
              <?= !empty($p['active']) ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Off</span>' ?>
              <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$p['id'] ?>">Editar</a>
              <?php if (!empty($p['active'])): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Desativar plano?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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

<div class="modal fade" id="planModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><?= $edit ? 'Editar plano' : 'Novo plano' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

          <div>
            <label class="form-label">Nome do plano</label>
            <input name="name" class="form-control" required placeholder="Ex: Clube Suprema" value="<?= e($edit['name'] ?? '') ?>">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Preço (R$)</label>
              <input type="number" step="0.01" name="price" class="form-control" required value="<?= e((string)($edit['price'] ?? '')) ?>">
            </div>
            <div class="col-6">
              <label class="form-label">Período</label>
              <input name="interval" class="form-control" value="<?= e($edit['interval'] ?? 'Mês') ?>" placeholder="Mês">
            </div>
          </div>
          <div>
            <label class="form-label">Título curto</label>
            <input name="headline" class="form-control" placeholder="Ex: Pai e filho o mês todo..." value="<?= e($edit['headline'] ?? '') ?>">
          </div>
          <div>
            <label class="form-label">Descrição</label>
            <textarea name="description" class="form-control" rows="3"><?= e($edit['description'] ?? '') ?></textarea>
          </div>
          <div class="row g-2">
            <div class="col-7">
              <label class="form-label">Benefício</label>
              <input name="benefit_label" class="form-control" value="<?= e($edit['benefit_label'] ?? 'Corte plano') ?>">
            </div>
            <div class="col-5">
              <label class="form-label">Limite uso</label>
              <input name="usage_limit" class="form-control" placeholder="vazio = ilimitado" value="<?= e($edit && array_key_exists('usage_limit', $edit) && $edit['usage_limit'] !== null ? (string)$edit['usage_limit'] : '') ?>">
            </div>
          </div>
          <div>
            <label class="form-label">Ordem</label>
            <input type="number" name="sort_order" class="form-control" value="<?= e((string)($edit['sort_order'] ?? 99)) ?>">
          </div>
          <div>
            <label class="form-label mb-2">Fotos (até 3)</label>
            <?php for ($i = 0; $i < 3; $i++): ?>
              <div class="mb-2 p-2 rounded border">
                <?php if (!empty($editImages[$i])): ?>
                  <img src="<?= e(media_url($editImages[$i])) ?>" alt="" style="width:100%;max-height:90px;object-fit:cover;border-radius:8px;margin-bottom:6px">
                  <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="clear_image_<?= $i ?>" id="clr<?= $i ?>">
                    <label class="form-check-label small" for="clr<?= $i ?>">Remover foto <?= $i + 1 ?></label>
                  </div>
                <?php endif; ?>
                <input type="file" name="image_<?= $i ?>" class="form-control form-control-sm js-image-input" accept="image/*">
              </div>
            <?php endfor; ?>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="recommended" id="rec" <?= !empty($edit['recommended']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="rec">Marcar como recomendado</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="active" id="active" <?= !isset($edit['active']) || !empty($edit['active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="active">Ativo no app do cliente</label>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="<?= e(url('dono/planos.php')) ?>">Cancelar</a>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <?php endif; ?>
            <button class="btn btn-accent" type="submit">Salvar plano</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($edit): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('planModal');
  if (el && window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();
});
</script>
<?php endif; ?>
<?php admin_layout_end(); ?>
