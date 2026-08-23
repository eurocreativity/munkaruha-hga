<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

$msg = '';
$db = Database::getInstance();

// Jelszavak frissítése valós bcrypt hashekre
if (isset($_GET['fix_passwords'])) {
    $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
    $jutaiHash = password_hash('jutai123', PASSWORD_DEFAULT);
    $nagygatHash = password_hash('nagygat123', PASSWORD_DEFAULT);
    $vezetoHash = password_hash('vezeto123', PASSWORD_DEFAULT);

    $db->execute("UPDATE users SET password_hash = ? WHERE username = 'admin'", [$adminHash]);
    $db->execute("UPDATE users SET password_hash = ? WHERE username = 'jutai_operator'", [$jutaiHash]);
    $db->execute("UPDATE users SET password_hash = ? WHERE username = 'nagygat_operator'", [$nagygatHash]);
    $db->execute("UPDATE users SET password_hash = ? WHERE username = 'vezeto'", [$vezetoHash]);

    $msg = "Sikeresen beállítva a jelszavak az éles PHP bcrypt algoritmussal!";
}

echo "<html><head><meta charset='utf-8'><title>Synology Diagnosztika & Jelszó Beállítás</title><style>body{font-family:sans-serif;padding:30px;line-height:1.6;background:#f8fafc;} .card{background:#fff;padding:25px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:600px;margin:auto;} h2{color:#0f172a;margin-top:0;} .ok{color:#16a34a;font-weight:bold;} .err{color:#dc2626;font-weight:bold;} .btn{display:inline-block;padding:12px 24px;background:#16a34a;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin-top:10px;}</style></head><body><div class='card'>";

echo "<h2>🔑 Jelszó Beállítás és Ellenőrzés</h2>";

if ($msg) {
    echo "<div style='background:#dcfce7;color:#166534;padding:15px;border-radius:8px;margin-bottom:15px;font-weight:bold;'>✓ {$msg}</div>";
}

// Ellenőrizzük az admin jelszót
$adminUser = $db->fetchOne("SELECT * FROM users WHERE username = 'admin'");
$adminOk = ($adminUser && password_verify('admin123', $adminUser['password_hash']));

if ($adminOk) {
    echo "<p class='ok' style='font-size:18px;'>✓ Az <code>admin</code> jelszó (<code>admin123</code>) tökéletesen érvényes!</p>";
    echo "<p><a href='login.php' class='btn'>👉 Tovább a Bejelentkezéshez (login.php)</a></p>";
} else {
    echo "<p class='err'>⚠️ A kezdő jelszó hash még nem volt véglegesítve a szerver PHP-jával.</p>";
    echo "<p><a href='diag.php?fix_passwords=1' class='btn' style='background:#2563eb;'>🔑 Kattints ide a Jelszavak Automatikus Beállításához</a></p>";
}

echo "<hr><p style='font-size:12px;color:#64748b;'>Alapértelmezett fiókok:<br>
• <b>admin</b> / <code>admin123</code><br>
• <b>jutai_operator</b> / <code>jutai123</code><br>
• <b>nagygat_operator</b> / <code>nagygat123</code><br>
• <b>vezeto</b> / <code>vezeto123</code></p>";

echo "</div></body></html>";
