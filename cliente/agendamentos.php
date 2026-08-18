<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';

$error = null;
$step = $_GET['step'] ?? 'home'; // home | telefone | senha | historico
$client = current_client();

if (isset($_GET['sair'])) {
    logout_client();
    redirect(url('cliente/agendamentos.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'phone') {
        $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
        $minLen = (defined('CLIENT_DEMO_OPEN') && CLIENT_DEMO_OPEN) ? 1 : 10;
        if (strlen($phone) < $minLen) {
            $error = 'Informe um telefone válido.';
            $step = 'telefone';
        } else {
            $_SESSION['login_phone'] = $phone;
            if (find_client_by_phone($phone)) {
                $step = 'senha';
            } else {
                $step = 'criar_senha';
            }
        }
    }

    if ($action === 'senha') {
        if (login_client_is_locked()) {
            $error = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
            $step = 'senha';
        } else {
            $phone = preg_replace('/\D+/', '', $_SESSION['login_phone'] ?? ($_POST['phone'] ?? ''));
            // Demo: aceita senha com qualquer coisa (números ou texto)
            $senha = (defined('CLIENT_DEMO_OPEN') && CLIENT_DEMO_OPEN)
                ? trim((string)($_POST['password'] ?? ''))
                : preg_replace('/\D+/', '', $_POST['password'] ?? '');
            $auth = attempt_client_login($phone, $senha);
            if (!$auth) {
                $error = login_client_is_locked()
                    ? 'Muitas tentativas. Aguarde 15 minutos e tente de novo.'
                    : 'Informe telefone e senha.';
                $step = 'senha';
            } else {
                login_client($auth);
                unset($_SESSION['login_phone']);
                redirect(url('cliente/agendamentos.php?step=historico'));
            }
        }
    }

    if ($action === 'criar_senha') {
        $phone = preg_replace('/\D+/', '', $_SESSION['login_phone'] ?? '');
        $senha = preg_replace('/\D+/', '', $_POST['password'] ?? '');
        $name = trim($_POST['name'] ?? 'Cliente');
        if (strlen($phone) < 10 || strlen($senha) < 4) {
            $error = 'Telefone e senha numérica (mín. 4 dígitos) são obrigatórios.';
            $step = 'criar_senha';
        } else {
            $existing = find_client_by_phone($phone);
            if ($existing) {
                save_user([
                    'id' => (int)$existing['id'],
                    'name' => $name !== '' ? $name : ($existing['name'] ?? 'Cliente'),
                    'phone' => $phone,
                    'password' => password_hash($senha, PASSWORD_DEFAULT),
                    'role' => 'cliente',
                    'active' => 1,
                    'email' => $existing['email'] ?? null,
                    'avatar' => $existing['avatar'] ?? null,
                ]);
                $u = find_user_by_id((int)$existing['id']);
            } else {
                $u = save_user([
                    'name' => $name !== '' ? $name : 'Cliente',
                    'email' => null,
                    'phone' => $phone,
                    'password' => password_hash($senha, PASSWORD_DEFAULT),
                    'role' => 'cliente',
                    'avatar' => null,
                    'active' => 1,
                ]);
            }
            if ($u) {
                login_client($u);
            }
            unset($_SESSION['login_phone']);
            redirect(url('cliente/agendamentos.php?step=historico'));
        }
    }

    if ($action === 'reset') {
        $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? ($_SESSION['login_phone'] ?? ''));
        $senha = preg_replace('/\D+/', '', $_POST['password'] ?? '');
        $found = find_client_by_phone($phone);
        if (!$found || strlen($senha) < 4) {
            $error = 'Não encontramos esse telefone ou a senha é inválida.';
            $step = 'reset';
        } else {
            save_user([
                'id' => (int)$found['id'],
                'name' => $found['name'],
                'phone' => $phone,
                'password' => password_hash($senha, PASSWORD_DEFAULT),
                'role' => 'cliente',
                'active' => 1,
                'email' => $found['email'] ?? null,
                'avatar' => $found['avatar'] ?? null,
            ]);
            login_client(find_user_by_id((int)$found['id']));
            flash('success', 'Senha atualizada!');
            redirect(url('cliente/agendamentos.php?step=historico'));
        }
    }
}

if ($client && ($step === 'home' || $step === 'historico' || isset($_GET['ver']))) {
    $step = 'historico';
}

$phone = $client['phone'] ?? ($_SESSION['login_phone'] ?? '');
$items = [];
$futuros = [];
$anteriores = [];
$ativos = [];

