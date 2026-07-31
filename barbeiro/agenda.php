<?php
/** Agenda antiga → painel mobile do dia. */
require_once __DIR__ . '/../config/config.php';
$date = $_GET['date'] ?? date('Y-m-d');
redirect(url('barbeiro/?date=' . urlencode($date)));
