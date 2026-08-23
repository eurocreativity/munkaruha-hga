<?php
/**
 * AJAX Frissítő & Biztonsági Mentés Végpont (ISPConfig, Synology & GitHub)
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

$settingsObj = new Settings();
$repo = $settingsObj->get('github_repo', defined('GITHUB_REPO') ? GITHUB_REPO : 'eurocreativity/munkaruha-hga');
$token = defined('GITHUB_TOKEN') && !empty(GITHUB_TOKEN) ? GITHUB_TOKEN : $settingsObj->get('github_token', '');
$versionFile = __DIR__ . '/version.txt';
$localCommit = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '';

// 1. Frissítés ellenőrzése a GitHub API-n
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

// 2. Frissítés letöltése és alkalmazása (Git pull vagy ZIP)
if ($action === 'apply_update') {
    // Ha van helyi .git mappa és futtatható a git parancs, először megpróbáljuk a natív git pull-t
    if (is_dir(__DIR__ . '/.git') && function_exists('shell_exec')) {
        $disabled = ini_get('disable_functions');
        if (empty($disabled) || strpos($disabled, 'shell_exec') === false) {
            try {
                $gitOutput = @shell_exec('git pull origin main 2>&1');
                if ($gitOutput && (strpos($gitOutput, 'Updating') !== false || strpos($gitOutput, 'Already up to date') !== false)) {
                    $headSha = @shell_exec('git rev-parse HEAD');
                    if ($headSha) {
                        @file_put_contents($versionFile, trim($headSha));
                    }
                    jsonResponse(['success' => true, 'method' => 'git_pull', 'message' => 'Sikeres frissítés Git-en keresztül!']);
                }
            } catch (Exception $e) {
                // Fallback to ZIP download
            }
        }
    }

    // ZIP alapú letöltés (ISPConfig / Synology Web Station alatt a legmegbízhatóbb)
    $zipUrl = "https://api.github.com/repos/{$repo}/zipball/main";
    $backupDir = __DIR__ . '/backups';
    $zipFile = $backupDir . '/update_temp.zip';

    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0777, true);
    }

    $fp = @fopen($zipFile, 'w+');
    if (!$fp) {
        jsonResponse(['success' => false, 'message' => 'Nem sikerült megnyitni az ideiglenes mentési mappát (írási jogosultság hiányzik a backups mappához)!']);
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
            $extractPath = $backupDir . '/extracted/';
            if (!is_dir($extractPath)) {
                @mkdir($extractPath, 0777, true);
            }
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
                    // Soha ne írjuk felül a helyi titkokat és a backups mappát
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
            
            // Kicsomagolt ideiglenes mappa törlése
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileinfo->getRealPath());
            }
            @rmdir($extractPath);

            // Verzió frissítése
            $remoteSha = $_POST['remote_commit'] ?? '';
            if ($remoteSha) {
                @file_put_contents($versionFile, trim($remoteSha));
            } else {
                // Lekérjük a legfrissebb sha-t
                $url = "https://api.github.com/repos/{$repo}/commits/main";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'HGA-Munkaruha-Updater');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                if (!empty($token)) curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
                $res = curl_exec($ch);
                curl_close($ch);
                $json = json_decode($res, true);
                if (!empty($json['sha'])) {
                    @file_put_contents($versionFile, trim($json['sha']));
                }
            }

            jsonResponse(['success' => true, 'method' => 'zip_download', 'message' => 'Frissítés sikeresen telepítve!']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Nem sikerült kicsomagolni a letöltött ZIP csomagot!']);
        }
    }

    jsonResponse(['success' => false, 'message' => 'Hiba történt a frissítő csomag letöltése közben (HTTP ' . $httpCode . ')!']);
}

// 3. Biztonsági mentés generálása
if ($action === 'download_backup') {
    if (ob_get_length()) ob_clean();
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $tables = [];
    $res = $pdo->query("SHOW TABLES");
    while ($row = $res->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sql = "-- HGA Biomed Munkaruha Mentés\n-- Készült: " . date('Y-m-d H:i:s') . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
    foreach ($tables as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC);
        $sql .= "DROP TABLE IF EXISTS `{$t}`;\n" . $create['Create Table'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $vals = array_map(function($v) use ($pdo) { return is_null($v) ? "NULL" : $pdo->quote($v); }, $r);
            $sql .= "INSERT INTO `{$t}` VALUES(" . implode(",", $vals) . ");\n";
        }
        $sql .= "\n";
    }
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="munkaruha_backup_' . date('Y-m-d_H-i') . '.sql"');
    echo $sql;
    exit();
}

jsonResponse(['success' => false, 'message' => 'Ismeretlen művelet!'], 400);
