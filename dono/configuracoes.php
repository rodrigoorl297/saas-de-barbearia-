<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/whatsapp.php';

$user = require_role(['dono']);

$shop = settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logoUrl = $shop['logo_url'] ?? '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $path = upload_image($_FILES['logo'], 'logo');
        if ($path) {
            $logoUrl = $path;
        }
    }

    save_settings([
        'shop_name' => trim($_POST['shop_name'] ?? 'Barba Flow'),
        'logo_url' => $logoUrl,
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'instagram' => trim($_POST['instagram'] ?? ''),
        'maps_url' => trim($_POST['maps_url'] ?? ''),
        'open_time' => normalize_time($_POST['open_time'] ?? '08:00', '08:00'),
        'close_time' => normalize_time($_POST['close_time'] ?? '20:00', '20:00'),
        'lunch_enabled' => isset($_POST['lunch_enabled']) ? 1 : 0,
        'lunch_start' => normalize_time($_POST['lunch_start'] ?? '12:00', '12:00'),
        'lunch_end' => normalize_time($_POST['lunch_end'] ?? '13:00', '13:00'),
        'slot_minutes' => max(15, (int)($_POST['slot_minutes'] ?? 60)),
        'primary_color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['primary_color'] ?? ''))
            ? $_POST['primary_color'] : ($shop['primary_color'] ?? '#11172f'),
        'accent_color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['accent_color'] ?? ''))
            ? $_POST['accent_color'] : ($shop['accent_color'] ?? '#c9a227'),
        'mp_public_key' => trim($_POST['mp_public_key'] ?? ''),
        'mp_access_token' => trim($_POST['mp_access_token'] ?? ''),
        'wa_phone_number_id' => trim($_POST['wa_phone_number_id'] ?? ''),
        'wa_access_token' => trim($_POST['wa_access_token'] ?? ''),
    ]);
    flash('success', 'Horários e configurações salvos. Os agendamentos dos clientes já usam esses horários.');
    redirect(url('dono/configuracoes.php'));
}

$previewSlots = available_slots(2, date('Y-m-d'), 60);

