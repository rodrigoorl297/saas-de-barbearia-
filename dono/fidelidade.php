<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = store_read('loyalty');
    $id = (int)($_POST['id'] ?? 0);
    $delta = (int)($_POST['points'] ?? 0);
    foreach ($rows as $i => $r) {
        if ((int)$r['id'] === $id) {
            $pts = max(0, (int)$r['points'] + $delta);
            $rows[$i]['points'] = $pts;
            $rows[$i]['tier'] = $pts >= 200 ? 'Ouro' : ($pts >= 100 ? 'Prata' : 'Bronze');
            break;
        }
    }
    store_write('loyalty', $rows);
    flash('success', 'Pontos atualizados.');
    redirect(url('dono/fidelidade.php'));
}

$members = store_read('loyalty');
usort($members, fn($a, $b) => (int)$b['points'] <=> (int)$a['points']);

admin_layout_start('Fidelidade', 'dono', 'fidelidade');
?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card-soft kpi"><div class="label">Membros</div><div class="value"><?= count($members) ?></div></div></div>
  <div class="col-md-4"><div class="card-soft kpi"><div class="label">Pontos totais</div><div class="value"><?= array_sum(array_column($members, 'points')) ?></div></div></div>
</div>

<div class="card-soft p-3">
  <div class="table-responsive">
    <table class="table table-darkish align-middle mb-0">
      <thead><tr><th>Cliente</th><th>Telefone</th><th>Pontos</th><th>Nível</th><th>Ajustar</th></tr></thead>
      <tbody>
      <?php foreach ($members as $m): ?>
        <tr>
          <td><?= e($m['client_name']) ?></td>
          <td><?= e($m['client_phone']) ?></td>
          <td><?= (int)$m['points'] ?></td>
          <td><span class="badge text-bg-warning text-dark"><?= e($m['tier']) ?></span></td>
          <td>
            <form method="post" class="d-flex gap-1">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <input type="number" name="points" class="form-control form-control-sm" style="width:90px" value="10">
              <button class="btn btn-sm btn-accent" type="submit">+/-</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="small text-secondary mt-3 mb-0">Bronze &lt; 100 · Prata 100+ · Ouro 200+</p>
</div>
<?php admin_layout_end(); ?>
