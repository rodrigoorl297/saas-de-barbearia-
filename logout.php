<?php
require_once __DIR__ . '/config/config.php';
logout_user();
redirect(url('login.php'));
