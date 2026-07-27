<?php
/**
 * Instalador one-shot do MySQL da barbearia.
 * 1) Configure o .env (DB_*)
 * 2) Abra /instalar-banco.php no navegador
 * 3) Apague este arquivo depois
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');

if (!db_enabled()) {
    echo '<h1>MySQL desligado</h1><p>No <code>.env</code> defina <code>DB_ENABLED=true</code> e os dados do banco <code>barbaflow</code>.</p>';
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
    echo '<li>Banco: <strong>' . htmlspecialchars(env_str('DB_NAME')) . '</strong></li>';
    echo '<li>Usuários: ' . $users . ' (visões: donos, barbeiros, clientes)</li>';
    echo '<li>Produtos: ' . $prods . '</li>';
    echo '<li>Agendamentos: ' . $ags . '</li>';
    echo '</ul>';
    echo '<p><strong>Apague</strong> o arquivo <code>instalar-banco.php</code> do servidor.</p>';
    echo '<p><a href="' . htmlspecialchars(url('login.php')) . '">Ir para o login</a></p>';
} catch (Throwable $e) {
    echo '<h1>Erro</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
