<?php
/**
 * Mantido por compatibilidade: o fluxo de data/hora agora está em profissional.php
 */
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barber_id'])) {
    $_SESSION['booking']['barber_id'] = (int) $_POST['barber_id'];
}

$qs = [];
if (!empty($_SESSION['booking']['barber_id'])) {
    $qs['barber_id'] = (int) $_SESSION['booking']['barber_id'];
}
if (!empty($_GET['date'])) {
    $qs['date'] = $_GET['date'];
}
redirect(url('cliente/profissional.php' . ($qs ? '?' . http_build_query($qs) : '')));
