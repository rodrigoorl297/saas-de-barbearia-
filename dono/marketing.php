<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/whatsapp.php';

$user = require_role(['dono']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $rows = store_read('campaigns');
        $payload = [
            'name' => trim($_POST['name'] ?? 'Campanha') ?: 'Campanha',
            'type' => trim($_POST['type'] ?? 'promocional') ?: 'promocional',
            'channel' => trim($_POST['channel'] ?? 'whatsapp') ?: 'whatsapp',
            'status' => trim($_POST['status'] ?? 'rascunho') ?: 'rascunho',
            'message' => trim($_POST['message'] ?? ''),
        ];

        if ($id > 0) {
            $found = false;
            foreach ($rows as &$r) {
                if ((int)$r['id'] === $id) {
                    $r = array_merge($r, $payload, ['id' => $id, 'updated_at' => date('c')]);
                    $found = true;
                    break;
                }
            }
            unset($r);
            if (!$found) {
                flash('danger', 'Campanha não encontrada.');
                redirect(url('dono/marketing.php'));
            }
            store_write('campaigns', $rows);
            flash('success', 'Campanha atualizada.');
        } else {
            $rows[] = array_merge($payload, [
                'id' => store_next_id('campaigns'),
                'created_at' => date('c'),
            ]);
            store_write('campaigns', $rows);
            flash('success', 'Campanha criada.');
        }
        redirect(url('dono/marketing.php'));
    }

    if ($action === 'disparar') {
        $cid = (int)$_POST['id'];
        $rows = store_read('campaigns');
        $camp = null;
        foreach ($rows as &$r) {
            if ((int)$r['id'] === $cid) {
                $r['status'] = 'enviada';
                $r['sent_at'] = date('c');
                $camp = $r;
                break;
            }
        }
        unset($r);
        if ($camp) {
            store_write('campaigns', $rows);
            $count = send_whatsapp_campaign($camp);
            if (!wa_configured()) {
                flash('warning', 'WhatsApp ainda não configurado. Em Configurações, informe a URL e a API Key da Evolution API e conecte o número.');
            } elseif ($count > 0) {
                flash('success', "WhatsApp disparado! $count mensagens enviadas.");
            } else {
                flash('warning', 'Nenhuma mensagem enviada. Confira se o número está conectado (Configurações) e se há clientes no público da campanha.');
            }
        }
        redirect(url('dono/marketing.php'));
    }

    if ($action === 'criar_avisos') {
        $cid = (int)$_POST['id'];
        $camp = null;
        foreach (store_read('campaigns') as $r) {
            if ((int)$r['id'] === $cid) {
                $camp = $r;
                break;
            }
        }
        if (!$camp) {
            flash('danger', 'Campanha não encontrada.');
            redirect(url('dono/marketing.php'));
        }
        $count = create_avisos_from_campaign($camp);
        if ($count > 0) {
            flash('success', "$count aviso(s) criados no app do cliente.");
        } else {
            flash('warning', 'Nenhum cliente no público desta campanha. Cadastre data de nascimento ou aguarde inativos.');
        }
        redirect(url('dono/marketing.php'));
    }

    if ($action === 'save_aviso') {
        $type = trim($_POST['type'] ?? 'aniversariantes');
        if (!in_array($type, ['aniversariantes', 'inativos'], true)) {
            $type = 'aniversariantes';
        }
        $name = trim($_POST['name'] ?? '') ?: ($type === 'aniversariantes' ? 'Aviso aniversário' : 'Aviso clientes inativos');
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            flash('danger', 'Escreva a mensagem do aviso.');
            redirect(url('dono/marketing.php'));
        }

        $camp = [
            'id' => 0,
            'name' => $name,
            'type' => $type,
            'message' => $message,
        ];
        $count = create_avisos_from_campaign($camp);

        // Também salva como campanha de aviso para histórico/edição
        $rows = store_read('campaigns');
        $rows[] = [
            'id' => store_next_id('campaigns'),
            'name' => $name,
            'type' => $type,
            'channel' => 'aviso',
            'status' => 'ativa',
            'message' => $message,
            'created_at' => date('c'),
            'avisos_sent' => $count,
        ];
        store_write('campaigns', $rows);

        if ($count > 0) {
            flash('success', "Aviso criado e enviado para $count cliente(s) no app.");
        } else {
            flash('warning', 'Aviso salvo, mas nenhum cliente no público agora.');
        }
        redirect(url('dono/marketing.php'));
    }

    if ($action === 'delete') {
        $cid = (int)$_POST['id'];
        $rows = array_values(array_filter(store_read('campaigns'), fn($c) => (int)$c['id'] !== $cid));
        store_write('campaigns', $rows);
        flash('success', 'Campanha removida.');
        redirect(url('dono/marketing.php'));
    }
}

