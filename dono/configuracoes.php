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
        'google_my_business_url' => trim($_POST['google_my_business_url'] ?? ''),
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
        'evo_api_url' => trim($_POST['evo_api_url'] ?? ''),
        'evo_api_key' => trim($_POST['evo_api_key'] ?? ''),
        'evo_instance' => trim($_POST['evo_instance'] ?? ''),
        'blocked_days' => trim($_POST['blocked_days'] ?? ''),
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
          <label class="form-label">Link do Google Maps (Localização)</label>
          <input name="maps_url" class="form-control" placeholder="https://maps.app.goo.gl/..." value="<?= e($shop['maps_url'] ?? '') ?>">
          <div class="form-text">Usado para o botão de "Como chegar" no app.</div>
        </div>
        <div>
          <label class="form-label">Link do Google Meu Negócio (Avaliações)</label>
          <input name="google_my_business_url" class="form-control" placeholder="https://g.page/r/..." value="<?= e($shop['google_my_business_url'] ?? '') ?>">
          <div class="form-text">Se preenchido, clientes que derem 5 estrelas serão redirecionados para avaliar publicamente.</div>
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
        <h2 class="h6 mb-0">Dias bloqueados (Feriados / Folgas)</h2>
        <p class="small text-secondary mb-0">Adicione datas em que a barbearia estará fechada.</p>
        <div class="d-flex gap-2 mt-2">
            <input type="text" id="blocked-date-input" class="form-control" placeholder="DD/MM/AAAA">
            <button type="button" class="btn btn-outline-secondary" id="add-blocked-date">Adicionar</button>
        </div>
        <ul id="blocked-dates-list" class="list-group mt-2"></ul>
        <input type="hidden" name="blocked_days" id="blocked-days-hidden" value="<?= e($shop['blocked_days'] ?? '') ?>">

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
        <h2 class="h6 mb-0">WhatsApp (Evolution API)</h2>
        <p class="small text-secondary mb-0">Conecta o WhatsApp real da barbearia (escaneando um QR Code) pra disparar mensagens em Marketing — texto livre, sem precisar de template aprovado pela Meta.</p>
        <div>
          <label class="form-label">URL da Evolution API</label>
          <input name="evo_api_url" class="form-control" autocomplete="off" value="<?= e($shop['evo_api_url'] ?? '') ?>" placeholder="https://sua-evolution.exemplo.com">
        </div>
        <div>
          <label class="form-label">API Key (global)</label>
          <input name="evo_api_key" class="form-control" autocomplete="off" value="<?= e($shop['evo_api_key'] ?? '') ?>" placeholder="Chave da sua Evolution API">
        </div>
        <div>
          <label class="form-label">Nome da instância</label>
          <input name="evo_instance" class="form-control" autocomplete="off" value="<?= e($shop['evo_instance'] ?? '') ?>" placeholder="<?= e(function_exists('evo_default_instance') ? evo_default_instance() : 'loja-' . shop_slug()) ?>">
          <div class="form-text">Deixe em branco para usar o padrão acima — só mude se já tiver uma instância criada com outro nome.</div>
        </div>
        <p class="small <?= evo_configured() ? 'text-success' : 'text-secondary' ?> mb-0">
          Status: <?= evo_configured() ? 'Credenciais salvas' : 'Preencha URL e API Key para liberar o Disparar em Marketing' ?>
        </p>

        <button class="btn btn-accent" type="submit">Salvar tudo</button>
      </form>

      <?php if (evo_configured()): ?>
      <div class="mt-3 pt-3 border-top border-secondary border-opacity-25">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div>
            <strong>Número conectado</strong>
            <div class="small text-secondary">Status: <span id="evoStatusLabel">verificando…</span></div>
          </div>
          <button type="button" class="btn btn-sm btn-accent" id="evoConnectBtn">Conectar / Ver QR Code</button>
        </div>
        <div id="evoQrBox" class="d-none text-center mt-3">
          <img id="evoQrImg" src="" alt="QR Code WhatsApp" style="width:220px;height:220px;background:#fff;border-radius:8px;padding:8px">
          <p class="small text-secondary mt-2 mb-0">Abra o WhatsApp da barbearia → Aparelhos conectados → Conectar um aparelho, e escaneie.</p>
        </div>
      </div>

      <div class="mt-3 pt-3 border-top border-secondary border-opacity-25">
        <strong>Lembrete automático (1 dia antes)</strong>
        <p class="small text-secondary mb-2">Manda WhatsApp sozinho pros clientes com horário confirmado pra amanhã. Precisa rodar 1x por dia — configure na sua hospedagem:</p>
        <ul class="small text-secondary">
          <li>VPS com cron real: <code>php <?= e(__DIR__) ?>/../scripts/lembretes.php</code></li>
          <li>Hospedagem compartilhada (cron por URL, ex.: cPanel):
            <div class="input-group mt-1">
              <input type="text" class="form-control form-control-sm" readonly value="<?= e(absolute_url(base_path() . '/scripts/lembretes.php') . '?token=' . urlencode(cron_secret())) ?>">
            </div>
          </li>
        </ul>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="evoTestReminders">Enviar lembretes de amanhã agora (teste)</button>
        <span id="evoTestRemindersResult" class="small text-secondary ms-2"></span>
      </div>
      <?php endif; ?>
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
    <div class="card-soft p-3 mb-3">
      <h2 class="h6">Backup dos dados</h2>
      <p class="small text-secondary">Baixa um arquivo JSON com clientes, agendamentos, serviços, estoque e caixa — guarde de vez em quando.</p>
      <a class="btn btn-outline-secondary w-100" href="<?= e(url('dono/backup.php')) ?>">⬇ Baixar backup agora</a>
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

  const hiddenInput = document.getElementById('blocked-days-hidden');
  const dateInput = document.getElementById('blocked-date-input');
  const addBtn = document.getElementById('add-blocked-date');
  const list = document.getElementById('blocked-dates-list');

  if (hiddenInput) {
    let dates = hiddenInput.value ? hiddenInput.value.split(',') : [];

    function renderDates() {
        list.innerHTML = '';
        hiddenInput.value = dates.join(',');
        dates.forEach((d, i) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center py-1';
            const parts = d.split('-');
            const displayDate = parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : d;
            li.innerHTML = `<span>${displayDate}</span><button type="button" class="btn btn-sm btn-outline-danger px-2 py-0 border-0" data-idx="${i}">&times;</button>`;
            list.appendChild(li);
        });
    }

    list.addEventListener('click', e => {
        if (e.target.tagName === 'BUTTON' && e.target.hasAttribute('data-idx')) {
            dates.splice(e.target.dataset.idx, 1);
            renderDates();
        }
    });

    addBtn.addEventListener('click', () => {
        const val = dateInput.value.trim();
        const match = val.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (match) {
            const ymd = `${match[3]}-${match[2]}-${match[1]}`;
            if (!dates.includes(ymd)) {
                dates.push(ymd);
                dates.sort();
                renderDates();
            }
            dateInput.value = '';
        } else {
            alert('Por favor, use o formato DD/MM/AAAA');
        }
    });

    renderDates();
  }

  // WhatsApp Evolution API — conectar/QR/status
  const evoBtn = document.getElementById('evoConnectBtn');
  const evoLabel = document.getElementById('evoStatusLabel');
  const evoBox = document.getElementById('evoQrBox');
  const evoImg = document.getElementById('evoQrImg');
  const evoUrl = <?= json_encode(url('api/whatsapp_evolution.php')) ?>;
  let evoPoll = null;

  function evoLabelText(state) {
    return { open: 'Conectado ✅', connecting: 'Aguardando escanear o QR…', close: 'Desconectado', erro: 'Erro ao consultar', nao_configurado: 'Não configurado' }[state] || state;
  }

  async function evoFetchStatus() {
    if (!evoLabel) return;
    try {
      const res = await fetch(evoUrl + '?action=status', { credentials: 'same-origin', cache: 'no-store' });
      const data = await res.json();
      const state = data.state || 'erro';
      evoLabel.textContent = evoLabelText(state);
      if (state === 'open' && evoPoll) {
        clearInterval(evoPoll);
        evoPoll = null;
        if (evoBox) evoBox.classList.add('d-none');
      }
    } catch (e) {
      evoLabel.textContent = 'Erro ao consultar';
    }
  }

  if (evoBtn) {
    evoBtn.addEventListener('click', async () => {
      evoBtn.disabled = true;
      evoBtn.textContent = 'Gerando QR Code…';
      try {
        const res = await fetch(evoUrl + '?action=connect', { credentials: 'same-origin', cache: 'no-store' });
        const data = await res.json();
        if (data.ok && data.qr && evoImg && evoBox) {
          evoImg.src = data.qr;
          evoBox.classList.remove('d-none');
          if (!evoPoll) evoPoll = setInterval(evoFetchStatus, 4000);
        } else if (data.ok && data.state === 'open') {
          if (evoLabel) evoLabel.textContent = evoLabelText('open');
        } else {
          alert(data.error || 'Não deu pra gerar o QR Code agora. Confira a URL/API Key.');
        }
      } catch (e) {
        alert('Falha ao falar com a Evolution API.');
      }
      evoBtn.disabled = false;
      evoBtn.textContent = 'Conectar / Ver QR Code';
    });
  }

  if (evoLabel) {
    evoFetchStatus();
    evoPoll = setInterval(evoFetchStatus, 6000);
  }

  const testBtn = document.getElementById('evoTestReminders');
  const testResult = document.getElementById('evoTestRemindersResult');
  if (testBtn) {
    testBtn.addEventListener('click', async () => {
      testBtn.disabled = true;
      testResult.textContent = 'Enviando…';
      try {
        const res = await fetch(evoUrl + '?action=send_reminders_now', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': <?= json_encode(csrf_token()) ?> },
        });
        const data = await res.json();
        testResult.textContent = data.ok
          ? `Enviados: ${data.sent} · ignorados: ${data.skipped} · erros: ${data.errors}`
          : (data.error || 'Falha ao enviar.');
      } catch (e) {
        testResult.textContent = 'Falha ao falar com o servidor.';
      }
      testBtn.disabled = false;
    });
  }
})();
</script>
<?php admin_layout_end(); ?>
