<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = ($_POST['type'] ?? '') === 'saida' ? 'saida' : 'entrada';
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

$currentMonth = date('Y-m');
$selectedMonth = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? (string)$_GET['m'] : $currentMonth;

$filteredEntries = array_filter($entries, function($e) use ($selectedMonth) {
    return str_starts_with($e['created_at'] ?? '', $selectedMonth);
});

$entradas = array_sum(array_map(fn($e) => $e['type'] === 'entrada' ? (float)$e['amount'] : 0, $filteredEntries));
$saidas = array_sum(array_map(fn($e) => $e['type'] === 'saida' ? (float)$e['amount'] : 0, $filteredEntries));
$saldo = $entradas - $saidas;
$displayEntries = array_slice($filteredEntries, 0, 100);

$monthsAvailable = [];
foreach ($entries as $e) {
    $m = substr($e['created_at'] ?? '', 0, 7);
    if ($m) $monthsAvailable[$m] = true;
}
$monthsAvailable[$currentMonth] = true;
$monthsAvailable = array_keys($monthsAvailable);
rsort($monthsAvailable);

admin_layout_start('Financeiro (DRE)', 'dono', 'financeiro');
?>
<div class="stock-page">
  <div class="stock-toolbar d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <div>
      <h2 class="stock-heading">DRE Financeiro</h2>
      <p class="stock-sub">Entradas, saídas e Lucro Líquido Real.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <form method="get" class="d-flex m-0">
        <select name="m" class="form-select form-select-sm me-2 admin-filter-select" onchange="this.form.submit()" aria-label="Filtrar por mês">
          <?php foreach ($monthsAvailable as $m): 
            $mLabel = date('M Y', strtotime($m . '-01'));
          ?>
            <option value="<?= e($m) ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>><?= e(ucfirst($mLabel)) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#cashModal">+ Novo lançamento</button>
    </div>
  </div>

  <div class="stock-kpis">
    <div class="stock-kpi">
      <span class="stock-kpi-label">Receita Bruta</span>
      <strong class="stock-kpi-value is-positive"><?= e(money($entradas)) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Despesas</span>
      <strong class="stock-kpi-value is-negative"><?= e(money($saidas)) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Lucro Líquido</span>
      <strong class="stock-kpi-value <?= $saldo >= 0 ? 'is-positive' : 'is-negative' ?>"><?= e(money($saldo)) ?></strong>
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
        <?php foreach ($displayEntries as $e): ?>
          <tr>
            <td><?= e(date('d/m/Y H:i', strtotime($e['created_at']))) ?></td>
            <td>
              <?php if ($e['type'] === 'entrada'): ?>
                <span class="badge text-bg-success">Entrada</span>
              <?php else: ?>
                <span class="badge text-bg-danger">Saída</span>
              <?php endif; ?>
              <div class="small text-secondary mt-1"><?= e($e['category'] ?? 'geral') ?></div>
            </td>
            <td><?= e($e['description']) ?></td>
            <td class="text-end fw-semibold"><?= e(money((float)$e['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$displayEntries): ?>
          <tr><td colspan="4"><div class="empty-state table-empty"><strong>Nenhum lançamento neste mês</strong><p>Registre receitas e despesas para acompanhar o resultado real da barbearia.</p><button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#cashModal">Criar lançamento</button></div></td></tr>
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
            <select name="type" class="form-select" id="cashType" aria-label="Tipo de lançamento">
              <option value="saida">Saída (Despesa)</option>
              <option value="entrada">Entrada (Receita)</option>
            </select>
          </div>
          <div>
            <label class="form-label">Categoria</label>
            <select name="category" class="form-select" id="cashCategory" aria-label="Categoria do lançamento">
              <!-- Populado via JS -->
            </select>
          </div>
          <div>
            <label class="form-label">Descrição</label>
            <input name="description" class="form-control" placeholder="Ex: Conta de Luz" required aria-label="Descrição do lançamento">
          </div>
          <div>
            <label class="form-label">Valor</label>
            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required aria-label="Valor do lançamento">
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
<script>
document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('cashType');
    const catSelect = document.getElementById('cashCategory');
    
    const categories = {
        'saida': ['Aluguel', 'Água', 'Luz / Internet', 'Folha de Pagamento / Comissão', 'Reposição de Estoque', 'Marketing / Anúncios', 'Impostos', 'Outros'],
        'entrada': ['Serviço', 'Produto / PDV', 'Investimento', 'Outros']
    };
    
    function updateCats() {
        const type = typeSelect.value;
        const options = categories[type] || ['Geral'];
        catSelect.innerHTML = options.map(c => `<option value="${c}">${c}</option>`).join('');
    }
    
    typeSelect.addEventListener('change', updateCats);
    updateCats();
});
</script>
<?php admin_layout_end(); ?>
