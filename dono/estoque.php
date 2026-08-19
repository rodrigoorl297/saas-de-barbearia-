<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rows = store_read('stock');
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $item = [
            'name' => trim($_POST['name'] ?? ''),
            'sku' => trim($_POST['sku'] ?? ''),
            'qty' => max(0, (int)($_POST['qty'] ?? 0)),
            'min_qty' => max(0, (int)($_POST['min_qty'] ?? 0)),
            'cost' => max(0, (float)($_POST['cost'] ?? 0)),
            'price' => max(0, (float)($_POST['price'] ?? 0)),
            'active' => 1,
        ];
        if ($item['name'] !== '') {
            if ($id > 0) {
                $found = false;
                foreach ($rows as $i => $r) {
                    if ((int)$r['id'] === $id) {
                        $rows[$i] = array_merge($r, $item);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    flash('danger', 'Produto não encontrado.');
                    redirect(url('dono/estoque.php'));
                }
            } else {
                $item['id'] = store_next_id('stock');
                $rows[] = $item;
            }
            store_write('stock', $rows);
            flash('success', 'Produto salvo.');
        }
    }
    if ($action === 'move') {
        $id = (int)$_POST['id'];
        $qty = max(0, (int)($_POST['move_qty'] ?? 0));
        $type = (string)($_POST['move_type'] ?? '');

        $delta = 0;
        if ($type === 'in_compra') {
            $delta = $qty;
        } elseif (in_array($type, ['out_venda', 'out_uso', 'out_perda'], true)) {
            $delta = -$qty;
        }

        if ($delta === 0) {
            flash('danger', 'Quantidade ou tipo de movimentação inválido.');
            redirect(url('dono/estoque.php'));
        }

        $productIndex = null;
        foreach ($rows as $i => $r) {
            if ((int)($r['id'] ?? 0) === $id) {
                $productIndex = $i;
                break;
            }
        }
        if ($productIndex === null) {
            flash('danger', 'Produto não encontrado.');
            redirect(url('dono/estoque.php'));
        }
        if ($delta < 0 && $qty > (int)($rows[$productIndex]['qty'] ?? 0)) {
            flash('danger', 'Estoque insuficiente (' . (int)($rows[$productIndex]['qty'] ?? 0) . ' disponível).');
            redirect(url('dono/estoque.php'));
        }

        $prodName = (string)($rows[$productIndex]['name'] ?? 'Produto');
        $rows[$productIndex]['qty'] = (int)$rows[$productIndex]['qty'] + $delta;
        store_write('stock', $rows);

        $history = store_read('stock_history');
        $history[] = [
            'id' => store_next_id('stock_history'),
            'product_id' => $id,
            'product_name' => $prodName,
            'qty' => $qty,
            'delta' => $delta,
            'type' => $type,
            'date' => date('Y-m-d H:i:s'),
        ];
        store_write('stock_history', $history);
        flash('success', 'Movimentação registrada com sucesso.');
    }
    redirect(url('dono/estoque.php'));
}

$items = store_read('stock');
usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));
$baixos = array_values(array_filter($items, fn($i) => (int)$i['qty'] <= (int)$i['min_qty']));
$valorCusto = array_sum(array_map(fn($i) => (float)$i['cost'] * (int)$i['qty'], $items));

$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    foreach ($items as $it) {
        if ((int)$it['id'] === $eid) {
            $edit = $it;
            break;
        }
    }
}

