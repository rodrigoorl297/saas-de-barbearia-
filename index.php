<?php
/**
 * PARTE 1 — App do cliente (público)
 * Quem acessa o site cai no catálogo/agendamento.
 * Dono/barbeiro logados vão para a PARTE 2 (painel).
 */
require_once __DIR__ . '/config/config.php';

$user = current_user();
if ($user && in_array($user['role'], ['dono', 'barbeiro'], true)) {
    redirect(panel_home_for($user['role']));
}

redirect(url('cliente/'));
