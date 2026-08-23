<?php
require_once __DIR__ . '/../config.php';
if (!isLoggedIn()) {
    setFlashMessage('warning', 'A folytatáshoz kérjük jelentkezzen be!');
    redirect('login.php');
    exit();
}
