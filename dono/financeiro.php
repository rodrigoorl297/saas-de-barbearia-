<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] === 'saida' ? 'saida' : 'entrada';
    $amount = (float)($_POST['amount'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $cat = trim($_POST['category'] ?? 'geral');
    if ($amount > 0 && $desc !== '') {
        save_cash_entry([
            'type' => $type,
            'category' => $cat,
            'description' => $desc,
            'amount' => $amount,
            'appointment_id' => null,
            'created_by' => (int)$user['id'],
        ]);
        flash('success', 'Lançamento adicionado.');
    }
    redirect(url('dono/financeiro.php'));
}

$entries = store_read('cash_entries');
usort($entries, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$entradas = array_sum(array_map(fn($e) => $e['type'] === 'entrada' ? (float)$e['amount'] : 0, $entries));
$saidas = array_sum(array_map(fn($e) => $e['type'] === 'saida' ? (float)$e['amount'] : 0, $entries));
$saldo = $entradas - $saidas;
$entries = array_slice($entries, 0, 40);

admin_layout_start('Financeiro', 'dono', 'financeiro');
?>
<div class="stock-page">
  <div class="stock-toolbar">
    <div>
      <h2 class="stock-heading">Financeiro</h2>
      <p class="stock-sub">Entradas, saídas e saldo da barbearia.</p>
    </div>
    <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#cashModal">+ Novo lançamento</button>
  </div>

  <div class="stock-kpis">
    <div class="stock-kpi">
      <span class="stock-kpi-label">Entradas</span>
      <strong class="stock-kpi-value" style="color:#16a34a"><?= e(money($entradas)) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Saídas</span>
      <strong class="stock-kpi-value" style="color:#dc2626"><?= e(money($saidas)) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Saldo</span>
      <strong class="stock-kpi-value"><?= e(money($saldo)) ?></strong>
    </div>
  </div>

  <div class="stock-panel">
    <div class="stock-panel-head">
      <div>
        <strong>Últimos lançamentos</strong>
        <span> · até 40 registros</span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table stock-table align-middle mb-0">
        <thead>
          <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Descrição</th>
            <th class="text-end">Valor</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $e): ?>
          <tr>
            <td><?= e(date('d/m/Y H:i', strtotime($e['created_at']))) ?></td>
            <td><?= $e['type'] === 'entrada' ? '<span class="badge text-bg-success">Entrada</span>' : '<span class="badge text-bg-danger">Saída</span>' ?></td>
            <td><?= e($e['description']) ?></td>
            <td class="text-end fw-semibold"><?= e(money((float)$e['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$entries): ?>
          <tr><td colspan="4" class="text-secondary text-center py-4">Nenhum lançamento ainda.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="cashModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Novo lançamento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" class="vstack gap-3">
          <div>
            <label class="form-label">Tipo</label>
            <select name="type" class="form-select">
              <option value="entrada">Entrada</option>
              <option value="saida">Saída</option>
            </select>
          </div>
          <div>
            <label class="form-label">Categoria</label>
            <input name="category" class="form-control" value="geral">
          </div>
          <div>
            <label class="form-label">Descrição</label>
            <input name="description" class="form-control" required>
          </div>
          <div>
            <label class="form-label">Valor</label>
            <input type="number" step="0.01" name="amount" class="form-control" required>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-accent" type="submit">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php admin_layout_end(); ?>