admin_layout_start('Configurações', 'dono', 'config');
?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-soft p-3 mb-3">
      <h2 class="h6 mb-2">Marca do sistema (fixa)</h2>
      <p class="small text-secondary mb-3">Padrão do software comercializado. Aparece no painel e nos logins — <strong>não pode ser alterada</strong>.</p>
      <div class="d-flex align-items-center gap-3">
        <img src="<?= e(media_url(product_logo_path())) ?>" alt="<?= e(product_name()) ?>" class="rounded-circle border" style="width:64px;height:64px;object-fit:cover;background:#000">
        <div>
          <div class="fw-bold"><?= e(str_upper(product_name())) ?></div>
          <div class="small text-secondary">Somente leitura · definida no sistema</div>
        </div>
      </div>
    </div>

    <div class="card-soft p-3">
      <form method="post" enctype="multipart/form-data" class="vstack gap-3">
        <h2 class="h6 mb-0">App do cliente</h2>
        <p class="small text-secondary mb-0">Logo e nome da <strong>barbearia</strong> — só no app do cliente. Não mudam a marca do painel.</p>
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <?php if (!empty($shop['logo_url'])): ?>
                    <img src="<?= e(media_url($shop['logo_url'])) ?>" alt="Logo" class="rounded-circle border" style="width:60px; height:60px; object-fit:cover; background:#000">
                <?php else: ?>
                    <div class="bg-secondary rounded-circle d-flex justify-content-center align-items-center text-white" style="width:60px; height:60px;">Sem Logo</div>
                <?php endif; ?>
            </div>
            <div class="col">
                <label class="form-label">Logo no app do cliente</label>
                <div class="js-image-upload">
                  <input type="file" name="logo" class="form-control" accept="image/*">
                </div>
            </div>
        </div>
        <div>
          <label class="form-label">Nome no app do cliente</label>
          <input name="shop_name" class="form-control" required value="<?= e($shop['shop_name']) ?>" placeholder="Ex: Barbearia do João">
          <div class="form-text">Esse nome vira o link público do app (slug).</div>
        </div>
        <div>
          <label class="form-label">Link do app do cliente</label>
          <div class="input-group">
            <input type="text" class="form-control" id="client-app-link" readonly value="<?= e(client_app_absolute_url()) ?>">
            <button type="button" class="btn btn-outline-secondary" id="copy-client-link">Copiar</button>
          </div>
          <div class="form-text">Compartilhe este link com os clientes. Ele muda automaticamente quando você altera o nome.</div>
        </div>

        <hr class="border-secondary opacity-25">
        <h2 class="h6 mb-0">Cores da marca</h2>
        <p class="small text-secondary mb-0">Usadas no app do cliente e nos destaques do painel. Se não mudar, mantém o padrão do sistema.</p>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Cor primária</label>
            <input type="color" name="primary_color" class="form-control form-control-color" value="<?= e($shop['primary_color'] ?? '#11172f') ?>">
          </div>
          <div class="col-6">
            <label class="form-label">Cor de destaque</label>
            <input type="color" name="accent_color" class="form-control form-control-color" value="<?= e($shop['accent_color'] ?? '#c9a227') ?>">
          </div>
        </div>

        <hr class="border-secondary opacity-25">
        <h2 class="h6 mb-0">Dados da barbearia</h2>
        <div><label class="form-label">Telefone</label><input name="phone" class="form-control" value="<?= e($shop['phone'] ?? '') ?>"></div>
        <div><label class="form-label">Endereço</label><input name="address" class="form-control" value="<?= e($shop['address'] ?? '') ?>"></div>
        <div>
          <label class="form-label">Instagram (link completo do perfil)</label>
          <input name="instagram" class="form-control" placeholder="https://www.instagram.com/sua_barbearia/" value="<?= e($shop['instagram'] ?? '') ?>">
          <?php
            $igPreview = normalize_external_url((string)($shop['instagram'] ?? ''), 'instagram');
          ?>
          <?php if ($igPreview !== ''): ?>
            <div class="form-text">
              Link que abre no app:
              <a href="<?= e($igPreview) ?>" target="_blank" rel="noopener noreferrer"><?= e($igPreview) ?></a>
            </div>
          <?php else: ?>
            <div class="form-text">Cole o link do perfil, ex.: https://www.instagram.com/sua_conta/</div>
          <?php endif; ?>
        </div>
        <div>
          <label class="form-label">Localização / Google Maps (link)</label>
          <input name="maps_url" class="form-control" placeholder="https://maps.google.com/?q=..." value="<?= e($shop['maps_url'] ?? '') ?>">
          <?php
            $mapsPreview = normalize_external_url((string)($shop['maps_url'] ?? ''));
          ?>
          <?php if ($mapsPreview !== ''): ?>
            <div class="form-text">
              Link que abre no app:
              <a href="<?= e($mapsPreview) ?>" target="_blank" rel="noopener noreferrer"><?= e($mapsPreview) ?></a>
            </div>
          <?php endif; ?>
        </div>
        <p class="small text-secondary mb-0">Esses links aparecem como ícones no topo do app do cliente.</p>

        <hr class="border-secondary opacity-25">
        <h2 class="h6 mb-0">Horário de funcionamento</h2>
        <p class="small text-secondary mb-0">Isso controla os horários disponíveis no app do cliente.</p>
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Abre</label>
            <input type="time" name="open_time" class="form-control" value="<?= e(normalize_time($shop['open_time'] ?? '08:00')) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Fecha</label>
            <input type="time" name="close_time" class="form-control" value="<?= e(normalize_time($shop['close_time'] ?? '20:00')) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Intervalo de agendamento</label>
            <select name="slot_minutes" class="form-select">
              <?php foreach ([60 => '1 hora', 30 => '30 minutos', 45 => '45 minutos', 90 => '1h30'] as $min => $label): ?>
                <option value="<?= $min ?>" <?= (int)($shop['slot_minutes'] ?? 60) === $min ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <hr class="border-secondary opacity-25">
        <h2 class="h6 mb-0">Horário de almoço</h2>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="lunch_enabled" id="lunch_enabled" <?= !empty($shop['lunch_enabled']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="lunch_enabled">Bloquear agendamentos no almoço</label>
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Início do almoço</label>
            <input type="time" name="lunch_start" class="form-control" value="<?= e(normalize_time($shop['lunch_start'] ?? '12:00')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Fim do almoço</label>
            <input type="time" name="lunch_end" class="form-control" value="<?= e(normalize_time($shop['lunch_end'] ?? '13:00')) ?>">
          </div>
        </div>

        <hr class="border-secondary opacity-25">
        <h2 class="h6 mb-0">Mercado Pago · cobrança real</h2>
        <p class="small text-secondary mb-0">Cartões tokenizados e cobrança de planos no app do cliente. Chaves em <a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank" rel="noopener">developers.mercadopago.com</a>.</p>
        <div>
          <label class="form-label">Public Key</label>
          <input name="mp_public_key" class="form-control" autocomplete="off" value="<?= e($shop['mp_public_key'] ?? '') ?>" placeholder="APP_USR-...">
        </div>
        <div>
          <label class="form-label">Access Token</label>
          <input name="mp_access_token" class="form-control" autocomplete="off" value="<?= e($shop['mp_access_token'] ?? '') ?>" placeholder="APP_USR-...">
        </div>

        <hr class="border-secondary opacity-25">
        <h2 class="h6 mb-0">WhatsApp (Meta Cloud API)</h2>
        <p class="small text-secondary mb-0">Necessário para o botão Disparar em Marketing. Crie templates aprovados no Business Manager. Também pode usar variáveis no arquivo <code>.env</code>.</p>
        <div>
          <label class="form-label">Phone Number ID</label>
          <input name="wa_phone_number_id" class="form-control" autocomplete="off" value="<?= e($shop['wa_phone_number_id'] ?? '') ?>" placeholder="Ex: 123456789012345">
        </div>
        <div>
          <label class="form-label">Access Token</label>
          <input name="wa_access_token" class="form-control" autocomplete="off" value="<?= e($shop['wa_access_token'] ?? '') ?>" placeholder="EAAB...">
        </div>
        <p class="small <?= wa_configured() ? 'text-success' : 'text-secondary' ?> mb-0">
          Status: <?= wa_configured() ? 'Configurado — disparos ativos' : 'Não configurado — Disparar não envia ainda' ?>
        </p>

        <button class="btn btn-accent" type="submit">Salvar tudo</button>
      </form>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card-soft p-3 mb-3">
      <h2 class="h6">Resumo atual</h2>
      <div class="small text-secondary mb-2">Exemplo: abre 08:00, fecha 20:00, almoço 12:00–13:00, intervalo 1h</div>
      <ul class="mb-0">
        <li>Aberto: <strong><?= e(normalize_time($shop['open_time'] ?? '08:00')) ?></strong> até <strong><?= e(normalize_time($shop['close_time'] ?? '20:00')) ?></strong></li>
        <li>Intervalo: <strong><?= (int)($shop['slot_minutes'] ?? 60) ?> min</strong></li>
        <li>Almoço: <?= !empty($shop['lunch_enabled']) ? '<strong>' . e(normalize_time($shop['lunch_start'] ?? '12:00')) . ' – ' . e(normalize_time($shop['lunch_end'] ?? '13:00')) . '</strong>' : 'desativado' ?></li>
      </ul>
    </div>
    <div class="card-soft p-3">
      <h2 class="h6">Horários ainda disponíveis hoje</h2>
      <p class="small text-secondary">Prévia com base nas regras acima (barbeiro exemplo).</p>
      <div class="d-flex flex-wrap gap-2">
        <?php if (!$previewSlots): ?>
          <span class="text-secondary">Nenhum horário livre hoje.</span>
        <?php else: ?>
          <?php foreach ($previewSlots as $slot): ?>
            <span class="badge text-bg-success"><?= e($slot) ?></span>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <hr class="border-secondary opacity-25">
      <p class="small text-secondary mb-1">Link do cliente:</p>
      <code class="d-block text-break"><?= e(client_app_absolute_url()) ?></code>
    </div>
  </div>
</div>
<script>
(() => {
  const btn = document.getElementById('copy-client-link');
  const input = document.getElementById('client-app-link');
  if (!btn || !input) return;
  btn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(input.value);
      btn.textContent = 'Copiado';
      setTimeout(() => { btn.textContent = 'Copiar'; }, 1600);
    } catch (e) {
      input.select();
      document.execCommand('copy');
      btn.textContent = 'Copiado';
      setTimeout(() => { btn.textContent = 'Copiar'; }, 1600);
    }
  });
})();
</script>
<?php admin_layout_end(); ?>