if ($client) {
    $phoneNorm = preg_replace('/\D+/', '', (string)$client['phone']);
    $items = appointments_enriched(fn($a) => preg_replace('/\D+/', '', (string)$a['client_phone']) === $phoneNorm);
    usort($items, fn($a, $b) => strcmp($b['date'] . $b['time'], $a['date'] . $a['time']));
    $today = date('Y-m-d');
    foreach ($items as $a) {
        $isFuture = $a['date'] > $today || ($a['date'] === $today && $a['time'] >= date('H:i'));
        $isActive = in_array($a['status'], ['agendado', 'confirmado'], true) && ($a['date'] > $today || ($a['date'] === $today));
        if ($isActive) {
            $ativos[] = $a;
        }
        if ($isFuture && in_array($a['status'], ['agendado', 'confirmado'], true)) {
            $futuros[] = $a;
        } else {
            $anteriores[] = $a;
        }
    }
}

render_head('Histórico', true);
client_shell_start('agendamentos');
?>

<?php if ($step === 'home'): ?>
  <div class="page-inner historico-home">
    <h1 class="page-title">Histórico de agendamentos</h1>
    <div class="historico-divider"></div>
    <p class="historico-empty">Não existe agendamento ativo!</p>
    <div class="ver-mais-wrap">
      <a class="btn-ver-mais" href="<?= e(url('cliente/agendamentos.php?step=telefone')) ?>">Ver mais +</a>
    </div>
  </div>

<?php elseif ($step === 'telefone'): ?>
  <div class="modal-as-backdrop">
    <form method="post" class="modal-as-card">
      <input type="hidden" name="action" value="phone">
      <label class="modal-label">Telefone:</label>
      <input class="input-telefone" type="tel" name="phone" required placeholder="<?= (defined('CLIENT_DEMO_OPEN') && CLIENT_DEMO_OPEN) ? 'Qualquer número (demo)' : 'Seu WhatsApp com DDD' ?>" value="<?= e($_SESSION['login_phone'] ?? '') ?>">
      <?php if (defined('CLIENT_DEMO_OPEN') && CLIENT_DEMO_OPEN): ?>
        <p class="page-sub" style="margin:8px 0">Modo demonstração: digite qualquer telefone.</p>
      <?php endif; ?>
      <?php if ($error): ?><div class="alert-as danger"><?= e($error) ?></div><?php endif; ?>
      <button class="btn-confirmar" type="submit">Continuar</button>
      <a class="voltar-link" href="<?= e(url('cliente/agendamentos.php')) ?>" style="margin-top:12px;display:inline-block">Cancelar</a>
    </form>
  </div>

<?php elseif ($step === 'senha'): ?>
  <div class="modal-as-backdrop">
    <form method="post" class="modal-as-card">
      <input type="hidden" name="action" value="senha">
      <label class="modal-label">Senha:</label>
      <div class="senha-wrap">
        <input class="input-telefone" type="password" name="password" id="pass" required placeholder="<?= (defined('CLIENT_DEMO_OPEN') && CLIENT_DEMO_OPEN) ? 'Qualquer senha (demo)' : 'Sua senha' ?>">
        <button type="button" class="eye-btn" onclick="const i=document.getElementById('pass');i.type=i.type==='password'?'text':'password'" aria-label="Mostrar senha"><?= icon_svg('eye', 16) ?></button>
      </div>
      <?php if (defined('CLIENT_DEMO_OPEN') && CLIENT_DEMO_OPEN): ?>
        <p class="page-sub" style="margin:8px 0">Modo demonstração: qualquer número e senha entram.</p>
      <?php endif; ?>
      <a class="link-esqueci" href="<?= e(url('cliente/agendamentos.php?step=reset')) ?>">Esqueci minha senha</a>
      <?php if ($error): ?><div class="alert-as danger"><?= e($error) ?></div><?php endif; ?>
      <button class="btn-confirmar" type="submit">Confirmar</button>
    </form>
  </div>

<?php elseif ($step === 'criar_senha'): ?>
  <div class="modal-as-backdrop">
    <form method="post" class="modal-as-card">
      <input type="hidden" name="action" value="criar_senha">
      <p class="page-sub" style="margin-top:0">Crie sua senha numérica para ver o histórico.</p>
      <label class="modal-label">Nome:</label>
      <input class="input-telefone" type="text" name="name" required placeholder="Seu nome">
      <label class="modal-label" style="margin-top:10px">Senha:</label>
      <div class="senha-wrap">
        <input class="input-telefone" type="password" name="password" required inputmode="numeric" pattern="[0-9]{4,}" placeholder="Digite apenas números">
      </div>
      <?php if ($error): ?><div class="alert-as danger"><?= e($error) ?></div><?php endif; ?>
      <button class="btn-confirmar" type="submit">Confirmar</button>
    </form>
  </div>

