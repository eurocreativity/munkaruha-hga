<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<html><head><meta charset='utf-8'><title>Synology Diagnosztika</title><style>body{font-family:sans-serif;padding:30px;line-height:1.6;background:#f8fafc;} .card{background:#fff;padding:25px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:600px;margin:auto;} h2{color:#0f172a;margin-top:0;} .ok{color:#16a34a;font-weight:bold;} .err{color:#dc2626;font-weight:bold;}</style></head><body><div class='card'>";

echo "<h2>🩺 Synology Rendszer-Diagnosztika</h2>";
echo "<p><b>PHP Verzió:</b> " . phpversion() . "</p>";

echo "<p><b>Szükséges PHP modulok:</b><br>";
echo "• PDO MySQL: " . (extension_loaded('pdo_mysql') ? "<span class='ok'>✓ Telepítve és aktív</span>" : "<span class='err'>✗ HIÁNYZIK (Web Station-ben be kell pipálni!)</span>") . "<br>";
echo "• OpenSSL: " . (extension_loaded('openssl') ? "<span class='ok'>✓ Telepítve és aktív</span>" : "<span class='err'>✗ HIÁNYZIK</span>") . "<br>";
echo "• cURL: " . (extension_loaded('curl') ? "<span class='ok'>✓ Telepítve és aktív</span>" : "<span class='err'>✗ HIÁNYZIK</span>") . "<br>";
echo "• ZipArchive: " . (extension_loaded('zip') ? "<span class='ok'>✓ Telepítve és aktív</span>" : "<span class='err'>✗ HIÁNYZIK</span>") . "</p>";

$confLocal = __DIR__ . '/config.local.php';
echo "<hr><p><b>Konfiguráció ellenőrzése:</b><br>";
if (!file_exists($confLocal)) {
    echo "• config.local.php: <span class='err'>✗ NEM TALÁLHATÓ! Másold le a config.local.example.php-t config.local.php néven!</span><br>";
} else {
    echo "• config.local.php: <span class='ok'>✓ Megvan</span><br>";
    require_once $confLocal;
    $host = defined('DB_HOST') ? DB_HOST : 'Nincs megadva';
    $port = defined('DB_PORT') ? DB_PORT : '3306';
    $name = defined('DB_NAME') ? DB_NAME : 'Nincs megadva';
    $user = defined('DB_USER') ? DB_USER : 'Nincs megadva';
    echo "&nbsp;&nbsp;Host: <code>{$host}</code>, Port: <code>{$port}</code>, DB: <code>{$name}</code>, User: <code>{$user}</code><br>";

    // Adatbázis teszt
    echo "<br><b>Adatbázis kapcsolódási teszt:</b><br>";
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "<span class='ok'>✓ SIKERES KAPCSOLÓDÁS A MARIADB-HEZ!</span><br>";
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM clothes");
        $count = $stmt->fetchColumn();
        echo "• Leltározott munkaruhák száma az adatbázisban: <b style='color:#2563eb;'>{$count} db</b><br>";
        
        $uStmt = $pdo->query("SELECT username, role FROM users");
        $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "• Felhasználók az adatbázisban: " . implode(', ', array_map(function($u){ return "<code>{$u['username']}</code>"; }, $users)) . "<br>";
    } catch (Exception $e) {
        echo "<span class='err'>✗ Hiba: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
}

echo "</p><hr><p style='text-align:center;'><a href='login.php' style='display:inline-block;padding:10px 20px;background:#16a34a;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;'>Tovább a Belépéshez &rarr;</a></p>";
echo "</div></body></html>";
