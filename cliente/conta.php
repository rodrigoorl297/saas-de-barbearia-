<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$shop = settings();
$client = current_client();
$mp = mp_settings();
$mpReady = mp_configured();

if (isset($_GET['sair'])) {
    logout_client();
    redirect(url('cliente/conta.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$client && $action !== '') {
        flash('warning', 'Entre com telefone e senha para continuar.');
        redirect(url('cliente/agendamentos.php?step=telefone'));
    }

    if ($client && $action === 'rename') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name !== '') {
            $payload = [
                'id' => (int)$client['id'],
                'name' => $name,
            ];
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $payload['email'] = $email;
            }
            save_user($payload);
            $_SESSION['client_name'] = $name;
            flash('success', 'Dados atualizados.');
        }
        redirect(url('cliente/conta.php'));
    }

    if ($client && $action === 'delete_card') {
        $id = (int)($_POST['id'] ?? 0);
        foreach (client_cards((int)$client['id']) as $c) {
            if ((int)$c['id'] === $id) {
                mp_delete_remote_card($c);
                break;
            }
        }
        delete_client_card($id, (int)$client['id']);
        flash('success', 'Cartão removido.');
        redirect(url('cliente/conta.php#cartoes'));
    }
}

$client = current_client();
$plans = active_plans();
$subs = $client ? client_subscriptions((int)$client['id']) : [];
$cards = $client ? client_cards((int)$client['id']) : [];
$charges = $client ? client_charges((int)$client['id']) : [];
$notifs = $client ? client_notifications((int)$client['id']) : [];