admin_layout_start('Estoque', 'dono', 'estoque');
?>
<div class="stock-page">
  <div class="stock-toolbar">
    <div>
      <h2 class="stock-heading">Produtos em estoque</h2>
      <p class="stock-sub">Acompanhe quantidade, custo e alertas de reposição.</p>
    </div>
    <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#productModal">
      + Novo produto
    </button>
  </div>

  <div class="stock-kpis">
    <div class="stock-kpi">
      <span class="stock-kpi-label">Produtos</span>
      <strong class="stock-kpi-value"><?= count($items) ?></strong>
    </div>
    <div class="stock-kpi <?= count($baixos) ? 'is-alert' : '' ?>">
      <span class="stock-kpi-label">Estoque baixo</span>
      <strong class="stock-kpi-value"><?= count($baixos) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Valor em custo</span>
      <strong class="stock-kpi-value stock-kpi-money"><?= e(money($valorCusto)) ?></strong>
    </div>
  </div>

  <div class="stock-panel">
    <div class="stock-panel-head">
      <div>
        <strong>Lista de produtos</strong>
        <span> · <?= count($items) ?> item(ns)</span>
      </div>
    </div>
    <?php if (!$items): ?>
      <div class="stock-empty">
        <p>Nenhum produto cadastrado ainda.</p>
        <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#productModal">Cadastrar primeiro produto</button>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table stock-table align-middle mb-0">
          <thead>
            <tr>
              <th class="col-prod">Produto</th>
              <th class="col-num">Qtd</th>
              <th class="col-num">Mín.</th>
              <th class="col-money">Custo</th>
              <th class="col-money">Venda</th>
              <th class="col-actions text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $i):
              $low = (int)$i['qty'] <= (int)$i['min_qty'];
          ?>
            <tr class="<?= $low ? 'is-low' : '' ?>">
              <td>
                <div class="stock-prod">
                  <span class="stock-prod-mark <?= $low ? 'low' : '' ?>"><?= e(strtoupper(str_cut($i['name'], 0, 1))) ?></span>
                  <div>
                    <strong><?= e($i['name']) ?></strong>
                    <div class="stock-sku"><?= e($i['sku'] ?: '—') ?><?= $low ? ' · repor' : '' ?></div>
                  </div>
                </div>
              </td>
              <td><span class="stock-qty <?= $low ? 'low' : '' ?>"><?= (int)$i['qty'] ?></span></td>
              <td><?= (int)$i['min_qty'] ?></td>
              <td><?= e(money((float)$i['cost'])) ?></td>
              <td><?= e(money((float)$i['price'])) ?></td>
              <td>
                <div class="stock-actions">
                  <button type="button" class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#moveModal<?= (int)$i['id'] ?>">Movimentar</button>
                  <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$i['id'] ?>">Editar</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><?= $edit ? 'Editar produto' : 'Novo produto' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" class="vstack gap-3" id="product-form">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div>
            <label class="form-label">Nome</label>
            <input name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>" placeholder="Ex: Pomada modeladora" aria-label="Nome do produto">
          </div>
          <div>
            <label class="form-label">SKU</label>
            <input name="sku" class="form-control" value="<?= e($edit['sku'] ?? '') ?>" placeholder="Ex: POM-01" aria-label="SKU do produto">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Quantidade</label>
              <input type="number" name="qty" class="form-control" min="0" value="<?= (int)($edit['qty'] ?? 0) ?>" aria-label="Quantidade em estoque">
            </div>
            <div class="col-6">
              <label class="form-label">Estoque mínimo</label>
              <input type="number" name="min_qty" class="form-control" min="0" value="<?= (int)($edit['min_qty'] ?? 5) ?>" aria-label="Estoque mínimo">
            </div>
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Custo (R$)</label>
              <input type="number" step="0.01" min="0" name="cost" class="form-control" value="<?= e((string)($edit['cost'] ?? 0)) ?>" aria-label="Custo do produto">
            </div>
            <div class="col-6">
              <label class="form-label">Venda (R$)</label>
              <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= e((string)($edit['price'] ?? 0)) ?>" aria-label="Preço de venda">
            </div>
          </div>
          <div class="d-flex gap-2 justify-content-end pt-1">
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="<?= e(url('dono/estoque.php')) ?>">Cancelar</a>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <?php endif; ?>
            <button class="btn btn-accent" type="submit">Salvar produto</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php foreach ($items as $i): ?>
<div class="modal fade" id="moveModal<?= (int)$i['id'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Movimentar: <?= e($i['name']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="small text-secondary mb-3">Estoque atual: <strong><?= (int)$i['qty'] ?></strong></p>
        <form method="post" class="vstack gap-3">
          <input type="hidden" name="action" value="move">
          <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
          <div>
            <label class="form-label">Quantidade</label>
            <input type="number" name="move_qty" class="form-control" required min="1" placeholder="Ex: 5" aria-label="Quantidade de <?= e($i['name']) ?>">
          </div>
          <div>
            <label class="form-label">Motivo / Tipo</label>
            <select name="move_type" class="form-select" required aria-label="Tipo de movimentação de <?= e($i['name']) ?>">
              <option value="in_compra">Entrada (+ Compra)</option>
              <option value="out_venda">Saída (− Venda)</option>
              <option value="out_uso">Saída (− Uso na bancada)</option>
              <option value="out_perda">Saída (− Perda / vencimento)</option>
            </select>
          </div>
          <button type="submit" class="btn btn-accent">Confirmar movimentação</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if ($edit): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('productModal');
  if (el && window.bootstrap) {
    bootstrap.Modal.getOrCreateInstance(el).show();
  }
});
</script>
<?php endif; ?>
<?php admin_layout_end(); ?>
