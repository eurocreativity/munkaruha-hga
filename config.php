<?php
/**
 * HGA Biomed Munkaruha & Mosoda Rendszer - Központi Konfiguráció
 */
date_default_timezone_set('Europe/Budapest');

$configLocalFile = __DIR__ . '/config.local.php';
if (!file_exists($configLocalFile)) {
    die('Hiányzó config.local.php! Másold le a config.local.example.php fájlt "config.local.php" néven és töltsd ki az adatbázis adatokat.');
}
require_once $configLocalFile;

// Session indítás biztonságos cookie paraméterekkel
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 8443);

    // PHP 7.3+ szabványos session cookie beállítás
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $isSecure, true);
    }

    @session_start();
}

// Biztonsági fejlécek
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
}

// Hibakezelés (biztonságos hibamegjelenítés teszteléskor)
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);

function getAppVersion() {
    $versionFile = __DIR__ . '/version.txt';
    if (file_exists($versionFile)) {
        $v = trim(file_get_contents($versionFile));
        if (!empty($v)) return '1.0-' . substr($v, 0, 8);
    }
    $vNum = __DIR__ . '/version_num.txt';
    if (file_exists($vNum)) {
        return trim(file_get_contents($vNum));
    }
    return '1.0.0';
}

function generateCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function getCsrfToken() {
    return generateCsrfToken();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'role' => $_SESSION['role'] ?? 'operator',
            'location_id' => $_SESSION['location_id'] ?? null
        ];
    }
    return null;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function canEdit() {
    if (!isLoggedIn()) return false;
    $role = $_SESSION['role'] ?? 'viewer';
    return in_array($role, ['admin', 'operator']);
}

function isViewer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'viewer';
}

function getActiveLocationId() {
    if (isset($_GET['location_id']) && $_GET['location_id'] !== '') {
        $_SESSION['active_location_id'] = $_GET['location_id'];
        return $_GET['location_id'];
    }
    if (isset($_SESSION['active_location_id'])) {
        return $_SESSION['active_location_id'];
    }
    if (isset($_SESSION['location_id']) && !empty($_SESSION['location_id'])) {
        return (string)$_SESSION['location_id'];
    }
    return '';
}
