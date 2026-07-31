<?php
/**
 * Redireciona para o login unificado.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = current_user();
if ($user && ($user['role'] ?? '') === 'barbeiro') {
    redirect(url('barbeiro/'));
}
redirect(url('login.php'));
