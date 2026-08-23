<?php
require_once __DIR__ . '/config.php';
if (isLoggedIn()) {
    redirect('scanner.php');
} else {
    redirect('login.php');
}