<?php elseif ($step === 'reset'): ?>
  <div class="modal-as-backdrop">
    <form method="post" class="modal-as-card">
      <input type="hidden" name="action" value="reset">
      <label class="modal-label">Telefone:</label>
      <input class="input-telefone" type="tel" name="phone" required inputmode="numeric" placeholder="Digite apenas números" value="<?= e($_SESSION['login_phone'] ?? '') ?>">
      <label class="modal-label" style="margin-top:10px">Nova senha:</label>
      <input class="input-telefone" type="password" name="password" required inputmode="numeric" placeholder="Digite apenas números">
      <?php if ($error): ?><div class="alert-as danger"><?= e($error) ?></div><?php endif; ?>
      <button class="btn-confirmar" type="submit">Confirmar</button>
    </form>
  </div>

<?php else: /* historico logado */ ?>
  <div class="page-inner">
    <h1 class="page-title">Histórico de agendamentos</h1>
    <div class="historico-nome"><?= e($client['name'] ?? '') ?></div>
    <div class="historico-divider"></div>

    <?php if (!$ativos): ?>
      <p class="historico-empty">Não existe agendamento ativo!</p>
    <?php endif; ?>

    <details class="hist-accordion" open>
      <summary>Agendamentos futuros</summary>
      <?php if (!$futuros): ?>
        <p class="page-sub">Não há agendamentos futuros.</p>
      <?php else: ?>
        <?php foreach ($futuros as $a): ?>
          <div class="hist-card">
            <div class="hist-card-top">
              <strong><?= e(date('d/m/Y', strtotime($a['date']))) ?> · <?= e(substr($a['time'], 0, 2)) ?>h</strong>
              <span class="badge-as"><?= e(status_label($a['status'])) ?></span>
            </div>
            <div class="hist-card-body">
              <div><?= e($a['service_name']) ?></div>
              <div class="page-sub" style="margin:4px 0"><?= e(date('d/m/Y', strtotime($a['date']))) ?> às <?= e($a['time']) ?></div>
              <div class="hist-card-foot">
                <span><span class="user-mini"><?= icon_svg('user', 14) ?></span> <?= e($a['barber_name']) ?></span>
                <strong><?= e(money((float)$a['price'])) ?></strong>
              </div>
              <a class="ver-mais-inline" href="<?= e(url('cliente/agendamentos.php?step=historico&id=' . (int)$a['id'])) ?>">Ver mais</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <p class="page-sub">Não há mais agendamentos no seu histórico!</p>
    </details>

    <details class="hist-accordion" open>
      <summary>Agendamentos anteriores</summary>
      <?php if (!$anteriores): ?>
        <p class="page-sub">Nenhum anterior.</p>
      <?php else: ?>
        <?php foreach ($anteriores as $a): ?>
          <div class="hist-card">
            <div class="hist-card-top">
              <strong><?= e(date('d/m/Y', strtotime($a['date']))) ?> · <?= e(substr($a['time'], 0, 2)) ?>h</strong>
              <span class="badge-as ok"><?= e(status_label($a['status'])) ?></span>
            </div>
            <div class="hist-card-body">
              <div><?= e($a['service_name']) ?></div>
              <div class="page-sub" style="margin:4px 0"><?= e(date('d/m/Y', strtotime($a['date']))) ?> às <?= e($a['time']) ?></div>
              <div class="hist-card-foot">
                <span><span class="user-mini"><?= icon_svg('user', 14) ?></span> <?= e($a['barber_name']) ?></span>
                <strong><?= e(money((float)$a['price'])) ?></strong>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                <a class="ver-mais-inline" href="#">Ver mais</a>
                <?php if ($a['status'] === 'concluido' && empty($a['rating'])): ?>
                  <a class="btn-sm" href="<?= e(url('cliente/avaliar.php?id=' . (int)$a['id'])) ?>" style="background: rgba(201,162,39,.1); color: #c9a227; padding: 4px 12px; border-radius: 12px; text-decoration: none; font-size: 0.8rem; font-weight: 600;">Avaliar</a>
                <?php elseif (!empty($a['rating'])): ?>
                  <span style="color: #c9a227; font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 2px;">
                    <?= $a['rating'] ?> <svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </details>

    <div style="margin-top:20px">
      <a class="voltar-link" href="<?= e(url('cliente/agendamentos.php?sair=1')) ?>">Sair da conta</a>
    </div>
  </div>
<?php endif; ?>

<?php client_shell_end('agendamentos'); ?>