render_head('Conta', true);
client_shell_start('conta');
?>
<div class="page-inner conta-page"
     data-mp-public="<?= e($mp['public_key']) ?>"
     data-save-card-url="<?= e(url('api/mp-save-card.php')) ?>"
     data-charge-url="<?= e(url('api/mp-charge-plan.php')) ?>"
     data-mp-ready="<?= $mpReady ? '1' : '0' ?>">
  <?php render_flash_client(); ?>

  <?php if (!$client): ?>
    <div class="conta-login-box">
      <div class="conta-avatar"><?= icon_svg('user', 22) ?></div>
      <h1 class="page-title" style="margin:0">Sua conta</h1>
      <p class="page-sub">Entre para ver planos, cartões e cobranças.</p>
      <a class="btn-confirmar" href="<?= e(url('cliente/agendamentos.php?step=telefone')) ?>">Entrar</a>
    </div>
  <?php else: ?>
    <div class="conta-profile-card">
      <div class="conta-avatar"><?= icon_svg('user', 22) ?></div>
      <div class="conta-profile-text">
        <strong>Olá <?= e($client['name'] ?: 'Cliente') ?>!</strong>
      </div>
      <button type="button" class="conta-edit-btn" onclick="document.getElementById('rename-modal').showModal()" aria-label="Editar"><?= icon_svg('edit', 18) ?></button>
    </div>
    <div class="conta-logout-wrap">
      <a class="voltar-link" href="?sair=1">Sair da conta</a>
    </div>

    <dialog id="rename-modal" class="conta-dialog">
      <form method="post" class="conta-dialog-form">
        <input type="hidden" name="action" value="rename">
        <h3>Editar perfil</h3>
        <input class="input-telefone" name="name" required value="<?= e($client['name'] ?? '') ?>" placeholder="Nome">
        <input class="input-telefone" type="email" name="email" value="<?= e($client['email'] ?? '') ?>" placeholder="E-mail (para cobrança)">
        <div class="conta-dialog-actions">
          <button type="button" class="btn-ghost-as" onclick="this.closest('dialog').close()">Cancelar</button>
          <button type="submit" class="btn-confirmar" style="margin:0;width:auto;padding:10px 18px">Salvar</button>
        </div>
      </form>
    </dialog>

    <h2 class="conta-section-title">Seus planos e pacotes:</h2>

    <?php if (!$subs): ?>
      <p class="page-sub" style="text-align:center;margin-bottom:18px">Você ainda não tem planos ativos.</p>
    <?php else: ?>
      <div class="plan-mine-track">
        <?php foreach ($subs as $sub):
            $plan = find_plan((int)$sub['plan_id']);
            if (!$plan) continue;
            $used = (int)($sub['usage_count'] ?? 0);
            $limit = array_key_exists('usage_limit', $plan) && $plan['usage_limit'] !== null
                ? (string)$plan['usage_limit']
                : '∞';
            $renews = !empty($sub['renews_at']) ? date('d/m/Y', strtotime((string)$sub['renews_at'])) : '—';
        ?>
          <div class="plan-mine-card">
            <div class="plan-mine-name"><?= e($plan['name']) ?></div>
            <div class="plan-usage-row">
              <span><?= e($plan['benefit_label'] ?? 'Benefício') ?></span>
              <div class="plan-usage-bar"><i></i></div>
              <span><?= $used ?> de <?= e($limit) ?></span>
            </div>
            <a class="btn-agendar-plano" href="<?= e(url('cliente/agendar-plano.php?plan_id=' . (int)$plan['id'])) ?>">Agendar</a>
            <div class="plan-renew">Será renovado em <?= e($renews) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="conta-accordion">
      <details class="conta-acc" id="cartoes" open>
        <summary>
          <span class="acc-ico"><?= icon_svg('card', 18) ?></span>
          <span>Cartões cadastrados</span>
          <span class="acc-chev"><?= icon_svg('chevron', 16) ?></span>
        </summary>
        <div class="acc-body">
          <?php if (!$mpReady): ?>
            <p class="page-sub" style="margin:0 0 12px">Pagamentos ainda não configurados. O dono precisa informar as chaves do Mercado Pago em Configurações.</p>
          <?php endif; ?>

          <?php if (!$cards): ?>
            <p class="page-sub" style="margin:0 0 12px">Nenhum cartão cadastrado.</p>
          <?php else: ?>
            <div class="saved-cards-grid">
              <?php foreach ($cards as $c): ?>
                <div class="cc-visual">
                  <div class="cc-chip"></div>
                  <div class="cc-brand"><?= e(strtoupper((string)($c['brand'] ?? 'CARTÃO'))) ?></div>
                  <div class="cc-number">**** **** **** <?= e($c['last4'] ?? '----') ?></div>
                  <div class="cc-footer">
                    <div>
                      <span class="cc-label">Titular</span>
                      <strong><?= e($c['holder'] ?? 'Cliente') ?></strong>
                    </div>
                    <div>
                      <span class="cc-label">Validade</span>
                      <strong><?= e($c['exp'] ?? '--/--') ?></strong>
                    </div>
                  </div>
                  <form method="post" class="cc-remove" onsubmit="return confirm('Remover cartão?')">
                    <input type="hidden" name="action" value="delete_card">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="icon-act" title="Excluir" aria-label="Excluir"><?= icon_svg('trash', 16) ?></button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($mpReady): ?>
            <button type="button" class="btn-confirmar" id="btn-open-add-card">Adicionar cartão</button>
            <dialog id="add-card-modal" class="conta-dialog conta-dialog-wide">
              <div class="conta-dialog-form">
                <h3>Cadastrar cartão</h3>
                <p class="page-sub" style="margin:0">Dados enviados direto ao Mercado Pago (PCI). Não armazenamos o número completo.</p>
                <form id="form-checkout">
                  <div class="mp-field">
                    <label>E-mail do pagador</label>
                    <input type="email" id="form-checkout__email" name="email" required value="<?= e($client['email'] ?? '') ?>" placeholder="seu@email.com">
                  </div>
                  <div class="mp-field">
                    <label>Nome no cartão</label>
                    <input type="text" id="form-checkout__cardholderName" name="cardholderName" required value="<?= e($client['name'] ?? '') ?>">
                  </div>
                  <div class="mp-field">
                    <label>Número do cartão</label>
                    <div id="form-checkout__cardNumber" class="mp-secure"></div>
                  </div>
                  <div class="mp-row">
                    <div class="mp-field">
                      <label>Validade</label>
                      <div id="form-checkout__expirationDate" class="mp-secure"></div>
                    </div>
                    <div class="mp-field">
                      <label>CVV</label>
                      <div id="form-checkout__securityCode" class="mp-secure"></div>
                    </div>
                  </div>
                  <div class="mp-row">
                    <div class="mp-field">
                      <label>Tipo doc.</label>
                      <select id="form-checkout__identificationType" name="identificationType"></select>
                    </div>
                    <div class="mp-field">
                      <label>Documento</label>
                      <input type="text" id="form-checkout__identificationNumber" name="identificationNumber" required>
                    </div>
                  </div>
                  <select id="form-checkout__issuer" name="issuer" style="display:none"></select>
                  <select id="form-checkout__installments" name="installments" style="display:none"></select>
                  <div id="mp-card-error" class="alert-as danger" style="display:none"></div>
                  <div class="conta-dialog-actions">
                    <button type="button" class="btn-ghost-as" onclick="document.getElementById('add-card-modal').close()">Cancelar</button>
                    <button type="submit" class="btn-confirmar" style="margin:0;width:auto;padding:10px 18px" id="btn-save-card">Salvar cartão</button>
                  </div>
                </form>
              </div>
            </dialog>
          <?php endif; ?>
        </div>
      </details>

      <details class="conta-acc">
        <summary>
          <span class="acc-ico"><?= icon_svg('transfer', 18) ?></span>
          <span>Cobranças</span>
          <span class="acc-chev"><?= icon_svg('chevron', 16) ?></span>
        </summary>
        <div class="acc-body">
          <?php if (!$charges): ?>
            <p class="page-sub" style="margin:0">Nenhuma cobrança encontrada.</p>
          <?php else: ?>
            <ul class="charge-timeline">
              <?php foreach ($charges as $ch):
                  $d = !empty($ch['date']) ? date('d/m/Y', strtotime((string)$ch['date'])) : '—';
                  $st = ($ch['status'] ?? '') === 'proxima' ? 'Próxima cobrança' : 'Pago';
              ?>
                <li>
                  <div class="charge-dot"></div>
                  <div class="charge-main">
                    <strong><?= e($ch['plan_name'] ?? 'Plano') ?></strong>
                    <div class="page-sub" style="margin:0">
                      <?= e(strtoupper((string)($ch['card_brand'] ?? 'Cartão'))) ?> · <?= e($ch['card_last4'] ?? '----') ?> · <?= e($d) ?>
                      <?php if (!empty($ch['mp_payment_id'])): ?> · MP #<?= e((string)$ch['mp_payment_id']) ?><?php endif; ?>
                    </div>
                  </div>
                  <div class="charge-side">
                    <strong><?= e(money((float)($ch['amount'] ?? 0))) ?></strong>
                    <div class="page-sub" style="margin:0"><?= e($st) ?></div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </details>

      <details class="conta-acc">
        <summary>
          <span class="acc-ico"><?= icon_svg('bell', 18) ?></span>
          <span>Notificações</span>
          <span class="acc-chev"><?= icon_svg('chevron', 16) ?></span>
        </summary>
        <div class="acc-body">
          <?php if (!$notifs): ?>
            <p class="page-sub" style="margin:0">Nenhuma notificação encontrada.</p>
          <?php else: ?>
            <ul class="notif-list">
              <?php foreach ($notifs as $n): ?>
                <li>
                  <?php if (!empty($n['title'])): ?><strong><?= e($n['title']) ?></strong><br><?php endif; ?>
                  <?= e($n['message'] ?? '') ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </details>
    </div>
  <?php endif; ?>

  <h2 class="conta-section-title" style="margin-top:28px">Confira os planos e pacotes abaixo:</h2>

  <?php if (!$plans): ?>
    <p class="page-sub" style="text-align:center">Nenhum plano disponível no momento.</p>
  <?php else: ?>
    <div class="plan-catalog-wrap">
      <button type="button" class="plan-nav plan-nav-prev" aria-label="Plano anterior">‹</button>
      <button type="button" class="plan-nav plan-nav-next" aria-label="Próximo plano">›</button>
      <div class="plan-catalog-track" id="plan-catalog-track">
        <?php foreach ($plans as $p):
            $imgs = is_array($p['images'] ?? null) ? $p['images'] : ['', '', ''];
            while (count($imgs) < 3) { $imgs[] = ''; }
        ?>
          <article class="plan-offer-card">
            <?php if (!empty($p['recommended'])): ?>
              <div class="plan-rec-badge">Recomendado</div>
            <?php endif; ?>
            <div class="plan-offer-top">
              <div class="plan-offer-head">
                <span class="plan-offer-title"><?= e($p['name']) ?></span>
              </div>
              <div class="plan-price-pill">
                <span><?= e(money((float)$p['price'])) ?></span>
                <span><?= e($p['interval'] ?? 'Mês') ?></span>
              </div>
            </div>
            <div class="plan-offer-body">
              <div class="plan-thumbs">
                <?php for ($i = 0; $i < 3; $i++): ?>
                  <div class="plan-thumb">
                    <?php if (!empty($imgs[$i])): ?>
                      <img src="<?= e(media_url($imgs[$i])) ?>" alt="">
                    <?php else: ?>
                      <?= icon_svg('camera', 26) ?>
                    <?php endif; ?>
                  </div>
                <?php endfor; ?>
              </div>
              <?php if (!empty($p['headline'])): ?>
                <p class="plan-headline"><?= e($p['headline']) ?></p>
              <?php endif; ?>
              <?php if (!empty($p['description'])): ?>
                <p class="plan-desc"><?= e($p['description']) ?></p>
              <?php endif; ?>
              <?php if ($client): ?>
                <button class="btn-assinar js-assinar"
                        type="button"
                        data-plan-id="<?= (int)$p['id'] ?>"
                        data-plan-name="<?= e($p['name']) ?>"
                        data-plan-price="<?= e((string)$p['price']) ?>">
                  Assinar
                </button>
              <?php else: ?>
                <a class="btn-assinar" href="<?= e(url('cliente/agendamentos.php?step=telefone')) ?>">Assinar</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="plan-dots" id="plan-dots" aria-hidden="true"></div>
    </div>
  <?php endif; ?>

  <?php if ($client): ?>
    <dialog id="pay-plan-modal" class="conta-dialog">
      <div class="conta-dialog-form">
        <h3 id="pay-plan-title">Assinar plano</h3>
        <p class="page-sub" id="pay-plan-sub" style="margin:0"></p>
        <?php if (!$cards): ?>
          <p class="page-sub">Cadastre um cartão antes de assinar.</p>
          <div class="conta-dialog-actions">
            <button type="button" class="btn-ghost-as" onclick="document.getElementById('pay-plan-modal').close()">Fechar</button>
            <button type="button" class="btn-confirmar" style="margin:0;width:auto;padding:10px 18px" onclick="document.getElementById('pay-plan-modal').close();document.getElementById('cartoes').open=true;document.getElementById('btn-open-add-card')?.click()">Cadastrar cartão</button>
          </div>
        <?php else: ?>
          <label class="page-sub" style="display:block;margin-bottom:6px">Cobrar no cartão</label>
          <div class="pay-card-list">
            <?php foreach ($cards as $i => $c): ?>
              <label class="pay-card-option">
                <input type="radio" name="pay_card_id" value="<?= (int)$c['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <span class="pay-card-mini">
                  <strong><?= e(strtoupper((string)($c['brand'] ?? 'CARTÃO'))) ?></strong>
                  **** <?= e($c['last4'] ?? '----') ?> · <?= e($c['exp'] ?? '') ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <div id="mp-pay-error" class="alert-as danger" style="display:none"></div>
          <div class="conta-dialog-actions">
            <button type="button" class="btn-ghost-as" onclick="document.getElementById('pay-plan-modal').close()">Cancelar</button>
            <button type="button" class="btn-confirmar" style="margin:0;width:auto;padding:10px 18px" id="btn-confirm-pay">Pagar e assinar</button>
          </div>
        <?php endif; ?>
      </div>
    </dialog>

  <?php endif; ?>
</div>
<?php if ($client && $mpReady): ?>
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script src="<?= e(url('assets/js/pagamentos.js')) ?>"></script>
<?php elseif ($client): ?>
<script src="<?= e(url('assets/js/pagamentos.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(url('assets/js/cliente.js')) ?>"></script>
<?php client_shell_end('conta'); ?>
