<?php
/**
 * Instalador one-shot do MySQL da barbearia.
 * 1) Configure o .env (DB_*)
 * 2) Abra /instalar-banco.php no navegador e confirme
 * 3) O próprio arquivo se apaga sozinho após instalar com sucesso
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');

if (!db_enabled()) {
    echo '<h1>MySQL desligado</h1><p>No <code>.env</code> defina <code>DB_ENABLED=true</code> e os dados do banco <code>barbaflow</code>.</p>';
    exit;
}

// Confirmação explícita antes de instalar: uma visita GET simples (ex.: bot
// indexando a URL) não deve disparar a instalação nem revelar dados do banco.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo '<h1>Instalar banco de dados</h1>';
    echo '<p>Isso vai criar as tabelas (se não existirem) e importar os dados atuais do JSON, se o banco estiver vazio.</p>';
    echo '<form method="post"><input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
    echo '<button type="submit">Instalar agora</button></form>';
    exit;
}

$pdo = db_pdo();
if (!$pdo) {
    echo '<h1>Falha na conexão</h1><p>Confira DB_HOST, DB_NAME, DB_USER e DB_PASS no .env.</p>';
    exit;
}

try {
    db_install_schema_if_needed();
    db_migrate_from_json_if_empty();
    $users = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $prods = (int)$pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn();
    $ags = (int)$pdo->query('SELECT COUNT(*) FROM agendamentos')->fetchColumn();
    echo '<h1>Banco pronto</h1>';
    echo '<ul>';
    echo '<li>Banco: <strong>' . htmlspecialchars(\App\DotEnv::getString('DB_NAME')) . '</strong></li>';
    echo '<li>Usuários: ' . $users . ' (visões: donos, barbeiros, clientes)</li>';
    echo '<li>Produtos: ' . $prods . '</li>';
    echo '<li>Agendamentos: ' . $ags . '</li>';
    echo '</ul>';

    if (@unlink(__FILE__)) {
        echo '<p>Este instalador se apagou automaticamente por segurança.</p>';
    } else {
        echo '<p><strong>Não foi possível apagar este arquivo automaticamente</strong> (permissão de escrita?). ';
        echo 'Apague manualmente <code>instalar-banco.php</code> do servidor agora.</p>';
    }
    echo '<p><a href="' . htmlspecialchars(url('login.php')) . '">Ir para o login</a></p>';
} catch (Throwable $e) {
    echo '<h1>Erro</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