$campaigns = store_read('campaigns');
usort($campaigns, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
$edit = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    foreach ($campaigns as $c) {
        if ((int)$c['id'] === $eid) {
            $edit = $c;
            break;
        }
    }
}

$all = appointments_enriched();
$cut = date('Y-m-d', strtotime('-45 days'));
$phones = [];
foreach ($all as $a) {
    $p = $a['client_phone'];
    if (!isset($phones[$p]) || $a['date'] > $phones[$p]['date']) {
        $phones[$p] = ['name' => $a['client_name'], 'date' => $a['date'], 'phone' => $p];
    }
}
$inativos = array_values(array_filter($phones, fn($c) => $c['date'] < $cut));
$aniversariantes = get_target_clients_for_campaign('aniversariantes');
$rascunhos = count(array_filter($campaigns, fn($c) => ($c['status'] ?? '') === 'rascunho'));

$typeLabels = [
    'promocional' => 'Promocional',
    'aniversariantes' => 'Aniversário',
    'inativos' => 'Inativos',
];

admin_layout_start('Marketing', 'dono', 'marketing');
?>
<div class="stock-page">
  <div class="stock-toolbar">
    <div>
      <h2 class="stock-heading">Marketing</h2>
      <p class="stock-sub">Campanhas WhatsApp e avisos no app (aniversário e inativos).</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-ghost" data-bs-toggle="modal" data-bs-target="#avisoModal">+ Novo aviso</button>
      <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#campaignModal">+ Nova campanha</button>
    </div>
  </div>

  <?php if (!wa_configured()): ?>
    <div class="alert alert-warning py-2 px-3 mb-3">
      WhatsApp ainda não conectado. Vá em <a href="<?= e(url('dono/configuracoes.php')) ?>"><strong>Configurações</strong></a>, informe a Evolution API e escaneie o QR Code pra liberar o Disparar.
    </div>
  <?php endif; ?>

  <div class="stock-kpis">
    <div class="stock-kpi">
      <span class="stock-kpi-label">Campanhas</span>
      <strong class="stock-kpi-value"><?= count($campaigns) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Aniversariantes</span>
      <strong class="stock-kpi-value"><?= count($aniversariantes) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Inativos +45d</span>
      <strong class="stock-kpi-value"><?= count($inativos) ?></strong>
    </div>
    <div class="stock-kpi">
      <span class="stock-kpi-label">Rascunhos</span>
      <strong class="stock-kpi-value"><?= $rascunhos ?></strong>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="stock-panel">
        <h2 class="h6 mb-3">Campanhas e avisos</h2>
        <?php if (!$campaigns): ?>
          <div class="stock-empty">
            <p>Nenhuma campanha ainda.</p>
            <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#campaignModal">Criar primeira campanha</button>
          </div>
        <?php else: ?>
          <div class="vstack gap-3">
            <?php foreach ($campaigns as $c):
                $st = $c['status'] ?? 'rascunho';
                $badge = in_array($st, ['enviada', 'ativa'], true) ? 'text-bg-success' : 'text-bg-secondary';
                $tipo = $c['type'] ?? 'promocional';
                $canDisparar = $st !== 'enviada';
            ?>
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap border-bottom pb-3">
                <div class="min-w-0">
                  <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                    <strong><?= e($c['name']) ?></strong>
                    <span class="badge text-bg-light text-dark"><?= e($typeLabels[$tipo] ?? $tipo) ?></span>
                    <span class="badge text-bg-light text-dark"><?= e(($c['channel'] ?? 'whatsapp') === 'aviso' ? 'aviso app' : 'whatsapp') ?></span>
                    <span class="badge <?= $badge ?>"><?= e($st) ?></span>
                  </div>
                  <div class="small text-secondary"><?= e($c['message'] ?? '') ?></div>
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <a class="btn btn-sm btn-ghost" href="?edit=<?= (int)$c['id'] ?>">Editar</a>
                  <?php if (in_array($tipo, ['aniversariantes', 'inativos', 'promocional'], true)): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Criar avisos no app para este público?')">
                      <input type="hidden" name="action" value="criar_avisos">
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <button class="btn btn-sm btn-ghost" type="submit">Criar avisos</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($canDisparar): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Disparar esta campanha no WhatsApp?')">
                      <input type="hidden" name="action" value="disparar">
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <button class="btn btn-sm btn-accent" type="submit">Disparar</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Remover campanha?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-sm btn-ghost" type="submit">Excluir</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="stock-panel mb-3">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <h2 class="h6 mb-0">Aniversariantes do mês</h2>
          <button type="button" class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#avisoModal" data-aviso-type="aniversariantes">Criar aviso</button>
        </div>
        <?php if (!$aniversariantes): ?>
          <div class="text-secondary small">Nenhum cliente com aniversário neste mês. Cadastre a data de nascimento em Clientes.</div>
        <?php else: ?>
          <ul class="list-unstyled mb-0 small">
            <?php foreach (array_slice($aniversariantes, 0, 8) as $c): ?>
              <li class="py-1 border-bottom"><?= e($c['name'] ?? '') ?> · <?= e($c['phone'] ?? '') ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="stock-panel">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
          <h2 class="h6 mb-0">Clientes inativos (+45 dias)</h2>
          <button type="button" class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#avisoModal" data-aviso-type="inativos">Criar aviso</button>
        </div>
        <?php if (!$inativos): ?>
          <div class="text-secondary small">Nenhum cliente inativo no momento.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table stock-table table-sm mb-0">
              <thead><tr><th>Cliente</th><th>Última visita</th></tr></thead>
              <tbody>
              <?php foreach (array_slice($inativos, 0, 10) as $c): ?>
                <tr>
                  <td><?= e($c['name']) ?></td>
                  <td><?= e(date('d/m/Y', strtotime($c['date']))) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="avisoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Novo aviso no app</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" class="vstack gap-3" id="avisoForm">
          <input type="hidden" name="action" value="save_aviso">
          <div>
            <label class="form-label">Para quem</label>
            <select name="type" id="avisoType" class="form-select" required aria-label="Público do aviso">
              <option value="aniversariantes">Aniversariantes do mês</option>
              <option value="inativos">Clientes inativos (+45 dias)</option>
            </select>
          </div>
          <div>
            <label class="form-label">Título</label>
            <input name="name" id="avisoName" class="form-control" placeholder="Ex: Feliz aniversário" aria-label="Nome do aviso">
          </div>
          <div>
            <label class="form-label">Mensagem do aviso</label>
            <textarea name="message" class="form-control" rows="4" required placeholder="Texto que o cliente verá em Notificações no app." aria-label="Mensagem do aviso"></textarea>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-accent" type="submit">Criar e enviar avisos</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="campaignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content stock-modal">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><?= $edit ? 'Editar campanha' : 'Nova campanha WhatsApp' ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="post" class="vstack gap-3">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div>
            <label class="form-label">Nome da campanha</label>
            <input name="name" class="form-control" required placeholder="Ex: Volta VIP" value="<?= e($edit['name'] ?? '') ?>" aria-label="Nome da campanha">
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Público</label>
              <select name="type" class="form-select" required aria-label="Público da campanha">
                <?php
                $type = $edit['type'] ?? 'promocional';
                $types = [
                    'promocional' => 'Promocional (toda a base)',
                    'aniversariantes' => 'Aniversariantes do mês',
                    'inativos' => 'Inativos (+45 dias)',
                ];
                foreach ($types as $val => $label):
                ?>
                  <option value="<?= e($val) ?>" <?= $type === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" class="form-select" aria-label="Status da campanha">
                <?php
                $st = $edit['status'] ?? 'rascunho';
                foreach (['rascunho' => 'Rascunho', 'ativa' => 'Ativa', 'enviada' => 'Enviada'] as $val => $label):
                ?>
                  <option value="<?= e($val) ?>" <?= $st === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label class="form-label">Mensagem do WhatsApp</label>
            <textarea name="message" class="form-control" rows="5" required placeholder="Ex: Oi {primeiro_nome}! Faz tempo que não te vemos. Agende com 10% OFF esta semana." aria-label="Mensagem da campanha"><?= e($edit['message'] ?? '') ?></textarea>
            <div class="form-text">Texto livre, enviado do jeito que está escrito. Use <code>{nome}</code> ou <code>{primeiro_nome}</code> pra personalizar por cliente.</div>
          </div>
          <div class="d-flex gap-2 justify-content-end">
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="<?= e(url('dono/marketing.php')) ?>">Cancelar</a>
            <?php else: ?>
              <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <?php endif; ?>
            <button class="btn btn-accent" type="submit"><?= $edit ? 'Salvar alterações' : 'Salvar rascunho' ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const avisoModal = document.getElementById('avisoModal');
  if (avisoModal) {
    avisoModal.addEventListener('show.bs.modal', (ev) => {
      const btn = ev.relatedTarget;
      const type = btn && btn.getAttribute('data-aviso-type');
      const sel = document.getElementById('avisoType');
      const name = document.getElementById('avisoName');
      if (sel && type) sel.value = type;
      if (name && !name.value) {
        name.value = type === 'inativos' ? 'Sentimos sua falta' : 'Feliz aniversário';
      }
    });
  }
  <?php if ($edit): ?>
  const el = document.getElementById('campaignModal');
  if (el && window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();
  <?php endif; ?>
});
</script>
<?php admin_layout_end(); ?>
