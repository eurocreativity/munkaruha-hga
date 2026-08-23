<?php
/**
 * AJAX Frissítő & Teljes Mentés / Visszaállítás Motor (ISPConfig, Synology & GitHub)
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Settings.php';

function jsonResponse($data, $statusCode = 200) {
    if (ob_get_length()) ob_clean();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isLoggedIn() || !isAdmin()) {
    jsonResponse(['success' => false, 'message' => 'Hozzáférés megtagadva!'], 403);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

if (!validateCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Érvénytelen CSRF token!'], 400);
}

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
    @chmod($backupDir, 0777);
}

$settingsObj = new Settings();
$repo = $settingsObj->get('github_repo', defined('GITHUB_REPO') ? GITHUB_REPO : 'eurocreativity/munkaruha-hga');
$token = defined('GITHUB_TOKEN') && !empty(GITHUB_TOKEN) ? GITHUB_TOKEN : $settingsObj->get('github_token', '');
$versionFile = __DIR__ . '/version.txt';
$localCommit = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';

// SQL dump generátor segédfüggvény
function generateSqlDump() {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $tables = [];
    $res = $pdo->query("SHOW TABLES");
    while ($row = $res->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $appVer = getAppVersion();
    $sql = "-- HGA Biomed Munkaruha Adatbázis Mentés\n";
    $sql .= "-- Készült: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Rendszer Verzió: " . $appVer . "\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC);
        $sql .= "DROP TABLE IF EXISTS `{$t}`;\n" . $create['Create Table'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $vals = array_map(function($v) use ($pdo) { return is_null($v) ? "NULL" : $pdo->quote($v); }, $r);
                $sql .= "INSERT INTO `{$t}` VALUES(" . implode(",", $vals) . ");\n";
            }
            $sql .= "\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $sql;
}

// SQL végrehajtó és visszaállító motor
function executeSqlScript($sqlContent) {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $queries = preg_split('/;\s*[\r\n]+/', $sqlContent);
    $executed = 0;

    foreach ($queries as $query) {
        $q = trim($query);
        if (!empty($q) && strpos($q, '--') !== 0) {
            $pdo->exec($q);
            $executed++;
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    return $executed;
}

// 1. Frissítés ellenőrzése
if ($action === 'check_update') {
    $url = "https://api.github.com/repos/{$repo}/commits/main";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'HGA-Munkaruha-Updater');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if (!empty($token)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}", "Accept: application/vnd.github.v3+json"]);
    }
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $json = json_decode($res, true);
        $remoteSha = $json['sha'] ?? '';
        $msg = $json['commit']['message'] ?? '';
        $author = $json['commit']['author']['name'] ?? '';
        $date = $json['commit']['author']['date'] ?? '';
        $updateAvailable = (!empty($remoteSha) && strpos($localCommit, substr($remoteSha, 0, 8)) === false && $remoteSha !== $localCommit);

        jsonResponse([
            'success' => true,
            'update_available' => $updateAvailable,
            'local_commit' => $localCommit ?: '1.0.0',
            'remote_commit' => $remoteSha,
            'commit_message' => $msg,
            'commit_date' => $date,
            'author' => $author
        ]);
    } else {
        jsonResponse([
            'success' => true,
            'update_available' => false,
            'local_commit' => $localCommit ?: '1.0.0',
            'message' => 'A GitHub API nem adott vissza újabb verziót, vagy privát repo token szükséges.'
        ]);
    }
}

// 2. Frissítés letöltése és telepítése (automatikus előzetes mentéssel)
if ($action === 'apply_update') {
    // Automatikus adatbázis mentés készítése frissítés előtt
    try {
        $autoSql = generateSqlDump();
        $autoSqlFile = $backupDir . '/auto_backup_before_update_' . date('Y-m-d_H-i-s') . '.sql';
        @file_put_contents($autoSqlFile, $autoSql);
    } catch (Exception $e) {
        // Mentési hiba nem állítja meg a frissítést
    }

    // Git pull kísérlet
    if (is_dir(__DIR__ . '/.git') && function_exists('shell_exec')) {
        $disabled = ini_get('disable_functions');
        if (empty($disabled) || strpos($disabled, 'shell_exec') === false) {
            try {
                $gitOutput = @shell_exec('git pull origin main 2>&1');
                if ($gitOutput && (strpos($gitOutput, 'Updating') !== false || strpos($gitOutput, 'Already up to date') !== false)) {
                    $headSha = @shell_exec('git rev-parse HEAD');
                    if ($headSha) @file_put_contents($versionFile, trim($headSha));
                    jsonResponse(['success' => true, 'method' => 'git_pull', 'message' => 'Sikeres frissítés Git-en keresztül!']);
                }
            } catch (Exception $e) {}
        }
    }

    // ZIP alapú letöltés (Ideiglenes mappa rugalmas kezelésével)
    $zipUrl = "https://api.github.com/repos/{$repo}/zipball/main";
    $tempDir = is_writable($backupDir) ? $backupDir : sys_get_temp_dir();
    $zipFile = $tempDir . '/update_' . uniqid() . '.zip';

    $fp = @fopen($zipFile, 'w+');
    if (!$fp) {
        jsonResponse(['success' => false, 'message' => "Nem sikerült írható ideiglenes mappát létrehozni a szerveren! Ellenőrizd a Synology /web/munkaruha mappa írási jogosultságát."]);
    }

    $ch = curl_init($zipUrl);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'HGA-Munkaruha-Updater');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if (!empty($token)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
    }
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($httpCode === 200 && class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === TRUE) {
            $extractPath = $tempDir . '/extracted_' . uniqid() . '/';
            @mkdir($extractPath, 0777, true);
            $zip->extractTo($extractPath);
            $zip->close();

            $dirs = glob($extractPath . '*', GLOB_ONLYDIR);
            if (!empty($dirs)) {
                $source = $dirs[0] . '/';
                $dest = __DIR__ . '/';
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($iterator as $item) {
                    $target = $dest . $iterator->getSubPathName();
                    if (basename($target) === 'config.local.php') continue;
                    if (strpos($iterator->getSubPathName(), 'backups/') === 0) continue;
                    if (strpos($iterator->getSubPathName(), 'logs/') === 0) continue;

                    if ($item->isDir()) {
                        if (!is_dir($target)) @mkdir($target, 0777, true);
                    } else {
                        @copy($item, $target);
                    }
                }
            }

            @unlink($zipFile);
            
            // Kicsomagolt mappa törlése
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileinfo->getRealPath());
            }
            @rmdir($extractPath);

            $remoteSha = $_POST['remote_commit'] ?? '';
            if ($remoteSha) {
                @file_put_contents($versionFile, trim($remoteSha));
            }

            jsonResponse(['success' => true, 'method' => 'zip_download', 'message' => 'Frissítés sikeresen letöltve és telepítve!']);
        }
    }

    jsonResponse(['success' => false, 'message' => "Hiba a csomag letöltésekor (HTTP {$httpCode})."]);
}

// 3. Mentések Listázása (időrendben)
if ($action === 'list_backups') {
    $list = [];
    if (is_dir($backupDir)) {
        $files = scandir($backupDir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
            $fullPath = $backupDir . '/' . $f;
            if (is_file($fullPath)) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (in_array($ext, ['sql', 'zip'])) {
                    $size = filesize($fullPath);
                    $mtime = filemtime($fullPath);
                    $type = ($ext === 'zip') ? 'Teljes Rendszermentés (Kód + DB)' : 'Adatbázis Mentés (.sql)';
                    $isAuto = (strpos($f, 'auto_backup_') === 0);

                    $sizeFormatted = ($size > 1048576) 
                        ? round($size / 1048576, 2) . ' MB' 
                        : round($size / 1024, 1) . ' KB';

                    $list[] = [
                        'filename' => $f,
                        'type' => $type,
                        'is_sql' => ($ext === 'sql'),
                        'is_auto' => $isAuto,
                        'size' => $sizeFormatted,
                        'created_at' => date('Y-m-d H:i:s', $mtime),
                        'timestamp' => $mtime
                    ];
                }
            }
        }
    }

    // Időrendben csökkenő rendezés
    usort($list, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    jsonResponse(['success' => true, 'backups' => $list]);
}

// 4. Csak Adatbázis Mentés Készítése (.sql fájl mentése a backups mappába)
if ($action === 'create_db_backup') {
    try {
        $sql = generateSqlDump();
        $ver = getAppVersion();
        $filename = 'munkaruha_db_' . date('Y-m-d_H-i-s') . '_v' . str_replace('.', '-', $ver) . '.sql';
        $targetFile = $backupDir . '/' . $filename;

        if (@file_put_contents($targetFile, $sql) !== false) {
            jsonResponse([
                'success' => true,
                'filename' => $filename,
                'message' => "Adatbázis mentés sikeresen elkészült és elmentve a backups mappába!"
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Nem sikerült menteni a fájlt a backups mappába (írási jog szükséges).']);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Hiba az SQL generálás közben: ' . $e->getMessage()]);
    }
}

// 5. Teljes Rendszermentés Készítése (.zip forráskód + beágyazott adatbázis dump)
if ($action === 'create_full_backup') {
    if (!class_exists('ZipArchive')) {
        jsonResponse(['success' => false, 'message' => 'A szerver PHP ZipArchive modulja nem elérhető!']);
    }

    try {
        $ver = getAppVersion();
        $filename = 'munkaruha_full_' . date('Y-m-d_H-i-s') . '_v' . str_replace('.', '-', $ver) . '.zip';
        $targetZip = $backupDir . '/' . $filename;

        $zip = new ZipArchive();
        if ($zip->open($targetZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            // 1. SQL dump generálása és hozzáadása a ZIP-hez
            $sql = generateSqlDump();
            $zip->addFromString('database_dump.sql', $sql);

            // 2. Összes forráskód hozzáadása (kivéve backups/ és logs/)
            $rootPath = __DIR__;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                $subPath = $iterator->getSubPathName();
                if (strpos($subPath, 'backups') === 0 || strpos($subPath, 'logs') === 0 || strpos($subPath, '.git') === 0) {
                    continue;
                }
                if ($file->isDir()) {
                    $zip->addEmptyDir($subPath);
                } else {
                    $zip->addFile($file->getRealPath(), $subPath);
                }
            }

            $zip->close();
            jsonResponse([
                'success' => true,
                'filename' => $filename,
                'message' => "Teljes rendszermentés (Forráskód + Adatbázis) sikeresen elkészült a backups mappába!"
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Nem sikerült létrehozni a ZIP archívumot a backups mappában.']);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Hiba a teljes mentés során: ' . $e->getMessage()]);
    }
}

// 6. Mentési fájl letöltése
if ($action === 'download_file') {
    $file = basename($_GET['file'] ?? '');
    $filePath = $backupDir . '/' . $file;

    if (!empty($file) && file_exists($filePath)) {
        if (ob_get_length()) ob_clean();
        $mime = (pathinfo($file, PATHINFO_EXTENSION) === 'zip') ? 'application/zip' : 'application/sql';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit();
    }
    jsonResponse(['success' => false, 'message' => 'A kért fájl nem található!'], 404);
}

// 7. Adatbázis Visszaállítása (Szerveren lévő .sql mentésből)
if ($action === 'restore_db_file') {
    $file = basename($_POST['file'] ?? '');
    $filePath = $backupDir . '/' . $file;

    if (empty($file) || !file_exists($filePath)) {
        jsonResponse(['success' => false, 'message' => 'A megadott mentési fájl nem található!']);
    }

    try {
        $sqlContent = file_get_contents($filePath);
        $executed = executeSqlScript($sqlContent);

        // Naplózás
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO audit_logs (user_id, username, action, entity_type, details) VALUES (?, ?, 'RESTORE', 'DATABASE', ?)",
            [$_SESSION['user_id'], $_SESSION['username'], "Adatbázis sikeresen visszaállítva ebből a mentésből: {$file} ({$executed} parancs)"]
        );

        jsonResponse([
            'success' => true,
            'message' => "Az adatbázis sikeresen visszaállítva! ({$executed} SQL lekérdezés lefutott)."
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Hiba a visszaállítás során: ' . $e->getMessage()]);
    }
}

// 8. Külső SQL Fájl Feltöltése és Visszaállítása
if ($action === 'upload_and_restore_sql') {
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'Hiba történt az SQL fájl feltöltése közben!']);
    }

    $uploadedFile = $_FILES['sql_file']['tmp_name'];
    $originalName = basename($_FILES['sql_file']['name']);

    try {
        $sqlContent = file_get_contents($uploadedFile);
        $executed = executeSqlScript($sqlContent);

        // Mentés elhelyezése a backups mappában is
        @copy($uploadedFile, $backupDir . '/uploaded_' . date('Y-m-d_H-i-s') . '_' . $originalName);

        // Naplózás
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO audit_logs (user_id, username, action, entity_type, details) VALUES (?, ?, 'RESTORE', 'DATABASE', ?)",
            [$_SESSION['user_id'], $_SESSION['username'], "Külső SQL fájl visszaállítva: {$originalName}"]
        );

        jsonResponse([
            'success' => true,
            'message' => "A feltöltött SQL mentés sikeresen betöltve! ({$executed} parancs végrehajtva)."
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Hiba a feltöltött SQL futtatásakor: ' . $e->getMessage()]);
    }
}

// 9. Mentési Fájl Törlése
if ($action === 'delete_backup_file') {
    $file = basename($_POST['file'] ?? '');
    $filePath = $backupDir . '/' . $file;

    if (!empty($file) && file_exists($filePath)) {
        if (@unlink($filePath)) {
            jsonResponse(['success' => true, 'message' => "A mentés ({$file}) sikeresen törölve."]);
        }
    }
    jsonResponse(['success' => false, 'message' => 'Nem sikerült törölni a fájlt!']);
}

jsonResponse(['success' => false, 'message' => 'Ismeretlen művelet!'], 400);
