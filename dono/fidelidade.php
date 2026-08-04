<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'adjust';

    // Preserva busca/filtro ativos ao voltar para a lista
    $backParams = [];
    if (trim((string)($_POST['q'] ?? '')) !== '') {
        $backParams['q'] = trim((string)$_POST['q']);
    }
    if (in_array((string)($_POST['tier'] ?? ''), ['Bronze', 'Prata', 'Ouro'], true)) {
        $backParams['tier'] = (string)$_POST['tier'];
    }
    $backUrl = url('dono/fidelidade.php') . ($backParams ? '?' . http_build_query($backParams) : '');

    if ($action === 'save_rules') {
        $pointsPerReal = max(0, (float)str_replace(',', '.', (string)($_POST['points_per_real'] ?? 1)));
        $prata = max(1, (int)($_POST['prata_min'] ?? 100));
        $ouro = max($prata + 1, (int)($_POST['ouro_min'] ?? 200));
        $multPrata = max(1, (float)str_replace(',', '.', (string)($_POST['mult_prata'] ?? 1)));
        $multOuro = max($multPrata, (float)str_replace(',', '.', (string)($_POST['mult_ouro'] ?? 1)));
        save_settings([
            'loyalty_points_per_real' => $pointsPerReal,
            'loyalty_prata_min' => $prata,
            'loyalty_ouro_min' => $ouro,
            'loyalty_mult_prata' => $multPrata,
            'loyalty_mult_ouro' => $multOuro,
            'loyalty_expire_days' => max(0, (int)($_POST['expire_days'] ?? 0)),
            'loyalty_birthday_bonus' => max(0, (int)($_POST['birthday_bonus'] ?? 0)),
            'loyalty_referral_bonus' => max(0, (int)($_POST['referral_bonus'] ?? 0)),
        ]);
        $rows = store_read('loyalty');
        foreach ($rows as $i => $r) {
            $rows[$i]['tier'] = loyalty_tier_for_points((int)($r['points'] ?? 0));
        }
        store_write('loyalty', $rows);
        flash('success', 'Regras do programa atualizadas.');
        redirect(url('dono/fidelidade.php'));
    }

    if ($action === 'reward_save') {
        $rewards = loyalty_rewards();
        $id = (int)($_POST['reward_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $cost = max(1, (int)($_POST['cost'] ?? 0));
        $active = !empty($_POST['active']) ? 1 : 0;
        if ($name === '') {
            flash('danger', 'Informe um nome para a recompensa.');
            redirect(url('dono/fidelidade.php'));
        }
        if ($id > 0) {
            foreach ($rewards as $i => $r) {
                if ((int)$r['id'] === $id) {
                    $rewards[$i] = ['id' => $id, 'name' => $name, 'cost' => $cost, 'active' => $active];
                    break;
                }
            }
        } else {
            $maxId = 0;
            foreach ($rewards as $r) {
                $maxId = max($maxId, (int)$r['id']);
            }
            $rewards[] = ['id' => $maxId + 1, 'name' => $name, 'cost' => $cost, 'active' => $active];
        }
        save_loyalty_rewards($rewards);
        flash('success', 'Recompensa salva.');
        redirect(url('dono/fidelidade.php'));
    }

    if ($action === 'reward_delete') {
        $id = (int)($_POST['reward_id'] ?? 0);
        $rewards = array_values(array_filter(loyalty_rewards(), fn($r) => (int)$r['id'] !== $id));
        save_loyalty_rewards($rewards);
        flash('success', 'Recompensa removida.');
        redirect(url('dono/fidelidade.php'));
    }

    if ($action === 'adjust') {
        $id = (int)($_POST['id'] ?? 0);
        $qty = max(1, (int)($_POST['points'] ?? 0));
        $sign = ($_POST['op'] ?? 'add') === 'sub' ? -1 : 1;
        $reasonInput = trim((string)($_POST['reason'] ?? ''));
        $member = find_loyalty_member($id);
        if ($member) {
            loyalty_add_points(
                (string)$member['client_phone'],
                (string)$member['client_name'],
                $qty * $sign,
                $reasonInput !== '' ? 'Ajuste: ' . $reasonInput : 'Ajuste manual',
                ['type' => 'manual', 'by' => (string)($user['name'] ?? 'dono')]
            );
            flash('success', 'Pontos atualizados.');
        }
        redirect($backUrl);
    }

    if ($action === 'redeem') {
        $id = (int)($_POST['id'] ?? 0);
        $rewardId = (int)($_POST['reward_id'] ?? 0);
        $member = find_loyalty_member($id);
        $reward = null;
        foreach (loyalty_rewards() as $r) {
            if ((int)$r['id'] === $rewardId) {
                $reward = $r;
                break;
            }
        }
        if (!$member || !$reward || empty($reward['active'])) {
            flash('danger', 'Recompensa indisponível.');
        } elseif ((int)$member['points'] < (int)$reward['cost']) {
            flash('danger', 'Pontos insuficientes para este resgate.');
        } else {
            loyalty_add_points(
                (string)$member['client_phone'],
                (string)$member['client_name'],
                -(int)$reward['cost'],
                'Resgate: ' . $reward['name'],
                ['type' => 'resgate', 'reward_id' => $rewardId, 'by' => (string)($user['name'] ?? 'dono')]
            );
            flash('success', 'Resgate registrado: ' . $reward['name']);
        }
        redirect($backUrl);
    }

    redirect(url('dono/fidelidade.php'));
}

$allMembers = store_read('loyalty');
usort($allMembers, fn($a, $b) => (int)$b['points'] <=> (int)$a['points']);

// KPIs do mês corrente (a partir do histórico de lançamentos)
$monthKey = date('Y-m');
$monthEarned = 0;
$monthRedeemCount = 0;
$monthRedeemPts = 0;
foreach ($allMembers as $m) {
    foreach (($m['history'] ?? []) as $h) {
        if (substr((string)($h['date'] ?? ''), 0, 7) !== $monthKey) {
            continue;
        }
        $delta = (int)($h['delta'] ?? 0);
        if (($h['type'] ?? '') === 'resgate') {
            $monthRedeemCount++;
            $monthRedeemPts += -$delta;
        } elseif ($delta > 0) {
            $monthEarned += $delta;
        }
    }
}

// Busca e filtro por nível
$q = trim((string)($_GET['q'] ?? ''));
$tierFilter = in_array((string)($_GET['tier'] ?? ''), ['Bronze', 'Prata', 'Ouro'], true) ? (string)$_GET['tier'] : '';
$members = $allMembers;
if ($q !== '') {
    $qDigits = preg_replace('/\D+/', '', $q);
    $members = array_values(array_filter($members, function ($m) use ($q, $qDigits) {
        if (stripos((string)($m['client_name'] ?? ''), $q) !== false) {
            return true;
        }
        return $qDigits !== '' && strpos(preg_replace('/\D+/', '', (string)($m['client_phone'] ?? '')), $qDigits) !== false;
    }));
}
if ($tierFilter !== '') {
    $members = array_values(array_filter($members, fn($m) => ($m['tier'] ?? 'Bronze') === $tierFilter));
}

$rewards = loyalty_rewards();
usort($rewards, fn($a, $b) => (int)$a['cost'] <=> (int)$b['cost']);
$activeRewards = array_values(array_filter($rewards, fn($r) => !empty($r['active'])));

$thresholds = loyalty_thresholds();
$pointsPerReal = loyalty_points_per_real();

$editReward = null;
if (isset($_GET['edit_reward'])) {
    $rid = (int)$_GET['edit_reward'];
    foreach ($rewards as $r) {
        if ((int)$r['id'] === $rid) {
            $editReward = $r;
            break;
        }
    }
}

admin_layout_start('Fidelidade', 'dono', 'fidelidade');
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="card-soft kpi"><div class="label">Membros</div><div class="value"><?= count($allMembers) ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card-soft kpi"><div class="label">Pontos em aberto</div><div class="value"><?= array_sum(array_column($allMembers, 'points')) ?></div><div class="small text-secondary">saldo a resgatar</div></div></div>
  <div class="col-6 col-md-3"><div class="card-soft kpi"><div class="label">Pontos ganhos no mês</div><div class="value"><?= $monthEarned ?></div></div></div>
  <div class="col-6 col-md-3"><div class="card-soft kpi"><div class="label">Resgates no mês</div><div class="value"><?= $monthRedeemCount ?></div><div class="small text-secondary"><?= $monthRedeemPts ?> pts resgatados</div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card-soft p-3 h-100">
      <h6 class="fw-bold mb-3">Regras do programa</h6>
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="save_rules">
        <div class="col-12">
          <label class="form-label small mb-1">Pontos por R$ 1 gasto</label>
          <input type="number" step="0.1" min="0" name="points_per_real" class="form-control form-control-sm" value="<?= e((string)$pointsPerReal) ?>">
          <div class="form-text">Concedidos automaticamente quando um agendamento é concluído.</div>
        </div>
        <div class="col-6">
          <label class="form-label small mb-1">Nível Prata a partir de</label>
          <input type="number" min="1" name="prata_min" class="form-control form-control-sm" value="<?= (int)$thresholds['prata'] ?>">
        </div>
        <div class="col-6">
          <label class="form-label small mb-1">Nível Ouro a partir de</label>
          <input type="number" min="1" name="ouro_min" class="form-control form-control-sm" value="<?= (int)$thresholds['ouro'] ?>">
        </div>
        <div class="col-6">
          <label class="form-label small mb-1">Bônus Prata (multiplicador)</label>
          <input type="number" step="0.1" min="1" name="mult_prata" class="form-control form-control-sm" value="<?= e((string)loyalty_tier_multiplier('Prata')) ?>">
        </div>
        <div class="col-6">
          <label class="form-label small mb-1">Bônus Ouro (multiplicador)</label>
          <input type="number" step="0.1" min="1" name="mult_ouro" class="form-control form-control-sm" value="<?= e((string)loyalty_tier_multiplier('Ouro')) ?>">
        </div>
        <div class="col-12">
          <div class="form-text">Ex.: 1,2 = cliente do nível ganha 20% a mais de pontos. Use 1 para desativar o bônus.</div>
        </div>

        <div class="col-12"><hr class="my-1 opacity-25"></div>
        <div class="col-12">
          <div class="small fw-bold mb-1">Engajamento <span class="text-secondary fw-normal">(0 = desativado)</span></div>
        </div>
        <div class="col-4">
          <label class="form-label small mb-1">Pontos de aniversário</label>
          <input type="number" min="0" name="birthday_bonus" class="form-control form-control-sm" value="<?= loyalty_birthday_bonus() ?>">
        </div>
        <div class="col-4">
          <label class="form-label small mb-1">Pontos por indicação</label>
          <input type="number" min="0" name="referral_bonus" class="form-control form-control-sm" value="<?= loyalty_referral_bonus() ?>">
        </div>
        <div class="col-4">
          <label class="form-label small mb-1">Expirar após (dias)</label>
          <input type="number" min="0" name="expire_days" class="form-control form-control-sm" value="<?= loyalty_expire_days() ?>">
        </div>
        <div class="col-12">
          <div class="form-text">
            Indicação: os dois lados ganham quando o indicado conclui o 1º atendimento.
            Aniversário e expiração dependem do cron diário (<code>cron/fidelidade-diaria.php</code>).
          </div>
        </div>

        <div class="col-12 pt-2">
          <button class="btn btn-accent btn-sm" type="submit">Salvar regras</button>
        </div>
      </form>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card-soft p-3 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Recompensas</h6>
        <button type="button" class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#rewardModal">+ Nova</button>
      </div>
      <?php if (!$rewards): ?>
        <p class="small text-secondary mb-0">Nenhuma recompensa cadastrada. Crie itens que os clientes podem resgatar com pontos, como "Corte grátis" ou "10% de desconto".</p>
      <?php else: ?>
        <ul class="list-unstyled mb-0 vstack gap-2">
          <?php foreach ($rewards as $r): ?>
            <li class="d-flex justify-content-between align-items-center">
              <div>
                <?= e($r['name']) ?>
                <span class="badge text-bg-light text-dark ms-1"><?= (int)$r['cost'] ?> pts</span>
                <?php if (empty($r['active'])): ?><span class="badge text-bg-secondary ms-1">inativa</span><?php endif; ?>
              </div>
              <div class="d-flex gap-1">
                <a class="btn btn-sm btn-ghost" href="?edit_reward=<?= (int)$r['id'] ?>">Editar</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Remover esta recompensa?')">
                  <input type="hidden" name="action" value="reward_delete">
                  <input type="hidden" name="reward_id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn-sm btn-ghost text-danger" type="submit">Excluir</button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card-soft p-3">
  <?php if (!$allMembers): ?>
    <p class="small text-secondary mb-0">Nenhum cliente no programa de fidelidade ainda.</p>
  <?php else: ?>
  <form method="get" class="row g-2 align-items-center mb-3">
    <div class="col-12 col-md-4">
      <input name="q" class="form-control form-control-sm" placeholder="Buscar por nome ou telefone" value="<?= e($q) ?>">
    </div>
    <div class="col-auto">
      <select name="tier" class="form-select form-select-sm">
        <option value="">Todos os níveis</option>
        <?php foreach (['Bronze', 'Prata', 'Ouro'] as $t): ?>
          <option value="<?= $t ?>" <?= $tierFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-accent" type="submit">Filtrar</button>
      <?php if ($q !== '' || $tierFilter !== ''): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('dono/fidelidade.php')) ?>">Limpar</a>
      <?php endif; ?>
    </div>
    <?php if ($q !== '' || $tierFilter !== ''): ?>
      <div class="col-auto small text-secondary"><?= count($members) ?> de <?= count($allMembers) ?> membros</div>
    <?php endif; ?>
  </form>
  <?php if (!$members): ?>
    <p class="small text-secondary mb-0">Nenhum cliente encontrado com esses filtros.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-darkish align-middle mb-0">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Telefone</th>
          <th>Pontos</th>
          <th>Nível</th>
          <th>Ajustar</th>
          <th>Resgatar</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($members as $m):
          $tierClass = match ($m['tier'] ?? '') {
              'Ouro' => 'ouro',
              'Prata' => 'prata',
              default => 'bronze',
          };
          $history = array_reverse($m['history'] ?? []);
          $next = loyalty_next_tier_info((int)($m['points'] ?? 0));
      ?>
        <tr>
          <td><?= e($m['client_name']) ?></td>
          <td><?= e($m['client_phone']) ?></td>
          <td><?= (int)$m['points'] ?></td>
          <td>
            <span class="badge badge-tier <?= $tierClass ?>"><?= e($m['tier']) ?></span>
            <?php if ($next): ?>
              <div class="tier-mini-progress" title="Faltam <?= (int)$next['missing'] ?> pts para <?= e($next['tier']) ?>">
                <div class="tier-mini-bar"><i style="width:<?= (int)$next['percent'] ?>%"></i></div>
                <span class="tier-mini-label">-<?= (int)$next['missing'] ?> p/ <?= e($next['tier']) ?></span>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" class="d-flex gap-1 align-items-center">
              <input type="hidden" name="action" value="adjust">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <input type="hidden" name="q" value="<?= e($q) ?>">
              <input type="hidden" name="tier" value="<?= e($tierFilter) ?>">
              <input type="number" name="points" class="form-control form-control-sm" style="width:64px" value="10" min="1">
              <input type="text" name="reason" class="form-control form-control-sm" style="width:120px" placeholder="Motivo" maxlength="80">
              <button class="btn btn-sm btn-outline-success" type="submit" name="op" value="add" title="Adicionar pontos">+</button>
              <button class="btn btn-sm btn-outline-danger" type="submit" name="op" value="sub" title="Remover pontos" onclick="return confirm('Remover pontos deste cliente?')">&minus;</button>
            </form>
          </td>
          <td>
            <?php if ($activeRewards): ?>
              <form method="post" class="d-flex gap-1" onsubmit="return confirm('Confirmar resgate: ' + this.reward_id.selectedOptions[0].text.trim() + '? Os pontos serão debitados.')">
                <input type="hidden" name="action" value="redeem">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="q" value="<?= e($q) ?>">
                <input type="hidden" name="tier" value="<?= e($tierFilter) ?>">
                <select name="reward_id" class="form-select form-select-sm" style="width:150px">
                  <?php foreach ($activeRewards as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= (int)$m['points'] < (int)$r['cost'] ? 'disabled' : '' ?>><?= e($r['name']) ?> &middot; <?= (int)$r['cost'] ?>p</option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-accent" type="submit">Resgatar</button>
              </form>
            <?php else: ?>
              <span class="small text-secondary">&mdash;</span>
            <?php endif; ?>
          </td>
          <td>
            <button class="btn btn-sm btn-ghost" type="button" data-bs-toggle="collapse" data-bs-target="#hist-<?= (int)$m['id'] ?>">Histórico</button>
          </td>
        </tr>
        <tr class="collapse" id="hist-<?= (int)$m['id'] ?>">
          <td colspan="7" class="bg-light">
            <?php if (!$history): ?>
              <span class="small text-secondary">Sem lançamentos ainda.</span>
            <?php else: ?>
              <ul class="list-unstyled small mb-0 vstack gap-1">
                <?php foreach (array_slice($history, 0, 10) as $h):
                    $delta = (int)($h['delta'] ?? 0);
                    $ts = strtotime((string)($h['date'] ?? ''));
                ?>
                  <li>
                    <?= e($ts ? date('d/m/Y H:i', $ts) : '—') ?>
                    &middot;
                    <strong class="<?= $delta >= 0 ? 'text-success' : 'text-danger' ?>"><?= $delta >= 0 ? '+' : '' ?><?= $delta ?></strong>
                    &middot;
                    <?= e((string)($h['reason'] ?? '')) ?>
                    <?php if (!empty($h['by'])): ?>
                      <span class="text-secondary">&middot; por <?= e((string)$h['by']) ?></span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>
  <?php
    $multPrata = loyalty_tier_multiplier('Prata');
    $multOuro = loyalty_tier_multiplier('Ouro');
  ?>
  <p class="small text-secondary mt-3 mb-0">
    Bronze &lt; <?= (int)$thresholds['prata'] ?>
    &middot; Prata <?= (int)$thresholds['prata'] ?>+<?= $multPrata > 1 ? ' (pontos ' . e(loyalty_format_mult($multPrata)) . 'x)' : '' ?>
    &middot; Ouro <?= (int)$thresholds['ouro'] ?>+<?= $multOuro > 1 ? ' (pontos ' . e(loyalty_format_mult($multOuro)) . 'x)' : '' ?>
  </p>
</div>

<div class="modal fade" id="rewardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><?= $editReward ? 'Editar recompensa' : 'Nova recompensa' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" class="vstack gap-3">
          <input type="hidden" name="action" value="reward_save">
          <input type="hidden" name="reward_id" value="<?= (int)($editReward['id'] ?? 0) ?>">
          <div>
            <label class="form-label">Nome</label>
            <input name="name" class="form-control" required value="<?= e($editReward['name'] ?? '') ?>" placeholder="Ex: Corte grátis">
          </div>
          <div>
            <label class="form-label">Custo em pontos</label>
            <input type="number" min="1" name="cost" class="form-control" value="<?= (int)($editReward['cost'] ?? 100) ?>">
          </div>
          <div class="form-check">
            <input type="checkbox" name="active" value="1" class="form-check-input" id="rewardActive" <?= ($editReward['active'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="rewardActive">Ativa (disponível para resgate)</label>
          </div>
          <div class="d-flex gap-2 justify-content-end pt-1">
            <?php if ($editReward): ?>
              <a class="btn btn-ghost" href="<?= e(url('dono/fidelidade.php')) ?>">Cancelar</a>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <?php endif; ?>
            <button class="btn btn-accent" type="submit">Salvar recompensa</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($editReward): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('rewardModal');
  if (el && window.bootstrap) {
    bootstrap.Modal.getOrCreateInstance(el).show();
  }
});
</script>
<?php endif; ?>
<?php admin_layout_end(); ?>
