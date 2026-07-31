<?php
/**
 * PARTE 2 — Entrada do painel (escolhe dono ou barbeiro)
 * A PARTE 1 (cliente) fica em /cliente/
 */
require_once __DIR__ . '/../config/config.php';

$user = current_user();
if ($user && in_array($user['role'], ['dono', 'barbeiro'], true)) {
    redirect(panel_home_for($user['role']));
}

redirect(url('login.php'));
